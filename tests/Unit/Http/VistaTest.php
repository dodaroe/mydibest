<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\Vista;
use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Le viste (App\Http\Vista). Due parti:
 *  1. il meccanismo, su una radice finta con modelli e testate finte (nessun database, nessuna sessione);
 *  2. le regole della CSP (passo S17) sui modelli VERI di viste/: nessun gestore in linea, ogni <script> in linea col nonce, nessuna
 *     conferma diversa da data-conferma, nessun escape a mano. La prova guarda sia il sorgente dei modelli sia l'HTML che rendono.
 */
final class VistaTest extends TestCase
{
    private const LAYOUT = [
        'sito' => ['header.php', 'footer.php'],
        'pannello' => ['admin/admin_header.php', 'admin/admin_footer.php'],
    ];

    private string $radice = '';
    private ?string $radicePrima = null;
    private array $layoutPrima = [];

    protected function setUp(): void
    {
        $this->radice = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vista_' . bin2hex(random_bytes(4));
        foreach (['viste/prova', 'admin'] as $d) {
            mkdir($this->radice . '/' . $d, 0777, true);
        }
        $guardia = "<?php if (!(\$d ?? null) instanceof \\App\\Http\\DatiVista) { exit; } ?>\n";
        $this->scrivi('viste/prova/ciao.php', $guardia . '<p><?php echo $d->nome; ?></p><i><?php echo $d->grezzo(\'ok\'); ?></i><?php echo $d->parziale(\'prova/_parte\', [\'x\' => $d->grezzo(\'nome\')]); ?>');
        $this->scrivi('viste/prova/_parte.php', $guardia . '[<?php echo $d->x; ?>]');
        $this->scrivi('viste/prova/doppio.php', $guardia . '<?php echo $d->parziale(\'prova/_parte\', [\'x\' => $d->nome]); ?>');
        $this->scrivi('viste/prova/scopo.php', $guardia . '<?php echo isset($segreto) ? \'vede\' : \'non vede\'; ?>');
        $this->scrivi('viste/prova/rotta.php', $guardia . "prima della rottura<?php throw new \\RuntimeException('vista rotta'); ?>");
        $this->scrivi('header.php', '<?php echo "<html><title>" . htmlspecialchars($page_cfg[\'titolo\'] ?? \'\') . "</title><meta d=\"" . htmlspecialchars($meta_descrizione ?? \'predefinita\') . "\"><body class=\"" . htmlspecialchars($page_cfg[\'classe\'] ?? \'\') . "\">"; ?>');
        $this->scrivi('footer.php', '</body></html>');
        $this->scrivi('admin/admin_header.php', '<?php echo "<div id=\"pannello\">"; ?>');
        $this->scrivi('admin/admin_footer.php', '</div>');
        $this->radicePrima = Vista::usaRadice($this->radice);
        $this->layoutPrima = Vista::layout(self::LAYOUT);
    }

    protected function tearDown(): void
    {
        Vista::usaRadice($this->radicePrima);
        if ($this->layoutPrima !== []) {
            Vista::layout($this->layoutPrima);
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->radice, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->radice);
    }

    private function scrivi(string $percorso, string $contenuto): void
    {
        file_put_contents($this->radice . '/' . $percorso, $contenuto);
    }

    // ── 1. Il meccanismo ───────────────────────────────────────────────────────

    public function testRendiRestituisceUnaStringaConLEscapeEGrezzoNo(): void
    {
        $html = Vista::rendi('prova/ciao', ['nome' => '<script>x</script>', 'ok' => '<b>sicuro</b>']);

        self::assertStringContainsString('<p>&lt;script&gt;x&lt;/script&gt;</p>', $html);
        self::assertStringContainsString('<i><b>sicuro</b></i>', $html, 'grezzo non si tocca');
        self::assertStringContainsString('[&lt;script&gt;x&lt;/script&gt;]', $html, 'il parziale riceve il valore grezzo e lo escapa lui, una volta');
    }

    /** Il tranello da conoscere: passare `$d->nome` (già escapato) a un parziale lo escapa due volte. Si passa `$d->grezzo('nome')`, o il record. */
    public function testPassareUnValoreGiaEscapatoAUnParzialeLoEscapaDueVolte(): void
    {
        self::assertSame('[&amp;lt;b&amp;gt;]', Vista::rendi('prova/doppio', ['nome' => '<b>']));
    }

    public function testIlModelloVedeSoloD(): void
    {
        $segreto = 'x';
        self::assertSame('non vede', Vista::rendi('prova/scopo', ['a' => 1]), 'nessuna variabile della pagina entra nel modello' . $segreto);
    }

    /** L'unico nome senza «__» che un modello vede è `$d`: `$livello` (il buffer) non c'è più, si chiama `$__livello`. */
    public function testIlModelloVedeSoloDEINomiConDoppioTrattino(): void
    {
        $this->scrivi('viste/prova/nomi.php', "<?php if (!(\$d ?? null) instanceof \\App\\Http\\DatiVista) { exit; } ?>" . '<?php echo implode(\',\', array_keys(get_defined_vars())); ?>');

        $nomi = explode(',', Vista::rendi('prova/nomi'));
        $senzaTrattino = array_values(array_filter($nomi, static fn (string $n): bool => !str_starts_with($n, '__')));

        self::assertSame(['d'], $senzaTrattino, 'nomi visibili al modello: ' . implode(', ', $nomi));
    }

    /**
     * La testata del pannello è uno script: `pn_briciole_html()` (chiamata dal piè di pagina) rilegge `$briciole_base` da $GLOBALS. Inclusa da un
     * metodo, la variabile resterebbe nell'ambito del metodo: Vista la ripubblica, e SOLO lei (elenco scritto a mano, DALLA_TESTATA_AI_GLOBALI).
     * Tutto il resto che la testata definisce NON arriva nei globali: nessuna lista nera da tenere aggiornata.
     */
    public function testDellaTestataDelPannelloArrivanoNeiGlobaliSoloLeBriciole(): void
    {
        $this->scrivi('admin/admin_header.php', '<?php $puo_prova_vista = true; $briciole_base = [[\'a\', \'b\']]; $permessi = \'x\'; echo "<div>"; ?>');
        $this->scrivi('admin/admin_footer.php', '<?php $GLOBALS[\'nel_footer\'] = $GLOBALS[\'briciole_base\'] ?? \'non vede\'; echo "</div>"; ?>');
        $nomi = ['puo_prova_vista', 'briciole_base', 'permessi', 'nel_footer', 'page_cfg', 'layout', 'corpo', 'titolo', 'apre', 'chiude', 'radice', 'livello'];
        $prima = [];
        foreach ($nomi as $n) {
            $prima[$n] = array_key_exists($n, $GLOBALS) ? [$GLOBALS[$n]] : null;
            unset($GLOBALS[$n]);
        }
        try {
            Vista::pannello('T', '<p>corpo</p>');

            self::assertSame([['a', 'b']], $GLOBALS['briciole_base'] ?? null, 'le briciole sono nei globali');
            self::assertSame([['a', 'b']], $GLOBALS['nel_footer'] ?? null, 'e già il piè di pagina le vede: viene dopo la testata');
            foreach (['puo_prova_vista', 'permessi', 'page_cfg', 'corpo', 'layout', 'titolo', 'apre', 'chiude', 'radice', 'livello'] as $mai) {
                self::assertArrayNotHasKey($mai, $GLOBALS, "«{$mai}» non si esporta: non è nell'elenco di chi serve a qualcuno");
            }
        } finally {
            foreach ($nomi as $n) {
                unset($GLOBALS[$n]);
                if ($prima[$n] !== null) {
                    $GLOBALS[$n] = $prima[$n][0];
                }
            }
        }
    }

    /** L'elenco delle variabili esportate è corto e ogni nome ha scritto chi lo legge: se cresce, qui qualcuno se ne accorge. */
    public function testElencoDelleVariabiliEsportateEScrittoAMano(): void
    {
        $sorgente = (string) file_get_contents(RADICE_SITO . '/src/Http/Vista.php');

        self::assertMatchesRegularExpression('/private const DALLA_TESTATA_AI_GLOBALI = \[\'briciole_base\'\];/', $sorgente, 'un nome nuovo entra con il suo lettore e va aggiornato anche questa prova');
        self::assertStringContainsString('pn_briciole_html()', $sorgente, 'accanto al nome c\'è scritto chi lo legge');
        self::assertDoesNotMatchRegularExpression('/in_array\(\$nome, \[\'layout\'/', $sorgente, 'niente lista nera: si esporta solo ciò che è nell\'elenco');
    }

