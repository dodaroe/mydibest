<?php

declare(strict_types=1);

namespace App\Portale\Pagine;

use App\Amministrazione\PermessiAmministrazione;
use App\Anagrafi\PersonaRepository;
use App\Anagrafi\Vista\Persone;
use App\Attestati\Vista\Qr;
use App\Auth\Csrf;
use App\Auth\Pubblico;
use App\Auth\Sessione;
use App\Auth\Vista\PubblicoVista;
use App\Core\Sito;
use App\Didattica\Costanti as CostantiDidattica;
use App\Http\Pagina;
use App\Http\Richiesta;
use App\Http\Risposta;
use App\Http\Vista;
use App\Portale\Pannello\ContestoPannello;
use App\SosAule\AuleRepository;
use App\SosAule\CampiSos;
use App\SosAule\Costanti;
use App\SosAule\ImpostazioniSos;
use App\SosAule\PersoneSos;
use App\SosAule\TipiRepository;

/**
 * Amministrazione › SOS aule (admin/sos_aule.php): la configurazione del modulo, a schede (?scheda=aule|persone|modulo|impostazioni|registro).
 * Solo chi può tutto il modulo Amministrazione: lo decide il Nucleo (PermessiPagine) prima di costruire la classe, e la pagina lo ricontrolla.
 * I POST li esegue AzioniSosPannello; qui si preparano i dati delle viste (viste/pannello/sos_aule.php e i suoi parziali _sos_*).
 */
final class PaginaSosPannello implements Pagina
{
    private const SCHEDE = [
        'aule' => ['Aule', 'fa-door-open'],
        'persone' => ['Persone', 'fa-user-group'],
        'modulo' => ['Modulo', 'fa-list-check'],
        'impostazioni' => ['Impostazioni', 'fa-sliders'],
        'registro' => ['Registro', 'fa-clock-rotate-left'],
    ];

    public function __construct(
        private readonly AzioniSosPannello $azioni,
        private readonly PermessiAmministrazione $permessi,
        private readonly AuleRepository $aule,
        private readonly PersoneSos $persone,
        private readonly TipiRepository $tipi,
        private readonly CampiSos $campi,
        private readonly ImpostazioniSos $impostazioni,
        private readonly PersonaRepository $anagrafe,
        private readonly ContestoPannello $contesto,
        private readonly Csrf $csrf,
        private readonly Sessione $sessione,
        private readonly Sito $sito,
        private readonly RegistroSosPannello $registro,
    ) {
    }

    public function __invoke(Richiesta $r): Risposta
    {
        if (!$this->permessi->puoTutto($this->contesto->utente($r))) {
            return Risposta::negata('La configurazione di SOS aule è riservata agli amministratori e a chi ha l\'abilitazione al modulo Amministrazione.');
        }
        $area = $this->contesto->area($r);
        $scheda = (string) $r->query('scheda', 'aule');
        $rimasto = [];

        if ($r->eUnPost()) {
            $esito = $this->azioni->esegui($r, (int) $this->sessione->leggi('utente_id'));
            if ($esito['rimasto'] === null) {
                return Risposta::vai('sos_aule.php?p_id=' . $area . '&scheda=' . $esito['scheda'] . ($esito['ancora'] !== '' ? '#' . $esito['ancora'] : ''));
            }
            $scheda = $esito['scheda'];
            $rimasto = $esito['rimasto'];
        }
        if (!isset(self::SCHEDE[$scheda])) {
            $scheda = 'aule';
        }

        if ($scheda === 'registro' && !$r->eUnPost() && $r->query('scarica') !== null) {
            return $this->registro->scarica($r, $area);
        }
        if ($scheda === 'registro' && !$r->eUnPost() && $r->query('foto') !== null) {
            return $this->registro->foto($r);
        }

        $schede = [];
        foreach (self::SCHEDE as $id => [$titolo, $icona]) {
            $schede[] = ['titolo' => $titolo, 'icona' => $icona, 'attiva' => $id === $scheda, 'url' => 'sos_aule.php?p_id=' . $area . '&scheda=' . $id];
        }
        $comuni = ['campoCsrf' => $this->csrf->campo(), 'filtro' => $area];
        $corpo = match ($scheda) {
            'aule' => Vista::rendi('pannello/_sos_aule', $comuni + $this->datiAule($area)),
            'persone' => Vista::rendi('pannello/_sos_persone', $comuni + $this->datiPersone($rimasto['studente'] ?? null)),
            'modulo' => Vista::rendi('pannello/_sos_modulo', $comuni + $this->datiModulo($rimasto['aggiunti'] ?? null)),
            'impostazioni' => Vista::rendi('pannello/_sos_impostazioni', $comuni + $this->datiImpostazioni($rimasto['impostazioni'] ?? null)),
            'registro' => Vista::rendi('pannello/_sos_registro', $comuni + $this->registro->dati($r, $area)),
        };

        return Risposta::html(Vista::pannello('SOS aule', Vista::rendi('pannello/sos_aule', ['schede' => $schede, 'corpo' => $corpo])));
    }

