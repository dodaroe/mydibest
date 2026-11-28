<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Auth\GruppiUtente;
use App\Auth\Pubblico;
use App\Auth\UtenteRepository;
use App\Core\Database;
use App\Core\Esito;
use App\Core\IndirizzoClient;
use App\Core\Orologio;
use App\Infrastructure\Audit\AuditLog;
use Closure;
use RuntimeException;
use Throwable;

/**
 * L'iter di una segnalazione SOS: invio e passaggi di stato. Niente HTML, niente email e niente push (le fa partire la pagina dopo l'esito).
 *
 * - Chi agisce è sempre la riga di utenti dell'account collegato: i suoi ruoli rispetto alla segnalazione li dice ruoli(), i passaggi ammessi Costanti::TRANSIZIONI.
 * - Ogni passaggio è UN SOLO UPDATE condizionato allo stato di partenza: se due premono insieme vince il primo, il secondo non cambia niente e riceve
 *   un errore che dice chi ha fatto cosa e quando.
 * - Ogni passaggio scrive una riga in sos_cronologia (nella stessa transazione) e una nel registro (AuditLog).
 */
final class ServizioSos
{
    public function __construct(
        private Database $db,
        private SegnalazioniRepository $segnalazioni,
        private AuleRepository $aule,
        private TipiRepository $tipi,
        private PersoneSos $persone,
        private ImpostazioniSos $impostazioni,
        private CampiSos $campi,
        private RegoleSos $regole,
        private FotoSos $foto,
        private GruppiUtente $gruppi,
        private AuditLog $audit,
        private IndirizzoClient $client,
        private Orologio $orologio,
    ) {
    }

    /** @param array<string, mixed>|null $utente la riga di utenti */
    public function puoInviare(?array $utente): bool
    {
        return $utente !== null && !empty($utente['id']) && $this->impostazioni->aperto()
            && Pubblico::ammesso(['profili' => $this->impostazioni->chi()], $utente, $this->gruppi->nomi($utente));
    }

    /**
     * Il form com'è adesso: campi fissi e aggiunti, aule e tipi attivi per id. È quello che vuole RegoleSos::valida.
     *
     * @return array{fissi: array<string, array<string, mixed>>, aggiunti: list<array<string, mixed>>, aule: array<int, array<string, mixed>>, tipi: array<int, array<string, mixed>>}
     */
    public function campiDelForm(): array
    {
        return [
            'fissi' => $this->campi->fissi(),
            'aggiunti' => $this->campi->aggiunti(),
            'aule' => array_column($this->aule->tutte(true), null, 'id'),
            'tipi' => array_column($this->tipi->elenco(TipiRepository::TIPI), null, 'id'),
        ];
    }

    /** Gli insegnamenti del Dipartimento, per la scelta di chi non ha JavaScript (con JavaScript si sceglie dal catalogo di Ateneo). @return list<array<string, mixed>> */
    public function insegnamentiDelDipartimento(): array
    {
        return $this->db->righe('SELECT id, nome, cds_nome FROM insegnamenti WHERE presente = 1 ORDER BY nome, cds_nome, id LIMIT 800');
    }

    /**
     * L'insegnamento e i campi aggiunti dal pannello di una segnalazione, solo quelli compilati (le card di chi interviene e il registro).
     * Un campo nascosto dopo l'invio si vede lo stesso: la risposta c'è.
     *
     * @param array<string, mixed> $s riga di sos_segnalazioni
     * @return list<array{etichetta: string, valore: string}>
     */
    public function datiCompilati(array $s): array
    {
        $out = [];
        $insegnamento = (int) ($s['insegnamento_id'] ?? 0);
        if ($insegnamento > 0) {
            $i = $this->db->riga('SELECT nome, cds_nome FROM insegnamenti WHERE id = ?', [$insegnamento])
                ?? $this->db->riga("SELECT nome, '' AS cds_nome FROM ateneo_insegnamenti WHERE id = ?", [$insegnamento]);
            if ($i !== null) {
                $out[] = ['etichetta' => (string) $this->campi->fissi()['insegnamento']['etichetta'], 'valore' => trim($i['nome'] . ((string) $i['cds_nome'] !== '' ? ' · ' . $i['cds_nome'] : ''))];
            }
        }
        $valori = json_decode((string) ($s['campi_extra_json'] ?? ''), true);
        if (!is_array($valori) || $valori === []) {
            return $out;
        }
        foreach ($this->campi->aggiunti() as $c) {
            $v = $valori[$c['chiave']] ?? '';
            $v = is_scalar($v) ? trim((string) $v) : '';
            if ($v === '') {
                continue;
            }
            if ($c['tipo'] === 'date' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
                $v = $m[3] . '/' . $m[2] . '/' . $m[1];
            }
            $out[] = ['etichetta' => $c['etichetta'], 'valore' => $v];
        }

        return $out;
    }