    public function testIlLayoutDelSitoNonEsportaNiente(): void
    {
        $this->scrivi('header.php', '<?php $variabile_del_sito_prova = 1; echo "<html>"; ?>');
        unset($GLOBALS['variabile_del_sito_prova']);

        Vista::pagina('T', '<p>x</p>');

        self::assertArrayNotHasKey('variabile_del_sito_prova', $GLOBALS, 'solo il pannello (che è scritto come script) rimette le variabili nei globali');
    }

    public function testNonStampaNienteDaSolo(): void
    {
        $this->expectOutputString('');
        Vista::rendi('prova/ciao', ['nome' => 'a', 'ok' => 'b']);
        Vista::pagina('T', '<p>corpo</p>');
        Vista::pannello('T', '<p>corpo</p>');
    }

    public function testUnaVistaRottaNonLasciaMezzaPaginaNelBuffer(): void
    {
        $livello = ob_get_level();
        try {
            Vista::rendi('prova/rotta');
            self::fail('l\'eccezione deve arrivare a chi chiama');
        } catch (RuntimeException $e) {
            self::assertSame('vista rotta', $e->getMessage());
        }
        self::assertSame($livello, ob_get_level(), 'il buffer della vista è stato chiuso');
    }

    public function testIlNomeDelModelloEControllato(): void
    {
        foreach (['../config', 'prova/../ciao', 'Prova/ciao', 'prova/ciao.php', '/prova/ciao', 'prova//ciao', '', 'prova/ciao ', "prova/ciao\0"] as $nome) {
            try {
                Vista::rendi($nome);
                self::fail("accettato il nome «$nome»");
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }
    }

    public function testUnModelloCheNonEsisteEUnErrore(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('viste/prova/manca.php');
        Vista::rendi('prova/manca');
    }

    public function testPaginaMetteIlCorpoFraTestataEPiede(): void
    {
        $html = Vista::pagina('Contatti & co', '<main>corpo</main>');

        self::assertSame('<html><title>Contatti &amp; co</title><meta d="predefinita"><body class=""><main>corpo</main></body></html>', $html);
    }

    public function testPaginaPassaLaDescrizioneEIValoriDiPageCfg(): void
    {
        $html = Vista::pagina('T', '<p>x</p>', ['descrizione' => 'Una "descrizione"', 'page_cfg' => ['classe' => 'verde', 'titolo' => 'ignorato: vince quello della chiamata']]);

        self::assertStringContainsString('<title>T</title>', $html);
        self::assertStringContainsString('<meta d="Una &quot;descrizione&quot;">', $html);
        self::assertStringContainsString('<body class="verde">', $html);
    }

    public function testIlCorpoDellaPaginaNonVieneEscapatoDaPagina(): void
    {
        self::assertStringContainsString('<b>x</b>', Vista::pagina('T', '<b>x</b>'), 'il corpo è già il risultato di un modello: ha avuto il suo escape lì');
    }

    public function testPannelloUsaIlLayoutDelPannello(): void
    {
        self::assertSame('<div id="pannello"><p>corpo</p></div>', Vista::pannello('T', '<p>corpo</p>'));
    }

    public function testPaginaConUnaTestataCheSollevaPulisceIlBuffer(): void
    {
        $this->scrivi('header.php', '<?php echo "mezza testata"; throw new \\RuntimeException("testata rotta"); ?>');
        $livello = ob_get_level();
        try {
            Vista::pagina('T', 'x');
            self::fail('deve arrivare l\'eccezione');
        } catch (RuntimeException) {
            self::assertSame($livello, ob_get_level());
        }
    }

    // ── 2. Le regole della CSP sui modelli veri ────────────────────────────────

    /**
     * I problemi di un pezzo di HTML o di un modello.
     *
     * @param bool $sorgente true = il testo di un modello (il nonce è scritto `nonce="<?php echo csp_nonce(); ?>"`);
     *                       false = l'HTML già reso (il nonce è un valore vero, non vuoto)
     * @return list<string>
     */
    public static function problemiCsp(string $testo, bool $sorgente): array
    {
        $problemi = [];
        // <script> in linea (senza src e che non sia un blocco di dati) senza nonce
        if ($sorgente) {
            $senzaNonce = '~<script\b(?!\s+nonce="<\?php echo csp_nonce\(\); \?>")(?![^>]*\bsrc=)(?![^>]*type="application/(?:ld\+)?json")~i';
        } else {
            $senzaNonce = '~<script\b(?![^>]*\bnonce="[^"]+")(?![^>]*\bsrc=)(?![^>]*type="application/(?:ld\+)?json")~i';
        }
        if (preg_match_all($senzaNonce, $testo, $m) > 0) {
            $problemi[] = count($m[0]) . ' <script> in linea senza nonce' . ($sorgente ? ' (la forma è nonce="<?php echo csp_nonce(); ?>", subito dopo <script)' : '');
        }
        // gestori in linea: onclick=, onchange=, onsubmit=…
        $gestori = $sorgente ? '~\son[a-z]{3,}\s*=~i' : '~<[a-z][^>]*\son[a-z]{3,}\s*=~i';
        if (preg_match($gestori, $testo, $m) === 1) {
            $problemi[] = 'gestore in linea (onclick, onchange, onsubmit…): usa i data-on-* di assets/js/azioni.js — ' . trim($m[0]);
        }
        // conferme: solo data-conferma / data-conferma-scrivi, mai confirm() né il vecchio data-confirm
        if (preg_match('~\bconfirm\s*\(|\bdata-confirm(?:-level)?\s*=~i', $testo) === 1) {
            $problemi[] = 'conferma diversa da data-conferma / data-conferma-scrivi (assets/js/conferma.js)';
        }

        return $problemi;
    }

    public function testIlControlloCspVedeIProblemi(): void
    {
        self::assertSame([], self::problemiCsp('<script nonce="<?php echo csp_nonce(); ?>">a()</script><script src="x.js"></script><script type="application/json">{}</script>', true));
        self::assertSame([], self::problemiCsp('<script nonce="AbC123=">a()</script><a data-on-click="f" data-conferma="Sicuro?">x</a> <a href="?online=1">y</a>', false), 'nessun falso allarme su data-on-click né su ?online=1');
        self::assertCount(1, self::problemiCsp('<script>a()</script>', true));
        self::assertCount(1, self::problemiCsp('<script>a()</script>', false));
        self::assertCount(1, self::problemiCsp('<script nonce="">a()</script>', false), 'un nonce vuoto non vale');
        self::assertCount(1, self::problemiCsp('<script type="text/javascript" nonce="<?php echo csp_nonce(); ?>">a()</script>', true), 'una sola forma ammessa, la più usata');
        self::assertNotEmpty(self::problemiCsp('<button onclick="f()">x</button>', false));
        self::assertNotEmpty(self::problemiCsp('<form method="post" onsubmit="return f()">', false));
        self::assertNotEmpty(self::problemiCsp('<button class="b" onchange = "f()">', true));
        self::assertNotEmpty(self::problemiCsp('<button data-confirm="Sicuro?">x</button>', false));
        self::assertNotEmpty(self::problemiCsp('<button onclick="if (confirm(\'Sicuro?\')) go()">x</button>', false));
    }

    /** @return list<string> percorsi relativi a viste/ di tutti i modelli (compresi i parziali) */
    private static function modelli(): array
    {
        $out = [];
        $base = RADICE_SITO . '/viste';
        if (!is_dir($base)) {
            return $out;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[] = substr(str_replace('\\', '/', $f->getPathname()), strlen($base) + 1, -4);
            }
        }
        sort($out);

        return $out;
    }

    public function testCiSonoDeiModelliVeri(): void
    {
        self::assertContains('pubblico/contatti', self::modelli());
        self::assertContains('pannello/contatti', self::modelli());
        self::assertContains('stampe/ricevuta', self::modelli());
        self::assertFileExists(RADICE_SITO . '/viste/.htaccess', 'la cartella dei modelli non si apre dal web');
    }

    public function testIlSorgenteDiOgniModelloRispettaLeRegole(): void
    {
        foreach (self::modelli() as $nome) {
            $src = (string) file_get_contents(RADICE_SITO . '/viste/' . $nome . '.php');

            self::assertSame([], self::problemiCsp($src, true), "viste/$nome.php");
            self::assertStringStartsWith('<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; }', $src, "viste/$nome.php: manca la guardia in cima (aperto da solo non deve fare niente)");
            self::assertStringNotContainsString('htmlspecialchars(', $src, "viste/$nome.php: l'escape lo fa \$d, non a mano (se serve scavalcarlo: \$d->grezzo())");
            self::assertDoesNotMatchRegularExpression('/\$h\s*=\s*fn/', $src, "viste/$nome.php: niente lambda \$h");
        }
    }