    // ─────────────────────────── I dati delle schede ───────────────────────────

    /** @return array<string, mixed> */
    private function datiAule(int $area): array
    {
        $qr = new Qr($this->sito->urlBase());
        $righe = $this->aule->conConteggi();
        $aule = [];
        foreach ($righe as $i => $a) {
            $n = (int) $a['referenti'];
            $aule[] = [
                'id' => (int) $a['id'], 'nome' => $a['nome'], 'edificio' => $a['edificio'], 'note' => $a['note'], 'codice' => $a['codice'],
                'attiva' => (int) $a['attiva'] === 1,
                'referenti' => $n === 0 ? 'Nessun referente' : ($n === 1 ? '1 referente' : $n . ' referenti'),
                'aperte' => (int) $a['aperte'],
                'cancellabile' => (int) $a['segnalazioni'] === 0,
                'prima' => $i === 0,
                'ultima' => $i === count($righe) - 1,
                'qr' => $qr->html($this->sito->urlBase() . '/sos.php?aula=' . $a['codice'], '', 'QR dell\'aula ' . $a['nome'], 'ui-sos-qr'),
                'urlCartello' => 'sos_cartello.php?p_id=' . $area . '&aula=' . (int) $a['id'],
                'urlProva' => '../sos.php?aula=' . $a['codice'],
            ];
        }

        return ['aule' => $aule, 'attive' => count(array_filter($aule, static fn (array $a): bool => $a['attiva'])), 'urlTutti' => 'sos_cartello.php?p_id=' . $area . '&tutte=1'];
    }

    /** @param array{id: int, valori: array<string, mixed>, errore: string, conferma: bool}|null $rimasto il modulo di uno studente da ridisegnare */
    private function datiPersone(?array $rimasto): array
    {
        $conAnagrafe = $this->anagrafe->conta(true) > 0;
        $ruoli = $conAnagrafe ? $this->anagrafe->opzioni('ruolo') : [];
        $strutture = $conAnagrafe ? $this->anagrafe->opzioni('struttura') : [];
        $ricerca = static fn (string $id): string => $conAnagrafe ? Persone::ricerca($ruoli, $strutture, 'Aggiungi', '', $id) : '';

        $aule = [];
        foreach ($this->aule->tutte() as $a) {
            $referenti = $this->persone->referentiDellAula((int) $a['id']);
            $aule[] = [
                'id' => (int) $a['id'], 'nome' => $a['nome'], 'edificio' => $a['edificio'], 'attiva' => (int) $a['attiva'] === 1,
                'persone' => $referenti, 'quanti' => count($referenti), 'ricerca' => $ricerca('sosa' . (int) $a['id']),
            ];
        }
        $vuoto = ['id' => 0, 'nome' => '', 'cognome' => '', 'email' => '', 'dal' => '', 'al' => '', 'note' => '', 'attivo' => 1];
        $modulo = static function (array $valori, string $azione) use ($rimasto): array {
            $mio = $rimasto !== null && $rimasto['id'] === (int) $valori['id'];

            return ($mio ? $rimasto['valori'] + $valori : $valori)
                + ['azione' => $azione, 'errore' => $mio ? $rimasto['errore'] : '', 'conferma' => $mio && $rimasto['conferma'], 'aperto' => $mio];
        };
        $studenti = [];
        foreach ($this->persone->studenti() as $s) {
            $periodo = trim(($s['dal'] ? 'dal ' . date('d/m/Y', strtotime((string) $s['dal'])) : '') . ($s['al'] ? ' al ' . date('d/m/Y', strtotime((string) $s['al'])) : ''));
            $studenti[] = [
                'id' => (int) $s['id'], 'nominativo' => trim($s['cognome'] . ' ' . $s['nome']), 'email' => $s['email'], 'note' => $s['note'],
                'periodo' => $periodo === '' ? 'senza scadenza' : $periodo, 'attivo' => (int) $s['attivo'] === 1, 'avvisi' => $s['avvisi'],
                'modulo' => $modulo([
                    'id' => (int) $s['id'], 'nome' => $s['nome'], 'cognome' => $s['cognome'], 'email' => $s['email'], 'dal' => (string) $s['dal'],
                    'al' => (string) $s['al'], 'note' => $s['note'], 'attivo' => (int) $s['attivo'],
                ], 'studente_salva'),
            ];
        }
        $riserva = $this->persone->riserva();

        return [
            'scoperte' => array_values(array_map(static fn (array $a): string => (string) $a['nome'], $this->persone->auleSenzaNessuno())),
            'conAnagrafe' => $conAnagrafe,
            'aule' => $aule,
            'riserva' => $riserva,
            'quantiRiserva' => count($riserva),
            'ricercaRiserva' => $ricerca('sosr'),
            'studenti' => $studenti,
            'nuovo' => $modulo($vuoto, 'studente_aggiungi'),
        ];
    }