    /**
     * Riga, foto, prima riga di cronologia e codice SOS-AAAA-NNNN: tutto insieme o niente. Nome, cognome ed email vengono dall'account (l'email solo se verificata).
     *
     * @param array<string, mixed> $utente
     * @param array<string, mixed> $dati
     * @param array<string, mixed> $file
     */
    public function invia(array $utente, array $dati, array $file): EsitoInvio
    {
        if (!$this->puoInviare($utente)) {
            return new EsitoInvio(Esito::errore($this->impostazioni->aperto() ? 'Il tuo profilo non può inviare segnalazioni SOS.' : 'Il servizio SOS aule non è attivo.'));
        }
        $campi = $this->campiDelForm();
        $errori = $this->regole->valida($dati, $file, $campi);
        $aulaId = (int) RegoleSos::testo($dati, 'aula_id');
        if (!isset($errori['aula_id']) && $this->persone->destinatari($aulaId) === []) {
            $errori['aula_id'] = 'Per quest\'aula nessuno riceverebbe la segnalazione: chiama il numero di riserva.';
        }
        $visibile = static fn (string $k): bool => (bool) $campi['fissi'][$k]['visibile'];
        $insegnamento = $visibile('insegnamento') ? (int) RegoleSos::testo($dati, 'insegnamento_id') : 0;
        // la scelta di cerca_insegnamenti.php dà gli id dell'Ateneo: valgono quelli del Dipartimento (insegnamenti) e quelli degli altri corsi (ateneo_insegnamenti)
        if ($insegnamento > 0 && !isset($errori['insegnamento_id'])
            && $this->db->riga('SELECT id FROM insegnamenti WHERE id = ? UNION SELECT id FROM ateneo_insegnamenti WHERE id = ?', [$insegnamento, $insegnamento]) === null) {
            $errori['insegnamento_id'] = 'Scegli l\'insegnamento dall\'elenco.';
        }
        if ($errori) {
            return new EsitoInvio(Esito::errore('Controlla i campi segnalati.'), 0, $errori);
        }

        $percorso = null;
        $foto = $visibile('foto') ? RegoleSos::foto($file) : null;
        if ($foto !== null) {
            $percorso = $this->foto->salva($foto);
            if ($percorso === null) {
                return new EsitoInvio(Esito::errore('Non è stato possibile salvare la foto: riprova.'), 0, ['foto' => 'Non è stato possibile salvare la foto: riprova.']);
            }
        }
        $extra = $this->regole->aggiunti($dati, $campi['aggiunti'])['valori'];
        $adesso = $this->orologio->adesso();
        $quando = $adesso->format('Y-m-d H:i:s');
        $riga = [
            'aula_id' => $aulaId,
            'tipo_id' => (int) RegoleSos::testo($dati, 'tipo_id'),
            'urgenza' => RegoleSos::testo($dati, 'urgenza'),
            'descrizione' => RegoleSos::testo($dati, 'descrizione'),
            'telefono' => $visibile('telefono') ? RegoleSos::testo($dati, 'telefono') : '',
            'insegnamento_id' => $insegnamento > 0 ? $insegnamento : null,
            'campi_extra_json' => $extra ? (string) json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
            'foto' => $percorso,
            'utente_id' => (int) $utente['id'],
            'nome' => (string) ($utente['nome'] ?? ''),
            'cognome' => (string) ($utente['cognome'] ?? ''),
            'email' => UtenteRepository::emailVerificata($utente) ?? '',
            'stato' => 'inviata',
            'inviata_il' => $quando,
        ];

        $id = 0;
        $codice = '';
        // due invii nello stesso istante possono scegliere lo stesso numero (codice è UNIQUE): si riprova
        for ($tentativo = 1; $tentativo <= 3 && $id === 0; $tentativo++) {
            try {
                [$id, $codice] = $this->db->transazione(function () use ($riga, $adesso, $quando, $utente): array {
                    $anno = (int) $adesso->format('Y');
                    $codice = sprintf('SOS-%d-%04d', $anno, $this->segnalazioni->prossimoNumero($anno));
                    $id = $this->segnalazioni->inserisci(['codice' => $codice] + $riga);
                    if ($id === 0 || !$this->segnalazioni->aggiungiCronologia($id, $quando, (int) $utente['id'], self::chi($utente, Costanti::DOCENTE), '', 'inviata')) {
                        throw new RuntimeException('Segnalazione non salvata: ' . $this->db->ultimoErrore());
                    }

                    return [$id, $codice];
                });
            } catch (Throwable $e) {
                error_log('[SOS aule] invio, tentativo ' . $tentativo . ': ' . $e->getMessage());
            }
        }
        if ($id === 0) {
            if ($percorso !== null) {
                $this->foto->elimina($percorso);
            }

            return new EsitoInvio(Esito::errore('Non è stato possibile salvare la segnalazione: riprova o chiama il numero di riserva.'));
        }
        $this->registra((int) $utente['id'], 'inviata', ['id' => $id, 'codice' => $codice]);

        return new EsitoInvio(Esito::ok('Segnalazione ' . $codice . ' inviata.'), $id);
    }