    /**
     * Dati di prova per ogni modello che si rende da solo (i parziali, che cominciano con «_», si rendono dal modello che li usa).
     *
     * @return array<string, list<array<string, mixed>>> modello => varianti di dati
     */
    private static function datiDiProva(): array
    {
        $cattivo = '<script>alert(1)</script> & "q" \'s\'';
        $contatto = ['id' => 7, 'titolo' => $cattivo, 'per_cosa' => 'Certificati', 'luogo' => $cattivo, 'orari' => 'lun 10-12', 'telefono' => '0984 49 0000', 'email' => 'u@example.org',
            'link_etichetta' => 'Prenota', 'link_url' => 'https://example.org/?a=1&b=2', 'ordine' => 1, 'visibile' => 1];
        $pubblico = $contatto + ['esterno' => true, 'telefono_href' => '098449000', 'link_testo' => 'Prenota'];
        $nuovo = ['id' => 0, 'titolo' => '', 'per_cosa' => '', 'luogo' => '', 'orari' => '', 'email' => '', 'telefono' => '', 'link_etichetta' => '', 'link_url' => '', 'ordine' => 2, 'visibile' => 0];
        $pannello = static fn (int $modifica, array $contatti): array => ['filtro' => 3, 'campoCsrf' => '<input type="hidden" name="csrf_token" value="tok">', 'modifica' => $modifica, 'nuovo' => $nuovo, 'contatti' => $contatti];
        $recapiti = static fn (array $c): array => $c + ['recapiti' => array_values(array_filter([$c['luogo'], $c['orari'], $c['telefono'], $c['email']], static fn ($x): bool => $x !== ''))];
        $ricevuta = ['codice_prenotazione' => 'OP-ABC123', 'stato' => 'in_attesa', 'nome_portale' => 'MyDiBEST', 'sottotitolo_portale' => 'Dipartimento', 'logo_path' => 'assets/logo.png',
            'pagina_titolo' => $cattivo, 'evento_titolo' => 'Laboratorio', 'nome_turno' => 'Gruppo 1', 'data_turno' => '2026-11-22', 'data_it' => '22/11/2026', 'orario' => '09:30–11:00',
            'evento_luogo' => 'Cubo 15B', 'nome' => 'Mario', 'cognome' => 'Rossi', 'email' => 'm@example.org', 'matricola_mostrata' => 'N/D', 'num_posti' => 2];

        // «Scuole e convenzioni» (Passo 8): una riga di elenco e una scheda con tutti i riquadri pieni, e una scuola scritta a mano con i riquadri vuoti
        $rigaScuola = ['nome' => $cattivo, 'comune' => $cattivo, 'codice' => 'CSIS00001A', 'aMano' => false, 'href' => 'fsl_scuole.php?k=CSIS00001A&anno=2026', 'statoHtml' => '<span class="pn-stato">Attiva</span>',
            'data' => 'fino al 31/08/2027', 'passo' => 'Dal Direttore per la firma', 'eventi' => 3, 'studenti' => 40, 'docenti' => 2, 'docentiNomi' => [$cattivo, 'Anna Verdi'], 'docentiAltri' => 2, 'cosa' => $cattivo, 'daFare' => true];
        $elencoScuole = static fn (array $righe, bool $puoFsl, bool $filtrato): array => ['campoCsrf' => '<input type="hidden" name="csrf_token" value="tok">', 'puoFsl' => $puoFsl, 'righe' => $righe,
            'tessere' => [['chiave' => 'da_controllare', 'nome' => 'Da controllare', 'numero' => 2, 'attiva' => true, 'href' => 'fsl_scuole.php?cosa=da_controllare'], ['chiave' => 'messaggi', 'nome' => 'Messaggi', 'numero' => 0, 'attiva' => false, 'href' => 'fsl_scuole.php']],
            'anni' => [['valore' => 'tutti', 'testo' => 'Tutti gli anni', 'scelto' => false], ['valore' => '2026', 'testo' => 'A.s. 2026/27', 'scelto' => true]],
            'stati' => [['valore' => '', 'testo' => 'Tutti gli stati', 'scelto' => true], ['valore' => 'attiva', 'testo' => 'Attiva', 'scelto' => false]],
            'filtroTesto' => $cattivo, 'annoValore' => '2026', 'totale' => count($righe), 'totaleScuole' => 9, 'filtrato' => $filtrato, 'pag' => 1, 'pagine' => 2, 'hrefPrec' => '', 'hrefSucc' => 'fsl_scuole.php?pag=2',
            'hrefScarica' => 'fsl_scuole.php?anno=2026', 'hrefTutti' => 'fsl_scuole.php?anno=2026', 'aMano' => 1, 'apriScuola' => $filtrato];
        $evento = ['id' => 11, 'codice' => 'FS-1', 'titolo' => $cattivo, 'evento_id' => 20, 'pagina_id' => 2, 'area' => $cattivo, 'turno' => 'Edizione 1', 'dal' => '2026-10-13', 'al' => '2026-12-02', 'anno' => 2026, 'stato' => 'confermata',
            'stato_testo' => 'Confermata', 'attiva' => true, 'conclusa' => true, 'sede_nome' => $cattivo, 'sede_comune' => 'Rende', 'sede_diversa' => true, 'docente' => $cattivo, 'email' => 'd@example.org', 'telefono' => '0984 12 34',
            'fsl' => true, 'solo_orientamento' => false, 'conv' => 'non_coperta', 'conv_id' => 0, 'perche' => $cattivo, 'studenti' => 12, 'n_elenco' => 0, 'classe' => true, 'puo_iscritti' => true, 'puo_studenti' => true,
            'ricorda_elenco' => true, 'autorizzazione' => true, 'attestati' => 'da_inviare', 'val_id' => 0, 'val_media' => '', 'scuola_scritta' => '', 'in_stipula' => false, 'elenco_manca' => false, 'autorizzazione_manca' => false];
        $fileConv = [['nome' => 'Convenzione firmata dalla scuola', 'quale' => 'scuola', 'data' => '2026-10-01'], ['nome' => 'Documento protocollato', 'quale' => 'prot', 'data' => '']];
        $iter = ['id' => 5, 'passo' => 'PDF firmato caricato', 'cosa' => 'Da prendere in carico', 'tag' => 'da_controllare', 'email' => 'ref@example.org', 'stato_iter' => 'caricata_scuola', 'compilata' => true, 'in_corso' => true, 'unita_in' => 0,
            'attivita' => 2, 'in_attesa' => 1, 'da_confermare' => [['pr' => 11, 'tutor' => $cattivo]], 'avviata_il' => '2026-10-01 10:00:00', 'avviata_da' => $cattivo, 'aggiornata_il' => '', 'protocollo_ingresso' => '', 'protocollo' => '',
            'convenzione_id' => 1, 'nota_ufficio' => '', 'files' => $fileConv, 'messaggi' => [], 'non_letti' => 1, 'archiviata' => false, 'docenti_json' => [],
            'fase_titulus' => 'ingresso', 'titulus' => ['stato' => 'libero', 'numero' => '', 'il' => ''], 'titulus_pronto' => true, 'valida_dal' => '2026-09-01', 'valida_al' => '2027-08-31',
            'docenti_iter' => [['nome' => $cattivo, 'email' => 'd@example.org'], ['nome' => 'Senza email', 'email' => '']], 'scuola_nome' => $cattivo, 'dirigente' => $cattivo, 'firmatario' => $cattivo, 'firmatario_direttore' => $cattivo,
            'da_firmare_il' => '', 'avviso_firma' => $cattivo, 'avviso_firma_ingresso' => '', 'protocollo_errore' => $cattivo, 'ha_protocollato' => false, 'dopo_firma' => false,
            'passi_html' => '<ol class="ui-percorso"><li>Uno</li></ol>', 'firmata_il' => '', 'firmata_direttore_il' => ''];
        $scheda = ['chiave' => 'CSIS00001A', 'codice' => 'CSIS00001A', 'a_mano' => false, 'in_anagrafe' => true, 'nome' => $cattivo,
            'anagrafe' => ['denominazione' => $cattivo, 'comune' => $cattivo, 'provincia' => 'Cs', 'tipo' => 'Liceo', 'indirizzo' => $cattivo, 'pec' => 'p@pec.example.org', 'email' => 'e@example.org', 'telefono' => '0984 49 00'],
            'sedi' => [['codice' => 'CSIS00001A', 'nome' => $cattivo, 'principale' => true], ['codice' => 'CSPS00001A', 'nome' => 'Sede', 'principale' => false]],
            'iter_aperti' => [$iter], 'registrate' => [['id' => 7, 'protocollo' => '0003', 'protocollo_data' => '2026-10-02', 'protocollo_ingresso' => '0002', 'protocollo_ingresso_data' => '2026-10-01', 'convenzione_id' => 1, 'scuola_nome' => $cattivo,
                'files' => $fileConv, 'originale_stato' => 'impronta', 'originale_avviso' => $cattivo, 'originale_tentativi' => 2,
                'resoconto' => ['coperte' => 2, 'approvate' => 1, 'senza_codice' => 1, 'errore' => $cattivo, 'restano' => [['codice' => 'FS-1', 'motivo' => $cattivo]]]]], 'direttore' => ['nome' => $cattivo, 'email' => 'dir@example.org'], 'cose' => [$cattivo, 'Da confermare'], 'cose_altre' => [$cattivo, 'Messaggi da leggere'], 'tags' => ['scaduta_nessuna_attivita'], 'attesa' => '', 'scrivi' => ['modo' => 'compilazione', 'predefinita' => 5, 'destinatari' => [['id' => 5, 'email' => $cattivo, 'passo' => $cattivo, 'in_corso' => true], ['id' => 4, 'email' => 'x@example.org', 'passo' => 'convenzione registrata', 'in_corso' => false]]],
            'periodo_nuovo' => ['dal' => '2026-09-01', 'al' => '2027-08-31'], 'contatti_indicati' => ['pec' => 'ind@pec.example.org', 'email' => '', 'telefono' => '0984 11 22'],
            'stato' => 'in_scadenza', 'stato_testo' => 'In scadenza', 'scadenza' => '2026-11-30', 'validita' => 'dal 01/09/2025 al 30/11/2026', 'azione' => ['chiave' => 'iter', 'testo' => $cattivo, 'ancora' => 'cv5', 'compilazione' => 5],
            'registro' => [['id' => 1, 'data_stipula' => '2025-09-01', 'scadenza' => '2026-11-30', 'validita' => 'dal 01/09/2025 al 30/11/2026', 'stato' => 'in_scadenza', 'protocollo' => '0001', 'registrata_da' => $cattivo, 'note' => $cattivo, 'file_convenzione' => true, 'file_allegato' => true, 'data_allegato' => '2026-09-20',
                'documenti' => [['voce' => 'Originale', 'url' => 'convenzione_file.php?c=7&f=dir', 'data' => '2026-10-01', 'origine' => $cattivo], ['voce' => 'Documento protocollato', 'url' => 'convenzione_file.php?id=1&f=conv', 'data' => '', 'origine' => 'copia del portale']],
                'docenti' => [['nome' => $cattivo, 'email' => 'd@example.org']], 'integrazioni' => [['id' => 3, 'stato' => 'da_vedere', 'caricato_il' => '2026-10-01 10:00:00', 'caricato_da' => $cattivo, 'nota' => $cattivo, 'attivita' => 2]]]],
            'iter' => $iter, 'aperte' => [['id' => 5, 'email' => 'ref@example.org', 'passo' => 'x'], ['id' => 6, 'email' => $cattivo, 'passo' => 'y']],
            'storico' => [['id' => 4, 'stato_iter' => 'unita', 'passo' => 'Unita', 'protocollo' => '0002', 'unita_in' => 5, 'files' => $fileConv]],
            'eventi' => [$evento, ['conv' => 'coperta', 'conv_id' => 1, 'perche' => '', 'ricorda_elenco' => false, 'n_elenco' => 12, 'autorizzazione' => false, 'attiva' => false, 'sede_nome' => '', 'scuola_scritta' => $cattivo, 'val_id' => 9, 'val_media' => '4.50', 'in_stipula' => true, 'elenco_manca' => true, 'autorizzazione_manca' => true] + $evento,
                ['conv' => 'non_richiesta', 'fsl' => false, 'classe' => false, 'studenti' => null, 'solo_orientamento' => true, 'puo_iscritti' => false] + $evento],
            'n_eventi_tutti' => 4, 'anni' => [2026, 2025], 'anno' => 2026, 'n_scoperte' => 1, 'n_fsl_in_essere' => 2,
            'docenti' => [['nome' => $cattivo, 'email' => 'd@example.org', 'telefono' => '0984 12 34', 'eventi' => 2, 'in_allegato' => true, 'proposto' => false, 'ultimo_contatto' => '2026-10-01 10:00:00', 'fonte' => 'indicato dalla scuola', 'modificabile' => true, 'senza_email' => false],
                ['nome' => 'Senza email', 'email' => '', 'telefono' => '', 'eventi' => 1, 'in_allegato' => false, 'proposto' => true, 'ultimo_contatto' => '', 'fonte' => 'Allegato A', 'modificabile' => false, 'senza_email' => true]],
            'messaggi' => [['id' => 1, 'da' => 'scuola', 'tipo' => 'messaggio', 'testo' => $cattivo, 'autore' => '', 'creato_il' => '2026-10-02 09:00:00', 'non_letto' => true, 'compilazione' => 5],
                ['id' => 2, 'da' => 'ufficio', 'tipo' => 'messaggio', 'testo' => "riga 1\nriga 2", 'autore' => $cattivo, 'creato_il' => '2026-10-03 09:00:00', 'non_letto' => false, 'compilazione' => 4]],
            'compilazioni_con_messaggi' => [5, 4], 'valutazioni' => [['conv' => 'coperta', 'val_id' => 9, 'val_media' => '4.50'] + $evento], 'con_numeri' => true];
        $comuneScheda = ['campoCsrf' => '<input type="hidden" name="csrf_token" value="tok">', 'puoFsl' => true, 'chiaveUrl' => 'CSIS00001A', 'annoValore' => '2026', 'hrefElenco' => 'fsl_scuole.php?anno=2026', 'hrefPdf' => 'fsl_scuole.php?k=CSIS00001A&pdf=1',
            'statoHtml' => '<span class="pn-stato">In scadenza</span>', 'docentiConEmail' => [['nome' => $cattivo, 'email' => 'd@example.org']], 'erroreAvvio' => '', 'anteprima' => null,
            'anni' => [['valore' => 'tutti', 'testo' => 'Tutti gli anni', 'scelto' => false], ['valore' => '2026', 'testo' => 'A.s. 2026/27', 'scelto' => true]], 'oggi' => '2026-10-05', 'iterId' => 5, 'periodoNuovo' => ['dal' => '2026-09-01', 'al' => '2027-08-31'], 'apri' => ['modifica' => 0, 'nuova' => false, 'rinnova' => 0]];
        $schedaMano = ['chiave' => 'x0123456789ab', 'codice' => '', 'a_mano' => true, 'in_anagrafe' => false, 'anagrafe' => null, 'sedi' => [], 'stato' => 'manca', 'stato_testo' => 'Manca', 'scadenza' => '', 'validita' => '',
            'azione' => ['chiave' => 'avvia', 'testo' => 'Avvia la stipula', 'ancora' => 'avvio'], 'registro' => [], 'iter' => null, 'aperte' => [], 'storico' => [], 'eventi' => [], 'docenti' => [], 'messaggi' => [], 'compilazioni_con_messaggi' => [], 'valutazioni' => [],
            'n_scoperte' => 0, 'n_fsl_in_essere' => 0, 'con_numeri' => false, 'cose_altre' => [], 'scrivi' => ['modo' => 'docenti', 'predefinita' => 0, 'destinatari' => [['prenotazione' => 11, 'nome' => $cattivo, 'email' => 'd@example.org']]]] + $scheda;
        $anteprima = ['errore' => null, 'azione' => 'creare', 'cosa' => "l'Allegato A", 'email' => $cattivo, 'scuola' => $cattivo, 'referente_attuale' => $cattivo, 'compilazione_id' => 5, 'link_nuovo' => true, 'copia' => ['disponibile' => true, 'permessa' => false, 'dirigente' => $cattivo]];

        return [
            'pannello/sos_aule' => self::datiSosAule($cattivo),
            'pannello/sos_cartello' => [
                ['cartelli' => [['nome' => $cattivo, 'edificio' => $cattivo, 'qr' => '<svg></svg>', 'indirizzo' => $cattivo], ['nome' => '5C2', 'edificio' => '', 'qr' => '<svg></svg>', 'indirizzo' => 'example.org/sos.php?aula=ABCD2345']],
                    'telefono' => $cattivo, 'indietro' => 'sos_aule.php?p_id=1&scheda=aule'],
                ['cartelli' => [], 'telefono' => '', 'indietro' => 'sos_aule.php?p_id=1&scheda=aule'],
            ],
            'pannello/autenticazione_richiesta' => [['css_bootstrap' => 'a.css', 'css_icone' => 'b.css', 'link_accedi' => '../saml_login.php?redirect=admin%2Findex.php'],
                ['css_bootstrap' => 'a.css?x=1&y=2', 'css_icone' => 'b.css', 'link_accedi' => '../saml_login.php?redirect=' . urlencode($cattivo)]],
            'pubblico/contatti' => [['contatti' => []], ['contatti' => [$pubblico, ['link_url' => '', 'esterno' => false] + $pubblico]]],
            'pubblico/approva_pubblicazioni' => (static function () use ($cattivo): array {
                $base = ['avvisoHtml' => '<div class="alert alert-warning">Avviso</div>', 'temaHtml' => '<script nonce="x"></script>'];
                $scheda = ['titolo' => $cattivo, 'richiedente' => $cattivo, 'dataEvento' => '10/12/2026', 'inviata' => '26/11/2026 alle 10:30', 'href' => 'approva_pubblicazioni.php?id=5', 'locandina' => ''];
                $decisa = ['titolo' => $cattivo, 'richiedente' => $cattivo, 'approvata' => 1, 'chi' => $cattivo, 'quando' => '26/11/2026 alle 11:00', 'motivo' => '', 'href' => 'approva_pubblicazioni.php?id=4'];
                $richiesta = $base + ['quale' => 'richiesta', 'id' => 5, 'titolo' => $cattivo, 'richiedente' => $cattivo, 'inviata' => '26/11/2026 alle 10:30', 'attesa' => 1, 'campoCsrf' => '<input type="hidden" name="csrf_token" value="tok">',
                    'righe' => [['etichetta' => 'Titolo', 'valore' => '<strong>Titolo</strong>'], ['etichetta' => 'Locandina', 'valore' => '<a href="allegato_pubblicazione.php?p=5&amp;a=1">locandina.pdf</a>']],
                    'hrefElenco' => 'approva_pubblicazioni.php', 'decisa' => 0, 'decisaApprovata' => 0, 'decisaDaMe' => 0, 'decisaDa' => '', 'decisaIl' => '', 'decisaMotivo' => '', 'statoTesto' => 'Da approvare', 'maxMotivo' => 1000, 'apriRespingi' => 0];

                return [
                    $base + ['quale' => 'elenco', 'da' => [], 'decise' => []],
                    $base + ['quale' => 'elenco', 'da' => [$scheda, ['locandina' => 'allegato_pubblicazione.php?p=5&a=1'] + $scheda], 'decise' => [$decisa, ['approvata' => 0, 'motivo' => $cattivo] + $decisa]],
                    $richiesta,
                    ['apriRespingi' => 1] + $richiesta,
                    ['attesa' => 0, 'decisa' => 1, 'decisaApprovata' => 1, 'decisaDa' => $cattivo, 'decisaIl' => '26/11/2026 alle 11:00', 'decisaMotivo' => $cattivo, 'statoTesto' => 'Ricevuta'] + $richiesta,
                    ['attesa' => 0, 'decisa' => 1, 'decisaApprovata' => 0, 'decisaDa' => 'Maria Rossi', 'decisaIl' => '26/11/2026 alle 11:00'] + $richiesta,
                    ['attesa' => 0, 'decisa' => 1, 'decisaApprovata' => 0, 'decisaDaMe' => 1, 'decisaIl' => '26/11/2026 alle 11:00', 'decisaMotivo' => $cattivo] + $richiesta,
                    ['attesa' => 0, 'statoTesto' => 'Pubblicata'] + $richiesta,
                    $base + ['quale' => 'mancante'],
                ];
            })(),
            'pubblico/sos' => self::datiSos($cattivo),
            'pubblico/sos_interventi' => self::datiSosInterventi($cattivo),
            'pannello/contatti' => [
                $pannello(-1, []),
                $pannello(0, []),
                $pannello(-1, [$recapiti($contatto), $recapiti(['luogo' => '', 'orari' => '', 'telefono' => '', 'email' => '', 'link_url' => '', 'visibile' => 0, 'id' => 8] + $contatto)]),
                $pannello(7, [$recapiti($contatto)]),
            ],
            'pannello/fsl_numeri' => [
                [
                    'anni' => [['valore' => '2026', 'testo' => '2026/2027', 'scelto' => true], ['valore' => '2025', 'testo' => '2025/2026', 'scelto' => false]], 'annoValore' => '2026', 'dal' => '01/09/2026', 'al' => '31/08/2027',
                    'compitiHtml' => '<div class="x">compiti</div>', 'percorsoHtml' => '<div class="x">percorso</div>',
                    'elenchi' => [['scuola' => $cattivo, 'titolo' => $cattivo, 'area' => $cattivo, 'al' => '10/11/2026', 'docente' => $cattivo, 'email' => $cattivo, 'href' => 'iscritti.php?p_id=1&f_cerca=ABC']],
                    'attivita' => [['titolo' => $cattivo, 'area' => $cattivo, 'periodo' => '10/11/2026', 'scuole' => 2, 'confermate' => 2, 'studenti' => 30, 'presenze' => 28, 'senzaConvenzione' => 1, 'valutazioni' => '1/2', 'media' => '4,5']],
                    'scuole' => [
                        ['nome' => $cattivo, 'codice' => 'CSPS00001A', 'href' => 'fsl_scuole.php?k=CSIS00001A', 'attivita' => $cattivo, 'iscrizioni' => 1, 'studenti' => 15, 'presenze' => 14, 'convenzione' => 'dal 01/01/2026', 'daStipulare' => true, 'media' => '4,0'],
                        ['nome' => 'Scuola a mano', 'codice' => '—', 'href' => '', 'attivita' => 'A', 'iscrizioni' => 1, 'studenti' => 0, 'presenze' => 0, 'convenzione' => 'nessuna', 'daStipulare' => false, 'media' => '—'],
                    ],
                    'scaricaAttivita' => '<span>scarica</span>', 'scaricaScuole' => '<span>scarica</span>', 'scaricaValutazioni' => '<span>scarica</span>', 'scaricaReport' => '<span>scarica</span>',
                    'valutazioni' => [['data' => '10/11/2026', 'titolo' => $cattivo, 'scuola' => $cattivo, 'compilata' => $cattivo, 'media' => '4,5', 'riproporrebbe' => 'Sì', 'testi' => [$cattivo, "riga\nsu due"]]],
                    'aspetti' => [['etichetta' => 'Accoglienza', 'media' => '4,5', 'larghezza' => 90]], 'rip' => [['testo' => 'Sì', 'n' => 1], ['testo' => 'Forse', 'n' => 0], ['testo' => 'No', 'n' => 0]],
                    'intestazioniAperte' => ['Cosa è piaciuto', 'Cosa migliorare'], 'reportPercorsoHtml' => '<div class="x">report</div>',
                    'reportScuole' => [['nome' => $cattivo, 'comune' => $cattivo, 'attivita' => 1, 'classi' => 1, 'studenti' => 15, 'presenti' => 14, 'ore' => '28,0', 'oreMedie' => '2,0']],
                ],
                [
                    'anni' => [['valore' => '2026', 'testo' => '2026/2027', 'scelto' => true]], 'annoValore' => '2026', 'dal' => '01/09/2026', 'al' => '31/08/2027', 'compitiHtml' => '', 'percorsoHtml' => '',
                    'elenchi' => [], 'attivita' => [], 'scuole' => [], 'scaricaAttivita' => '', 'scaricaScuole' => '', 'scaricaValutazioni' => '', 'scaricaReport' => '',
                    'valutazioni' => [], 'aspetti' => [], 'rip' => [], 'intestazioniAperte' => [], 'reportPercorsoHtml' => '', 'reportScuole' => [],
                ],
            ],
            'pannello/fsl_scuole_elenco' => [
                $elencoScuole([$rigaScuola, ['aMano' => true, 'studenti' => null, 'daFare' => false, 'passo' => '', 'data' => ''] + $rigaScuola], true, false),
                $elencoScuole([], false, true),
            ],
            'pannello/fsl_scuole_scheda' => [
                ['s' => $scheda] + $comuneScheda,
                ['s' => $schedaMano, 'puoFsl' => false] + $comuneScheda,
                ['s' => $scheda, 'apri' => ['modifica' => 1, 'nuova' => false, 'rinnova' => 0]] + $comuneScheda,
                ['s' => $scheda, 'apri' => ['modifica' => 0, 'nuova' => true, 'rinnova' => 1]] + $comuneScheda,
                ['s' => ['iter' => null, 'azione' => ['chiave' => 'rinnova', 'testo' => 'Rinnova la convenzione', 'ancora' => 'avvio']] + $scheda, 'anteprima' => $anteprima, 'erroreAvvio' => $cattivo] + $comuneScheda,
                ['s' => ['iter' => null, 'azione' => ['chiave' => 'nessuna', 'testo' => 'Niente da fare ora', 'ancora' => ''], 'stato' => 'attiva'] + $scheda, 'anteprima' => ['azione' => 'bloccata'] + $anteprima] + $comuneScheda,
            ],
            'stampe/ricevuta' => [
                ['p' => $ricevuta, 'qr' => '<svg></svg>', 'css_bootstrap' => 'a.css', 'css_icone' => 'b.css', 'col_ric' => '#B80000', 'col_ric_txt' => '#FFFFFF', 'col_ric_testo' => '#B80000', 'senza_prenotazione' => false,
                    'custom' => [['chiave' => 'scuola', 'valore' => $cattivo, 'allegato' => false], ['chiave' => 'documento', 'valore' => 'uploads/x.pdf', 'allegato' => true]]],
                ['p' => ['stato' => 'confermata', 'logo_path' => '', 'nome_turno' => '', 'data_turno' => ''] + $ricevuta, 'qr' => '', 'css_bootstrap' => 'a.css', 'css_icone' => 'b.css',
                    'col_ric' => '#000000', 'col_ric_txt' => '#FFFFFF', 'col_ric_testo' => '#000000', 'senza_prenotazione' => false, 'custom' => []],
            ],
        ];
    }

