<?php

declare(strict_types=1);

namespace App\Portale\Pagine;

use App\Anagrafi\PersonaRepository;
use App\Auth\Csrf;
use App\Auth\Flash;
use App\Auth\LimiteRichieste;
use App\Auth\Sessione;
use App\Auth\UtenteRepository;
use App\Core\Esito;
use App\Core\File;
use App\Core\Orologio;
use App\Core\Sito;
use App\Http\Pagina;
use App\Http\Richiesta;
use App\Http\Risposta;
use App\Http\Vista;
use App\Portale\Instradamento\SincronizzaAccesso;
use App\SosAule\AuleRepository;
use App\SosAule\Costanti;
use App\SosAule\FotoSos;
use App\SosAule\ImpostazioniSos;
use App\SosAule\NotificheSos;
use App\SosAule\RegoleSos;
use App\SosAule\SegnalazioniRepository;
use App\SosAule\ServizioSos;

/**
 * SOS aule: la pagina di chi chiede aiuto (sos.php, il QR in aula porta a sos.php?aula=<codice>). Disegno approvato: documentazione/schizzo_sos_docente.html.
 *
 * Non collegato = login SSO e ritorno qui (con il codice dell'aula). Il token CSRF di ogni POST lo controlla il Nucleo.
 *  - sos.php[?aula=codice]: il form (ServizioSos::campiDelForm), l'avviso dei doppioni, quello fuori orario e «Le mie segnalazioni»; l'invio rimanda a ?segnalazione=<id>;
 *  - sos.php?segnalazione=<id>: stato e cronologia, SOLO per chi ha un ruolo su quella segnalazione (ServizioSos::ruoli); &frammento=1 è la parte che si aggiorna da
 *    sola, &foto=1 la foto. Nota, «ho risolto da solo» e «non è risolto» sono di chi l'ha inviata.
 * Le regole stanno in src/SosAule; gli avvisi (NotificheSos) partono dopo l'esito.
 */
final class PaginaSos implements Pagina
{
    private const MINUTI_DOPPIONI = 120;
    private const MAX_INVII = 5;
    private const FINESTRA_INVII = 600;
    private const LIMITE = 'sos_invio';
    private const ICONE_URGENZA = ['ferma' => 'fa-circle-pause', 'aiuto' => 'fa-hand', 'prossime' => 'fa-calendar-day'];
    private const SOTTOTITOLO = 'Segnala un problema in aula: chi segue l\'aula viene avvisato subito.';

    public function __construct(
        private readonly ServizioSos $servizio,
        private readonly SegnalazioniRepository $segnalazioni,
        private readonly AuleRepository $aule,
        private readonly ImpostazioniSos $impostazioni,
        private readonly FotoSos $foto,
        private readonly NotificheSos $notifiche,
        private readonly LimiteRichieste $limite,
        private readonly UtenteRepository $utenti,
        private readonly PersonaRepository $persone,
        private readonly Orologio $orologio,
        private readonly Sito $sito,
        private readonly Sessione $sessione,
        private readonly Csrf $csrf,
        private readonly Flash $flash,
        private readonly SincronizzaAccesso $sincronizza,
    ) {
    }

    public function __invoke(Richiesta $r): Risposta
    {
        ($this->sincronizza)($r);
        $id = (int) $r->query('segnalazione', 0);
        $uid = (int) $this->sessione->leggi('utente_id');
        $utente = $uid > 0 ? $this->utenti->perId($uid) : null;
        if ($utente === null) {
            return Risposta::vai('saml_login.php?redirect=' . urlencode(self::url((string) $r->query('aula', ''), $id)), 302);
        }
        if ($id > 0) {
            return $this->segnalazione($r, $utente, $id);
        }
        // la nota dall'avviso dei doppioni: la può scrivere anche chi l'ha inviata quando il servizio è spento, quindi decide ServizioSos
        if ($r->eUnPost() && $r->post('azione') === 'nota') {
            return $this->notaDaAvviso($r, $utente);
        }
        if (!$this->impostazioni->aperto()) {
            return $this->messaggio($utente, 'Il servizio SOS aule non è attivo.', 'attesa');
        }
        if (!$this->servizio->puoInviare($utente)) {
            return Risposta::negata('Il tuo profilo non può inviare segnalazioni SOS.', fn (string $m): string => $this->messaggio($utente, $m, 'azione')->corpo());
        }

        return $r->eUnPost() ? $this->invia($r, $utente) : $this->form($r, $utente);
    }

