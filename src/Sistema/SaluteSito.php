<?php

declare(strict_types=1);

namespace App\Sistema;

use App\Core\Ambiente;
use App\Core\Sito;
use App\Sistema\Backup\StatoBackup;

/**
 * Controllo di salute del portale, una riga con semaforo per voce (pagina Sistema):
 * processi automatici (cron), spazio su disco, ultimo backup riuscito, certificato HTTPS, email.
 *
 * Semafori: verde = a posto, giallo = da guardare, rosso = qualcosa non va, grigio = non valutabile
 * (processo non programmato, copia di prova o ambiente locale, certificato non raggiungibile).
 * `raccogli()` legge i dati veri; `valuta()` li trasforma in semafori senza toccare nulla (si prova con dati finti).
 */
final class SaluteSito
{
    public const VERDE = 'verde';
    public const GIALLO = 'giallo';
    public const ROSSO = 'rosso';
    public const GRIGIO = 'grigio';

    /** Secondi: oltre «verde» è giallo, oltre «giallo» è rosso. */
    public const SOGLIA_CRON_QUARTO_D_ORA = [3600, 6 * 3600];
    public const SOGLIA_CRON_GIORNALIERO = [26 * 3600, 50 * 3600];
    public const SOGLIA_BACKUP = [36 * 3600, 72 * 3600];
    /** Spazio libero in GB: da «verde» in su è verde, da «rosso» a «verde» giallo, sotto «rosso» rosso. */
    public const SOGLIA_SPAZIO_GB = [5.0, 1.0];
    /** Giorni alla scadenza del certificato: da «verde» in su verde, da «rosso» a «verde» giallo (avviso sotto i 30), sotto «rosso» rosso. */
    public const SOGLIA_CERTIFICATO_GIORNI = [30, 7];

    private const CERT_TENTA_DI_NUOVO_DOPO = 3600;
    private const CERT_VALIDO_PER = 86400;

    /**
     * @param ?\Closure(string): ?int $certificato dato l'indirizzo del sito dice quando scade il certificato (null = non leggibile); di norma lo legge dalla rete
     */
    public function __construct(
        private Sito $sito,
        private StatoBackup $backup,
        private StatoCron $cron,
        private LogEmailRepository $logEmail,
        private Ambiente $ambiente,
        private bool $ambienteLocale,
        private ?\Closure $certificato = null,
    ) {
    }

    /**
     * Le righe del controllo, già valutate.
     *
     * @return list<array{chiave: string, nome: string, semaforo: string, dettaglio: string}>
     */
    public function righe(): array
    {
        return self::valuta($this->raccogli(), time());
    }

    /**
     * Esiti nel formato del controllo notturno (ControlloSito): solo le voci che il controllo non guarda già
     * (processi e certificato), con ok=false solo se sono rosse; le grigie e le gialle non fanno scattare l'avviso.
     *
     * @return list<array{ok: bool|null, voce: string}>
     */
    public function esitiPerAvviso(): array
    {
        if ($this->modo() !== 'normale') {
            return []; // copia di prova o ambiente locale: nessun avviso
        }
        $esiti = [];
        foreach ($this->righe() as $r) {
            if (!in_array($r['chiave'], ['cron_background', 'cron_sos', 'cron_reminders', 'cron_backup', 'certificato'], true)) {
                continue;
            }
            if ($r['semaforo'] === self::ROSSO || $r['semaforo'] === self::VERDE) {
                $esiti[] = ['ok' => $r['semaforo'] === self::VERDE, 'voce' => $r['nome'] . ': ' . $r['dettaglio']];
            }
        }

        return $esiti;
    }

    /**
     * La versione caricata sul server, dal file che crea_pacchetto.sh mette nel pacchetto (inc/versione_portale.txt).
     * Null se il file manca (copia locale) o non è leggibile.
     *
     * @return array{commit: string, data: string, ramo: string}|null
     */
    public static function versioneCaricata(string $radice): ?array
    {
        $f = $radice . '/inc/versione_portale.txt';
        $v = is_file($f) ? \App\Core\File::leggiIni((string) file_get_contents($f), true) : false;
        if (!is_array($v) || trim((string) ($v['commit'] ?? '')) === '') {
            return null;
        }

        return ['commit' => trim((string) $v['commit']), 'data' => trim((string) ($v['data'] ?? '')), 'ramo' => trim((string) ($v['ramo'] ?? ''))];
    }