    /**
     * SOS aule: il modello principale riceve la scheda aperta già resa («corpo»), quindi qui ogni scheda si rende con il suo parziale _sos_*
     * (che a sua volta rende i parziali più piccoli): prima piena, poi con gli elenchi vuoti e gli avvisi che compaiono solo allora.
     * Va chiamata con la radice vera delle viste.
     *
     * @return list<array<string, mixed>>
     */
    private static function datiSosAule(string $cattivo): array
    {
        $csrf = '<input type="hidden" name="csrf_token" value="tok">';
        $comuni = ['campoCsrf' => $csrf, 'filtro' => 1];

        $aula = ['id' => 3, 'nome' => $cattivo, 'edificio' => $cattivo, 'note' => $cattivo, 'codice' => 'ABCD2345', 'attiva' => true, 'referenti' => '1 referente', 'aperte' => 2,
            'cancellabile' => true, 'prima' => true, 'ultima' => false, 'qr' => '<svg></svg>', 'urlCartello' => 'sos_cartello.php?p_id=1&aula=3', 'urlProva' => '../sos.php?aula=ABCD2345'];
        $aule = $comuni + ['aule' => [$aula, ['id' => 4, 'edificio' => '', 'note' => '', 'attiva' => false, 'cancellabile' => false, 'prima' => false, 'ultima' => true] + $aula], 'attive' => 1, 'urlTutti' => 'sos_cartello.php?p_id=1&tutte=1'];

        $persona = ['persona_id' => $cattivo, 'nominativo' => $cattivo, 'email' => $cattivo, 'avvisi' => [$cattivo]];
        $modulo = ['azione' => 'studente_salva', 'id' => 9, 'nome' => $cattivo, 'cognome' => $cattivo, 'email' => $cattivo, 'dal' => '2026-10-01', 'al' => '2027-09-30', 'note' => $cattivo, 'attivo' => 1,
            'errore' => $cattivo, 'conferma' => true, 'aperto' => true];
        $nuovo = ['azione' => 'studente_aggiungi', 'id' => 0, 'nome' => '', 'cognome' => '', 'email' => '', 'dal' => '', 'al' => '', 'note' => '', 'attivo' => 1, 'errore' => '', 'conferma' => false, 'aperto' => false];
        $persone = $comuni + ['scoperte' => [$cattivo], 'conAnagrafe' => true,
            'aule' => [['id' => 3, 'nome' => $cattivo, 'edificio' => $cattivo, 'attiva' => false, 'persone' => [$persona, ['email' => '', 'avvisi' => []] + $persona], 'quanti' => 2, 'ricerca' => '<div class="ricerca"></div>'],
                ['id' => 4, 'nome' => '5C3', 'edificio' => '', 'attiva' => true, 'persone' => [], 'quanti' => 0, 'ricerca' => '']],
            'riserva' => [$persona], 'quantiRiserva' => 1, 'ricercaRiserva' => '<div class="ricerca"></div>',
            'studenti' => [['id' => 9, 'nominativo' => $cattivo, 'email' => $cattivo, 'note' => $cattivo, 'periodo' => 'dal 01/10/2026', 'attivo' => false, 'avvisi' => [$cattivo], 'modulo' => $modulo]],
            'nuovo' => $nuovo];

        $icone = [['valore' => 'fa-video', 'nome' => $cattivo, 'scelta' => true], ['valore' => 'fa-key', 'nome' => 'Chiave', 'scelta' => false]];
        $voce = ['id' => 2, 'nome' => $cattivo, 'aiuto' => $cattivo, 'icona' => 'fa-video', 'icone' => $icone, 'chiedeTesto' => true, 'attivo' => false, 'prima' => true, 'ultima' => true];
        $elenco = ['quale' => 'sos_tipi', 'titolo' => 'Tipi di problema', 'spiegazione' => $cattivo, 'nuova' => ['nome' => '', 'aiuto' => '', 'chiedeTesto' => false, 'icone' => $icone], 'voci' => [$voce]];
        $riga = ['chiave' => 'x_1', 'etichetta' => $cattivo, 'opzioni' => $cattivo, 'aiuto' => $cattivo, 'obbligatorio' => true, 'visibile' => false, 'tipi' => [['valore' => 'text', 'nome' => 'Testo breve', 'scelto' => true]]];
        $campoAnteprima = static fn (string $forma, array $scelte = []): array => ['etichetta' => $cattivo, 'aiuto' => $cattivo, 'obbligatorio' => true, 'forma' => $forma, 'scelte' => $scelte];
        $modulo = $comuni + [
            'fissi' => [['chiave' => 'telefono', 'etichetta' => $cattivo, 'aiuto' => $cattivo, 'obbligatorio' => true, 'visibile' => true, 'fisso_obb' => false, 'fisso_vis' => true, 'originale' => 'Cellulare', 'cambiato' => true]],
            'righe' => [$riga], 'vuota' => ['chiave' => '', 'etichetta' => '', 'opzioni' => '', 'aiuto' => '', 'obbligatorio' => false, 'visibile' => true] + $riga, 'massimo' => 15, 'erroreAggiunti' => $cattivo,
            'elenchi' => [$elenco],
            'anteprima' => ['intro' => $cattivo, 'aule' => [$cattivo], 'tipiProblema' => [['nome' => $cattivo, 'icona' => 'fa-video', 'aiuto' => $cattivo]], 'urgenze' => [$cattivo],
                'campi' => array_merge(array_map($campoAnteprima, ['aula', 'tipo', 'urgenza', 'casella', 'lungo', 'file', 'data', 'testo']), [$campoAnteprima('radio', [$cattivo]), $campoAnteprima('caselle', [$cattivo])])],
        ];

        $impostazioni = $comuni + ['aperto' => true, 'attivoDavvero' => false, 'motivo' => $cattivo, 'chi' => '<select></select>', 'telefono' => $cattivo, 'orari' => $cattivo,
            'ferma' => '10', 'altre' => '60', 'intro' => $cattivo, 'giorni' => '90', 'errori' => [$cattivo]];

        $opzione = [['valore' => '3', 'testo' => $cattivo, 'scelta' => true]];
        $registro = $comuni + ['dal' => '2026-10-01', 'al' => '2026-11-28', 'testo' => $cattivo, 'periodo' => $cattivo, 'aule' => $opzione, 'tipi' => $opzione, 'stati' => $opzione, 'urgenze' => $opzione,
            'studenti' => $opzione, 'urlAzzera' => 'sos_aule.php?p_id=1&scheda=registro', 'quante' => 201, 'altre' => 1, 'soglia' => 3, 'numeriHtml' => '<ol></ol>',
            'scaricaElencoHtml' => '<div></div>', 'scaricaNumeriHtml' => '<div></div>', 'perAula' => [['nome' => $cattivo, 'quante' => 3]], 'perTipo' => [['nome' => $cattivo, 'quante' => 3]],
            'malate' => [['aula' => $cattivo, 'tipo' => $cattivo, 'quante' => 3]], 'minuti' => [['studente' => $cattivo, 'mese' => 'novembre 2026', 'minuti' => 20, 'interventi' => 2]],
            'righe' => [['id' => 5, 'codice' => $cattivo, 'quando' => '28/11/2026 10:12', 'aula' => $cattivo, 'edificio' => $cattivo, 'tipo' => $cattivo, 'icona' => 'fa-video', 'urgenza' => $cattivo,
                'stato' => $cattivo, 'colore' => 'ok', 'docente' => $cattivo, 'email' => $cattivo, 'telefono' => $cattivo, 'descrizione' => $cattivo, 'aggiunti' => [['etichetta' => $cattivo, 'valore' => $cattivo]],
                'studente' => $cattivo, 'esito' => $cattivo, 'inoltrata' => $cattivo, 'foto' => 'sos_aule.php?p_id=1&scheda=registro&foto=5', 'cronologia' => [['ora' => '28/11 10:12', 'testo' => $cattivo]]]]];

        $varianti = [
            ['_sos_aule', $aule],
            ['_sos_aule', $comuni + ['aule' => [], 'attive' => 0, 'urlTutti' => '']],
            ['_sos_persone', $persone],
            ['_sos_persone', ['scoperte' => [], 'conAnagrafe' => false, 'aule' => [], 'riserva' => [], 'quantiRiserva' => 0, 'ricercaRiserva' => '', 'studenti' => []] + $persone],
            ['_sos_modulo', $modulo],
            ['_sos_impostazioni', $impostazioni],
            ['_sos_impostazioni', ['attivoDavvero' => true, 'motivo' => '', 'errori' => []] + $impostazioni],
            ['_sos_registro', $registro],
            ['_sos_registro', ['altre' => 0, 'quante' => 0, 'righe' => [], 'perAula' => [], 'perTipo' => [], 'malate' => [], 'minuti' => []] + $registro],
        ];

        return array_map(static fn (array $v): array => [
            'schede' => [['titolo' => $cattivo, 'icona' => 'fa-door-open', 'attiva' => true, 'url' => 'sos_aule.php?p_id=1&scheda=aule'], ['titolo' => 'Persone', 'icona' => 'fa-user-group', 'attiva' => false, 'url' => 'sos_aule.php?p_id=1&scheda=persone']],
            'corpo' => Vista::rendi('pannello/' . $v[0], $v[1]),
        ], $varianti);
    }

