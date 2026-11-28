<?php

declare(strict_types=1);

namespace App\Portale\Pagine;

use App\Assistenza\Push\ServizioPush;
use App\Auth\Csrf;
use App\Auth\Flash;
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
use App\SosAule\CodaInterventi;
use App\SosAule\Costanti;
use App\SosAule\ImpostazioniSos;
use App\SosAule\NotificheSos;
use App\SosAule\PersoneSos;
use App\SosAule\RegoleSos;
use App\SosAule\SegnalazioniRepository;
use App\SosAule\ServizioSos;
use App\SosAule\TipiRepository;

/**
 * SOS aule: la pagina di chi interviene (sos_interventi.php: referenti delle aule, responsabili di riserva, studenti part-time). Disegno approvato:
 * documentazione/schizzo_sos_interventi.html.
 *
 * Non collegato = login SSO e ritorno qui (con ?id=, il link delle email). Chi non interviene = negato. Il token CSRF di ogni POST lo controlla il Nucleo.
 *  - sos_interventi.php[?scheda=da_fare|in_corso|chiuse]: le schede (CodaInterventi); &frammento=1 è la parte che si aggiorna da sola;
 *  - sos_interventi.php?id=<id>: la scheda di quella segnalazione, con la sua cronologia. La vede (e ci agisce) solo chi per ServizioSos::ruoli è referente
 *    o studente di quella segnalazione: vale per la pagina, il frammento e i POST.
 * Le regole dei passaggi stanno in ServizioSos e Costanti::TRANSIZIONI (i pulsanti si mostrano da lì); gli avvisi (NotificheSos) partono dopo l'esito.
 * La foto è quella di sos.php?segnalazione=<id>&foto=1, con i suoi controlli.
 */
final class PaginaSosInterventi implements Pagina
{
    private const SCHEDE = ['da_fare' => 'Da fare', 'in_corso' => 'In corso', 'chiuse' => 'Chiuse oggi'];
    private const VUOTE = ['da_fare' => 'Niente da fare.', 'in_corso' => 'Niente in corso.', 'chiuse' => 'Oggi non è stata chiusa nessuna segnalazione.'];

    public function __construct(
        private readonly ServizioSos $servizio,
        private readonly SegnalazioniRepository $segnalazioni,
        private readonly CodaInterventi $coda,
        private readonly PersoneSos $persone,
        private readonly TipiRepository $tipi,
        private readonly ImpostazioniSos $impostazioni,
        private readonly NotificheSos $notifiche,
        private readonly ServizioPush $push,
        private readonly UtenteRepository $utenti,
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
        $id = max(0, (int) $r->query('id', 0));
        $uid = (int) $this->sessione->leggi('utente_id');
        $utente = $uid > 0 ? $this->utenti->perId($uid) : null;
        if ($utente === null) {
            return Risposta::vai('saml_login.php?redirect=' . urlencode(self::url($id)), 302);
        }
        if (!$this->coda->ammesso($utente)) {
            return $this->negata('Questa pagina è per chi interviene in aula: referenti, responsabili di riserva e studenti in servizio.');
        }
        $selezionata = null;
        if ($id > 0) {
            $selezionata = $this->segnalazioni->perId($id);
            if ($selezionata === null) {
                return Risposta::nonTrovata('La segnalazione non esiste.');
            }
            if ($this->ruoli($utente, $selezionata) === []) {
                return $this->negata('Non puoi vedere questa segnalazione: non è di un\'aula che segui e non è affidata a te (forse è stata ripresa o passata a un altro).');
            }
        }
        if ($r->eUnPost()) {
            return $selezionata === null ? Risposta::vai(self::url()) : $this->azione($r, $utente, $selezionata);
        }

        $schede = $this->coda->schede($utente);
        $aperto = $this->impostazioni->aperto();
        // a servizio spento resta aperta a chi ha ancora qualcosa in mano (o guarda una sua segnalazione): deve poterla chiudere
        if (!$aperto && $selezionata === null && $schede['da_fare'] === [] && $schede['in_corso'] === []) {
            return $this->pagina('messaggio', ['testo' => 'Il servizio SOS aule non è attivo.', 'tono' => 'attesa', 'indietro' => '']);
        }
        $this->caricaUi();
        $elenco = $this->elenco($r, $utente, $schede, $selezionata);
        if ($r->query('frammento') !== null) {
            return Risposta::html(Vista::rendi('pubblico/_sos_interventi_elenco', $elenco))->conIntestazione('Cache-Control', 'private, no-store');
        }

        $aule = $this->persone->auleDelReferente($utente);
        $script = 'assets/js/assistenza-push.js';

        return $this->pagina('elenco', [
            'sottotitolo' => $aule === null ? 'Le segnalazioni di tutte le aule.' : ($aule !== [] ? 'Le segnalazioni delle tue aule.' : 'Gli interventi affidati a te.'),
            'spento' => !$aperto,
            'elencoHtml' => Vista::rendi('pubblico/_sos_interventi_elenco', $elenco),
            'urlFrammento' => self::url($id, $elenco['scheda']) . ($id > 0 || $elenco['scheda'] !== 'da_fare' ? '&' : '?') . 'frammento=1',
            'ancora' => $id > 0 ? 'sos-' . $id : '',
            'push' => $this->push->attivo(),
            'csrfToken' => $this->csrf->token(),
            'scriptPush' => $script . '?v=' . (File::modificato($this->sito->radice() . '/' . $script) ?: 1),
        ]);
    }

