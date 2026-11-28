<?php

declare(strict_types=1);

namespace App\Portale\Pagine;

use App\Auth\Flash;
use App\Auth\Sessione;
use App\Core\IndirizzoClient;
use App\Core\Orologio;
use App\Http\Richiesta;
use App\Http\Risposta;
use App\Infrastructure\Audit\AuditLog;
use App\Infrastructure\Documenti\FogliEsportabili;
use App\Infrastructure\Documenti\TabellaEsportabile;
use App\Portale\Vista\Pannello;
use App\SosAule\AuleRepository;
use App\SosAule\Costanti;
use App\SosAule\FotoSos;
use App\SosAule\NumeriSos;
use App\SosAule\PersoneSos;
use App\SosAule\SegnalazioniRepository;
use App\SosAule\ServizioSos;
use App\SosAule\TipiRepository;

/**
 * La scheda «Registro» di admin/sos_aule.php (la chiama PaginaSosPannello, che ha già controllato chi può): tutte le segnalazioni con i filtri, la cronologia
 * aprendo una riga, i numeri del periodo e gli scarichi in Excel, PDF e CSV (?scarica=<formato>&cosa=elenco|numeri).
 *
 * Il periodo vale per elenco e numeri; gli altri filtri solo per l'elenco. Senza date nell'indirizzo è l'anno accademico in corso (dal 1° ottobre a oggi);
 * date presenti ma vuote = nessun limite (è così che si arriva dalla voce «non risolte», per non nasconderne di vecchie).
 */
final class RegistroSosPannello
{
    /** Quante segnalazioni si disegnano nella pagina: le altre si vedono stringendo i filtri o scaricando l'elenco. */
    private const MAX_RIGHE = 200;

    private const MESI = [1 => 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];

    public function __construct(
        private readonly SegnalazioniRepository $segnalazioni,
        private readonly ServizioSos $servizio,
        private readonly AuleRepository $aule,
        private readonly TipiRepository $tipi,
        private readonly PersoneSos $persone,
        private readonly Orologio $orologio,
        private readonly Sessione $sessione,
        private readonly Flash $flash,
        private readonly AuditLog $audit,
        private readonly IndirizzoClient $indirizzo,
        private readonly FotoSos $foto,
    ) {
    }

    /** La foto di una riga (?foto=<id>): stessi controlli e stesse intestazioni di sos.php, con il permesso del registro. */
    public function foto(Richiesta $r): Risposta
    {
        $s = $this->segnalazioni->perId((int) $this->parametro($r, 'foto'));

        return $s === null ? Risposta::nonTrovata('La foto non c\'è.') : PaginaSos::scaricaFoto($this->foto, $s);
    }

    /** I dati di viste/pannello/_sos_registro.php. @return array<string, mixed> */
    public function dati(Richiesta $r, int $area): array
    {
        $f = $this->filtri($r);
        $righe = $this->segnalazioni->elenco($f);
        $periodo = $this->segnalazioni->elenco(['dal' => $f['dal'], 'al' => $f['al']]);
        $mostrate = array_slice($righe, 0, self::MAX_RIGHE);
        $cronologie = $this->segnalazioni->cronologie(array_values(array_unique(array_map('intval', array_column(array_merge($periodo, $mostrate), 'id')))));
        $n = NumeriSos::calcola($periodo, $cronologie);
        $studenti = $this->persone->studenti();

        $scelte = static function (array $voci, string $scelta): array {
            $out = [];
            foreach ($voci as $valore => $testo) {
                $out[] = ['valore' => (string) $valore, 'testo' => (string) $testo, 'scelta' => (string) $valore === $scelta];
            }

            return $out;
        };
        $nomiStudenti = $this->nomiStudenti($studenti);
        $url = 'sos_aule.php?' . http_build_query(['p_id' => $area, 'scheda' => 'registro', 'dal' => $f['dal'], 'al' => $f['al'], 'aula' => $f['aula_id'] ?: '', 'tipo' => $f['tipo_id'] ?: '',
            'stato' => $f['stato'], 'urgenza' => $f['urgenza'], 'studente' => $f['studente_id'] ?: '', 'q' => $f['testo']]);

        return [
            'dal' => $f['dal'], 'al' => $f['al'], 'testo' => $f['testo'],
            'periodo' => $this->periodo($f),
            'aule' => $scelte(array_column($this->aule->tutte(), 'nome', 'id'), (string) $f['aula_id']),
            'tipi' => $scelte(array_column($this->tipi->elenco(TipiRepository::TIPI, false), 'nome', 'id'), (string) $f['tipo_id']),
            'stati' => $scelte(array_map(static fn (array $s): string => $s['etichetta'], Costanti::STATI), $f['stato']),
            'urgenze' => $scelte(Costanti::URGENZE, $f['urgenza']),
            'studenti' => $scelte($nomiStudenti, (string) $f['studente_id']),
            'urlAzzera' => 'sos_aule.php?p_id=' . $area . '&scheda=registro',
            'quante' => count($righe),
            'altre' => count($righe) - count($mostrate),
            'righe' => $this->righe($mostrate, $cronologie, $nomiStudenti, 'sos_aule.php?p_id=' . $area . '&scheda=registro&foto='),
            'numeriHtml' => Pannello::percorso([
                ['numero' => $n['quante'], 'testo' => 'Segnalazioni', 'nota' => 'inviate nel periodo'],
                ['numero' => NumeriSos::durata($n['presa']['minuti']), 'testo' => 'Tempo medio fino alla presa in carico', 'nota' => 'su ' . $n['presa']['quante'] . ' prese in carico'],
                ['numero' => NumeriSos::durata($n['chiusura']['minuti']), 'testo' => 'Tempo medio fino alla chiusura', 'nota' => 'su ' . $n['chiusura']['quante'] . ' chiuse'],
                ['numero' => $n['alPrimo'] . '/' . $n['risolte'], 'testo' => 'Risolte al primo intervento', 'nota' => 'sul totale delle risolte'],
            ], 'Numeri del periodo'),
            'perAula' => $n['perAula'],
            'perTipo' => $n['perTipo'],
            'malate' => $n['malate'],
            'soglia' => Costanti::SOGLIA_AULA_MALATA,
            'minuti' => $this->minuti($f),
            'scaricaElencoHtml' => Pannello::scarica($url . '&cosa=elenco', 'Scarica l\'elenco'),
            'scaricaNumeriHtml' => Pannello::scarica($url . '&cosa=numeri', 'Scarica i numeri'),
            'filtro' => $area,
        ];
    }