    /** @param array{errore: string, post: array<string, mixed>}|null $rimasto i campi aggiunti da ridisegnare */
    private function datiModulo(?array $rimasto): array
    {
        $fissi = $this->campi->fissi();
        $righeFisse = [];
        foreach ($fissi as $chiave => $c) {
            $o = CampiSos::FISSI[$chiave];
            $righeFisse[] = $c + [
                'chiave' => $chiave,
                'originale' => $o['etichetta'],
                'cambiato' => $c['etichetta'] !== $o['etichetta'] || $c['aiuto'] !== $o['aiuto'] || $c['obbligatorio'] !== $o['obbligatorio'] || $c['visibile'] !== $o['visibile'],
            ];
        }

        $aggiunti = $this->campi->aggiunti();
        $righe = [];
        if ($rimasto !== null) {
            // il salvataggio ha trovato un errore: si rimette quello che era stato scritto
            $p = $rimasto['post'];
            foreach ((array) ($p['sx_etichetta'] ?? []) as $i => $etichetta) {
                if (trim((string) $etichetta) !== '') {
                    $righe[] = $this->rigaAggiunta(
                        (string) ($p['sx_chiave'][$i] ?? ''), (string) $etichetta, (string) ($p['sx_tipo'][$i] ?? 'text'), (string) ($p['sx_opzioni'][$i] ?? ''),
                        ($p['sx_obbl'][$i] ?? '0') === '1', (string) ($p['sx_aiuto'][$i] ?? ''), ($p['sx_vis'][$i] ?? '1') === '1'
                    );
                }
            }
        } else {
            foreach ($aggiunti as $c) {
                $righe[] = $this->rigaAggiunta($c['chiave'], $c['etichetta'], $c['tipo'], implode(', ', $c['opzioni']), $c['obbligatorio'], $c['aiuto'], $c['visibile']);
            }
        }
        $vuota = $this->rigaAggiunta('', '', 'text', '', false, '', true);
        if ($righe === []) {
            $righe[] = $vuota;
        }

        $elenchi = [];
        foreach ([TipiRepository::TIPI => ['Tipi di problema', 'Sono i pulsanti grandi che il docente tocca.'],
            TipiRepository::ESITI => ['Esiti «non risolto»', 'Quello che lo studente sceglie quando non è riuscito a risolvere.']] as $quale => [$titolo, $spiegazione]) {
            $voci = $this->tipi->elenco($quale, false);
            $elenchi[] = [
                'quale' => $quale, 'titolo' => $titolo, 'spiegazione' => $spiegazione,
                'nuova' => ['nome' => '', 'aiuto' => '', 'chiedeTesto' => false, 'icone' => $this->icone('fa-circle-question')],
                'voci' => array_map(fn (array $v, int $i): array => [
                    'id' => (int) $v['id'], 'nome' => $v['nome'], 'aiuto' => $v['aiuto'], 'icona' => $v['icona'], 'icone' => $this->icone((string) $v['icona']),
                    'chiedeTesto' => (int) $v['chiede_testo'] === 1, 'attivo' => (int) $v['attivo'] === 1, 'prima' => $i === 0, 'ultima' => $i === count($voci) - 1,
                ], $voci, array_keys($voci)),
            ];
        }

        return [
            'fissi' => $righeFisse,
            'righe' => $righe,
            'vuota' => $vuota,
            'massimo' => CampiSos::MAX_AGGIUNTI,
            'erroreAggiunti' => $rimasto['errore'] ?? '',
            'elenchi' => $elenchi,
            'anteprima' => $this->anteprima($fissi, $aggiunti),
        ];
    }