    /** L'indirizzo della pagina: con la segnalazione (il link delle email) o con la scheda. */
    public static function url(int $id = 0, string $scheda = 'da_fare'): string
    {
        if ($id > 0) {
            return 'sos_interventi.php?id=' . $id;
        }

        return 'sos_interventi.php' . ($scheda !== 'da_fare' ? '?scheda=' . $scheda : '');
    }

    /** I ruoli che danno accesso a questa pagina: referente o studente (chi l'ha solo inviata la vede in sos.php). @param array<string, mixed> $utente @param array<string, mixed> $s @return list<string> */
    private function ruoli(array $utente, array $s): array
    {
        return array_values(array_intersect($this->servizio->ruoli($utente, $s), [Costanti::REFERENTE, Costanti::STUDENTE]));
    }

    // ───────────────────────────── I pulsanti ─────────────────────────────

    /**
     * Un passaggio di stato, il messaggio dell'esito e, se è riuscito, gli avvisi.
     *
     * @param array<string, mixed> $utente
     * @param array<string, mixed> $prima la segnalazione com'è prima del passaggio (serve a sapere a quale studente viene tolta)
     */
    private function azione(Richiesta $r, array $utente, array $prima): Risposta
    {
        $id = (int) $prima['id'];
        $dati = $r->datiModulo();
        $azione = RegoleSos::testo($dati, 'azione');
        $minuti = RegoleSos::testo($dati, 'minuti');
        $minuti = $minuti === '' ? null : (ctype_digit($minuti) && strlen($minuti) < 6 ? (int) $minuti : -1);

        $esito = match ($azione) {
            'prendi' => $this->servizio->prendi($id, $utente),
            'riprendi' => $this->servizio->riprendi($id, $utente),
            'affida' => $this->servizio->affida($id, $utente, (int) RegoleSos::testo($dati, 'studente')),
            'sto_andando' => $this->servizio->staAndando($id, $utente),
            'risolta' => $this->servizio->risolta($id, $utente, $minuti),
            'non_risolta' => $this->servizio->nonRisolta($id, $utente, (int) RegoleSos::testo($dati, 'esito'), RegoleSos::testo($dati, 'nota'), $minuti),
            'inoltra' => $this->servizio->inoltra($id, $utente, RegoleSos::testo($dati, 'a_chi')),
            'chiudi' => $this->servizio->chiudiNonRisolta($id, $utente, RegoleSos::testo($dati, 'nota')),
            default => Esito::errore('Pulsante non riconosciuto: riprova.'),
        };
        $this->flash->imposta($esito->messaggio, $esito->riuscito ? 'success' : 'warning');
        if ($esito->riuscito) {
            match ($azione) {
                'prendi', 'riprendi', 'sto_andando' => $this->notifiche->staArrivando($id, $utente),
                'affida' => $this->notifiche->affidata($id, $utente),
                'non_risolta' => $this->notifiche->nonRisolta($id, $utente),
                'risolta', 'inoltra', 'chiudi' => $this->notifiche->chiusa($id, $utente),
                default => null,
            };
            $this->notifiche->tolta($prima, $utente);
        }

        return Risposta::vai(self::url($id));
    }