    /** «prova» = copia di prova (cron spenti), «locale» = sviluppo sul proprio computer, «normale» = il sito vero. */
    private function modo(): string
    {
        return $this->ambiente->inProva() ? 'prova' : ($this->ambienteLocale ? 'locale' : 'normale');
    }

    // ------------------------------------------------------------------ lettura dei dati

    /**
     * I dati grezzi per `valuta()`.
     *
     * @return array<string, mixed>
     */
    public function raccogli(): array
    {
        $modo = $this->modo();
        $radice = $this->sito->radice();
        $bk = $this->backup->leggi();
        $libero = static function (string $cartella): ?float {
            $b = is_dir($cartella) ? disk_free_space($cartella) : false;

            return $b === false ? null : $b / 1073741824;
        };
        $quando = static fn (mixed $d): ?int => ($t = strtotime((string) $d)) === false || $d === null || $d === '' ? null : $t;

        $email = ['ultima_ok' => null, 'errori24' => 0, 'tot24' => 0];
        try {
            $email['ultima_ok'] = $this->logEmail->ultimaRiuscita();
            $x = $this->logEmail->esitiGiorno();
            $email['errori24'] = (int) ($x['ko'] ?? 0);
            $email['tot24'] = (int) ($x['tot'] ?? 0);
        } catch (\Throwable) {
            // tabella ancora da creare: nessuna email nel registro
        }

        return [
            'modo' => $modo,
            'cron' => [
                'cron_background' => $this->cron->ultima('cron_background'),
                'cron_attestati' => $this->cron->ultima('cron_attestati'),
                'cron_reminders' => $this->cron->ultima('cron_reminders'),
                'cron_sos' => $this->cron->ultima('cron_sos'),
                'cron_backup' => $quando($bk['data'] ?? null),
            ],
            'spazio' => ['portale' => $libero($radice), 'uploads' => $libero($radice . '/uploads')],
            'backup' => [
                // «ultimo_ok» lo scrive il backup; nelle copie scritte prima, se l'ultimo giro era riuscito vale la sua data
                'ultimo_ok' => $quando($bk['ultimo_ok'] ?? (!empty($bk['ok']) ? ($bk['data'] ?? null) : null)),
                'ultimo_giro_ok' => $bk ? !empty($bk['ok']) : null,
            ],
            'certificato' => ['scade' => $this->scadenzaCertificato()],
            'email' => $email,
        ];
    }

    /** Scadenza del certificato HTTPS del sito (timestamp), null se non leggibile. Il risultato vale un giorno (un'ora se la lettura è fallita). */
    private function scadenzaCertificato(): ?int
    {
        $url = $this->sito->urlBase();
        $cache = $this->sito->radice() . '/cache/salute_certificato.json';
        $c = is_file($cache) ? json_decode((string) file_get_contents($cache), true) : null;
        if (is_array($c) && ($c['url'] ?? '') === $url) {
            $eta = time() - (int) ($c['letto'] ?? 0);
            if ($eta >= 0 && $eta < (($c['scade'] ?? null) === null ? self::CERT_TENTA_DI_NUOVO_DOPO : self::CERT_VALIDO_PER)) {
                return $c['scade'] === null ? null : (int) $c['scade'];
            }
        }
        $scade = $this->certificato ? ($this->certificato)($url) : self::leggiCertificato($url);
        if (is_dir(dirname($cache)) || \App\Core\File::creaCartella(dirname($cache), 0755)) {
            \App\Core\File::scrivi($cache, json_encode(['url' => $url, 'letto' => time(), 'scade' => $scade]));
        }

        return $scade;
    }