    /** L'indirizzo della pagina: con il codice dell'aula (dal QR) o con la segnalazione. */
    public static function url(string $aula = '', int $segnalazione = 0): string
    {
        if ($segnalazione > 0) {
            return 'sos.php?segnalazione=' . $segnalazione;
        }

        return 'sos.php' . (preg_match('/^[A-Za-z0-9]{1,20}$/', $aula) === 1 ? '?aula=' . $aula : '');
    }

    // ───────────────────────────── Invio ─────────────────────────────

    /** @param array<string, mixed> $utente */
    private function invia(Richiesta $r, array $utente): Risposta
    {
        $dati = $r->datiModulo();
        // il catalogo di Ateneo (campi-pratica.js) lascia la scelta in un campo nascosto; senza JavaScript arriva dall'elenco a discesa
        if (RegoleSos::testo($dati, 'insegnamento_id') === '') {
            $scelto = json_decode(RegoleSos::testo($dati, 'insegnamento_meta'), true);
            $dati['insegnamento_id'] = is_array($scelto) ? (string) (int) ($scelto['id'] ?? 0) : '';
            if ($dati['insegnamento_id'] === '0') {
                $dati['insegnamento_id'] = '';
            }
        }
        $aulaId = (int) RegoleSos::testo($dati, 'aula_id');
        if ($r->post('togli_avviso') !== null) {   // «Invia comunque»: toglie l'avviso e lascia compilare, non invia
            return $this->form($r, $utente, ['avvisato' => (string) $aulaId] + $dati);
        }
        if ($this->doppione($aulaId) !== null && (int) RegoleSos::testo($dati, 'avvisato') !== $aulaId) {
            return $this->form($r, $utente, $dati, [], 'Non ho ancora inviato niente: per quest\'aula c\'è già una segnalazione aperta. Se vuoi inviarne un\'altra premi di nuovo «Invia la richiesta di aiuto».');
        }
        $chiave = 'utente:' . (int) $utente['id'];
        if ($this->limite->bloccato($chiave, self::LIMITE, self::MAX_INVII, self::FINESTRA_INVII)) {
            $telefono = trim((string) $this->impostazioni->tutte()['telefono_riserva']);

            return $this->form($r, $utente, $dati, [], 'Non ho inviato niente: hai già mandato ' . self::MAX_INVII . ' segnalazioni in 10 minuti.' . ($telefono !== '' ? ' Se è urgente chiama il ' . $telefono . '.' : ''), 429);
        }
        $foto = $r->file('foto');
        $esito = $this->servizio->invia($utente, $dati, $foto === null ? [] : ['foto' => $foto]);
        if (!$esito->esito->riuscito) {
            return $this->form($r, $utente, $dati, $esito->errori, $esito->errori === [] ? $esito->esito->messaggio : '');
        }
        $this->limite->registra($chiave, self::LIMITE);
        $this->notifiche->nuova($esito->id);

        return Risposta::vai(self::url('', $esito->id));
    }

    /** La segnalazione aperta da meno di due ore per quell'aula, la più recente. @return array<string, mixed>|null */
    private function doppione(int $aulaId): ?array
    {
        if ($aulaId <= 0) {
            return null;
        }
        $da = $this->orologio->adesso()->modify('-' . self::MINUTI_DOPPIONI . ' minutes')->format('Y-m-d H:i:s');

        return $this->segnalazioni->aperteDellAula($aulaId, $da)[0] ?? null;
    }

