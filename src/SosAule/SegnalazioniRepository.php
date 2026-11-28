<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Core\Database;

/** Le segnalazioni e la loro cronologia (tabelle sos_segnalazioni e sos_cronologia). Chi può fare cosa lo decide ServizioSos. */
final class SegnalazioniRepository
{
    private const SELEZIONE = 'SELECT s.*, a.nome AS aula_nome, a.edificio AS aula_edificio, t.nome AS tipo_nome, t.icona AS tipo_icona
        FROM sos_segnalazioni s LEFT JOIN sos_aule a ON a.id = s.aula_id LEFT JOIN sos_tipi t ON t.id = s.tipo_id';

    /** La più urgente e più vecchia in alto (le urgenze di Costanti::URGENZE, nell'ordine). */
    private const PER_URGENZA = " ORDER BY FIELD(s.urgenza, 'ferma', 'aiuto', 'prossime'), s.inviata_il, s.id";

    public function __construct(private Database $db)
    {
    }

    /**
     * @param array<string, mixed> $campi colonna => valore (le colonne le scrive il codice, mai chi invia)
     * @return int l'id (0 se non salvata)
     */
    public function inserisci(array $campi): int
    {
        return $this->db->inserisci(
            'INSERT INTO sos_segnalazioni (' . implode(', ', array_keys($campi)) . ') VALUES (' . implode(', ', array_fill(0, count($campi), '?')) . ')',
            array_values($campi)
        );
    }