    /**
     * I ruoli dell'utente rispetto a una segnalazione (nessuno, uno o più).
     *
     * @param array<string, mixed> $utente
     * @param array<string, mixed> $s riga di sos_segnalazioni
     * @return list<string> in ordine: referente, studente, docente
     */
    public function ruoli(array $utente, array $s): array
    {
        $ruoli = [];
        if ($this->persone->eReferente($utente, (int) $s['aula_id'])) {
            $ruoli[] = Costanti::REFERENTE;
        }
        $studente = $this->persone->studenteDi($utente);
        if ($studente !== null && (int) $s['assegnata_a_studente'] === (int) $studente['id']) {
            $ruoli[] = Costanti::STUDENTE;
        }
        if (!empty($utente['id']) && (int) $s['utente_id'] === (int) $utente['id']) {
            $ruoli[] = Costanti::DOCENTE;
        }

        return $ruoli;
    }

    /**
     * Una nota su una segnalazione ancora aperta, da chi l'ha inviata, da un referente o dallo studente a cui è affidata. Lo stato non cambia.
     * Un altro che può inviare («collega») scrive solo se ha scelto dal suo form l'aula della segnalazione ($aulaScelta); non ottiene altro.
     *
     * @param array<string, mixed> $chi
     * @param int $aulaScelta l'aula del form da cui scrive il collega; 0 = non è un collega
     */
    public function aggiungiNota(int $id, array $chi, string $nota, int $aulaScelta = 0): Esito
    {
        $nota = trim(str_replace("\r\n", "\n", $nota));
        $s = $this->segnalazioni->perId($id);
        if ($s === null) {
            return Esito::errore('La segnalazione non esiste.');
        }
        $ruoli = $this->ruoli($chi, $s);
        if ($ruoli === [] && ($aulaScelta !== (int) $s['aula_id'] || !$this->puoInviare($chi))) {
            return Esito::errore('Non puoi scrivere su questa segnalazione.');
        }
        if (($errore = self::erroreTesto($nota, 'Scrivi la nota.')) !== null) {
            return Esito::errore($errore);
        }
        // chi ha inviato la segnalazione scrive da docente, anche se è pure un referente
        $ruolo = in_array(Costanti::DOCENTE, $ruoli, true) ? Costanti::DOCENTE : ($ruoli[0] ?? Costanti::COLLEGA);
        // null = nel frattempo è stata chiusa; false = non salvata
        $scritta = $this->db->transazione(function () use ($id, $chi, $ruolo, $nota): ?bool {
            $riga = $this->segnalazioni->blocca($id);
            if ($riga === null || !in_array($riga['stato'], Costanti::STATI_APERTI, true)) {
                return null;
            }

            return $this->segnalazioni->aggiungiCronologia($id, $this->adesso(), (int) $chi['id'], self::chi($chi, $ruolo), '', '', $nota);
        });
        if ($scritta !== true) {
            return Esito::errore($scritta === null ? 'La segnalazione è già chiusa: la nota non è stata aggiunta.' : 'Non è stato possibile salvare la nota: riprova.');
        }
        $this->registra((int) $chi['id'], 'nota aggiunta', ['id' => $id, 'codice' => (string) $s['codice']]);

        return Esito::ok('Nota aggiunta.');
    }