    /** «Aggiungi una nota a quella»: vale solo per una segnalazione aperta dell'aula scelta. @param array<string, mixed> $utente */
    private function notaDaAvviso(Richiesta $r, array $utente): Risposta
    {
        $aulaId = (int) $r->post('aula', 0);
        $id = (int) $r->post('doppione', 0);
        $aperte = array_map('intval', array_column($this->segnalazioni->aperteDellAula($aulaId), 'id'));
        $esito = in_array($id, $aperte, true)
            ? $this->aggiungiNota($utente, $id, (string) $r->post('nota', ''), $aulaId)
            : Esito::errore('La segnalazione è già chiusa: la nota non è stata aggiunta.');
        $this->flash->imposta($esito->messaggio, $esito->riuscito ? 'success' : 'warning');
        $s = $this->segnalazioni->perId($id);
        if ($esito->riuscito && $s !== null && (int) $s['utente_id'] === (int) $utente['id']) {
            return Risposta::vai(self::url('', $id));
        }

        return Risposta::vai(self::url((string) ($this->aule->perId($aulaId)['codice'] ?? '')));
    }

    /** La nota e, se è di chi ha segnalato o di un collega, l'avviso a chi la segue. @param array<string, mixed> $utente */
    private function aggiungiNota(array $utente, int $id, string $nota, int $aulaId = 0): Esito
    {
        $esito = $this->servizio->aggiungiNota($id, $utente, $nota, $aulaId);
        $s = $esito->riuscito ? $this->segnalazioni->perId($id) : null;
        if ($s !== null) {
            $ruoli = $this->servizio->ruoli($utente, $s);
            if ($ruoli === [] || in_array(Costanti::DOCENTE, $ruoli, true)) {
                $this->notifiche->nota($id, $utente, trim($nota), $ruoli === [] ? Costanti::COLLEGA : Costanti::DOCENTE);
            }
        }

        return $esito;
    }

    // ───────────────────────────── Il form ─────────────────────────────

