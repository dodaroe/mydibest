<?php

declare(strict_types=1);

namespace Tests\Unit\Sistema;

use App\Core\Sito;
use App\Sistema\SaluteSito;
use App\Sistema\StatoCron;
use PHPUnit\Framework\TestCase;

/** Controllo di salute in Sistema (S15): i semafori con dati finti, l'annotazione dei cron e la versione caricata. Senza rete né database. */
final class SaluteSitoTest extends TestCase
{
    private const ADESSO = 1795000000; // un momento fisso

    private string $cartella;

    protected function setUp(): void
    {
        $this->cartella = sys_get_temp_dir() . '/salute_' . bin2hex(random_bytes(4));
        mkdir($this->cartella . '/cache', 0755, true);
        mkdir($this->cartella . '/inc', 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cartella . '/{cache,inc}/*', GLOB_BRACE) ?: [] as $f) {
            is_file($f) && unlink($f);
        }
        is_dir($this->cartella . '/cache') && rmdir($this->cartella . '/cache');
        is_dir($this->cartella . '/inc') && rmdir($this->cartella . '/inc');
        is_dir($this->cartella) && rmdir($this->cartella);
    }

    /** @return array<string, mixed> dati di una giornata «tutto a posto» */
    private function dati(string $modo = 'normale'): array
    {
        $a = self::ADESSO;

        return [
            'modo' => $modo,
            'cron' => ['cron_background' => $a - 600, 'cron_sos' => $a - 600, 'cron_attestati' => null, 'cron_reminders' => $a - 600, 'cron_backup' => $a - 8 * 3600],
            'spazio' => ['portale' => 40.0, 'uploads' => 40.0],
            'backup' => ['ultimo_ok' => $a - 8 * 3600, 'ultimo_giro_ok' => true],
            'certificato' => ['scade' => $a + 80 * 86400],
            'email' => ['ultima_ok' => $a - 300, 'errori24' => 0, 'tot24' => 20],
        ];
    }

    /** @param array<string, mixed> $d */
    private function semafori(array $d): array
    {
        return array_column(SaluteSito::valuta($d, self::ADESSO), 'semaforo', 'chiave');
    }

    public function testTuttoAPostoEIlProcessoDegliAttestatiNonProgrammatoEGrigio(): void
    {
        $s = $this->semafori($this->dati());
        $this->assertSame(
            ['cron_background', 'cron_attestati', 'cron_sos', 'cron_reminders', 'cron_backup', 'spazio_portale', 'spazio_uploads', 'backup', 'certificato', 'email'],
            array_keys($s)
        );
        foreach ($s as $chiave => $sem) {
            $this->assertSame($chiave === 'cron_attestati' ? 'grigio' : 'verde', $sem, $chiave);
        }
        $det = array_column(SaluteSito::valuta($this->dati(), self::ADESSO), 'dettaglio', 'chiave');
        $this->assertStringContainsString('non programmato', $det['cron_attestati']);
    }

    public function testIProcessiFermiDiventanoGialliEPoiRossi(): void
    {
        $d = $this->dati();
        $d['cron']['cron_background'] = self::ADESSO - 3 * 3600;
        $this->assertSame('giallo', $this->semafori($d)['cron_background']);
        $d['cron']['cron_background'] = self::ADESSO - 7 * 3600;
        $this->assertSame('rosso', $this->semafori($d)['cron_background']);
        $d['cron']['cron_background'] = null; // mai girato
        $s = $this->semafori($d);
        $this->assertSame('rosso', $s['cron_background']);
        $this->assertStringContainsString('mai girato', array_column(SaluteSito::valuta($d, self::ADESSO), 'dettaglio', 'chiave')['cron_background']);
        $d['cron']['cron_backup'] = self::ADESSO - 30 * 3600;
        $this->assertSame('giallo', $this->semafori($d)['cron_backup']);
        $d['cron']['cron_backup'] = self::ADESSO - 60 * 3600;
        $this->assertSame('rosso', $this->semafori($d)['cron_backup']);
    }

    public function testGliAttestatiNonSonoMaiRossi(): void
    {
        $d = $this->dati();
        $d['cron']['cron_attestati'] = self::ADESSO - 3600;
        $this->assertSame('verde', $this->semafori($d)['cron_attestati']);
        $d['cron']['cron_attestati'] = self::ADESSO - 40 * 86400;
        $this->assertSame('grigio', $this->semafori($d)['cron_attestati'], 'fermo da settimane: non è programmato, non è un guasto');
    }

    public function testSpazioSuDisco(): void
    {
        $this->assertSame('verde', SaluteSito::esitoSpazio(5.0)[0]);
        $this->assertSame('giallo', SaluteSito::esitoSpazio(2.5)[0]);
        $this->assertSame('rosso', SaluteSito::esitoSpazio(0.4)[0]);
        $this->assertStringContainsString('meno di 1 GB', SaluteSito::esitoSpazio(0.4)[1]);
        $this->assertSame('grigio', SaluteSito::esitoSpazio(null)[0]);
        $d = $this->dati();
        $d['spazio'] = ['portale' => 40.0, 'uploads' => 0.5];
        $s = $this->semafori($d);
        $this->assertSame(['verde', 'rosso'], [$s['spazio_portale'], $s['spazio_uploads']]);
    }

    public function testUltimoBackupRiuscito(): void
    {
        $a = self::ADESSO;
        $this->assertSame('verde', SaluteSito::esitoBackup($a - 10 * 3600, true, $a)[0]);
        $this->assertSame('giallo', SaluteSito::esitoBackup($a - 48 * 3600, true, $a)[0]);
        $this->assertSame('rosso', SaluteSito::esitoBackup($a - 4 * 86400, true, $a)[0]);
        $this->assertSame('rosso', SaluteSito::esitoBackup(null, null, $a)[0], 'nessun backup riuscito');
        [$sem, $testo] = SaluteSito::esitoBackup($a - 10 * 3600, false, $a);
        $this->assertSame('giallo', $sem, "l'ultimo tentativo è andato male anche se un backup recente c'è");
        $this->assertStringContainsString('problemi', $testo);
    }

    public function testCertificatoHttps(): void
    {
        $a = self::ADESSO;
        $this->assertSame('verde', SaluteSito::esitoCertificato($a + 45 * 86400, $a)[0]);
        $this->assertSame('giallo', SaluteSito::esitoCertificato($a + 20 * 86400, $a)[0], 'avviso sotto i 30 giorni');
        $this->assertSame('rosso', SaluteSito::esitoCertificato($a + 3 * 86400, $a)[0]);
        [$sem, $testo] = SaluteSito::esitoCertificato($a - 86400, $a);
        $this->assertSame('rosso', $sem);
        $this->assertStringContainsString('già scaduto', $testo);
        $this->assertSame('grigio', SaluteSito::esitoCertificato(null, $a)[0], 'non raggiungibile: grigio, mai rosso');
    }

    public function testEmail(): void
    {
        $a = self::ADESSO;
        $this->assertSame('verde', SaluteSito::esitoEmail(['ultima_ok' => $a - 60, 'errori24' => 0, 'tot24' => 30], $a)[0]);
        $this->assertSame('giallo', SaluteSito::esitoEmail(['ultima_ok' => $a - 60, 'errori24' => 2, 'tot24' => 30], $a)[0]);
        $this->assertSame('rosso', SaluteSito::esitoEmail(['ultima_ok' => $a - 60, 'errori24' => 12, 'tot24' => 30], $a)[0]);
        $this->assertSame('rosso', SaluteSito::esitoEmail(['ultima_ok' => null, 'errori24' => 5, 'tot24' => 5], $a)[0]);
        $this->assertSame('grigio', SaluteSito::esitoEmail(['ultima_ok' => null, 'errori24' => 0, 'tot24' => 0], $a)[0], 'registro vuoto');
        $this->assertStringContainsString('2026', SaluteSito::esitoEmail(['ultima_ok' => date('Y-m-d H:i:s', $a - 60), 'errori24' => 0, 'tot24' => 1], $a)[1], 'la data può arrivare come testo del database');
    }

    public function testCopiaDiProvaELocaleNonDannoMaiRossoSuiProcessi(): void
    {
        $d = $this->dati('prova');
        $d['cron'] = ['cron_background' => null, 'cron_attestati' => null, 'cron_reminders' => null, 'cron_backup' => null];
        $d['backup'] = ['ultimo_ok' => null, 'ultimo_giro_ok' => null];
        foreach (['prova', 'locale'] as $modo) {
            $d['modo'] = $modo;
            $r = SaluteSito::valuta($d, self::ADESSO);
            foreach (['cron_background', 'cron_attestati', 'cron_reminders', 'cron_backup', 'backup'] as $chiave) {
                $riga = array_column($r, null, 'chiave')[$chiave];
                $this->assertSame('grigio', $riga['semaforo'], "$modo / $chiave");
            }
            $this->assertStringContainsString($modo === 'prova' ? 'copia di prova' : 'ambiente locale', array_column($r, 'dettaglio', 'chiave')['cron_background']);
            $this->assertSame('verde', array_column($r, 'semaforo', 'chiave')['spazio_portale'], 'lo spazio si controlla comunque');
        }
        // gli stessi dati nel sito vero sarebbero rossi
        $d['modo'] = 'normale';
        $s = $this->semafori($d);
        $this->assertSame(['rosso', 'grigio', 'rosso', 'rosso', 'rosso'], [$s['cron_background'], $s['cron_attestati'], $s['cron_reminders'], $s['cron_backup'], $s['backup']]);
    }

    public function testAnnotazioneDeiCron(): void
    {
        $cron = new StatoCron(new Sito($this->cartella, 'https://esempio.test', true));
        $this->assertNull($cron->ultima('cron_background'), 'mai girato');
        $cron->registra('cron_background', 1700000000);
        $cron->registra('cron_reminders', 1700000500);
        $this->assertSame(1700000000, $cron->ultima('cron_background'));
        $this->assertSame(1700000500, $cron->ultima('cron_reminders'));
        $this->assertNull($cron->ultima('cron_attestati'));
        // un processo già in funzione prima di questa annotazione: vale la data del suo file di blocco
        touch($this->cartella . '/cache/cron_attestati.lock', 1700001000);
        $this->assertSame(1700001000, $cron->ultima('cron_attestati'));
        is_file($this->cartella . '/cache/cron_attestati.lock') && unlink($this->cartella . '/cache/cron_attestati.lock');
    }

    public function testVersioneCaricata(): void
    {
        $this->assertNull(SaluteSito::versioneCaricata($this->cartella), 'copia locale: il file non c\'è');
        file_put_contents($this->cartella . '/inc/versione_portale.txt', "commit=d80ede2\ndata=21/11/2026 22:10\nramo=main\n");
        $this->assertSame(['commit' => 'd80ede2', 'data' => '21/11/2026 22:10', 'ramo' => 'main'], SaluteSito::versioneCaricata($this->cartella));
        file_put_contents($this->cartella . '/inc/versione_portale.txt', "rotto\n");
        $this->assertNull(SaluteSito::versioneCaricata($this->cartella));
    }

    public function testCertificatoDiUnSitoSenzaHttpsNonSiCerca(): void
    {
        $this->assertNull(SaluteSito::leggiCertificato('http://127.0.0.1:8080'));
        $this->assertNull(SaluteSito::leggiCertificato('non un indirizzo'));
    }
}