    /** L'elenco (con tutti i filtri) o i numeri (del periodo) in Excel, PDF o CSV. */
    public function scarica(Richiesta $r, int $area): Risposta
    {
        $formato = $this->parametro($r, 'scarica');
        $cosa = $this->parametro($r, 'cosa') === 'numeri' ? 'numeri' : 'elenco';
        $torna = 'sos_aule.php?p_id=' . $area . '&scheda=registro';
        if (!isset(TabellaEsportabile::FORMATI[$formato])) {
            $this->flash->imposta('Formato non disponibile.', 'danger');

            return Risposta::vai($torna);
        }
        $f = $this->filtri($r);
        $tab = $cosa === 'numeri' ? $this->tabelleNumeri($f) : $this->tabellaElenco($f, $formato);
        $nome = 'SOS_aule_' . $cosa . '_' . $this->orologio->adesso()->format('Y-m-d');
        $this->audit->registra((int) $this->sessione->leggi('utente_id'), 'SOS aule: registro esportato',
            ['Cosa' => $cosa, 'Formato' => $formato, 'Periodo' => $this->periodo($f)], $this->indirizzo->ip() ?? 'Sconosciuto');
        if ($formato === 'csv') {
            return Risposta::scaricabile($tab->csv(), $nome . '.csv', 'text/csv; charset=utf-8');
        }
        if ($formato === 'pdf') {
            return Risposta::scaricabile($tab->pdf(), $nome . '.pdf', 'application/pdf');
        }
        $tmp = $tab->xlsx();
        if ($tmp === null) {
            $this->flash->imposta('Il file Excel non si è potuto creare: scarica il CSV, che si apre con Excel.', 'warning');

            return Risposta::vai($torna);
        }

        return Risposta::file($tmp, $nome . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', true);
    }

    /** @return array{dal: string, al: string, aula_id: int, tipo_id: int, stato: string, urgenza: string, studente_id: int, testo: string} */
    private function filtri(Richiesta $r): array
    {
        $data = function (string $nome) use ($r): string {
            $v = $this->parametro($r, $nome);

            return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $v : '';
        };
        $dal = $data('dal');
        $al = $data('al');
        if ($r->query('dal') === null && $r->query('al') === null) {
            $oggi = $this->orologio->adesso();
            $dal = ((int) $oggi->format('Y') - ((int) $oggi->format('n') >= 10 ? 0 : 1)) . '-10-01';
            $al = $oggi->format('Y-m-d');
        }
        $stato = $this->parametro($r, 'stato');
        $urgenza = $this->parametro($r, 'urgenza');

        return [
            'dal' => $dal, 'al' => $al,
            'aula_id' => max(0, (int) $this->parametro($r, 'aula')),
            'tipo_id' => max(0, (int) $this->parametro($r, 'tipo')),
            'stato' => isset(Costanti::STATI[$stato]) ? $stato : '',
            'urgenza' => isset(Costanti::URGENZE[$urgenza]) ? $urgenza : '',
            'studente_id' => max(0, (int) $this->parametro($r, 'studente')),
            'testo' => mb_substr($this->parametro($r, 'q'), 0, 100),
        ];
    }

    private function parametro(Richiesta $r, string $nome): string
    {
        $v = $r->query($nome, '');

        return is_scalar($v) ? trim((string) $v) : '';
    }

    /** @param array{dal: string, al: string} $f */
    private function periodo(array $f): string
    {
        return match (true) {
            $f['dal'] !== '' && $f['al'] !== '' => 'dal ' . self::giorno($f['dal']) . ' al ' . self::giorno($f['al']),
            $f['dal'] !== '' => 'dal ' . self::giorno($f['dal']),
            $f['al'] !== '' => 'fino al ' . self::giorno($f['al']),
            default => 'tutte le date',
        };
    }

    /** @param list<array<string, mixed>> $studenti @return array<int, string> */
    private function nomiStudenti(array $studenti): array
    {
        $out = [];
        foreach ($studenti as $s) {
            $out[(int) $s['id']] = trim($s['cognome'] . ' ' . $s['nome']);
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $righe
     * @param array<int, list<array<string, mixed>>> $cronologie
     * @param array<int, string> $studenti
     * @return list<array<string, mixed>>
     */
    private function righe(array $righe, array $cronologie, array $studenti, string $urlFoto): array
    {
        $esiti = array_column($this->tipi->elenco(TipiRepository::ESITI, false), 'nome', 'id');
        $out = [];
        foreach ($righe as $s) {
            $stato = (string) $s['stato'];
            $storia = [];
            foreach ($cronologie[(int) $s['id']] ?? [] as $c) {
                $storia[] = ['ora' => date('d/m H:i', (int) strtotime((string) $c['quando'])), 'testo' => PaginaSos::frase($c)];
            }
            $nota = trim((string) $s['esito_nota']);
            $esito = (string) ($esiti[(int) $s['esito_id']] ?? '');
            $out[] = [
                'id' => (int) $s['id'],
                'codice' => (string) $s['codice'],
                'quando' => date('d/m/Y H:i', (int) strtotime((string) $s['inviata_il'])),
                'aula' => (string) $s['aula_nome'],
                'edificio' => (string) $s['aula_edificio'],
                'tipo' => (string) $s['tipo_nome'],
                'icona' => (string) $s['tipo_icona'],
                'urgenza' => Costanti::URGENZE_BREVI[(string) $s['urgenza']] ?? (string) $s['urgenza'],
                'stato' => Costanti::etichetta($stato),
                'colore' => Costanti::STATI[$stato]['colore'] ?? 'neutro',
                'docente' => trim($s['cognome'] . ' ' . $s['nome']),
                'email' => (string) $s['email'],
                'telefono' => (string) $s['telefono'],
                'descrizione' => (string) $s['descrizione'],
                'aggiunti' => $this->servizio->datiCompilati($s),
                'studente' => (string) ($studenti[(int) $s['assegnata_a_studente']] ?? ''),
                'esito' => $esito !== '' ? $esito . ($nota !== '' ? ': ' . $nota : '') : '',
                'inoltrata' => (string) $s['inoltrata_a'],
                'foto' => (string) $s['foto'] !== '' ? $urlFoto . (int) $s['id'] : '',
                'cronologia' => $storia,
            ];
        }

        return $out;
    }

    /** @param array{dal: string, al: string} $f @return list<array{studente: string, mese: string, minuti: int, interventi: int}> */
    private function minuti(array $f): array
    {
        $out = [];
        foreach ($this->segnalazioni->minutiPerMese($f['dal'], $f['al']) as $m) {
            // tolto dall'elenco: vale il nome salvato in cronologia quando ha agito, senza il ruolo
            $nome = trim($m['cognome'] . ' ' . $m['nome']) ?: trim((string) preg_replace('/\(' . Costanti::STUDENTE . '\)$/', '', (string) $m['chi']));
            [$anno, $mese] = array_map('intval', explode('-', (string) $m['mese']));
            $out[] = ['studente' => $nome !== '' ? $nome : 'Studente tolto dall\'elenco', 'mese' => self::MESI[$mese] . ' ' . $anno, 'minuti' => (int) $m['minuti'], 'interventi' => (int) $m['interventi']];
        }

        return $out;
    }

    /**
     * Excel e CSV hanno tutte le colonne; il PDF solo quelle che ci stanno su un foglio.
     *
     * @param array<string, mixed> $f
     */
    private function tabellaElenco(array $f, string $formato): TabellaEsportabile
    {
        $studenti = $this->nomiStudenti($this->persone->studenti());
        $esiti = array_column($this->tipi->elenco(TipiRepository::ESITI, false), 'nome', 'id');
        $data = static fn (mixed $d): string => (string) $d === '' ? '' : date('d/m/Y H:i', (int) strtotime((string) $d));
        $righe = [];
        foreach ($this->segnalazioni->elenco($f) as $s) {
            $altri = implode('; ', array_map(static fn (array $a): string => $a['etichetta'] . ': ' . $a['valore'], $this->servizio->datiCompilati($s)));
            $righe[] = [
                'Codice' => $s['codice'], 'Inviata il' => $data($s['inviata_il']), 'Aula' => $s['aula_nome'], 'Edificio' => $s['aula_edificio'], 'Tipo di problema' => $s['tipo_nome'],
                'Urgenza' => Costanti::URGENZE_BREVI[(string) $s['urgenza']] ?? $s['urgenza'], 'Stato' => Costanti::etichetta((string) $s['stato']), 'Docente' => trim($s['cognome'] . ' ' . $s['nome']),
                'Email' => $s['email'], 'Cellulare' => $s['telefono'], 'Descrizione' => $s['descrizione'], 'Altri dati' => $altri, 'Studente' => $studenti[(int) $s['assegnata_a_studente']] ?? '',
                'Presa il' => $data($s['presa_il']), 'Chiusa il' => $data($s['chiusa_il']), 'Non risolto: che cosa' => $esiti[(int) $s['esito_id']] ?? '', 'Nota del non risolto' => $s['esito_nota'],
                'Inoltrata a' => $s['inoltrata_a'],
            ];
        }
        $colonne = ['Codice', 'Inviata il', 'Aula', 'Edificio', 'Tipo di problema', 'Urgenza', 'Stato', 'Docente', 'Email', 'Cellulare', 'Descrizione', 'Altri dati', 'Studente', 'Presa il', 'Chiusa il',
            'Non risolto: che cosa', 'Nota del non risolto', 'Inoltrata a'];
        if ($formato === 'pdf') {
            $colonne = ['Codice', 'Inviata il', 'Aula', 'Tipo di problema', 'Urgenza', 'Stato', 'Docente', 'Studente', 'Chiusa il'];
        }

        return new TabellaEsportabile('Segnalazioni SOS aule', $colonne,
            array_map(static fn (array $r): array => array_map(static fn (string $c): mixed => $r[$c], $colonne), $righe),
            'Periodo: ' . $this->periodo($f) . ', stampato il ' . $this->orologio->adesso()->format('d/m/Y'));
    }

    /** @param array{dal: string, al: string} $f */
    private function tabelleNumeri(array $f): FogliEsportabili
    {
        $periodo = $this->segnalazioni->elenco(['dal' => $f['dal'], 'al' => $f['al']]);
        $n = NumeriSos::calcola($periodo, $this->segnalazioni->cronologie(array_map('intval', array_column($periodo, 'id'))));
        $conteggio = static fn (array $voci): array => array_map(static fn (array $v): array => [$v['nome'], $v['quante']], $voci);

        return new FogliEsportabili('SOS aule, numeri ' . $this->periodo($f), [
            new TabellaEsportabile('Riepilogo', ['Voce', 'Valore'], [
                ['Periodo', $this->periodo($f)],
                ['Segnalazioni', $n['quante']],
                ['Tempo medio fino alla presa in carico', NumeriSos::durata($n['presa']['minuti'])],
                ['Segnalazioni prese in carico', $n['presa']['quante']],
                ['Tempo medio fino alla chiusura', NumeriSos::durata($n['chiusura']['minuti'])],
                ['Segnalazioni chiuse', $n['chiusura']['quante']],
                ['Risolte', $n['risolte']],
                ['Risolte al primo intervento', $n['alPrimo']],
            ]),
            new TabellaEsportabile('Per aula', ['Aula', 'Segnalazioni'], $conteggio($n['perAula'])),
            new TabellaEsportabile('Per tipo di problema', ['Tipo di problema', 'Segnalazioni'], $conteggio($n['perTipo'])),
            new TabellaEsportabile('Aule malate', ['Aula', 'Tipo di problema', 'Segnalazioni'], array_map(static fn (array $m): array => [$m['aula'], $m['tipo'], $m['quante']], $n['malate'])),
            new TabellaEsportabile('Minuti degli studenti', ['Studente', 'Mese', 'Minuti', 'Interventi'],
                array_map(static fn (array $m): array => [$m['studente'], $m['mese'], $m['minuti'], $m['interventi']], $this->minuti($f))),
        ]);
    }

    private static function giorno(string $data): string
    {
        return date('d/m/Y', (int) strtotime($data));
    }
}