    /**
     * @param array<string, mixed> $utente
     * @param array<string, mixed> $dati quello che era scritto (vuoto = form nuovo)
     * @param array<string, string> $errori errori campo per campo
     */
    private function form(Richiesta $r, array $utente, array $dati = [], array $errori = [], string $messaggio = '', int $codice = 200): Risposta
    {
        $campi = $this->servizio->campiDelForm();
        $fissi = $campi['fissi'];
        $nuovo = $dati === [];
        $vecchio = false;
        $aulaId = (int) RegoleSos::testo($dati, 'aula_id');
        $codiceQr = (string) $r->query('aula', '');
        if ($nuovo && $codiceQr !== '') {
            $trovata = $this->aule->perCodice($codiceQr);
            $vecchio = $trovata !== null && $trovata['vecchio'];
            $aulaId = $trovata !== null && !$trovata['vecchio'] ? (int) $trovata['aula']['id'] : 0;
        }
        $aula = $campi['aule'][$aulaId] ?? null;

        $doppione = $aula === null ? null : $this->doppione($aulaId);
        $avvisoDoppione = $doppione !== null && (int) RegoleSos::testo($dati, 'avvisato') !== $aulaId;

        $etichette = [];
        foreach ($fissi as $chiave => $c) {
            $etichette[$chiave] = ['etichetta' => $c['etichetta'], 'aiuto' => $c['aiuto'], 'facoltativo' => !$c['obbligatorio'], 'visibile' => $c['visibile']];
        }
        $nome = (string) ($aula['nome'] ?? '');
        $conParola = NotificheSos::conAula($nome) !== $nome;

        $tipi = [];
        foreach ($campi['tipi'] as $tipoId => $t) {
            $tipi[] = ['id' => (int) $tipoId, 'nome' => (string) $t['nome'], 'icona' => (string) $t['icona'], 'aiuto' => (string) ($t['aiuto'] ?? ''), 'scelto' => (int) RegoleSos::testo($dati, 'tipo_id') === (int) $tipoId];
        }
        $urgenze = [];
        foreach (Costanti::URGENZE as $k => $testo) {
            $urgenze[] = ['id' => $k, 'testo' => $testo, 'icona' => self::ICONE_URGENZA[$k] ?? 'fa-circle', 'ferma' => $k === 'ferma', 'scelta' => RegoleSos::testo($dati, 'urgenza') === $k];
        }
        $aggiunti = [];
        foreach ($campi['aggiunti'] as $c) {
            if (!$c['visibile']) {
                continue;
            }
            $k = $c['chiave'];
            $scelte = is_array($dati[$k] ?? null) ? array_map('strval', $dati[$k]) : [RegoleSos::testo($dati, $k)];
            $aggiunti[] = [
                'chiave' => $k, 'etichetta' => $c['etichetta'], 'tipo' => $c['tipo'], 'aiuto' => $c['aiuto'], 'facoltativo' => !$c['obbligatorio'],
                'valore' => RegoleSos::testo($dati, $k), 'errore' => $errori[$k] ?? '',
                'opzioni' => array_map(static fn (string $o): array => ['testo' => $o, 'scelta' => in_array($o, $scelte, true)], $c['opzioni']),
            ];
        }
        $insegnamento = (int) RegoleSos::testo($dati, 'insegnamento_id');
        $conInsegnamento = $fissi['insegnamento']['visibile'];
        $js = 'assets/js/campi-pratica.js';
        $telefono = $nuovo ? $this->telefonoDalProfilo($utente) : RegoleSos::testo($dati, 'telefono');
        $intro = trim((string) $this->impostazioni->tutte()['testo_introduttivo']);

        $campiErrore = ['aula_id', 'tipo_id', 'urgenza', 'descrizione', 'foto', 'telefono', 'insegnamento_id'];
        $mappaErrori = [];
        foreach ($campiErrore as $k) {
            $mappaErrori[$k] = $errori[$k] ?? '';
        }
        if ($messaggio === '' && $errori !== []) {
            $messaggio = 'Non ho inviato niente. Controlla ' . (count($errori) === 1 ? 'il campo segnato' : 'i campi segnati') . ' in rosso.';
        }

        return $this->pagina('form', [
            'azione' => self::url((string) ($aula['codice'] ?? '')),
            'campoCsrf' => $this->csrf->campo(),
            'sottotitolo' => $intro !== '' ? $intro : self::SOTTOTITOLO,
            'messaggio' => $messaggio,
            'cartelloVecchio' => $vecchio,
            'fuoriOrario' => $this->impostazioni->fuoriOrario($this->orologio->adesso()),
        ] + $this->riserva() + [
            'aulaScelta' => $aula !== null,
            'aulaId' => $aula !== null ? $aulaId : 0,
            'aulaPrima' => $conParola ? 'Sei nell\'aula' : 'Sei nell\'',
            'aulaStacco' => $conParola,
            'aulaNome' => $nome,
            'aulaEdificio' => (string) ($aula['edificio'] ?? ''),
            'aule' => array_values(array_map(static fn (array $a): array => [
                'id' => (int) $a['id'], 'nome' => trim($a['nome'] . ((string) $a['edificio'] !== '' ? ' · ' . $a['edificio'] : '')), 'breve' => (string) $a['nome'], 'scelta' => (int) $a['id'] === $aulaId,
            ], $campi['aule'])),
            'avvisoDoppione' => $avvisoDoppione,
            'avvisato' => $doppione !== null ? $aulaId : 0,
            'doppione' => [
                'id' => (int) ($doppione['id'] ?? 0),
                'ora' => $doppione === null ? '' : $this->ora((string) $doppione['inviata_il']),
                'tipo' => (string) ($doppione['tipo_nome'] ?? ''),
                'stato' => $doppione === null ? '' : Costanti::etichetta((string) $doppione['stato']),
                'href' => $doppione !== null && (int) $doppione['utente_id'] === (int) $utente['id'] ? self::url('', (int) $doppione['id']) : '',
            ],
            'maxNota' => Costanti::MAX_NOTA,
            'docenteNome' => trim(($utente['nome'] ?? '') . ' ' . ($utente['cognome'] ?? '')),
            'docenteEmail' => (string) (UtenteRepository::emailVerificata($utente) ?? ''),
            'c' => $etichette,
            'errori' => $mappaErrori,
            'tipi' => $tipi,
            'urgenze' => $urgenze,
            'descrizione' => RegoleSos::testo($dati, 'descrizione'),
            'maxDescrizione' => Costanti::MAX_DESCRIZIONE,
            'fotoDaRiscegliere' => !$nuovo,
            'telefono' => $telefono,
            'maxTelefono' => Costanti::MAX_TELEFONO,
            'insegnamentoTesto' => RegoleSos::testo($dati, 'insegnamento_testo'),
            'insegnamentoMeta' => RegoleSos::testo($dati, 'insegnamento_meta'),
            'insegnamenti' => !$conInsegnamento ? [] : array_map(static fn (array $i): array => [
                'id' => (int) $i['id'], 'nome' => trim($i['nome'] . ((string) $i['cds_nome'] !== '' ? ' · ' . $i['cds_nome'] : '')), 'scelto' => (int) $i['id'] === $insegnamento,
            ], $this->servizio->insegnamentiDelDipartimento()),
            'scriptInsegnamenti' => $js . '?v=' . (File::modificato($this->sito->radice() . '/' . $js) ?: 1),
            'base' => rtrim((string) parse_url($this->sito->urlBase(), PHP_URL_PATH), '/'),
            'aggiunti' => $aggiunti,
            'mie' => $this->mie($utente),
        ], $codice);
    }