    /** sos.php: il form (con tutti gli avvisi e i campi possibili), il messaggio e la pagina di una segnalazione. @return list<array<string, mixed>> */
    private static function datiSos(string $cattivo): array
    {
        $csrf = '<input type="hidden" name="csrf_token" value="tok">';
        $mie = [['href' => 'sos.php?segnalazione=1', 'titolo' => $cattivo, 'sotto' => $cattivo, 'stato' => 'In attesa', 'colore' => 'attesa']];
        $base = ['avvisoHtml' => '', 'temaHtml' => '<script nonce="x"></script>', 'mie' => $mie];
        $riserva = ['telefonoRiserva' => $cattivo, 'telefonoRiservaHref' => 'tel:0984'];
        $campo = ['etichetta' => $cattivo, 'aiuto' => $cattivo, 'facoltativo' => true, 'visibile' => true];
        $aggiunto = static fn (string $tipo): array => ['chiave' => 'x_1', 'etichetta' => $cattivo, 'tipo' => $tipo, 'aiuto' => $cattivo, 'facoltativo' => true, 'valore' => $cattivo, 'errore' => $cattivo,
            'opzioni' => [['testo' => $cattivo, 'scelta' => true]]];
        $form = $base + $riserva + [
            'quale' => 'form', 'azione' => 'sos.php?aula=ABCD2345', 'campoCsrf' => $csrf, 'sottotitolo' => $cattivo, 'messaggio' => $cattivo, 'cartelloVecchio' => true, 'fuoriOrario' => true,
            'aulaScelta' => true, 'aulaId' => 3, 'aulaPrima' => 'Sei nell\'aula', 'aulaStacco' => true, 'aulaNome' => $cattivo, 'aulaEdificio' => $cattivo,
            'aule' => [['id' => 3, 'nome' => $cattivo, 'breve' => $cattivo, 'scelta' => true]],
            'avvisoDoppione' => true, 'avvisato' => 3, 'doppione' => ['id' => 5, 'ora' => '10:12', 'tipo' => $cattivo, 'stato' => $cattivo, 'href' => 'sos.php?segnalazione=5'], 'maxNota' => 500,
            'docenteNome' => $cattivo, 'docenteEmail' => $cattivo,
            'c' => array_fill_keys(['docente', 'telefono', 'aula', 'tipo', 'urgenza', 'descrizione', 'foto', 'insegnamento'], $campo),
            'errori' => array_fill_keys(['aula_id', 'tipo_id', 'urgenza', 'descrizione', 'foto', 'telefono', 'insegnamento_id'], $cattivo),
            'tipi' => [['id' => 1, 'nome' => $cattivo, 'icona' => 'fa-video', 'aiuto' => $cattivo, 'scelto' => true]],
            'urgenze' => [['id' => 'ferma', 'testo' => $cattivo, 'icona' => 'fa-hand', 'ferma' => true, 'scelta' => true]],
            'descrizione' => $cattivo, 'maxDescrizione' => 500, 'fotoDaRiscegliere' => true, 'telefono' => $cattivo, 'maxTelefono' => 30,
            'insegnamentoTesto' => $cattivo, 'insegnamentoMeta' => $cattivo, 'insegnamenti' => [['id' => 7, 'nome' => $cattivo, 'scelto' => true]],
            'scriptInsegnamenti' => 'assets/js/campi-pratica.js?v=1', 'base' => '/mydibest',
            'aggiunti' => array_map($aggiunto, ['text', 'textarea', 'radio', 'multicheck', 'date', 'checkbox']),
        ];
        $stato = Vista::rendi('pubblico/_sos_stato', ['stato' => 'in_corso', 'tono' => 'ok', 'icona' => 'fa-circle-check', 'titolo' => $cattivo, 'riga' => $cattivo, 'passiHtml' => '<ol></ol>',
            'cronologia' => [['ora' => '10:12', 'testo' => $cattivo]], 'foto' => 'sos.php?segnalazione=5&foto=1', 'aperta' => true]);
        $segnalazione = $base + ['quale' => 'segnalazione', 'statoHtml' => $stato, 'aperta' => true, 'urlFrammento' => 'sos.php?segnalazione=5&frammento=1', 'azione' => 'sos.php?segnalazione=5',
            'campoCsrf' => $csrf, 'puoScrivere' => true, 'maxNota' => 500, 'riapribile' => false, 'riapriEntro' => ''];

        return [
            $form,
            ['messaggio' => '', 'cartelloVecchio' => false, 'fuoriOrario' => false, 'aulaScelta' => false, 'avvisoDoppione' => false, 'aggiunti' => [], 'mie' => []] + $form,
            $base + $riserva + ['quale' => 'messaggio', 'testo' => $cattivo, 'tono' => 'attesa'],
            $segnalazione,
            ['aperta' => false, 'puoScrivere' => false, 'riapribile' => true, 'riapriEntro' => 'domani alle 10:31'] + $segnalazione,
        ];
    }

