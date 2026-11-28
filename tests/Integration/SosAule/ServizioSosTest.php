<?php

declare(strict_types=1);

namespace Tests\Integration\SosAule;

use App\Core\Container;
use App\Core\Database;
use App\Core\IndirizzoClient;
use App\Core\Orologio;
use App\Core\Schema\Definizioni;
use App\Core\Sito;
use App\SosAule\AuleRepository;
use App\SosAule\PersoneSos;
use App\SosAule\SegnalazioniRepository;
use App\SosAule\ServizioSos;
use App\SosAule\VociDiPartenza;
use Tests\Doppi\ClientFinto;
use Tests\Doppi\OrologioFisso;
use Tests\Integration\DatabaseDiProva;

/** S1, con MariaDB: un invio, due referenti che prendono la stessa segnalazione, il giro studente → non risolta → ripresa → risolta. */
final class ServizioSosTest extends DatabaseDiProva
{
    private const DOCENTE = ['id' => 3, 'nome' => 'Maria', 'cognome' => 'Docente', 'email' => 'maria.docente@unical.it', 'ruolo_id' => 5, 'ruoli_secondari' => '6'];
    private const MARIO = ['id' => 10, 'nome' => 'Mario', 'cognome' => 'Rossi', 'persona_id' => 'P-MARIO'];
    private const LUCIA = ['id' => 11, 'nome' => 'Lucia', 'cognome' => 'Bianchi', 'persona_id' => 'P-LUCIA'];
    private const SARA = ['id' => 20, 'nome' => 'Sara', 'cognome' => 'Verdi', 'email' => 'sara.verdi@studenti.unical.it', 'email_personalizzata' => 0];

    private Container $c;
    private int $aula = 0;
    private int $studente = 0;

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
            'utenti' => 'CREATE TABLE utenti (id INT PRIMARY KEY, nome VARCHAR(100), cognome VARCHAR(100), email VARCHAR(255), ruolo_id INT DEFAULT 5)',
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
        $this->allineaColonne(); // le colonne sos_* di impostazioni_sistema come le aggiunge la migrazione
        VociDiPartenza::inserisci($this->db);
        $this->db->esegui('INSERT INTO impostazioni_sistema (id, sos_aperto) VALUES (1, 1)');
        $this->db->esegui("INSERT INTO ruoli (id, nome) VALUES (6, 'Docenti')");
        foreach ([self::DOCENTE, self::MARIO, self::LUCIA, self::SARA] as $u) {
            $this->db->esegui(
                'INSERT INTO utenti (id, nome, cognome, email, ruolo_id, ruoli_secondari, persona_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$u['id'], $u['nome'], $u['cognome'], $u['email'] ?? null, $u['ruolo_id'] ?? 5, $u['ruoli_secondari'] ?? '', $u['persona_id'] ?? null]
            );
        }

        $c = new Container();
        $c->istanza(Container::class, $c);
        $c->istanza(Database::class, $this->db);
        $c->istanza(Orologio::class, new OrologioFisso('2026-11-28 10:14:00'));
        $c->istanza(IndirizzoClient::class, new ClientFinto());
        $c->istanza(Sito::class, new Sito(dirname(__DIR__, 3), 'https://portale.test/mydibest'));
        $this->c = $c;