    /** «Ci penso io». @param array<string, mixed> $referente */
    public function prendi(int $id, array $referente): Esito
    {
        return $this->passaggio($id, $referente, 'presa', ['inviata'], [Costanti::REFERENTE], fn (array $s): array => $this->inMano($s, $referente), '', 'Segnalazione presa in carico.');
    }

    /** «La riprendo io»: da non risolta, inoltrata, o togliendola allo studente. @param array<string, mixed> $referente */
    public function riprendi(int $id, array $referente): Esito
    {
        return $this->passaggio(
            $id,
            $referente,
            'presa',
            ['non_risolta', 'assegnata', 'in_corso', 'inoltrata'],
            [Costanti::REFERENTE],
            fn (array $s): array => $this->inMano($s, $referente) + ['assegnata_a_studente' => null, 'assegnata_il' => null],
            '',
            'Segnalazione ripresa.'
        );
    }

    /** «Mando uno studente» (o «la passo a un altro»): solo uno studente attivo oggi. @param array<string, mixed> $referente */
    public function affida(int $id, array $referente, int $studenteId): Esito
    {
        $studente = $this->persone->studentePerId($studenteId);
        if ($studente === null || !PersoneSos::attivoIl($studente, $this->orologio->adesso()->format('Y-m-d'))) {
            return Esito::errore('Scegli uno studente in servizio oggi.');
        }
        $s = $this->segnalazioni->perId($id);
        if ($s !== null && in_array($s['stato'], ['assegnata', 'in_corso'], true) && (int) $s['assegnata_a_studente'] === $studenteId && $this->persone->eReferente($referente, (int) $s['aula_id'])) {
            return Esito::errore('È già affidata a questo studente.');
        }
        $nome = trim($studente['nome'] . ' ' . $studente['cognome']);

        return $this->passaggio(
            $id,
            $referente,
            'assegnata',
            ['inviata', 'presa', 'assegnata', 'in_corso', 'non_risolta'],
            [Costanti::REFERENTE],
            fn (array $riga): array => $this->inMano($riga, $referente) + ['assegnata_a_studente' => $studenteId, 'assegnata_il' => $this->adesso()],
            'Affidata a ' . $nome,
            'Segnalazione affidata a ' . $nome . '.'
        );
    }

    /** «Sto andando». @param array<string, mixed> $studente la riga di utenti dello studente */
    public function staAndando(int $id, array $studente): Esito
    {
        return $this->passaggio($id, $studente, 'in_corso', ['assegnata'], [Costanti::STUDENTE], static fn (): array => [], '', 'Segnato: stai andando.');
    }

    /** Risolta da un referente o dallo studente a cui è affidata. @param array<string, mixed> $chi */
    public function risolta(int $id, array $chi, ?int $minuti = null): Esito
    {
        if (($errore = self::erroreMinuti($minuti)) !== null) {
            return Esito::errore($errore);
        }

        return $this->passaggio(
            $id,
            $chi,
            'risolta',
            ['presa', 'assegnata', 'in_corso', 'inoltrata'],
            [Costanti::REFERENTE, Costanti::STUDENTE],
            fn (): array => $this->chiusura($chi),
            self::notaMinuti($minuti),
            'Segnalazione risolta.',
            minuti: $minuti
        );
    }