    /** sos_interventi.php: l'elenco con una card piena (tutti i pulsanti) e una vuota, l'elenco senza card e il messaggio. @return list<array<string, mixed>> */
    private static function datiSosInterventi(string $cattivo): array
    {
        $base = ['avvisoHtml' => '', 'temaHtml' => '<script nonce="x"></script>'];
        $card = [
            'id' => 5, 'aperta' => true, 'classe' => 'ferma', 'ferma' => true, 'urgenza' => 'Lezione ferma', 'aula' => $cattivo, 'edificio' => $cattivo, 'daQuanto' => 'da 7 minuti',
            'stato' => $cattivo, 'colore' => 'azione', 'icona' => 'fa-video', 'tipo' => $cattivo, 'descrizione' => $cattivo, 'aggiunti' => [['etichetta' => $cattivo, 'valore' => $cattivo]], 'foto' => 'sos.php?segnalazione=5&foto=1', 'docente' => $cattivo,
            'telefono' => $cattivo, 'telefonoHref' => 'tel:333', 'codice' => $cattivo, 'ora' => '10:12', 'nonRisoltaTitolo' => $cattivo, 'nonRisoltaTesto' => $cattivo, 'inMano' => $cattivo,
            'cronologia' => [['ora' => '10:12', 'testo' => $cattivo]], 'url' => 'sos_interventi.php?id=5', 'campoCsrf' => '<input type="hidden" name="csrf_token" value="tok">',
            'prendi' => 'La riprendo io', 'prendiAzione' => 'riprendi', 'confermaPrendi' => $cattivo, 'affida' => 'La passo a un altro studente', 'confermaAffida' => $cattivo,
            'studenti' => [['id' => 2, 'nome' => $cattivo]], 'nessunoStudente' => $cattivo, 'risolta' => true, 'inoltra' => true, 'chiudi' => true,
            'staAndando' => true, 'risoltoStudente' => true, 'nonRisolto' => true, 'esiti' => [['id' => 1, 'nome' => $cattivo, 'icona' => 'fa-lock']], 'maxNota' => 500, 'maxMinuti' => 1440,
        ];
        $spoglia = ['id' => 6, 'aggiunti' => [], 'aperta' => false, 'classe' => 'aiuto', 'ferma' => false, 'daQuanto' => '', 'descrizione' => '', 'foto' => '', 'telefono' => '', 'nonRisoltaTitolo' => '',
            'prendi' => '', 'affida' => '', 'studenti' => [], 'risolta' => false, 'inoltra' => false, 'chiudi' => false, 'staAndando' => false, 'risoltoStudente' => false, 'nonRisolto' => false] + $card;
        $elenco = static fn (array $righe): string => Vista::rendi('pubblico/_sos_interventi_elenco', ['scheda' => 'da_fare', 'card' => $righe, 'vuoto' => 'Niente da fare.',
            'linguette' => [['testo' => 'Da fare', 'numero' => 2, 'attiva' => true, 'url' => 'sos_interventi.php'], ['testo' => 'In corso', 'numero' => 0, 'attiva' => false, 'url' => 'sos_interventi.php?scheda=in_corso']]]);
        $pagina = $base + ['quale' => 'elenco', 'sottotitolo' => 'Le segnalazioni delle tue aule.', 'spento' => true, 'elencoHtml' => $elenco([$card, ['affida' => 'Mando uno studente'] + $spoglia, $spoglia]),
            'urlFrammento' => 'sos_interventi.php?id=5&frammento=1', 'ancora' => 'sos-5', 'push' => true, 'csrfToken' => 'tok', 'scriptPush' => 'assets/js/assistenza-push.js?v=1'];

        return [
            $pagina,
            ['spento' => false, 'push' => false, 'ancora' => '', 'elencoHtml' => $elenco([])] + $pagina,
            $base + ['quale' => 'messaggio', 'testo' => $cattivo, 'tono' => 'azione', 'indietro' => 'sos_interventi.php'],
        ];
    }

