<?php

declare(strict_types=1);

namespace App\Sistema;

use App\Core\Sito;

/**
 * Quando è girato l'ultima volta ciascun processo automatico (cron_background, cron_attestati, cron_reminders, e cron_sos che gira dentro cron_background):
 * lo scrive il processo stesso, a lavoro finito, in cache/cron_ultima_esecuzione.json; lo legge il controllo di salute in Sistema.
 * Per il backup basta cache/backup_stato.json. Per i processi già in funzione prima di questa classe, in mancanza
 * dell'annotazione si legge la data dell'ultima modifica del loro file di blocco (cache/<nome>.lock, riscritto a ogni avvio).
 */
final class StatoCron
{
    public function __construct(private Sito $sito)
    {
    }

    /** Annota che il processo è appena terminato (senza errori). */
    public function registra(string $nome, ?int $quando = null): void
    {
        $cache = $this->sito->radice() . '/cache';
        if (!is_dir($cache)) {
            \App\Core\File::creaCartella($cache, 0755);
        }
        $percorso = $this->file();
        $f = (is_file($percorso) ? is_writable($percorso) : is_writable(dirname($percorso))) ? fopen($percorso, 'c+') : false;
        if (!$f) {
            error_log('[StatoCron] file non scrivibile: ' . $percorso);

            return;
        }
        if (flock($f, LOCK_EX)) {
            $dati = json_decode((string) stream_get_contents($f), true);
            $dati = is_array($dati) ? $dati : [];
            $dati[$nome] = date('Y-m-d H:i:s', $quando ?? time());
            ftruncate($f, 0);
            rewind($f);
            fwrite($f, json_encode($dati, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fflush($f);
            flock($f, LOCK_UN);
        }
        fclose($f);
    }

    /** Il momento dell'ultima esecuzione (timestamp), null se il processo non è mai girato. */
    public function ultima(string $nome): ?int
    {
        $f = $this->file();
        $dati = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        $t = is_array($dati) && isset($dati[$nome]) ? strtotime((string) $dati[$nome]) : false;
        if ($t !== false) {
            return $t;
        }
        $lock = $this->sito->radice() . '/cache/' . $nome . '.lock';

        return is_file($lock) ? (int) filemtime($lock) : null;
    }

    private function file(): string
    {
        return $this->sito->radice() . '/cache/cron_ultima_esecuzione.json';
    }
}