    // ───────────────────────────── L'elenco ─────────────────────────────

    /**
     * Le linguette e le card della scheda aperta. Con una segnalazione scelta si apre la sua scheda; se non è in nessuna (chiusa un altro giorno,
     * o appena segnata «non risolta» dallo studente) la card sta in cima a «Da fare».
     *
     * @param array<string, mixed> $utente
     * @param array{da_fare: list<array<string, mixed>>, in_corso: list<array<string, mixed>>, chiuse: list<array<string, mixed>>} $schede
     * @param array<string, mixed>|null $selezionata
     * @return array{scheda: string, linguette: list<array<string, mixed>>, card: list<array<string, mixed>>, vuoto: string}
     */
    private function elenco(Richiesta $r, array $utente, array $schede, ?array $selezionata): array
    {
        $scheda = (string) $r->query('scheda', 'da_fare');
        $scheda = isset(self::SCHEDE[$scheda]) ? $scheda : 'da_fare';
        $scelta = (int) ($selezionata['id'] ?? 0);
        $fuori = $selezionata !== null;
        foreach ($schede as $chiave => $righe) {
            if ($scelta > 0 && in_array($scelta, array_map('intval', array_column($righe, 'id')), true)) {
                $scheda = $chiave;
                $fuori = false;
            }
        }
        $righe = $schede[$scheda];
        if ($fuori) {
            array_unshift($righe, $selezionata);
        }
        $studenti = $this->persone->studentiAttiviOggi();
        $esiti = $this->tipi->elenco(TipiRepository::ESITI);

        $linguette = [];
        foreach (self::SCHEDE as $chiave => $nome) {
            $linguette[] = ['testo' => $nome, 'numero' => count($schede[$chiave]), 'attiva' => $chiave === $scheda, 'url' => self::url(0, $chiave)];
        }

        return [
            'scheda' => $scheda,
            'linguette' => $linguette,
            'card' => array_map(fn (array $s): array => $this->card($utente, $s, (int) $s['id'] === $scelta, $studenti, $esiti), $righe),
            'vuoto' => self::VUOTE[$scheda],
        ];
    }

