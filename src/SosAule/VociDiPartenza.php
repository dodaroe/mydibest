<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Core\Database;

/** I tipi di problema e gli esiti «non risolto» di partenza: li inserisce la migrazione (v147), solo se la tabella è vuota. */
final class VociDiPartenza
{
    /** @var list<array{0: string, 1: string, 2: string, 3: int}> nome, icona, aiuto, chiede_testo */
    private const TIPI = [
        ['Proiettore o schermo', 'fa-video', '', 0],
        ['PC dell\'aula', 'fa-desktop', 'Non si accende, password, programmi', 0],
        ['Collegare il mio portatile', 'fa-laptop', 'HDMI, adattatori', 0],
        ['Audio e microfono', 'fa-microphone', '', 0],
        ['Rete / Wi-Fi', 'fa-wifi', '', 0],
        ['Aula chiusa o chiavi', 'fa-key', '', 0],
        ['Manca materiale', 'fa-box-open', 'Gesso, pennarelli, batterie, telecomando', 0],
        ['Luci, riscaldamento, arredi', 'fa-lightbulb', '', 0],
        ['Altro', 'fa-circle-question', '', 1],
    ];

    /** @var list<array{0: string, 1: string, 2: string, 3: int}> */
    private const ESITI = [
        ['Serve un pezzo di ricambio', 'fa-screwdriver-wrench', '', 0],
        ['Guasto da tecnico esterno', 'fa-user-gear', '', 0],
        ['Mi serve una password o un permesso', 'fa-lock', '', 0],
        ['Non sono riuscito a entrare in aula', 'fa-door-closed', '', 0],
        ['Altro', 'fa-circle-question', '', 1],
    ];

    public static function inserisci(Database $db): void
    {
        foreach (['sos_tipi' => self::TIPI, 'sos_esiti' => self::ESITI] as $tabella => $voci) {
            // senza risposta dal database (null) non si inserisce niente: meglio nessuna riga che righe doppie
            if ((int) ($db->valore("SELECT COUNT(*) FROM $tabella") ?? 1) !== 0) {
                continue;
            }
            foreach ($voci as $i => [$nome, $icona, $aiuto, $chiedeTesto]) {
                $db->esegui("INSERT INTO $tabella (nome, icona, aiuto, ordine, chiede_testo) VALUES (?, ?, ?, ?, ?)", [$nome, $icona, $aiuto, $i + 1, $chiedeTesto]);
            }
        }
    }
}