    public function testOgniModelloHaIDatiDiProvaECosiLaProvaDellaCspLoRende(): void
    {
        $radicePrima = Vista::usaRadice(null);
        try {
            $principali = array_values(array_filter(self::modelli(), static fn (string $n): bool => !str_starts_with(basename($n), '_')));
            self::assertSame($principali, (static function (): array { $k = array_keys(self::datiDiProva()); sort($k); return $k; })(), 'un modello nuovo ha bisogno dei suoi dati di prova qui: senza, la CSP non lo guarda');
            foreach (self::datiDiProva() as $nome => $varianti) {
                foreach ($varianti as $i => $dati) {
                    $html = Vista::rendi($nome, $dati);
                    self::assertNotSame('', trim($html), "$nome #$i: reso vuoto");
                    self::assertSame([], self::problemiCsp($html, false), "$nome #$i: HTML reso");
                    self::assertStringNotContainsString('<script>alert(1)</script>', $html, "$nome #$i: un valore con <script> deve uscire escapato");
                }
            }
        } finally {
            Vista::usaRadice($radicePrima);
        }
    }

    public function testIDatiSpecialiUsciEscapatiNelleTrePagine(): void
    {
        $radicePrima = Vista::usaRadice(null);
        try {
            $dati = self::datiDiProva();
            $elenco = Vista::rendi('pannello/contatti', $dati['pannello/contatti'][2]);
            self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;q&quot; &#039;s&#039;', $elenco);
            self::assertStringContainsString('data-conferma="Eliminare il contatto «&lt;script&gt;', $elenco, 'la conferma passa da data-conferma');
            self::assertStringContainsString('Nessun recapito ancora', $elenco);
            self::assertSame(4, substr_count($elenco, 'name="csrf_token" value="tok"'), 'un campo CSRF in ogni modulo di azione: mostra/nascondi ed elimina, per due contatti');

            $modulo = Vista::rendi('pannello/contatti', $dati['pannello/contatti'][1]);
            foreach (['titolo', 'per_cosa', 'luogo', 'orari', 'email', 'telefono', 'link_etichetta', 'link_url', 'ordine'] as $campo) {
                self::assertStringContainsString('name="' . $campo . '"', $modulo, "campo $campo nel modulo");
            }
            self::assertStringContainsString('name="salva" value="0"', $modulo);
            self::assertStringNotContainsString(' checked', $modulo, 'un contatto nuovo parte nascosto');

            $modifica = Vista::rendi('pannello/contatti', $dati['pannello/contatti'][3]);
            self::assertStringContainsString('name="salva" value="7"', $modifica);
            self::assertStringContainsString(' checked', $modifica);
            self::assertStringContainsString('value="&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;q&quot; &#039;s&#039;"', $modifica, 'un valore dentro un attributo non esce dall\'attributo');

            $pubblica = Vista::rendi('pubblico/contatti', $dati['pubblico/contatti'][1]);
            self::assertStringContainsString('target="_blank" rel="noopener noreferrer"', $pubblica);
            self::assertStringContainsString('href="https://example.org/?a=1&amp;b=2"', $pubblica);
            self::assertStringContainsString('href="tel:098449000"', $pubblica);
            self::assertStringContainsString('I contatti sono in preparazione', Vista::rendi('pubblico/contatti', $dati['pubblico/contatti'][0]));

            $ricevuta = Vista::rendi('stampe/ricevuta', $dati['stampe/ricevuta'][0]);
            self::assertStringContainsString('Lista d\'Attesa', $ricevuta);
            self::assertStringContainsString('📎 File Allegato', $ricevuta);
            self::assertStringNotContainsString('uploads/x.pdf', $ricevuta, 'il percorso di un allegato non si mostra');
            self::assertStringContainsString('<svg></svg>', $ricevuta, 'il QR è HTML già sicuro: passa da grezzo');
            self::assertStringContainsString('<!DOCTYPE html>', ltrim($ricevuta), 'documento intero, senza testata del sito');
            self::assertStringContainsString('Confermata', Vista::rendi('stampe/ricevuta', $dati['stampe/ricevuta'][1]));
            // Evento ad accesso libero: «Presenza registrata», niente codice né QR
            $libera = Vista::rendi('stampe/ricevuta', ['senza_prenotazione' => true] + $dati['stampe/ricevuta'][0]);
            self::assertStringContainsString('Presenza registrata', $libera);
            self::assertStringContainsString('RICEVUTA DI PRESENZA', $libera);
            self::assertStringNotContainsString('OP-ABC123', $libera, 'il codice non si mostra');
            self::assertStringNotContainsString('<svg></svg>', $libera, 'e nemmeno il QR che lo contiene');
            self::assertStringContainsString('RICEVUTA DI PRENOTAZIONE', $ricevuta);
        } finally {
            Vista::usaRadice($radicePrima);
        }
    }

