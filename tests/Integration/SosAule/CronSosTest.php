<?php

declare(strict_types=1);

namespace Tests\Integration\SosAule;

use App\Core\Container;
use App\Core\Database;
use App\Core\IndirizzoClient;
use App\Core\Orologio;
use App\Core\Schema\Definizioni;
use App\Core\Sito;
use App\Infrastructure\Mail\Mailer;
use App\SosAule\AuleRepository;
use App\SosAule\CronSos;
use App\SosAule\PersoneSos;
use App\SosAule\ServizioSos;
use App\SosAule\VociDiPartenza;
use Tests\Doppi\ClientFinto;
use Tests\Doppi\MailerFinto;
use Tests\Doppi\OrologioFisso;
use Tests\Integration\DatabaseDiProva;

/** S6, con MariaDB: il promemoria di chi aspetta. */
final class CronSosTest extends DatabaseDiProva
{
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
            'utenti' => 'CREATE TABLE utenti (id INT PRIMARY KEY, nome VARCHAR(100), cognome VARCHAR(100), email VARCHAR(255), ruolo_id INT DEFAULT 5, ruoli_secondari VARCHAR(255) NOT NULL DEFAULT \'\', persona_id VARCHAR(80) NULL)',
            'ruoli' => 'CREATE TABLE ruoli (id INT PRIMARY KEY, nome VARCHAR(100))',
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
        $this->allineaColonne();
        VociDiPartenza::inserisci($this->db);
        $this->db->esegui('INSERT INTO impostazioni_sistema (id, sos_aperto) VALUES (1, 1)');
        $this->db->esegui("INSERT INTO ruoli (id, nome) VALUES (6, 'Docenti')");
        $this->db->esegui("INSERT INTO utenti (id, nome, cognome, email, ruoli_secondari, persona_id) VALUES (3, 'Maria', 'Docente', 'maria.docente@unical.it', '6', NULL), (4, 'Anna', 'Rossi', 'referente@unical.it', '', 'P-1')");
    }

    public function testIlPromemoriaParteUnaVoltaSolaENonPerUnaGiaPresa(): void
    {
        $mailer = new MailerFinto();
        $c = new Container();
        $c->istanza(Container::class, $c);
        $c->istanza(Database::class, $this->db);
        $c->istanza(Orologio::class, new OrologioFisso('2026-11-28 10:14:00'));
        $c->istanza(IndirizzoClient::class, new ClientFinto());
        $c->istanza(Sito::class, new Sito(dirname(__DIR__, 3), 'https://portale.test/mydibest'));
        $c->istanza(Mailer::class, $mailer);
        \App\Assistenza\Registrazione::registra($c);

        $aula = $c->get(AuleRepository::class)->aggiungi(['nome' => '5C2'], 1);
        foreach ([['P-1', 'referente@unical.it', 'aula', $aula], ['P-2', 'riserva@unical.it', 'riserva', null]] as [$persona, $email, $ambito, $dove]) {
            $this->db->esegui('INSERT INTO personale_ateneo (id, nome, cognome, email, attivo) VALUES (?, ?, ?, ?, 1)', [$persona, 'Nome', $persona, $email]);
            self::assertTrue($c->get(PersoneSos::class)->aggiungiReferente($ambito, $dove, $persona, 1)->riuscito);
        }
        $tipo = (int) $this->db->valore("SELECT id FROM sos_tipi WHERE nome = 'Proiettore o schermo'");
        $docente = (array) $this->db->riga('SELECT * FROM utenti WHERE id = 3');
        $referente = (array) $this->db->riga('SELECT * FROM utenti WHERE id = 4');
        $servizio = $c->get(ServizioSos::class);
        $invia = static fn (): int => $servizio->invia($docente, ['aula_id' => $aula, 'tipo_id' => $tipo, 'urgenza' => 'ferma', 'descrizione' => 'Non si accende'], [])->id;
        $ferma = $invia();
        $presa = $invia();
        self::assertTrue($servizio->prendi($presa, $referente)->riuscito);
        // mandate 14 minuti fa: oltre i 10 di «La lezione è ferma»
        $this->db->esegui("UPDATE sos_segnalazioni SET inviata_il = '2026-11-28 10:00:00'");

        $cron = $c->get(CronSos::class);
        self::assertSame([1, 0], [$cron->promemoria(), $cron->promemoria()]);

        $a = array_map(static fn (array $m): string => strtolower($m['a']), $mailer->inviate);
        sort($a);
        self::assertSame(['referente@unical.it', 'riserva@unical.it'], $a);
        self::assertStringContainsString('sos_interventi.php?id=' . $ferma, $mailer->inviate[0]['corpo']);
        self::assertNull($this->db->valore('SELECT promemoria_il FROM sos_segnalazioni WHERE id = ?', [$presa]));
    }
}
