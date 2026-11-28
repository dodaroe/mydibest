<?php

declare(strict_types=1);

namespace Tests\Integration\Portale;

use App\Auth\Sessione;
use App\Core\Container;
use App\Core\Database;
use App\Core\IndirizzoClient;
use App\Core\Orologio;
use App\Core\Schema\Definizioni;
use App\Core\Sito;
use App\Http\Vista;
use App\Infrastructure\Mail\Mailer;
use App\Infrastructure\Pdf\VerificaFirme;
use Tests\Doppi\AuthSessioneInMemoria;
use Tests\Doppi\ClientFinto;
use Tests\Doppi\MailerFinto;
use Tests\Doppi\OrologioFisso;
use Tests\Integration\DatabaseDiProva;

require_once RADICE_SITO . '/inc/base.php';   // ids_gestori_da_campi(), che i permessi del pannello chiedono

/**
 * Base delle prove delle pagine di SOS aule nel pannello, senza browser: i modelli VERI copiati in una radice temporanea con testata e piè finti.
 * Utenti: 1 = amministratore, 2 = docente senza abilitazioni.
 */
abstract class PaginaSosBase extends DatabaseDiProva
{
    protected Container $c;
    protected AuthSessioneInMemoria $sessione;
    private string $cartella = '';
    private ?string $radicePrima = null;
    /** @var array<string, array{0: string, 1: string}> */
    private array $layoutPrima = [];

    protected function tabelle(): array
    {
        $t = Definizioni::tabelle();

        return [
            'sos_aule' => $t['sos_aule'],
            'sos_referenti' => $t['sos_referenti'],
            'sos_studenti' => $t['sos_studenti'],
            'sos_tipi' => $t['sos_tipi'],
            'sos_esiti' => $t['sos_esiti'],
            'sos_segnalazioni' => $t['sos_segnalazioni'],
            'sos_cronologia' => $t['sos_cronologia'],
            'personale_ateneo' => $t['personale_ateneo'],
            'abilitazioni_ambito' => $t['abilitazioni_ambito'],
            'utenti' => "CREATE TABLE utenti (id INT PRIMARY KEY, nome VARCHAR(100), cognome VARCHAR(100), email VARCHAR(255), ruolo_id INT DEFAULT 5, ruoli_secondari VARCHAR(255) NOT NULL DEFAULT '', persona_id VARCHAR(80) NULL)",
            'ruoli' => 'CREATE TABLE ruoli (id INT PRIMARY KEY, nome VARCHAR(100))',
            'pagine_eventi' => "CREATE TABLE pagine_eventi (id INT AUTO_INCREMENT PRIMARY KEY, titolo VARCHAR(200) DEFAULT '', slug VARCHAR(100) DEFAULT '', ordine INT DEFAULT 0, gestore_utente_id INT DEFAULT 0, gestori_utenti_ids VARCHAR(255) DEFAULT '', permessi_gestori_json TEXT NULL, tipo_area VARCHAR(20) NOT NULL DEFAULT '')",
            'log_attivita' => 'CREATE TABLE log_attivita (id INT AUTO_INCREMENT PRIMARY KEY, utente_id INT DEFAULT NULL, azione VARCHAR(255) NOT NULL, dettagli_json TEXT DEFAULT NULL, indirizzo_ip VARCHAR(45) DEFAULT NULL, data_ora DATETIME DEFAULT CURRENT_TIMESTAMP)',
            'impostazioni_sistema' => 'CREATE TABLE impostazioni_sistema (id INT PRIMARY KEY)',
        ];
    }

    protected function setUp(): void
    {
        if (self::$conn !== null) {
            self::$conn->query('DROP TABLE IF EXISTS `sos_cronologia`'); // punta a sos_segnalazioni (chiave esterna): va tolta per prima
        }
        parent::setUp();
        $this->allineaColonne(); // le colonne sos_* di impostazioni_sistema
        $this->db->esegui('INSERT INTO impostazioni_sistema (id) VALUES (1)');
        $this->db->esegui("INSERT INTO utenti (id, nome, cognome, email, ruolo_id) VALUES (1, 'Anna', 'Admin', 'admin@prova.it', 1), (2, 'Maria', 'Docente', 'docente@prova.it', 5)");

        $c = new Container();
        $c->istanza(Container::class, $c);
        $c->istanza(Database::class, $this->db);
        foreach (glob(RADICE_SITO . '/src/*/Registrazione.php') ?: [] as $f) {
            $classe = 'App\\' . basename(dirname($f)) . '\\Registrazione';
            if (!in_array($classe, ['App\\Iscrizioni\\Registrazione', 'App\\Fsl\\Registrazione'], true)) {
                $classe::registra($c);
            }
        }
        $this->sessione = new AuthSessioneInMemoria();
        $c->istanza(Database::class, $this->db);
        $c->istanza(Sessione::class, $this->sessione);
        $c->istanza(IndirizzoClient::class, new ClientFinto());
        // le chiedono i permessi del pannello (che arrivano fino alle convenzioni di tesi): SOS aule qui non spedisce e non firma niente
        $c->istanza(Mailer::class, new MailerFinto());
        $c->istanza(VerificaFirme::class, new class () implements VerificaFirme {
            public function firmePades(string $pdf): array
            {
                return [];
            }
        });
        $c->istanza(Orologio::class, new OrologioFisso('2026-11-28 10:14:00'));
        $c->istanza(Sito::class, new Sito(RADICE_SITO, 'https://portale.test/mydibest'));
        $this->c = $c;
        $this->entra(1);

        $this->cartella = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pagina_sos_' . bin2hex(random_bytes(4));
        mkdir($this->cartella . '/viste/pannello', 0777, true);
        mkdir($this->cartella . '/viste/pubblico', 0777, true);
        foreach (array_merge(glob(RADICE_SITO . '/viste/pannello/*sos_*.php') ?: [], glob(RADICE_SITO . '/viste/pubblico/*sos*.php') ?: []) as $f) {
            copy($f, $this->cartella . '/viste/' . basename(dirname($f)) . '/' . basename($f));   // i modelli VERI
        }
        file_put_contents($this->cartella . '/header.php', '<?php echo "<html><body>"; ?>');
        file_put_contents($this->cartella . '/footer.php', '</body></html>');
        $this->radicePrima = Vista::usaRadice($this->cartella);
        $this->layoutPrima = Vista::layout(['sito' => ['header.php', 'footer.php'], 'pannello' => ['header.php', 'footer.php']]);
    }

    protected function tearDown(): void
    {
        Vista::usaRadice($this->radicePrima);
        Vista::layout($this->layoutPrima ?: ['sito' => ['header.php', 'footer.php'], 'pannello' => ['header.php', 'footer.php']]);
        foreach (array_merge(glob($this->cartella . '/viste/*/*.php') ?: [], [$this->cartella . '/header.php', $this->cartella . '/footer.php']) as $f) {
            @unlink($f);
        }
        @rmdir($this->cartella . '/viste/pannello');
        @rmdir($this->cartella . '/viste/pubblico');
        @rmdir($this->cartella . '/viste');
        @rmdir($this->cartella);
        parent::tearDown();
    }

    protected function entra(int $utente): void
    {
        $this->sessione->dati = ['utente_id' => $utente];
    }
}