    /** Come farebbe Apache aprendo il file dal browser: un processo PHP a parte, senza `$d`. La guardia fa `exit`, quindi non si può provare qui dentro. */
    public function testIModelliAperiDirettamenteNonStampanoNiente(): void
    {
        foreach (self::modelli() as $nome) {
            $p = proc_open([PHP_BINARY, '-d', 'display_errors=1', RADICE_SITO . '/viste/' . $nome . '.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipe);
            self::assertIsResource($p);
            $uscita = (string) stream_get_contents($pipe[1]) . (string) stream_get_contents($pipe[2]);
            fclose($pipe[1]);
            fclose($pipe[2]);
            proc_close($p);

            self::assertSame('', trim($uscita), "viste/$nome.php aperto da solo deve restare muto");
        }
    }

    /** Le tre pagine convertite sono ora classi (src/Portale/Pagine/): l'escape lo fa la vista, e il guscio di oggi non ha più né logica né HTML. */
    public function testLeTrePagineConvertiteNonHannoPiuLaLambdaDelEscape(): void
    {
        foreach (['contatti.php' => 'PaginaContatti', 'admin/contatti.php' => 'PaginaContattiPannello', 'stampa_ricevuta.php' => 'PaginaStampaRicevuta'] as $guscio => $classe) {
            $src = (string) file_get_contents(RADICE_SITO . '/src/Portale/Pagine/' . $classe . '.php');

            self::assertDoesNotMatchRegularExpression('/\$h\s*=\s*fn/', $src, $classe);
            self::assertStringNotContainsString('htmlspecialchars(', $src, "$classe: l'escape lo fa la vista");
            self::assertStringContainsString('Vista::rendi(', $src, $classe);
            self::assertSame([], self::problemiCsp($src, true), $classe);

            $righe = array_filter(array_map('trim', file(RADICE_SITO . '/' . $guscio) ?: []), static fn (string $r): bool => $r !== '' && !str_starts_with($r, '//') && $r !== '<?php');
            self::assertContains('App\Http\Nucleo::serviFile(__FILE__);', $righe, "$guscio: il guscio chiama il Nucleo");
            self::assertLessThanOrEqual(3, count($righe), "$guscio: un guscio ha solo i require e la chiamata al Nucleo");
        }
    }
}
