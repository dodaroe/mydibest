<?php

declare(strict_types=1);

namespace Tests\Integration\Portale;

use App\Auth\Saml\CollegamentoAnagrafe;
use App\Core\Schema\Definizioni;
use App\Http\Richiesta;
use App\Http\Risposta;
use App\Portale\Pagine\PaginaSos;
use App\SosAule\AuleRepository;
use App\SosAule\PersoneSos;
use App\SosAule\VociDiPartenza;

/** La pagina di chi chiede aiuto (sos.php) senza browser: una Richiesta::finta() entra, si legge la Risposta. Le regole le provano ServizioSosTest e RegoleSosTest. */
final class PaginaSosTest extends PaginaSosBase
{
    private int $aula = 0;
    private string $codice = '';
    private int $tipo = 0;

    protected function tabelle(): array
    {
        $t = Definizioni::tabelle();

        return parent::tabelle() + ['rate_limit_attempts' => $t['rate_limit_attempts'], 'personale_modifiche' => $t['personale_modifiche']];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->c->istanza(CollegamentoAnagrafe::class, new class () implements CollegamentoAnagrafe {
            public function collega(int $utenteId, string $emailSso): ?string
            {
                return null;
            }
        });
        VociDiPartenza::inserisci($this->db);
        $this->db->esegui('UPDATE impostazioni_sistema SET sos_aperto = 1 WHERE id = 1');
        $this->db->esegui("INSERT INTO ruoli (id, nome) VALUES (6, 'Docenti')");
        $this->db->esegui("UPDATE utenti SET ruoli_secondari = '6' WHERE id = 2");
        $this->db->esegui("INSERT INTO utenti (id, nome, cognome, email, ruolo_id, ruoli_secondari) VALUES (3, 'Paolo', 'Altro', 'altro@prova.it', 5, '6')");
        $this->db->esegui("INSERT INTO personale_ateneo (id, nome, cognome, email, attivo) VALUES ('P-REF', 'Luca', 'Referente', 'referente@unical.it', 1)");
        $this->aula = $this->c->get(AuleRepository::class)->aggiungi(['nome' => '5C2'], 1);
        $this->codice = (string) $this->c->get(AuleRepository::class)->perId($this->aula)['codice'];
        self::assertTrue($this->c->get(PersoneSos::class)->aggiungiReferente('aula', $this->aula, 'P-REF', 1)->riuscito);
        $this->tipo = (int) $this->db->valore("SELECT id FROM sos_tipi WHERE nome = 'Proiettore o schermo'");
        $this->entra(2);
    }

    private function chiama(string $metodo, array $query = [], array $post = []): Risposta
    {
        $pagina = $this->c->get(PaginaSos::class);

        return $pagina(Richiesta::finta($metodo, '/sos.php', $query, $post));
    }

    public function testSenzaAccessoSiVaAlLoginEIlCodiceDellAulaTornaIndietro(): void
    {
        $this->sessione->dati = [];

        $r = $this->chiama('GET', ['aula' => $this->codice]);

        self::assertSame(302, $r->codice());
        self::assertSame('saml_login.php?redirect=' . urlencode('sos.php?aula=' . $this->codice), $r->intestazione('Location'));
    }

    public function testUnInvioValidoSalvaERimandaAllaSegnalazione(): void
    {
        $r = $this->chiama('POST', ['aula' => $this->codice], ['aula_id' => (string) $this->aula, 'tipo_id' => (string) $this->tipo, 'urgenza' => 'ferma', 'descrizione' => 'Non si accende']);

        $riga = $this->db->riga('SELECT * FROM sos_segnalazioni');
        self::assertNotNull($riga, $r->corpo());
        self::assertSame(303, $r->codice());
        self::assertSame('sos.php?segnalazione=' . $riga['id'], $r->intestazione('Location'));
        self::assertSame(['inviata', 'Non si accende', 2], [$riga['stato'], $riga['descrizione'], (int) $riga['utente_id']]);
        $pagina = $this->chiama('GET', ['segnalazione' => (string) $riga['id']]);
        self::assertSame(200, $pagina->codice(), 'chi l\'ha inviata la vede');
        self::assertStringContainsString('Segnalazione ' . $riga['codice'] . ' inviata', $pagina->corpo());
    }

    public function testConUnErroreIlFormTornaConQuelloCheEraScritto(): void
    {
        $r = $this->chiama('POST', ['aula' => $this->codice], ['aula_id' => (string) $this->aula, 'urgenza' => 'aiuto', 'descrizione' => 'Il telecomando è sparito']);

        self::assertSame(200, $r->codice());
        self::assertStringContainsString('Il telecomando è sparito</textarea>', $r->corpo(), 'il testo scritto resta');
        self::assertMatchesRegularExpression('/<legend[^>]*>Tipo di problema<\/legend>\s*<p class="ui-comp-errore"[^>]*>.*?Scegli il tipo di problema\./s', $r->corpo(), 'l\'errore sta accanto al campo');
        self::assertMatchesRegularExpression('/value="aiuto" checked|id="sos-urgenza-aiuto" value="aiuto" checked/', $r->corpo());
        self::assertSame(0, (int) $this->db->valore('SELECT COUNT(*) FROM sos_segnalazioni'));
    }

    public function testUnAltroDocenteNonVedeLaSegnalazione(): void
    {
        $this->chiama('POST', [], ['aula_id' => (string) $this->aula, 'tipo_id' => (string) $this->tipo, 'urgenza' => 'ferma', 'descrizione' => 'Testo riservato']);
        $id = (int) $this->db->valore('SELECT id FROM sos_segnalazioni');
        self::assertGreaterThan(0, $id);
        $this->entra(3);

        $r = $this->chiama('GET', ['segnalazione' => (string) $id]);

        self::assertSame(403, $r->codice());
        self::assertStringNotContainsString('Testo riservato', $r->corpo());
        self::assertStringNotContainsString('SOS-2026-0001', $r->corpo());
        self::assertSame(403, $this->chiama('GET', ['segnalazione' => (string) $id, 'frammento' => '1'])->codice(), 'neanche il frammento');
    }
}