    /** Il cellulare che la persona ha scritto nel proprio profilo (scheda di Ateneo), se va bene per richiamarla. @param array<string, mixed> $utente */
    private function telefonoDalProfilo(array $utente): string
    {
        $persona = trim((string) ($utente['persona_id'] ?? ''));
        $telefono = $persona === '' ? '' : trim((string) ($this->persone->modifiche($persona)['telefono'] ?? ''));

        return RegoleSos::telefonoValido($telefono) ? $telefono : '';
    }

    // ───────────────────────────── Una segnalazione ─────────────────────────────

    /** @param array<string, mixed> $utente */
    private function segnalazione(Richiesta $r, array $utente, int $id): Risposta
    {
        $s = $this->segnalazioni->perId($id);
        if ($s === null) {
            return Risposta::nonTrovata('La segnalazione non esiste.');
        }
        $ruoli = $this->servizio->ruoli($utente, $s);
        if ($ruoli === []) {
            return Risposta::negata('Questa segnalazione la vede solo chi l\'ha inviata e chi la segue.', fn (string $m): string => $this->messaggio($utente, $m, 'azione')->corpo());
        }
        if ($r->eUnPost()) {
            return $this->azione($r, $utente, $id);
        }
        if ($r->query('foto') !== null) {
            return self::scaricaFoto($this->foto, $s);
        }
        $this->caricaUi();
        $stato = $this->datiStato($s);
        if ($r->query('frammento') !== null) {
            return Risposta::html(Vista::rendi('pubblico/_sos_stato', $stato))->conIntestazione('Cache-Control', 'private, no-store');
        }
        $docente = in_array(Costanti::DOCENTE, $ruoli, true);
        $aperta = in_array((string) $s['stato'], Costanti::STATI_APERTI, true);
        $fine = $s['stato'] === 'risolta' ? (int) strtotime((string) $s['chiusa_il'] . ' +' . Costanti::ORE_RIAPERTURA . ' hours') : 0;

        return $this->pagina('segnalazione', [
            'statoHtml' => Vista::rendi('pubblico/_sos_stato', $stato),
            'aperta' => $aperta,
            'urlFrammento' => self::url('', $id) . '&frammento=1',
            'azione' => self::url('', $id),
            'campoCsrf' => $this->csrf->campo(),
            'puoScrivere' => $docente && $aperta,
            'maxNota' => Costanti::MAX_NOTA,
            'riapribile' => $docente && $fine >= $this->orologio->adesso()->getTimestamp(),
            'riapriEntro' => $fine > 0 ? $this->entro($fine) : '',
            'mie' => $this->mie($utente),
        ]);
    }