        $this->aula = $c->get(AuleRepository::class)->aggiungi(['nome' => '5C2', 'edificio' => 'Cubo 5C'], 1);
        $persone = $c->get(PersoneSos::class);
        foreach ([self::MARIO, self::LUCIA] as $p) {
            $this->db->esegui('INSERT INTO personale_ateneo (id, nome, cognome, email, attivo) VALUES (?, ?, ?, ?, 1)', [$p['persona_id'], $p['nome'], $p['cognome'], strtolower($p['nome']) . '@unical.it']);
            self::assertTrue($persone->aggiungiReferente('aula', $this->aula, $p['persona_id'], 1)->riuscito);
        }
        self::assertTrue($persone->aggiungiStudente(['nome' => 'Sara', 'cognome' => 'Verdi', 'email' => self::SARA['email']], 1)->riuscito);
        $this->studente = (int) $this->db->valore('SELECT id FROM sos_studenti');
    }

    private function sos(): ServizioSos
    {
        return $this->c->get(ServizioSos::class);
    }

    private function inviata(): int
    {
        $tipo = (int) $this->db->valore("SELECT id FROM sos_tipi WHERE nome = 'Proiettore o schermo'");
        $esito = $this->sos()->invia(self::DOCENTE, ['aula_id' => $this->aula, 'tipo_id' => $tipo, 'urgenza' => 'ferma', 'descrizione' => 'Non si accende', 'telefono' => '+39 333 1234567'], []);
        self::assertSame([], $esito->errori);
        self::assertTrue($esito->esito->riuscito, $esito->esito->messaggio);

        return $esito->id;
    }

    /** @return array<string, mixed> */
    private function riga(int $id): array
    {
        return (array) $this->c->get(SegnalazioniRepository::class)->perId($id);
    }

    public function testUnInvioValido(): void
    {
        $id = $this->inviata();

        $s = $this->riga($id);
        self::assertSame(['SOS-2026-0001', 'inviata', 'Maria', 'Docente', 'maria.docente@unical.it'], [$s['codice'], $s['stato'], $s['nome'], $s['cognome'], $s['email']]);
        $storia = $this->c->get(SegnalazioniRepository::class)->cronologia($id);
        self::assertCount(1, $storia);
        self::assertSame(['inviata', 'Docente Maria (docente)'], [$storia[0]['a_stato'], $storia[0]['chi']]);
        self::assertSame('SOS aule: inviata', $this->db->valore('SELECT azione FROM log_attivita'));
    }

    public function testDuePrendonoInsiemeVinceIlPrimo(): void
    {
        $id = $this->inviata();

        self::assertTrue($this->sos()->prendi($id, self::MARIO)->riuscito);
        $secondo = $this->sos()->prendi($id, self::LUCIA);

        self::assertFalse($secondo->riuscito);
        self::assertSame('La segnalazione è già presa da Mario Rossi alle 10:14.', $secondo->messaggio);
        self::assertSame(self::MARIO['id'], (int) $this->riga($id)['presa_da']);
        self::assertCount(2, $this->c->get(SegnalazioniRepository::class)->cronologia($id), 'il secondo non scrive niente');
    }

    public function testAffidataNonRisoltaRipresaERisolta(): void
    {
        $id = $this->inviata();
        $esito = (int) $this->db->valore("SELECT id FROM sos_esiti WHERE nome = 'Serve un pezzo di ricambio'");

        foreach ([
            $this->sos()->affida($id, self::MARIO, $this->studente),
            $this->sos()->staAndando($id, self::SARA),
            $this->sos()->nonRisolta($id, self::SARA, $esito, 'La lampada è bruciata', 15),
            $this->sos()->riprendi($id, self::MARIO),
            $this->sos()->risolta($id, self::MARIO),
        ] as $passo) {
            self::assertTrue($passo->riuscito, $passo->messaggio);
        }

        $s = $this->riga($id);
        self::assertSame(['risolta', null, $esito], [$s['stato'], $s['assegnata_a_studente'], (int) $s['esito_id']]);
        $nonRisolta = (array) $this->db->riga("SELECT studente_id, minuti FROM sos_cronologia WHERE segnalazione_id = ? AND a_stato = 'non_risolta'", [$id]);
        self::assertSame([$this->studente, 15], [(int) $nonRisolta['studente_id'], (int) $nonRisolta['minuti']]);
        self::assertSame(self::MARIO['id'], (int) $s['chiusa_da']);
        self::assertSame(
            ['>inviata', 'inviata>assegnata', 'assegnata>in_corso', 'in_corso>non_risolta', 'non_risolta>presa', 'presa>risolta'],
            array_map(static fn (array $r): string => $r['da_stato'] . '>' . $r['a_stato'], $this->c->get(SegnalazioniRepository::class)->cronologia($id))
        );
    }
}