    /** Legge dalla rete il certificato del sito (collegamento TLS, 3 secondi al massimo): quando scade, null se il sito non è in https o non risponde. */
    public static function leggiCertificato(string $url): ?int
    {
        $p = parse_url($url);
        if (!is_array($p) || strtolower((string) ($p['scheme'] ?? '')) !== 'https' || empty($p['host'])) {
            return null;
        }
        $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false, 'SNI_enabled' => true, 'peer_name' => $p['host']]]);
        $errno = 0;
        $errstr = '';
        // La @ resta: un sito senza rete o con il certificato non raggiungibile è un caso previsto (si risponde null, senza avvisi nel registro)
        $s = @stream_socket_client('ssl://' . $p['host'] . ':' . ($p['port'] ?? 443), $errno, $errstr, 3, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) {
            return null;
        }
        $par = stream_context_get_params($s);
        fclose($s);
        $info = isset($par['options']['ssl']['peer_certificate']) ? openssl_x509_parse($par['options']['ssl']['peer_certificate']) : false;

        return is_array($info) && isset($info['validTo_time_t']) ? (int) $info['validTo_time_t'] : null;
    }

    // ------------------------------------------------------------------ valutazione (nessun accesso a file, rete o database)

    /**
     * @param array<string, mixed> $d dati di `raccogli()`
     * @return list<array{chiave: string, nome: string, semaforo: string, dettaglio: string}>
     */
    public static function valuta(array $d, int $adesso): array
    {
        $righe = [];
        $riga = static function (string $chiave, string $nome, array $esito) use (&$righe): void {
            $righe[] = ['chiave' => $chiave, 'nome' => $nome, 'semaforo' => $esito[0], 'dettaglio' => $esito[1]];
        };
        $modo = (string) ($d['modo'] ?? 'normale');
        $spento = $modo === 'prova' ? 'copia di prova: i processi automatici sono spenti' : ($modo === 'locale' ? 'ambiente locale: i processi automatici non girano' : null);

        $cron = (array) ($d['cron'] ?? []);
        $riga('cron_background', 'Processi automatici (cron_background)', $spento ? [self::GRIGIO, $spento] : self::esitoCron($cron['cron_background'] ?? null, $adesso, self::SOGLIA_CRON_QUARTO_D_ORA));
        if ($spento) {
            $riga('cron_attestati', 'Attestati automatici (cron_attestati)', [self::GRIGIO, $spento]);
        } else {
            $riga('cron_attestati', 'Attestati automatici (cron_attestati)', self::esitoAttestati($cron['cron_attestati'] ?? null, $adesso));
        }
        $riga('cron_sos', 'SOS aule: promemoria e foto (cron_sos, dentro cron_background)', $spento ? [self::GRIGIO, $spento] : self::esitoCron($cron['cron_sos'] ?? null, $adesso, self::SOGLIA_CRON_QUARTO_D_ORA));
        $riga('cron_reminders', 'Promemoria (cron_reminders)', $spento ? [self::GRIGIO, $spento] : self::esitoCron($cron['cron_reminders'] ?? null, $adesso, self::SOGLIA_CRON_QUARTO_D_ORA));
        $riga('cron_backup', 'Esecuzione del backup (cron_backup)', $spento ? [self::GRIGIO, $spento] : self::esitoCron($cron['cron_backup'] ?? null, $adesso, self::SOGLIA_CRON_GIORNALIERO));

        $sp = (array) ($d['spazio'] ?? []);
        $riga('spazio_portale', 'Spazio libero nella cartella del portale', self::esitoSpazio($sp['portale'] ?? null));
        $riga('spazio_uploads', 'Spazio libero nella cartella dei file caricati', self::esitoSpazio($sp['uploads'] ?? null));

        $bk = (array) ($d['backup'] ?? []);
        $riga('backup', 'Ultimo backup riuscito', $spento ? [self::GRIGIO, $spento] : self::esitoBackup($bk['ultimo_ok'] ?? null, $bk['ultimo_giro_ok'] ?? null, $adesso));

        $cert = (array) ($d['certificato'] ?? []);
        $riga('certificato', 'Certificato HTTPS', self::esitoCertificato($cert['scade'] ?? null, $adesso));

        $riga('email', 'Email', self::esitoEmail((array) ($d['email'] ?? []), $adesso));

        return $righe;
    }

    /** @return array{0: string, 1: string} */
    public static function esitoCron(?int $ultima, int $adesso, array $soglie): array
    {
        if ($ultima === null) {
            return [self::ROSSO, 'mai girato: il crontab del server non lo avvia?'];
        }
        $eta = max(0, $adesso - $ultima);
        $testo = 'ultima esecuzione ' . date('d/m/Y H:i', $ultima) . ($eta >= 3600 ? ' (' . self::durata($eta) . ' fa)' : '');

        return [$eta <= $soglie[0] ? self::VERDE : ($eta <= $soglie[1] ? self::GIALLO : self::ROSSO), $testo];
    }

    /** Il processo degli attestati non è nel crontab: mai girato è «non programmato» (grigio), mai rosso. */
    public static function esitoAttestati(?int $ultima, int $adesso): array
    {
        if ($ultima === null) {
            return [self::GRIGIO, 'non programmato: non è mai girato (gli attestati partono anche da cron_background)'];
        }
        $eta = max(0, $adesso - $ultima);

        return [$eta <= 2 * 86400 ? self::VERDE : self::GRIGIO, 'ultima esecuzione ' . date('d/m/Y H:i', $ultima) . ($eta > 2 * 86400 ? ' (' . self::durata($eta) . ' fa: non è un processo programmato)' : '')];
    }

    /** @return array{0: string, 1: string} */
    public static function esitoSpazio(?float $gb): array
    {
        if ($gb === null) {
            return [self::GRIGIO, 'non leggibile'];
        }
        $t = number_format($gb, 1, ',', '.') . ' GB liberi';

        return [$gb >= self::SOGLIA_SPAZIO_GB[0] ? self::VERDE : ($gb >= self::SOGLIA_SPAZIO_GB[1] ? self::GIALLO : self::ROSSO), $t . ($gb < self::SOGLIA_SPAZIO_GB[1] ? ' (meno di 1 GB)' : '')];
    }

    /** @return array{0: string, 1: string} */
    public static function esitoBackup(?int $ultimoOk, ?bool $ultimoGiroOk, int $adesso): array
    {
        if ($ultimoOk === null) {
            return [self::ROSSO, 'nessun backup riuscito registrato'];
        }
        $eta = max(0, $adesso - $ultimoOk);
        $sem = $eta <= self::SOGLIA_BACKUP[0] ? self::VERDE : ($eta <= self::SOGLIA_BACKUP[1] ? self::GIALLO : self::ROSSO);
        $t = date('d/m/Y H:i', $ultimoOk) . ($eta >= 3600 ? ' (' . self::durata($eta) . ' fa)' : '');
        if ($ultimoGiroOk === false) {
            $t .= '; l\'ultimo tentativo ha avuto problemi';
            $sem = $sem === self::VERDE ? self::GIALLO : $sem;
        }

        return [$sem, $t];
    }

    /** @return array{0: string, 1: string} */
    public static function esitoCertificato(?int $scade, int $adesso): array
    {
        if ($scade === null) {
            return [self::GRIGIO, 'non controllabile (sito non in https o non raggiungibile)'];
        }
        $giorni = (int) floor(($scade - $adesso) / 86400);
        $t = 'scade il ' . date('d/m/Y', $scade) . ($giorni >= 0 ? " (tra $giorni giorni)" : ' (già scaduto)');
        $sem = $giorni >= self::SOGLIA_CERTIFICATO_GIORNI[0] ? self::VERDE : ($giorni >= self::SOGLIA_CERTIFICATO_GIORNI[1] ? self::GIALLO : self::ROSSO);

        return [$sem, $t];
    }

    /**
     * @param array<string, mixed> $e ultima_ok (timestamp o null), errori24, tot24
     * @return array{0: string, 1: string}
     */
    public static function esitoEmail(array $e, int $adesso): array
    {
        $ultima = isset($e['ultima_ok']) && $e['ultima_ok'] !== null ? (is_int($e['ultima_ok']) ? $e['ultima_ok'] : (int) strtotime((string) $e['ultima_ok'])) : null;
        $ko = (int) ($e['errori24'] ?? 0);
        $tot = (int) ($e['tot24'] ?? 0);
        if ($ultima === null && $tot === 0) {
            return [self::GRIGIO, 'nessuna email nel registro'];
        }
        $t = ($ultima === null ? 'nessuna inviata con successo' : 'ultima inviata il ' . date('d/m/Y H:i', $ultima)) . "; non partite nelle ultime 24 ore: $ko su $tot";
        if ($ko === 0) {
            return [self::VERDE, $t];
        }

        return [$ko <= max(3, intdiv($tot, 10)) ? self::GIALLO : self::ROSSO, $t];
    }

    private static function durata(int $secondi): string
    {
        if ($secondi >= 2 * 86400) {
            return intdiv($secondi, 86400) . ' giorni';
        }
        if ($secondi >= 86400) {
            return '1 giorno';
        }
        $ore = intdiv($secondi, 3600);

        return $ore . ($ore === 1 ? ' ora' : ' ore');
    }
}