    /** I pulsanti di chi ha inviato la segnalazione. @param array<string, mixed> $utente */
    private function azione(Richiesta $r, array $utente, int $id): Risposta
    {
        $esito = match ($r->post('azione')) {
            'nota' => $this->aggiungiNota($utente, $id, (string) $r->post('nota', '')),
            'annulla' => $this->servizio->annulla($id, $utente),
            'riapri' => $this->servizio->riapri($id, $utente),
            default => null,
        };
        if ($esito !== null) {
            $this->flash->imposta($esito->messaggio, $esito->riuscito ? 'success' : 'warning');
        }
        if ($esito?->riuscito && $r->post('azione') === 'annulla') {
            $this->notifiche->annullata($id, $utente);
        } elseif ($esito?->riuscito && $r->post('azione') === 'riapri') {
            $this->notifiche->nonRisolta($id, $utente);
        }

        return Risposta::vai(self::url('', $id));
    }

    /** La foto di una segnalazione. Chi può vederla lo decide chi chiama (qui e il registro del pannello). @param array<string, mixed> $s */
    public static function scaricaFoto(FotoSos $foto, array $s): Risposta
    {
        $relativo = (string) $s['foto'];
        $percorso = $relativo === '' ? null : $foto->percorsoAssoluto($relativo);
        if ($percorso === null || !is_file($percorso)) {
            return Risposta::nonTrovata('La foto non c\'è.');
        }
        $estensione = strtolower(pathinfo($percorso, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpg';

        return Risposta::file($percorso, 'foto-' . $s['codice'] . '.' . $estensione, $estensione === 'png' ? 'image/png' : 'image/jpeg', false, true)
            ->conIntestazione('Cache-Control', 'private, no-store');
    }

    /** Riquadro in cima, barra dello stato e cronologia: la parte che si aggiorna da sola. @param array<string, mixed> $s @return array<string, mixed> */
    private function datiStato(array $s): array
    {
        $stato = (string) $s['stato'];
        $cronologia = $this->segnalazioni->cronologia((int) $s['id']);
        $aula = self::maiuscola(NotificheSos::conAula((string) $s['aula_nome']));
        $riga = $s['codice'] . ' · ' . $aula . ' · ' . $s['tipo_nome'];
        [$tono, $icona, $titolo] = match ($stato) {
            'risolta' => ['ok', 'fa-circle-check', 'Risolta alle ' . $this->ora((string) $s['chiusa_il'])],
            'non_risolta' => ['azione', 'fa-triangle-exclamation', Costanti::etichetta($stato)],
            'annullata', 'chiusa_non_risolta' => ['neutro', 'fa-circle-xmark', Costanti::etichetta($stato)],
            default => ['ok', 'fa-circle-check', 'Segnalazione ' . $s['codice'] . ' inviata'],
        };
        // finché è aperta il codice è già nel titolo: sotto, aula, problema e urgenza
        if (in_array($stato, Costanti::STATI_APERTI, true) && $stato !== 'non_risolta') {
            $riga = $aula . ' · ' . $s['tipo_nome'] . ' · ' . (Costanti::URGENZE[(string) $s['urgenza']] ?? $s['urgenza']);
        }
        $righe = [];
        foreach ($cronologia as $c) {
            $righe[] = ['ora' => $this->ora((string) $c['quando']), 'testo' => self::frase($c)];
        }

        return [
            'stato' => $stato,
            'tono' => $tono,
            'icona' => $icona,
            'titolo' => $titolo,
            'riga' => $riga,
            'passiHtml' => $this->passi($s, $cronologia),
            'cronologia' => $righe,
            'foto' => (string) $s['foto'] !== '' ? self::url('', (int) $s['id']) . '&foto=1' : '',
            'aperta' => in_array($stato, Costanti::STATI_APERTI, true),
        ];
    }

    /**
     * La barra: In attesa → Presa in carico → Intervento in corso → Risolta. Il passo dove si è fermata prende il nome vero dello stato;
     * rosso per «Non risolta», grigio per chiusa senza soluzione e annullata.
     *
     * @param array<string, mixed> $s
     * @param list<array<string, mixed>> $cronologia
     */
    private function passi(array $s, array $cronologia): string
    {
        $stato = (string) $s['stato'];
        $posti = ['inviata' => 0, 'presa' => 1, 'assegnata' => 1, 'in_corso' => 2, 'non_risolta' => 2, 'inoltrata' => 2];
        $ultimo = [];
        foreach ($cronologia as $c) {
            if ((string) $c['a_stato'] !== '') {
                $ultimo[(string) $c['a_stato']] = $c;
            }
        }
        $quando = fn (mixed $d): string => (string) $d === '' ? '' : $this->ora((string) $d);
        $passi = [
            ['titolo' => 'In attesa', 'nota' => $quando($s['inviata_il'])],
            ['titolo' => 'Presa in carico', 'nota' => $quando($s['presa_il'])],
            ['titolo' => 'Intervento in corso', 'nota' => $quando($ultimo['in_corso']['quando'] ?? '')],
            ['titolo' => 'Risolta', 'nota' => ''],
        ];
        if ($stato === 'risolta') {
            $passi[3]['nota'] = $quando($s['chiusa_il']);

            return ui_passi($passi, -1, 'normale', 'A che punto è la segnalazione');
        }
        $fermata = $stato === 'annullata' || $stato === 'chiusa_non_risolta';
        $qui = $posti[$fermata ? (string) ($ultimo[$stato]['da_stato'] ?? 'inviata') : $stato] ?? 0;
        $passi[$qui]['titolo'] = Costanti::etichetta($stato);
        if ($fermata) {
            $passi[$qui] = ['titolo' => $passi[$qui]['titolo'], 'stato' => 'fermo', 'nota' => $quando($s['chiusa_il'])];
        } elseif ($stato === 'non_risolta') {
            $passi[$qui] = ['titolo' => $passi[$qui]['titolo'], 'stato' => 'bloccato', 'nota' => $quando($ultimo[$stato]['quando'] ?? '')];
        } elseif ($stato === 'assegnata') {
            $passi[$qui]['nota'] = (string) ($ultimo[$stato]['nota'] ?? '');
        } elseif ($stato === 'in_corso') {
            $passi[$qui]['nota'] = self::nome((string) ($ultimo[$stato]['chi'] ?? '')) . ' sta andando';
        } elseif ($stato === 'inoltrata') {
            $passi[$qui]['nota'] = (string) $s['inoltrata_a'];
        }
        for ($i = $qui + 1; $i < 4; $i++) {
            $passi[$i]['nota'] = '';
        }

        return ui_passi($passi, $qui, 'normale', 'A che punto è la segnalazione');
    }

    /** Una riga di «Cosa è successo»: chi e che cosa (la usa anche PaginaSosInterventi). @param array<string, mixed> $c */
    public static function frase(array $c): string
    {
        $chi = (string) preg_replace('/ \((\p{L}+)\)$/u', ', $1', (string) $c['chi']);
        $nota = (string) $c['nota'];
        $con = static fn (string $testo): string => $testo . ($nota !== '' ? ' — ' . $nota : '');
        $cosa = match ((string) $c['a_stato']) {
            '' => 'nota «' . $nota . '»',
            'inviata' => 'inviata',
            'presa' => 'presa in carico',
            'assegnata' => $nota !== '' ? mb_strtolower(mb_substr($nota, 0, 1)) . mb_substr($nota, 1) : 'affidata a uno studente',
            'in_corso' => 'sta andando',
            'risolta' => trim('risolta ' . $nota),
            'non_risolta' => (string) $c['da_stato'] === 'risolta' ? 'non è risolto' : $con('non risolta'),
            'inoltrata' => $con('inoltrata'),
            'chiusa_non_risolta' => $con('chiusa senza soluzione'),
            'annullata' => 'annullata',
            default => $con(Costanti::etichetta((string) $c['a_stato'])),
        };

        return $chi . ': ' . $cosa;
    }

    /** «Cognome Nome (ruolo)» senza il ruolo. */
    public static function nome(string $chi): string
    {
        return (string) preg_replace('/ \(\p{L}+\)$/u', '', $chi);
    }

    // ───────────────────────────── Parti comuni ─────────────────────────────

    /** Servizio spento, profilo non ammesso, segnalazione di altri: il messaggio, il telefono di riserva e le proprie segnalazioni. @param array<string, mixed> $utente */
    private function messaggio(array $utente, string $testo, string $tono): Risposta
    {
        return $this->pagina('messaggio', ['testo' => $testo, 'tono' => $tono, 'mie' => $this->mie($utente)] + $this->riserva());
    }

    /** @return array{telefonoRiserva: string, telefonoRiservaHref: string} */
    private function riserva(): array
    {
        $telefono = trim((string) $this->impostazioni->tutte()['telefono_riserva']);

        return ['telefonoRiserva' => $telefono, 'telefonoRiservaHref' => 'tel:' . preg_replace('/[^0-9+]/', '', $telefono)];
    }

    /** Le ultime dieci segnalazioni inviate dalla persona. @param array<string, mixed> $utente @return list<array<string, string>> */
    private function mie(array $utente): array
    {
        $out = [];
        foreach ($this->segnalazioni->delDocente((int) $utente['id']) as $s) {
            $t = (int) strtotime((string) $s['inviata_il']);
            $out[] = [
                'href' => self::url('', (int) $s['id']),
                'titolo' => self::maiuscola(NotificheSos::conAula((string) $s['aula_nome'])) . ' · ' . $s['tipo_nome'],
                'sotto' => $s['codice'] . ' · ' . date('d/m \a\l\l\e G:i', $t),
                'stato' => Costanti::etichetta((string) $s['stato']),
                'colore' => Costanti::STATI[(string) $s['stato']]['colore'] ?? 'neutro',
            ];
        }

        return $out;
    }

    private static function maiuscola(string $testo): string
    {
        return mb_strtoupper(mb_substr($testo, 0, 1)) . mb_substr($testo, 1);
    }

    /** «10:12»; di un altro giorno «18/11 10:12». */
    private function ora(string $data): string
    {
        $t = strtotime($data);

        return $t ? date(date('Y-m-d', $t) === $this->orologio->adesso()->format('Y-m-d') ? 'H:i' : 'd/m H:i', $t) : '';
    }

    /** «domani alle 10:31». */
    private function entro(int $istante): string
    {
        $adesso = $this->orologio->adesso();
        $giorno = match (date('Y-m-d', $istante)) {
            $adesso->format('Y-m-d') => 'oggi',
            $adesso->modify('+1 day')->format('Y-m-d') => 'domani',
            default => 'il ' . date('d/m', $istante),
        };

        return $giorno . ' alle ' . date('H:i', $istante);
    }

    /** inc/ui.php (ui_passi, tema scuro) serve prima della testata, che è dove di solito arriva. */
    private function caricaUi(): void
    {
        if (!function_exists('ui_tema_script')) {
            require_once dirname(__DIR__, 3) . '/inc/ui.php';
        }
    }

    /** @param array<string, mixed> $dati */
    private function pagina(string $quale, array $dati, int $codice = 200): Risposta
    {
        $this->caricaUi();
        $dati += ['avvisoHtml' => $this->flash->html(), 'temaHtml' => ui_tema_script()];

        return Risposta::html(Vista::pagina('SOS assistenza didattica', Vista::rendi('pubblico/sos', ['quale' => $quale] + $dati)), $codice);
    }
}