    /** @return array<string, mixed> */
    private function rigaAggiunta(string $chiave, string $etichetta, string $tipo, string $opzioni, bool $obbligatorio, string $aiuto, bool $visibile): array
    {
        return [
            'chiave' => $chiave, 'etichetta' => $etichetta, 'opzioni' => $opzioni, 'aiuto' => $aiuto, 'obbligatorio' => $obbligatorio, 'visibile' => $visibile,
            'tipi' => array_map(
                static fn (string $t): array => ['valore' => $t, 'nome' => CostantiDidattica::TIPI_CAMPO_PRATICA[$t], 'scelto' => $t === $tipo],
                CampiSos::TIPI_AGGIUNTI
            ),
        ];
    }

    /** Le icone da scegliere, con quella di adesso segnata (se non è nell'elenco resta, per non cambiarla salvando). @return list<array{valore: string, nome: string, scelta: bool}> */
    private function icone(string $adesso): array
    {
        $elenco = Costanti::ICONE + (isset(Costanti::ICONE[$adesso]) || $adesso === '' ? [] : [$adesso => 'Quella di adesso']);
        $out = [];
        foreach ($elenco as $classe => $nome) {
            $out[] = ['valore' => $classe, 'nome' => $nome, 'scelta' => $classe === $adesso];
        }

        return $out;
    }

    /**
     * Il form com'è adesso, nell'ordine di sos.php (aula, docente, tipo, urgenza, descrizione, foto, cellulare, insegnamento) e poi i campi aggiunti. Solo da guardare.
     *
     * @param array<string, array<string, mixed>> $fissi
     * @param list<array<string, mixed>> $aggiunti
     * @return array<string, mixed>
     */
    private function anteprima(array $fissi, array $aggiunti): array
    {
        $tipi = [];
        foreach (['aula' => 'aula', 'docente' => 'testo', 'tipo' => 'tipo', 'urgenza' => 'urgenza', 'descrizione' => 'lungo', 'foto' => 'file', 'telefono' => 'testo', 'insegnamento' => 'testo'] as $chiave => $forma) {
            if ($fissi[$chiave]['visibile']) {
                $tipi[] = ['etichetta' => $fissi[$chiave]['etichetta'], 'aiuto' => $fissi[$chiave]['aiuto'], 'obbligatorio' => $fissi[$chiave]['obbligatorio'], 'forma' => $forma, 'scelte' => []];
            }
        }
        $forme = ['textarea' => 'lungo', 'radio' => 'radio', 'multicheck' => 'caselle', 'checkbox' => 'casella', 'date' => 'data'];
        foreach ($aggiunti as $c) {
            if ($c['visibile']) {
                $tipi[] = ['etichetta' => $c['etichetta'], 'aiuto' => $c['aiuto'], 'obbligatorio' => $c['obbligatorio'], 'forma' => $forme[$c['tipo']] ?? 'testo', 'scelte' => $c['opzioni']];
            }
        }

        return [
            'intro' => $this->impostazioni->tutte()['testo_introduttivo'],
            'campi' => $tipi,
            'aule' => array_map(static fn (array $a): string => trim($a['nome'] . ($a['edificio'] !== '' ? ' · ' . $a['edificio'] : '')), $this->aule->tutte(true)),
            'tipiProblema' => array_map(static fn (array $t): array => ['nome' => $t['nome'], 'icona' => $t['icona'], 'aiuto' => $t['aiuto']], $this->tipi->elenco(TipiRepository::TIPI)),
            'urgenze' => array_values(Costanti::URGENZE),
        ];
    }

    /** @param array{valori: array<string, mixed>, errori: array<string, string>}|null $rimasto */
    private function datiImpostazioni(?array $rimasto): array
    {
        $v = ($rimasto['valori'] ?? []) + $this->impostazioni->tutte();
        $errori = $rimasto['errori'] ?? [];
        $profili = array_values(array_diff(Pubblico::PROFILI, [Pubblico::ANONIMI]));

        return [
            'aperto' => (bool) $v['aperto'],
            'attivoDavvero' => $this->impostazioni->aperto(),
            'motivo' => $this->impostazioni->motivoChiusura() ?? '',
            'chi' => PubblicoVista::scelta('sos_chi', ['profili' => (array) $v['chi']], $profili, 'Chi può inviare una segnalazione'),
            'telefono' => (string) $v['telefono_riserva'],
            'orari' => (string) $v['orari'],
            'ferma' => (string) $v['promemoria_ferma'],
            'altre' => (string) $v['promemoria_altre'],
            'intro' => (string) $v['testo_introduttivo'],
            'giorni' => (string) $v['conserva_foto_giorni'],
            'errori' => array_values($errori),
        ];
    }
}