    /** Lo studente non è riuscito: dice che cosa non va; torna al referente. @param array<string, mixed> $studente la riga di utenti */
    public function nonRisolta(int $id, array $studente, int $esitoId, string $nota, ?int $minuti = null): Esito
    {
        $nota = trim(str_replace("\r\n", "\n", $nota));
        $esito = $this->tipi->perId(TipiRepository::ESITI, $esitoId);
        if ($esito === null || (int) $esito['attivo'] !== 1) {
            return Esito::errore('Scegli che cosa non ha funzionato.');
        }
        if ((int) $esito['chiede_testo'] === 1 && $nota === '') {
            return Esito::errore('Scrivi una nota: che cosa non ha funzionato?');
        }
        if (mb_strlen($nota) > Costanti::MAX_NOTA) {
            return Esito::errore('La nota è troppo lunga (massimo ' . Costanti::MAX_NOTA . ' caratteri).');
        }
        if (($errore = self::erroreMinuti($minuti)) !== null) {
            return Esito::errore($errore);
        }

        return $this->passaggio(
            $id,
            $studente,
            'non_risolta',
            ['assegnata', 'in_corso'],
            [Costanti::STUDENTE],
            static fn (): array => ['esito_id' => $esitoId, 'esito_nota' => $nota],
            trim($esito['nome'] . ($nota !== '' ? ': ' . $nota : '') . ' ' . self::notaMinuti($minuti)),
            'Segnato come non risolto: torna al referente.',
            minuti: $minuti
        );
    }

    /** A un altro ufficio: il testo dice chi e con quale riferimento. Resta aperta. @param array<string, mixed> $referente */
    public function inoltra(int $id, array $referente, string $aChi): Esito
    {
        $aChi = trim(str_replace("\r\n", "\n", $aChi));
        if (($errore = self::erroreTesto($aChi, 'Scrivi a quale ufficio la inoltri e con quale riferimento.')) !== null) {
            return Esito::errore($errore);
        }

        return $this->passaggio(
            $id,
            $referente,
            'inoltrata',
            ['presa', 'non_risolta'],
            [Costanti::REFERENTE],
            static fn (): array => ['inoltrata_a' => $aChi, 'assegnata_a_studente' => null, 'assegnata_il' => null],
            $aChi,
            'Segnalazione inoltrata.'
        );
    }

    /** @param array<string, mixed> $referente */
    public function chiudiNonRisolta(int $id, array $referente, string $nota): Esito
    {
        $nota = trim(str_replace("\r\n", "\n", $nota));
        if (($errore = self::erroreTesto($nota, 'Scrivi perché la chiudi senza soluzione.')) !== null) {
            return Esito::errore($errore);
        }

        return $this->passaggio(
            $id,
            $referente,
            'chiusa_non_risolta',
            ['presa', 'non_risolta', 'inoltrata'],
            [Costanti::REFERENTE],
            fn (): array => $this->chiusura($referente),
            $nota,
            'Segnalazione chiusa senza soluzione.'
        );
    }

    /** «Ho risolto da solo». @param array<string, mixed> $docente chi l'ha inviata */
    public function annulla(int $id, array $docente): Esito
    {
        return $this->passaggio(
            $id,
            $docente,
            'annullata',
            Costanti::STATI_APERTI,
            [Costanti::DOCENTE],
            fn (): array => $this->chiusura($docente),
            '',
            'Segnalazione annullata.'
        );
    }

    /** «Non è risolto», entro 24 ore dalla chiusura come risolta: torna al referente. @param array<string, mixed> $docente chi l'ha inviata */
    public function riapri(int $id, array $docente): Esito
    {
        return $this->passaggio(
            $id,
            $docente,
            'non_risolta',
            ['risolta'],
            [Costanti::DOCENTE],
            fn (): array => ['chiusa_il' => null, 'chiusa_da' => null, 'riaperta_il' => $this->adesso()],
            'Il docente segnala che non è risolto',
            'Segnalazione riaperta: torna al referente.',
            [['chiusa_il >= ?', $this->limiteRiapertura()]]
        );
    }

