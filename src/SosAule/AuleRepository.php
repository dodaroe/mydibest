<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Core\Database;
use App\Core\Esito;
use RuntimeException;

/** Le aule di SOS (tabella sos_aule) e il codice del loro QR. */
final class AuleRepository
{
    /** Senza i caratteri che si confondono (0/O, 1/I): il codice è anche scritto sul cartello per chi non riesce a inquadrare. */
    private const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const LUNGHEZZA = 8;

    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function tutte(bool $soloAttive = false): array
    {
        return $this->db->righe('SELECT * FROM sos_aule' . ($soloAttive ? ' WHERE attiva = 1' : '') . ' ORDER BY ordine, nome, id');
    }

    /** Le aule con quanti referenti hanno e quante segnalazioni, aperte e in tutto. @return list<array<string, mixed>> */
    public function conConteggi(): array
    {
        $aperti = Costanti::STATI_APERTI;

        return $this->db->righe(
            'SELECT a.*, (SELECT COUNT(*) FROM sos_referenti r WHERE r.aula_id = a.id) AS referenti,
                    (SELECT COUNT(*) FROM sos_segnalazioni s WHERE s.aula_id = a.id AND s.stato IN (' . implode(', ', array_fill(0, count($aperti), '?')) . ')) AS aperte,
                    (SELECT COUNT(*) FROM sos_segnalazioni s WHERE s.aula_id = a.id) AS segnalazioni
             FROM sos_aule a ORDER BY a.ordine, a.nome, a.id',
            $aperti
        );
    }

    /** @return array<string, mixed>|null */
    public function perId(int $id): ?array
    {
        return $id > 0 ? $this->db->riga('SELECT * FROM sos_aule WHERE id = ?', [$id]) : null;
    }

    /**
     * L'aula di un codice letto dal QR. «vecchio» = il codice è di un cartello che è stato rigenerato (si dice di scegliere l'aula, non si manda in errore).
     *
     * @return array{aula: array<string, mixed>, vecchio: bool}|null null se il codice non è di nessuna aula
     */
    public function perCodice(string $codice): ?array
    {
        $codice = strtoupper(trim($codice));
        if (!preg_match('/^[A-Z0-9]{' . self::LUNGHEZZA . '}$/', $codice)) {
            return null;
        }
        $aula = $this->db->riga('SELECT * FROM sos_aule WHERE codice = ?', [$codice]);
        if ($aula !== null) {
            return ['aula' => $aula, 'vecchio' => false];
        }
        $aula = $this->db->riga('SELECT * FROM sos_aule WHERE codici_vecchi LIKE ?', ['%"' . $codice . '"%']);

        return $aula === null ? null : ['aula' => $aula, 'vecchio' => true];
    }

    /** Un codice nuovo, mai usato (neppure da un cartello vecchio). */
    public function generaCodice(): string
    {
        for ($tentativo = 0; $tentativo < 20; $tentativo++) {
            $codice = '';
            for ($i = 0; $i < self::LUNGHEZZA; $i++) {
                $codice .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            }
            if ($this->perCodice($codice) === null) {
                return $codice;
            }
        }
        throw new RuntimeException('Non è stato possibile generare un codice per l\'aula.');
    }

    /** @param array{nome: string, edificio?: string, note?: string, risorsa_id?: int|null, ordine?: int} $dati @return int l'id (0 se non salvata) */
    public function aggiungi(array $dati, int $da): int
    {
        return $this->db->inserisci(
            'INSERT INTO sos_aule (nome, edificio, codice, risorsa_id, note, ordine, creata_il, creata_da) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)',
            [mb_substr(trim($dati['nome']), 0, 80), mb_substr(trim($dati['edificio'] ?? ''), 0, 120), $this->generaCodice(), $dati['risorsa_id'] ?? null,
             mb_substr(trim($dati['note'] ?? ''), 0, 500), (int) ($dati['ordine'] ?? 0), $da > 0 ? $da : null]
        );
    }

    /** @param array{nome: string, edificio?: string, note?: string, risorsa_id?: int|null, ordine?: int, attiva?: bool} $dati il codice non cambia mai da qui */
    public function modifica(int $id, array $dati): bool
    {
        return $this->db->esegui(
            'UPDATE sos_aule SET nome = ?, edificio = ?, risorsa_id = ?, note = ?, ordine = ?, attiva = ? WHERE id = ?',
            [mb_substr(trim($dati['nome']), 0, 80), mb_substr(trim($dati['edificio'] ?? ''), 0, 120), $dati['risorsa_id'] ?? null, mb_substr(trim($dati['note'] ?? ''), 0, 500),
             (int) ($dati['ordine'] ?? 0), ($dati['attiva'] ?? true) ? 1 : 0, $id]
        ) >= 0;
    }

    /** Il posto in fondo all'elenco, per un'aula nuova. */
    public function prossimoOrdine(): int
    {
        return 1 + (int) ($this->db->valore('SELECT COALESCE(MAX(ordine), 0) FROM sos_aule') ?? 0);
    }

    /** Sposta un'aula di un posto: $verso -1 = su, 1 = giù. Gli altri numeri d'ordine si rifanno 1, 2, 3… */
    public function sposta(int $id, int $verso): void
    {
        $ids = array_map('intval', array_column($this->tutte(), 'id'));
        $i = array_search($id, $ids, true);
        if ($i === false || !isset($ids[$i + $verso])) {
            return;
        }
        [$ids[$i], $ids[$i + $verso]] = [$ids[$i + $verso], $ids[$i]];
        foreach ($ids as $posto => $aula) {
            $this->db->esegui('UPDATE sos_aule SET ordine = ? WHERE id = ?', [$posto + 1, $aula]);
        }
    }

    /** Un'aula con segnalazioni non si cancella (il registro deve continuare a dire dov'è successo): si spegne. */
    public function elimina(int $id): Esito
    {
        $aula = $this->perId($id);
        if ($aula === null) {
            return Esito::errore('L\'aula non c\'è più.');
        }
        if ((int) $this->db->valore('SELECT COUNT(*) FROM sos_segnalazioni WHERE aula_id = ?', [$id]) > 0) {
            return Esito::errore('L\'aula «' . $aula['nome'] . '» ha delle segnalazioni: non si può cancellare, puoi spegnerla.');
        }
        $this->db->esegui('DELETE FROM sos_referenti WHERE aula_id = ?', [$id]);

        return $this->db->esegui('DELETE FROM sos_aule WHERE id = ?', [$id]) === 1
            ? Esito::ok('Aula «' . $aula['nome'] . '» cancellata.')
            : Esito::errore('Non è stato possibile cancellare l\'aula: riprova tra qualche minuto.');
    }

    /** Dà all'aula un codice nuovo e tiene il vecchio tra quelli rigenerati. @return string|null il codice nuovo, null se l'aula non c'è o non si salva */
    public function rigeneraCodice(int $id): ?string
    {
        return $this->db->transazione(function () use ($id): ?string {
            $aula = $this->db->riga('SELECT codice, codici_vecchi FROM sos_aule WHERE id = ? FOR UPDATE', [$id]);
            if ($aula === null) {
                return null;
            }
            $vecchi = json_decode((string) ($aula['codici_vecchi'] ?? ''), true);
            $vecchi = is_array($vecchi) ? array_values($vecchi) : [];
            $vecchi[] = (string) $aula['codice'];
            $nuovo = $this->generaCodice();

            return $this->db->esegui('UPDATE sos_aule SET codice = ?, codici_vecchi = ? WHERE id = ?', [$nuovo, (string) json_encode($vecchi), $id]) === 1 ? $nuovo : null;
        });
    }
}
