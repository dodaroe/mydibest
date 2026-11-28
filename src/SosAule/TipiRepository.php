<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Core\Database;
use App\Core\Esito;
use InvalidArgumentException;

/**
 * I due elenchi modificabili dal pannello: i tipi di problema (sos_tipi) e gli esiti «non risolto» (sos_esiti), con le stesse colonne.
 * Il primo parametro di ogni metodo dice quale: self::TIPI o self::ESITI. «chiede_testo» resta com'è se la voce viene rinominata e non lo si indica.
 * Una voce usata da una segnalazione non si cancella: si spegne.
 */
final class TipiRepository
{
    public const TIPI = 'tipi';
    public const ESITI = 'esiti';

    /** elenco => [tabella, colonna di sos_segnalazioni che la usa] */
    private const ELENCHI = [self::TIPI => ['sos_tipi', 'tipo_id'], self::ESITI => ['sos_esiti', 'esito_id']];

    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string, mixed>> */
    public function elenco(string $quale, bool $soloAttivi = true): array
    {
        return $this->db->righe('SELECT * FROM ' . $this->tabella($quale) . ($soloAttivi ? ' WHERE attivo = 1' : '') . ' ORDER BY ordine, id');
    }

    /** @return array<string, mixed>|null */
    public function perId(string $quale, int $id): ?array
    {
        return $this->db->riga('SELECT * FROM ' . $this->tabella($quale) . ' WHERE id = ?', [$id]);
    }

    public function aggiungi(string $quale, string $nome, string $icona = '', string $aiuto = '', bool $chiedeTesto = false): Esito
    {
        $tabella = $this->tabella($quale);
        if (($errore = self::erroreNome($nome)) !== null) {
            return Esito::errore($errore);
        }
        $ordine = 1 + (int) ($this->db->valore("SELECT COALESCE(MAX(ordine), 0) FROM $tabella") ?? 0);
        $id = $this->db->inserisci(
            "INSERT INTO $tabella (nome, icona, aiuto, ordine, chiede_testo) VALUES (?, ?, ?, ?, ?)",
            [trim($nome), self::icona($icona), mb_substr(trim($aiuto), 0, 255), $ordine, $chiedeTesto ? 1 : 0]
        );

        return $id > 0 ? Esito::ok('Voce aggiunta.') : Esito::errore('Non è stato possibile salvare la voce.');
    }

    /** Nome, e se indicati anche icona, aiuto e «chiede testo». */
    public function rinomina(string $quale, int $id, string $nome, ?string $icona = null, ?string $aiuto = null, ?bool $chiedeTesto = null): Esito
    {
        $voce = $this->perId($quale, $id);
        if ($voce === null) {
            return Esito::errore('Quella voce non c\'è più.');
        }
        if (($errore = self::erroreNome($nome)) !== null) {
            return Esito::errore($errore);
        }
        $n = $this->db->esegui(
            'UPDATE ' . $this->tabella($quale) . ' SET nome = ?, icona = ?, aiuto = ?, chiede_testo = ? WHERE id = ?',
            [trim($nome), $icona === null ? (string) $voce['icona'] : self::icona($icona), $aiuto === null ? (string) $voce['aiuto'] : mb_substr(trim($aiuto), 0, 255),
             $chiedeTesto === null ? (int) $voce['chiede_testo'] : ($chiedeTesto ? 1 : 0), $id]
        );

        return $n >= 0 ? Esito::ok('Voce salvata.') : Esito::errore('Non è stato possibile salvare la voce.');
    }

    /** @param list<int> $ids tutte le voci nell'ordine voluto */
    public function ordina(string $quale, array $ids): void
    {
        $tabella = $this->tabella($quale);
        foreach (array_values($ids) as $i => $id) {
            $this->db->esegui("UPDATE $tabella SET ordine = ? WHERE id = ?", [$i + 1, (int) $id]);
        }
    }

    /** Accende o spegne una voce. L'ultima voce accesa non si spegne: il form resterebbe senza scelte. */
    public function accendi(string $quale, int $id, bool $attivo): Esito
    {
        $voce = $this->perId($quale, $id);
        if ($voce === null) {
            return Esito::errore('Quella voce non c\'è più.');
        }
        if (!$attivo && count(array_filter($this->elenco($quale), static fn (array $v): bool => (int) $v['id'] !== $id)) === 0) {
            return Esito::errore('Deve restare almeno una voce accesa.');
        }
        $this->db->esegui('UPDATE ' . $this->tabella($quale) . ' SET attivo = ? WHERE id = ?', [$attivo ? 1 : 0, $id]);

        return Esito::ok($attivo ? 'Voce accesa.' : 'Voce spenta.');
    }

    public function elimina(string $quale, int $id): Esito
    {
        $voce = $this->perId($quale, $id);
        if ($voce === null) {
            return Esito::errore('Quella voce non c\'è più.');
        }
        [$tabella, $colonna] = self::ELENCHI[$quale];
        if ((int) $this->db->valore("SELECT COUNT(*) FROM sos_segnalazioni WHERE $colonna = ?", [$id]) > 0) {
            return Esito::errore('La voce «' . $voce['nome'] . '» è usata da qualche segnalazione: non si può cancellare, puoi spegnerla.');
        }
        if ((int) $voce['attivo'] === 1 && count($this->elenco($quale)) === 1) {
            return Esito::errore('Deve restare almeno una voce accesa.');
        }
        $this->db->esegui("DELETE FROM $tabella WHERE id = ?", [$id]);

        return Esito::ok('Voce cancellata.');
    }

    private function tabella(string $quale): string
    {
        if (!isset(self::ELENCHI[$quale])) {
            throw new InvalidArgumentException("Elenco sconosciuto: $quale");
        }

        return self::ELENCHI[$quale][0];
    }

    private static function erroreNome(string $nome): ?string
    {
        $nome = trim($nome);
        if ($nome === '') {
            return 'Scrivi il nome della voce.';
        }

        return mb_strlen($nome) > 120 ? 'Il nome è troppo lungo (massimo 120 caratteri).' : null;
    }

    /** Una classe di Font Awesome (fa-…), altrimenti il punto interrogativo. */
    private static function icona(string $icona): string
    {
        $icona = trim($icona);

        return preg_match('/^fa-[a-z0-9-]{1,40}$/', $icona) ? $icona : 'fa-circle-question';
    }
}