    // ─────────────────────────── Interno ───────────────────────────

    /**
     * Il passaggio di stato, uguale per tutti.
     *
     * @param array<string, mixed> $utente chi agisce
     * @param list<string> $da gli stati di partenza di QUESTA azione
     * @param list<string> $ruoliAzione chi può fare questa azione
     * @param Closure(array<string, mixed>): array<string, mixed> $cambi le colonne da cambiare oltre allo stato (riceve la riga bloccata)
     * @param list<array{0: string, 1: mixed}> $condizioni altre condizioni dell'UPDATE
     * @param int|null $minuti i minuti dell'intervento: restano nella riga di cronologia, insieme allo studente se è lui che agisce
     */
    private function passaggio(int $id, array $utente, string $a, array $da, array $ruoliAzione, Closure $cambi, string $nota, string $riuscito, array $condizioni = [], ?int $minuti = null): Esito
    {
        $s = $this->segnalazioni->perId($id);
        if ($s === null) {
            return Esito::errore('La segnalazione non esiste.');
        }
        $miei = array_values(array_intersect($ruoliAzione, $this->ruoli($utente, $s)));
        if ($miei === []) {
            return Esito::errore($ruoliAzione === [Costanti::STUDENTE]
                ? 'Questa segnalazione non è affidata a te: forse il referente l\'ha ripresa o passata a un altro.'
                : 'Non puoi fare questo passaggio su questa segnalazione.');
        }
        // chi è sia referente sia studente agisce da referente (che può partire da più stati)
        $ruolo = $miei[0];
        $daAmmessi = array_values(array_filter($da, static fn (string $x): bool => Costanti::ammessa($x, $a, $ruolo)));
        if ($ruolo === Costanti::STUDENTE) {
            $condizioni[] = ['assegnata_a_studente = ?', (int) $s['assegnata_a_studente']];
        } elseif ($ruolo === Costanti::DOCENTE) {
            $condizioni[] = ['utente_id = ?', (int) $utente['id']];
        }

        $studenteId = $ruolo === Costanti::STUDENTE ? (int) $s['assegnata_a_studente'] : null;

        $prima = '';
        try {
            $righe = $this->db->transazione(function () use ($id, $utente, $a, $daAmmessi, $cambi, $nota, $condizioni, $ruolo, $studenteId, $minuti, &$prima): int {
                $riga = $this->segnalazioni->blocca($id);
                if ($riga === null) {
                    return 0;
                }
                $prima = (string) $riga['stato'];
                $n = $this->segnalazioni->passa($id, $daAmmessi, ['stato' => $a] + $cambi($riga), $condizioni);
                if ($n === 1 && !$this->segnalazioni->aggiungiCronologia($id, $this->adesso(), (int) $utente['id'], self::chi($utente, $ruolo), $prima, $a, $nota, $studenteId, $minuti)) {
                    throw new RuntimeException('Cronologia non scritta: ' . $this->db->ultimoErrore());
                }

                return $n;
            });
        } catch (Throwable $e) {
            error_log('[SOS aule] passaggio a ' . $a . ' della segnalazione ' . $id . ': ' . $e->getMessage());
            $righe = -1;
        }
        if ($righe < 0) {
            return Esito::errore('Non è stato possibile salvare: riprova tra qualche minuto.');
        }
        if ($righe === 0) {
            return Esito::errore($this->motivoRifiuto($id, $a));
        }
        $this->registra((int) $utente['id'], mb_strtolower(Costanti::etichetta($a)), ['id' => $id, 'codice' => (string) $s['codice'], 'da' => $prima, 'a' => $a] + ($nota !== '' ? ['nota' => $nota] : []));

        return Esito::ok($riuscito);
    }