    /**
     * Una segnalazione com'è per chi la guarda: i dati, chi l'ha in mano e i pulsanti ammessi da Costanti::TRANSIZIONI per lo stato e il ruolo.
     *
     * @param array<string, mixed> $utente
     * @param array<string, mixed> $s
     * @param list<array<string, mixed>> $studenti quelli in servizio oggi
     * @param list<array<string, mixed>> $esiti
     * @return array<string, mixed>
     */
    private function card(array $utente, array $s, bool $aperta, array $studenti, array $esiti): array
    {
        $id = (int) $s['id'];
        $stato = (string) $s['stato'];
        $ruoli = $this->ruoli($utente, $s);
        $referente = in_array(Costanti::REFERENTE, $ruoli, true);
        $studente = in_array(Costanti::STUDENTE, $ruoli, true);
        $puo = static fn (string $a): bool => $referente && Costanti::ammessa($stato, $a, Costanti::REFERENTE);
        $cronologia = $this->segnalazioni->cronologia($id);
        $ultimo = [];
        foreach ($cronologia as $c) {
            if ((string) $c['a_stato'] !== '') {
                $ultimo[(string) $c['a_stato']] = $c;
            }
        }
        $assegnato = $s['assegnata_a_studente'] === null ? null : $this->persone->studentePerId((int) $s['assegnata_a_studente']);
        $nomeAssegnato = $assegnato === null ? '' : trim($assegnato['nome'] . ' ' . $assegnato['cognome']);
        // «toglie a qualcuno»: l'intervento è in mano a uno studente
        $daStudente = in_array($stato, ['assegnata', 'in_corso'], true);

        $altri = [];
        foreach ($studenti as $x) {
            if (!$daStudente || (int) $x['id'] !== (int) $s['assegnata_a_studente']) {
                $altri[] = ['id' => (int) $x['id'], 'nome' => trim($x['nome'] . ' ' . $x['cognome'])];
            }
        }

        $nonRisolta = ['titolo' => '', 'testo' => ''];
        if ($stato === 'non_risolta' && isset($ultimo[$stato])) {
            $p = $ultimo[$stato];
            if ((string) $p['da_stato'] === 'risolta') {
                $nonRisolta = ['titolo' => 'Chi ha segnalato dice che non è risolto', 'testo' => PaginaSos::nome((string) $p['chi']) . ', ' . $this->alle((string) $p['quando'])];
            } else {
                $nome = '';
                foreach ($esiti as $e) {
                    $nome = (int) $e['id'] === (int) $s['esito_id'] ? (string) $e['nome'] : $nome;
                }
                $nota = trim((string) $s['esito_nota']);
                $nonRisolta = ['titolo' => 'Non risolto: ' . mb_strtolower(mb_substr($nome, 0, 1)) . mb_substr($nome, 1),
                    'testo' => ($nota !== '' ? '«' . $nota . '» ' : '') . PaginaSos::nome((string) $p['chi']) . ', ' . $this->alle((string) $p['quando'])];
            }
        }

        $chi = static fn (string $a): string => PaginaSos::nome((string) ($ultimo[$a]['chi'] ?? ''));
        $quando = fn (string $a): string => $this->alle((string) ($ultimo[$a]['quando'] ?? ''));
        $dalle = static fn (string $a): string => str_replace('alle ', 'dalle ', $quando($a));
        $soloStudente = $studente && !$referente;
        $inMano = match ($stato) {
            'inviata' => 'Non l\'ha ancora presa nessuno.',
            'presa' => 'In mano a ' . $chi($stato) . ' ' . $dalle($stato) . '.',
            'assegnata' => $soloStudente ? 'Te l\'ha affidata ' . $chi($stato) . ' ' . $quando($stato) . '.' : 'Affidata a ' . $nomeAssegnato . ' ' . $quando($stato) . '.',
            'in_corso' => $soloStudente ? 'Stai andando ' . $dalle($stato) . '.' : $nomeAssegnato . ' sta andando ' . $dalle($stato) . '.',
            'inoltrata' => 'Inoltrata a: «' . $s['inoltrata_a'] . '». ' . $chi($stato) . '.',
            'risolta', 'chiusa_non_risolta', 'annullata' => Costanti::etichetta($stato) . ' ' . $quando($stato) . ' da ' . $chi($stato) . '.',
            default => '',
        };

        $telefono = trim((string) $s['telefono']);
        $urgenza = (string) $s['urgenza'];
        $righe = [];
        if ($aperta) {
            foreach ($cronologia as $c) {
                $righe[] = ['ora' => $this->ora((string) $c['quando']), 'testo' => PaginaSos::frase($c)];
            }
        }

        return [
            'id' => $id,
            'aperta' => $aperta,
            'classe' => isset(Costanti::URGENZE_BREVI[$urgenza]) ? $urgenza : 'prossime',
            'ferma' => $urgenza === 'ferma',
            'urgenza' => Costanti::URGENZE_BREVI[$urgenza] ?? $urgenza,
            'aula' => (string) $s['aula_nome'],
            'edificio' => (string) $s['aula_edificio'],
            'daQuanto' => in_array($stato, Costanti::STATI_APERTI, true) ? $this->daQuanto((string) $s['inviata_il']) : '',
            'stato' => Costanti::etichetta($stato),
            'colore' => Costanti::STATI[$stato]['colore'] ?? 'neutro',
            'icona' => (string) $s['tipo_icona'],
            'tipo' => (string) $s['tipo_nome'],
            'descrizione' => (string) $s['descrizione'],
            'aggiunti' => $this->servizio->datiCompilati($s),
            'foto' => (string) $s['foto'] !== '' ? PaginaSos::url('', $id) . '&foto=1' : '',
            'docente' => trim($s['nome'] . ' ' . $s['cognome']),
            'telefono' => $telefono,
            'telefonoHref' => $telefono !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $telefono) : '',
            'codice' => (string) $s['codice'],
            'ora' => $this->ora((string) $s['inviata_il']),
            'nonRisoltaTitolo' => $nonRisolta['titolo'],
            'nonRisoltaTesto' => $nonRisolta['testo'],
            'inMano' => $inMano,
            'cronologia' => $righe,
            'url' => self::url($id),
            'campoCsrf' => $this->csrf->campo(),
            // referente
            'prendi' => $puo('presa') ? ($stato === 'inviata' ? 'Ci penso io' : 'La riprendo io') : '',
            'prendiAzione' => $stato === 'inviata' ? 'prendi' : 'riprendi',
            'confermaPrendi' => $daStudente ? 'Togli l\'intervento a ' . $nomeAssegnato . ' e lo riprendi tu?' : '',
            'affida' => $puo('assegnata') ? (in_array($stato, ['inviata', 'presa'], true) ? 'Mando uno studente' : 'La passo a un altro studente') : '',
            'confermaAffida' => $daStudente ? 'Togli l\'intervento a ' . $nomeAssegnato . ' e lo passi a un altro studente?' : '',
            'studenti' => $altri,
            'nessunoStudente' => $studenti === [] ? 'Oggi non c\'è nessuno studente in servizio.' : 'Oggi non c\'è un altro studente in servizio.',
            'risolta' => $puo('risolta'),
            'inoltra' => $puo('inoltrata'),
            'chiudi' => $puo('chiusa_non_risolta'),
            // studente
            'staAndando' => $studente && Costanti::ammessa($stato, 'in_corso', Costanti::STUDENTE),
            'risoltoStudente' => $studente && !$referente && Costanti::ammessa($stato, 'risolta', Costanti::STUDENTE),
            'nonRisolto' => $studente && Costanti::ammessa($stato, 'non_risolta', Costanti::STUDENTE),
            'esiti' => array_values(array_map(
                static fn (array $e): array => ['id' => (int) $e['id'], 'nome' => (string) $e['nome'], 'icona' => (string) $e['icona']],
                array_filter($esiti, static fn (array $e): bool => (int) $e['attivo'] === 1)
            )),
            'maxNota' => Costanti::MAX_NOTA,
            'maxMinuti' => Costanti::MAX_MINUTI,
        ];
    }

    // ───────────────────────────── Parti comuni ─────────────────────────────

    /** «da 7 minuti», «da 3 ore», «da 2 giorni». */
    private function daQuanto(string $inviata): string
    {
        $minuti = max(0, intdiv($this->orologio->adesso()->getTimestamp() - (int) strtotime($inviata), 60));

        return match (true) {
            $minuti < 1 => 'da meno di un minuto',
            $minuti === 1 => 'da 1 minuto',
            $minuti < 60 => 'da ' . $minuti . ' minuti',
            $minuti < 120 => 'da 1 ora',
            $minuti < 1440 => 'da ' . intdiv($minuti, 60) . ' ore',
            $minuti < 2880 => 'da 1 giorno',
            default => 'da ' . intdiv($minuti, 1440) . ' giorni',
        };
    }

    /** «10:12»; di un altro giorno «18/11 10:12». */
    private function ora(string $data): string
    {
        $t = strtotime($data);

        return $t ? date(date('Y-m-d', $t) === $this->orologio->adesso()->format('Y-m-d') ? 'H:i' : 'd/m H:i', $t) : '';
    }

    /** «alle 10:12»; di un altro giorno «il 18/11 alle 10:12». */
    private function alle(string $data): string
    {
        $t = strtotime($data);
        if (!$t) {
            return '';
        }

        return (date('Y-m-d', $t) === $this->orologio->adesso()->format('Y-m-d') ? '' : 'il ' . date('d/m', $t) . ' ') . 'alle ' . date('H:i', $t);
    }

    private function negata(string $messaggio): Risposta
    {
        return Risposta::negata($messaggio, fn (string $m): string => $this->pagina('messaggio', ['testo' => $m, 'tono' => 'azione', 'indietro' => self::url()])->corpo());
    }

    /** inc/ui.php (tema scuro) serve prima della testata, che è dove di solito arriva. */
    private function caricaUi(): void
    {
        if (!function_exists('ui_tema_script')) {
            require_once dirname(__DIR__, 3) . '/inc/ui.php';
        }
    }

    /** @param array<string, mixed> $dati */
    private function pagina(string $quale, array $dati): Risposta
    {
        $this->caricaUi();
        $dati += ['avvisoHtml' => $this->flash->html(), 'temaHtml' => ui_tema_script()];

        return Risposta::html(Vista::pagina('SOS aule', Vista::rendi('pubblico/sos_interventi', ['quale' => $quale] + $dati)));
    }
}