    /** Il numero della prossima segnalazione dell'anno. Va chiamato dentro la transazione che la inserisce. */
    public function prossimoNumero(int $anno): int
    {
        $prefisso = 'SOS-' . $anno . '-';

        return 1 + (int) ($this->db->valore(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(codice, ' . (strlen($prefisso) + 1) . ') AS UNSIGNED)), 0) FROM sos_segnalazioni WHERE codice LIKE ? FOR UPDATE',
            [$prefisso . '%']
        ) ?? 0);
    }

    /** @return array<string, mixed>|null con aula_nome, aula_edificio, tipo_nome e tipo_icona */
    public function perId(int $id): ?array
    {
        return $this->db->riga(self::SELEZIONE . ' WHERE s.id = ?', [$id]);
    }

    /** La sola riga, bloccata fino alla fine della transazione. @return array<string, mixed>|null */
    public function blocca(int $id): ?array
    {
        return $this->db->riga('SELECT * FROM sos_segnalazioni WHERE id = ? FOR UPDATE', [$id]);
    }

    /**
     * Il passaggio di stato: un solo UPDATE che vale solo se la segnalazione è ancora in uno degli stati di partenza (e rispetta le altre condizioni).
     *
     * @param list<string> $daStati
     * @param array<string, mixed> $cambi colonna => valore nuovo
     * @param list<array{0: string, 1: mixed}> $condizioni [«colonna = ?», valore] scritte dal codice
     * @return int righe cambiate (0 = nel frattempo è cambiata; -1 = errore del database)
     */
    public function passa(int $id, array $daStati, array $cambi, array $condizioni = []): int
    {
        if ($daStati === [] || $cambi === []) {
            return 0;
        }
        $sql = 'UPDATE sos_segnalazioni SET ' . implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($cambi)))
            . ' WHERE id = ? AND stato IN (' . implode(', ', array_fill(0, count($daStati), '?')) . ')';
        $parametri = array_merge(array_values($cambi), [$id], array_values($daStati));
        foreach ($condizioni as [$condizione, $valore]) {
            $sql .= ' AND ' . $condizione;
            $parametri[] = $valore;
        }

        return $this->db->esegui($sql, $parametri);
    }

    /** Le segnalazioni ancora aperte di un'aula, dalla più recente. $dallaData ('Y-m-d H:i:s') serve all'avviso dei doppioni. @return list<array<string, mixed>> */
    public function aperteDellAula(int $aulaId, ?string $dallaData = null): array
    {
        [$in, $parametri] = self::in('s.stato', Costanti::STATI_APERTI);
        $sql = self::SELEZIONE . " WHERE s.aula_id = ? AND $in";
        $parametri = array_merge([$aulaId], $parametri);
        if ($dallaData !== null) {
            $sql .= ' AND s.inviata_il >= ?';
            $parametri[] = $dallaData;
        }

        return $this->db->righe($sql . ' ORDER BY s.inviata_il DESC, s.id DESC', $parametri);
    }

    /** @return list<array<string, mixed>> le ultime segnalazioni inviate da un utente */
    public function delDocente(int $utenteId, int $quante = 10): array
    {
        return $this->db->righe(self::SELEZIONE . ' WHERE s.utente_id = ? ORDER BY s.inviata_il DESC, s.id DESC LIMIT ' . max(1, min(100, $quante)), [$utenteId]);
    }

    /**
     * @param list<int>|null $aulaIds le aule del referente (PersoneSos::auleDelReferente): null = tutte (riserva), [] = nessuna
     * @param list<string> $stati vuoto = tutti
     * @param string|null $chiusaDal 'Y-m-d H:i:s': solo quelle chiuse da quel momento
     * @return list<array<string, mixed>>
     */
    public function perReferente(?array $aulaIds, array $stati = [], ?string $chiusaDal = null): array
    {
        if ($aulaIds === []) {
            return [];
        }
        $dove = [];
        $parametri = [];
        foreach ([['s.aula_id', $aulaIds ?? []], ['s.stato', $stati]] as [$colonna, $valori]) {
            if ($valori !== []) {
                [$dove[], $p] = self::in($colonna, $valori);
                $parametri = array_merge($parametri, $p);
            }
        }

        if ($chiusaDal !== null) {
            $dove[] = 's.chiusa_il >= ?';
            $parametri[] = $chiusaDal;
        }

        return $this->db->righe(self::SELEZIONE . ($dove ? ' WHERE ' . implode(' AND ', $dove) : '') . self::PER_URGENZA, $parametri);
    }

    /** Solo quelle affidate a questo studente (sos_studenti.id). @param list<string> $stati vuoto = tutti @return list<array<string, mixed>> */
    public function perStudente(int $studenteId, array $stati = [], ?string $chiusaDal = null): array
    {
        $sql = self::SELEZIONE . ' WHERE s.assegnata_a_studente = ?';
        $parametri = [$studenteId];
        if ($stati !== []) {
            [$in, $p] = self::in('s.stato', $stati);
            $sql .= " AND $in";
            $parametri = array_merge($parametri, $p);
        }
        if ($chiusaDal !== null) {
            $sql .= ' AND s.chiusa_il >= ?';
            $parametri[] = $chiusaDal;
        }

        return $this->db->righe($sql . self::PER_URGENZA, $parametri);
    }

    /** Quante sono «Non risolta» da prima di quel momento ('Y-m-d H:i:s'): conta l'ultima volta in cui sono tornate non risolte. */
    public function contaNonRisoltePrimaDi(string $limite): int
    {
        return (int) $this->db->valore(
            "SELECT COUNT(*) FROM sos_segnalazioni s WHERE s.stato = 'non_risolta'
             AND (SELECT MAX(c.quando) FROM sos_cronologia c WHERE c.segnalazione_id = s.id AND c.a_stato = 'non_risolta') < ?",
            [$limite]
        );
    }

    /**
     * Il registro, dalla più recente. Filtri (tutti facoltativi): dal, al (Y-m-d), aula_id, tipo_id, stato, urgenza, studente_id, testo (codice, descrizione, nome, cognome).
     *
     * @param array<string, mixed> $filtri
     * @return list<array<string, mixed>>
     */
    public function elenco(array $filtri = []): array
    {
        $dove = [];
        $parametri = [];
        foreach (['dal' => ['s.inviata_il >= ?', ' 00:00:00'], 'al' => ['s.inviata_il <= ?', ' 23:59:59']] as $k => [$condizione, $ora]) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($filtri[$k] ?? ''))) {
                $dove[] = $condizione;
                $parametri[] = $filtri[$k] . $ora;
            }
        }
        foreach (['aula_id' => 's.aula_id', 'tipo_id' => 's.tipo_id'] as $k => $colonna) {
            if ((int) ($filtri[$k] ?? 0) > 0) {
                $dove[] = "$colonna = ?";
                $parametri[] = (int) $filtri[$k];
            }
        }
        // «dello studente»: affidata a lui adesso, oppure ci ha lavorato (poi il referente può averla ripresa)
        if ((int) ($filtri['studente_id'] ?? 0) > 0) {
            $dove[] = '(s.assegnata_a_studente = ? OR EXISTS (SELECT 1 FROM sos_cronologia c WHERE c.segnalazione_id = s.id AND c.studente_id = ?))';
            array_push($parametri, (int) $filtri['studente_id'], (int) $filtri['studente_id']);
        }
        foreach (['stato' => 's.stato', 'urgenza' => 's.urgenza'] as $k => $colonna) {
            if (trim((string) ($filtri[$k] ?? '')) !== '') {
                $dove[] = "$colonna = ?";
                $parametri[] = trim((string) $filtri[$k]);
            }
        }
        $testo = trim((string) ($filtri['testo'] ?? ''));
        if ($testo !== '') {
            $dove[] = '(s.codice LIKE ? OR s.descrizione LIKE ? OR s.nome LIKE ? OR s.cognome LIKE ?)';
            $parametri = array_merge($parametri, array_fill(0, 4, '%' . addcslashes($testo, '%_\\') . '%'));
        }

        return $this->db->righe(self::SELEZIONE . ($dove ? ' WHERE ' . implode(' AND ', $dove) : '') . ' ORDER BY s.inviata_il DESC, s.id DESC', $parametri);
    }

    /**
     * Una riga di storia. Per una nota aggiunta (non è un passaggio) $da e $a sono vuoti.
     * $studenteId (sos_studenti.id) c'è quando agisce lo studente; $minuti sono quelli dell'intervento chiuso con quel passaggio.
     */
    public function aggiungiCronologia(int $segnalazioneId, string $quando, ?int $utenteId, string $chi, string $da, string $a, string $nota = '', ?int $studenteId = null, ?int $minuti = null): bool
    {
        return $this->db->inserisci(
            'INSERT INTO sos_cronologia (segnalazione_id, quando, utente_id, chi, da_stato, a_stato, nota, studente_id, minuti) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$segnalazioneId, $quando, $utenteId, mb_substr($chi, 0, 250), $da, $a, mb_substr($nota, 0, 1000), $studenteId, $minuti]
        ) > 0;
    }

    /** @return list<array<string, mixed>> dalla più vecchia */
    public function cronologia(int $segnalazioneId): array
    {
        return $this->db->righe('SELECT * FROM sos_cronologia WHERE segnalazione_id = ? ORDER BY id', [$segnalazioneId]);
    }

    /** Le cronologie di più segnalazioni in un colpo: id => righe dalla più vecchia. @param list<int> $ids @return array<int, list<array<string, mixed>>> */
    public function cronologie(array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, 500) as $blocco) {
            [$in, $parametri] = self::in('segnalazione_id', $blocco);
            foreach ($this->db->righe("SELECT * FROM sos_cronologia WHERE $in ORDER BY id", $parametri) as $c) {
                $out[(int) $c['segnalazione_id']][] = $c;
            }
        }

        return $out;
    }

    /**
     * I minuti degli interventi segnati dagli studenti, per studente e per mese ('Y-m'), dal mese più recente. Conta il giorno in cui è stato segnato
     * l'intervento. $dal e $al sono Y-m-d o vuoti.
     *
     * Per uno studente tolto dall'elenco nome e cognome sono null: resta `chi`, il testo salvato in cronologia («Cognome Nome (studente)»).
     *
     * @return list<array{studente_id: int|string, nome: string|null, cognome: string|null, chi: string, mese: string, minuti: int|string, interventi: int|string}>
     */
    public function minutiPerMese(string $dal = '', string $al = ''): array
    {
        $dove = ['c.studente_id IS NOT NULL', 'c.minuti IS NOT NULL'];
        $parametri = [];
        if ($dal !== '') {
            $dove[] = 'c.quando >= ?';
            $parametri[] = $dal . ' 00:00:00';
        }
        if ($al !== '') {
            $dove[] = 'c.quando <= ?';
            $parametri[] = $al . ' 23:59:59';
        }

        return $this->db->righe(
            "SELECT c.studente_id, st.nome, st.cognome, MAX(c.chi) AS chi, DATE_FORMAT(c.quando, '%Y-%m') AS mese, SUM(c.minuti) AS minuti, COUNT(*) AS interventi
             FROM sos_cronologia c LEFT JOIN sos_studenti st ON st.id = c.studente_id WHERE " . implode(' AND ', $dove) . '
             GROUP BY c.studente_id, st.nome, st.cognome, mese ORDER BY mese DESC, st.cognome, st.nome, c.studente_id',
            $parametri
        );
    }

    /**
     * Quelle ancora «In attesa», senza promemoria, mandate prima del limite della loro urgenza ('Y-m-d H:i:s'; null = per quell'urgenza il promemoria è spento).
     *
     * @return list<array{id: int|string, inviata_il: string}>
     */
    public function daSollecitare(?string $limiteFerma, ?string $limiteAltre): array
    {
        $casi = [];
        $parametri = [];
        foreach ([["urgenza = 'ferma'", $limiteFerma], ["urgenza <> 'ferma'", $limiteAltre]] as [$quali, $limite]) {
            if ($limite !== null) {
                $casi[] = "($quali AND inviata_il < ?)";
                $parametri[] = $limite;
            }
        }
        if ($casi === []) {
            return [];
        }

        return $this->db->righe("SELECT id, inviata_il FROM sos_segnalazioni WHERE stato = 'inviata' AND promemoria_il IS NULL AND (" . implode(' OR ', $casi) . ') ORDER BY id', $parametri);
    }

    /** Prenota il promemoria: vero solo per chi lo segna per primo (così parte una volta sola) e solo se è ancora in attesa. */
    public function segnaPromemoria(int $id, string $quando): bool
    {
        return $this->db->esegui("UPDATE sos_segnalazioni SET promemoria_il = ? WHERE id = ? AND stato = 'inviata' AND promemoria_il IS NULL", [$quando, $id]) === 1;
    }

    /** Le foto delle segnalazioni chiuse prima di quel momento. @return list<array{id: int|string, foto: string}> */
    public function fotoDiChiusePrimaDi(string $limite): array
    {
        [$in, $parametri] = self::in('stato', Costanti::STATI_CHIUSI);

        return $this->db->righe("SELECT id, foto FROM sos_segnalazioni WHERE foto IS NOT NULL AND foto <> '' AND $in AND chiusa_il < ? ORDER BY id", array_merge($parametri, [$limite]));
    }

    public function togliFoto(int $id): void
    {
        $this->db->esegui('UPDATE sos_segnalazioni SET foto = NULL WHERE id = ?', [$id]);
    }

    /** L'ultimo passaggio che ha portato in uno stato, con il nome e cognome dell'account. @return array<string, mixed>|null */
    public function ultimoPassaggio(int $segnalazioneId, string $aStato): ?array
    {
        return $this->db->riga(
            "SELECT c.*, TRIM(CONCAT(COALESCE(u.nome, ''), ' ', COALESCE(u.cognome, ''))) AS nome_account FROM sos_cronologia c LEFT JOIN utenti u ON u.id = c.utente_id
             WHERE c.segnalazione_id = ? AND c.a_stato = ? ORDER BY c.id DESC LIMIT 1",
            [$segnalazioneId, $aStato]
        );
    }

    /** @param list<int|string> $valori non vuoto @return array{0: string, 1: list<int|string>} */
    private static function in(string $colonna, array $valori): array
    {
        return [$colonna . ' IN (' . implode(', ', array_fill(0, count($valori), '?')) . ')', array_values($valori)];
    }
}