    /** L'UPDATE non ha cambiato niente: si rilegge la riga e si dice chi ha fatto cosa e quando. */
    private function motivoRifiuto(int $id, string $voluto): string
    {
        $s = $this->segnalazioni->perId($id);
        if ($s === null) {
            return 'La segnalazione non esiste.';
        }
        $stato = (string) $s['stato'];
        if ($voluto === 'non_risolta' && $stato === 'risolta' && (string) $s['chiusa_il'] < $this->limiteRiapertura()) {
            return 'Sono passate più di ' . Costanti::ORE_RIAPERTURA . ' ore dalla chiusura: invia una nuova segnalazione.';
        }
        $frasi = [
            'presa' => 'La segnalazione è già presa da %s.',
            'assegnata' => 'La segnalazione è già affidata a uno studente da %s.',
            'in_corso' => 'L\'intervento è già in corso: l\'ha iniziato %s.',
            'non_risolta' => 'La segnalazione è già tornata al referente come non risolta, segnata da %s.',
            'inoltrata' => 'La segnalazione è già inoltrata a un altro ufficio da %s.',
            'risolta' => 'La segnalazione è già segnata come risolta da %s.',
            'chiusa_non_risolta' => 'La segnalazione è già chiusa senza soluzione da %s.',
            'annullata' => 'La segnalazione è già annullata da %s.',
        ];
        $p = isset($frasi[$stato]) ? $this->segnalazioni->ultimoPassaggio($id, $stato) : null;
        if ($p === null) {
            return 'Ora la segnalazione è «' . Costanti::etichetta($stato) . '»: questo passaggio non si può fare.';
        }
        $t = (int) strtotime((string) $p['quando']);
        $giorno = date('Y-m-d', $t) === $this->orologio->adesso()->format('Y-m-d') ? '' : 'il ' . date('d/m', $t) . ' ';
        $nome = (string) $p['nome_account'] !== '' ? (string) $p['nome_account'] : (string) $p['chi'];

        return sprintf($frasi[$stato], $nome . ' ' . $giorno . 'alle ' . date('H:i', $t));
    }

    /** Chi ha in mano la segnalazione: il referente che agisce; l'ora è quella della prima presa. @param array<string, mixed> $riga @param array<string, mixed> $referente */
    private function inMano(array $riga, array $referente): array
    {
        return ['presa_da' => (int) $referente['id'], 'presa_il' => $riga['presa_il'] ?? $this->adesso()];
    }

    /** @param array<string, mixed> $chi */
    private function chiusura(array $chi): array
    {
        return ['chiusa_il' => $this->adesso(), 'chiusa_da' => (int) $chi['id']];
    }

    /** @param array<string, mixed> $dettagli */
    private function registra(int $utenteId, string $azione, array $dettagli): void
    {
        $this->audit->registra($utenteId, 'SOS aule: ' . $azione, $dettagli, $this->client->ip() ?? 'Sconosciuto');
    }

    /** «Cognome Nome (ruolo)». @param array<string, mixed> $utente */
    private static function chi(array $utente, string $ruolo): string
    {
        return trim(($utente['cognome'] ?? '') . ' ' . ($utente['nome'] ?? '')) . ' (' . $ruolo . ')';
    }

    private static function erroreTesto(string $testo, string $seVuoto): ?string
    {
        if ($testo === '') {
            return $seVuoto;
        }

        return mb_strlen($testo) > Costanti::MAX_NOTA ? 'Il testo è troppo lungo (massimo ' . Costanti::MAX_NOTA . ' caratteri).' : null;
    }

    private static function erroreMinuti(?int $minuti): ?string
    {
        return $minuti !== null && ($minuti < 0 || $minuti > Costanti::MAX_MINUTI) ? 'I minuti dell\'intervento non sono validi.' : null;
    }

    private static function notaMinuti(?int $minuti): string
    {
        return $minuti === null ? '' : '(' . $minuti . ' minuti)';
    }

    private function limiteRiapertura(): string
    {
        return $this->orologio->adesso()->modify('-' . Costanti::ORE_RIAPERTURA . ' hours')->format('Y-m-d H:i:s');
    }

    private function adesso(): string
    {
        return $this->orologio->adesso()->format('Y-m-d H:i:s');
    }
}
