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
use App\SosAule\NotificheSos;
use App\SosAule\PersoneSos;
use App\SosAule\ServizioSos;
use App\SosAule\VociDiPartenza;
use Tests\Doppi\ClientFinto;
use Tests\Doppi\MailerFinto;
use Tests\Doppi\OrologioFisso;
use Tests\Integration\DatabaseDiProva;

/** S4, con MariaDB: l'avviso di una segnalazione nuova per un'aula senza referenti. */
final class NotificheSosTest extends DatabaseDiProva
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
        $this->db->esegui("INSERT INTO utenti (id, nome, cognome, email, ruoli_secondari) VALUES (3, 'Maria', 'Docente', 'maria.docente@unical.it', '6')");
    }

    public function testAnnullataAvvisaChiLHaPresaENonChiHaAnnullato(): void
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
        foreach ([['P-1', 'chi.prende@unical.it'], ['P-2', 'altro.referente@unical.it']] as [$persona, $email]) {
            $this->db->esegui('INSERT INTO personale_ateneo (id, nome, cognome, email, attivo) VALUES (?, ?, ?, ?, 1)', [$persona, 'Nome', $persona, $email]);
            self::assertTrue($c->get(PersoneSos::class)->aggiungiReferente('aula', $aula, $persona, 1)->riuscito);
        }
        $this->db->esegui("INSERT INTO utenti (id, nome, cognome, email, persona_id) VALUES (4, 'Anna', 'Rossi', 'chi.prende@unical.it', 'P-1')");
        $tipo = (int) $this->db->valore("SELECT id FROM sos_tipi WHERE nome = 'Proiettore o schermo'");
        $docente = (array) $this->db->riga('SELECT * FROM utenti WHERE id = 3');
        $referente = (array) $this->db->riga('SELECT * FROM utenti WHERE id = 4');
        $servizio = $c->get(ServizioSos::class);
        $inviata = $servizio->invia($docente, ['aula_id' => $aula, 'tipo_id' => $tipo, 'urgenza' => 'ferma', 'descrizione' => 'Non si accende'], []);
        self::assertTrue($inviata->esito->riuscito, $inviata->esito->messaggio);
        self::assertTrue($servizio->prendi($inviata->id, $referente)->riuscito);
        self::assertTrue($servizio->annulla($inviata->id, $docente)->riuscito);

        $c->get(NotificheSos::class)->annullata($inviata->id, $docente);

        self::assertSame(['chi.prende@unical.it'], array_map(static fn (array $m): string => strtolower($m['a']), $mailer->inviate));
        self::assertStringContainsString('annullata da chi', $mailer->inviate[0]['corpo']);
    }

    public function testLoStudenteCheLaPerdeEAvvisatoEIlReferenteNo(): void
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
        $this->db->esegui("INSERT INTO personale_ateneo (id, nome, cognome, email, attivo) VALUES ('P-1', 'Anna', 'Rossi', 'referente@unical.it', 1)");
        self::assertTrue($c->get(PersoneSos::class)->aggiungiReferente('aula', $aula, 'P-1', 1)->riuscito);
        $this->db->esegui("INSERT INTO utenti (id, nome, cognome, email, persona_id) VALUES (4, 'Anna', 'Rossi', 'referente@unical.it', 'P-1')");
        $this->db->esegui("INSERT INTO sos_studenti (nome, cognome, email, attivo, aggiunto_il) VALUES ('Sara', 'Verdi', 'sara.verdi@studenti.unical.it', 1, '2026-10-01 09:00:00')");
        $tipo = (int) $this->db->valore("SELECT id FROM sos_tipi WHERE nome = 'Proiettore o schermo'");
        $docente = (array) $this->db->riga('SELECT * FROM utenti WHERE id = 3');
        $referente = (array) $this->db->riga('SELECT * FROM utenti WHERE id = 4');
        $servizio = $c->get(ServizioSos::class);
        $id = $servizio->invia($docente, ['aula_id' => $aula, 'tipo_id' => $tipo, 'urgenza' => 'ferma', 'descrizione' => 'Non si accende'], [])->id;
        $riga = fn (): array => (array) $this->db->riga('SELECT * FROM sos_segnalazioni WHERE id = ?', [$id]);
        $notifiche = $c->get(NotificheSos::class);

        // da «Presa in carico» il referente passa a uno studente: nessuno la perde
        self::assertTrue($servizio->prendi($id, $referente)->riuscito);
        $prima = $riga();
        self::assertTrue($servizio->affida($id, $referente, (int) $this->db->valore('SELECT id FROM sos_studenti'))->riuscito);
        $notifiche->tolta($prima, $referente);
        self::assertSame([], $mailer->inviate);

        $prima = $riga();
        self::assertTrue($servizio->riprendi($id, $referente)->riuscito);
        $notifiche->tolta($prima, $referente);

        self::assertSame(['sara.verdi@studenti.unical.it'], array_map(static fn (array $m): string => strtolower($m['a']), $mailer->inviate));
        self::assertStringContainsString('non è più affidata a te: non serve più che tu vada', html_entity_decode($mailer->inviate[0]['corpo'], ENT_QUOTES));
    }

    public function testSenzaReferentiVaAllaRiservaUnaEmailPerPersona(): void
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
        // la stessa persona compare due volte (due schede dell'anagrafe con lo stesso indirizzo): deve ricevere una email sola
        foreach ([['P-1', 'coordinatore@unical.it'], ['P-2', 'secondo@unical.it'], ['P-3', 'Coordinatore@unical.it']] as [$persona, $email]) {
            $this->db->esegui('INSERT INTO personale_ateneo (id, nome, cognome, email, attivo) VALUES (?, ?, ?, ?, 1)', [$persona, 'Nome', $persona, $email]);
            self::assertTrue($c->get(PersoneSos::class)->aggiungiReferente('riserva', null, $persona, 1)->riuscito);
        }
        $tipo = (int) $this->db->valore("SELECT id FROM sos_tipi WHERE nome = 'Proiettore o schermo'");
        $utente = (array) $this->db->riga('SELECT * FROM utenti WHERE id = 3');
        $esito = $c->get(ServizioSos::class)->invia($utente, ['aula_id' => $aula, 'tipo_id' => $tipo, 'urgenza' => 'ferma', 'descrizione' => 'Non si accende'], []);
        self::assertTrue($esito->esito->riuscito, $esito->esito->messaggio);

        $c->get(NotificheSos::class)->nuova($esito->id);

        $a = array_map(static fn (array $m): string => strtolower($m['a']), $mailer->inviate);
        sort($a);
        self::assertSame(['coordinatore@unical.it', 'secondo@unical.it'], $a);
        self::assertSame('SOS aula 5C2 — La lezione è ferma — Proiettore o schermo', $mailer->inviate[0]['oggetto']);
        self::assertStringContainsString('sos_interventi.php?id=' . $esito->id, $mailer->inviate[0]['corpo']);
        self::assertStringContainsString('Non si accende', $mailer->inviate[0]['corpo']);
    }
}
