<?php

declare(strict_types=1);

namespace Tests\Integration\Portale;

use App\Auth\Saml\CollegamentoAnagrafe;
use App\Http\Richiesta;
use App\Http\Risposta;
use App\Portale\Pagine\PaginaSosInterventi;
use App\SosAule\AuleRepository;
use App\SosAule\PersoneSos;
use App\SosAule\ServizioSos;
use App\SosAule\VociDiPartenza;

/**
 * La pagina di chi interviene (sos_interventi.php) senza browser: chi vede che cosa e un modulo incompleto. Le regole dei passaggi le prova ServizioSosTest.
 * Utenti: 2 = docente che segnala, 3 = referente dell'aula A, 4 = studente part-time.
 */
final class PaginaSosInterventiTest extends PaginaSosBase
{
    /** @var array<string, int> aula => id della segnalazione */
    private array $id = [];

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
        $this->db->esegui("INSERT INTO utenti (id, nome, cognome, email, persona_id) VALUES (3, 'Luca', 'Referente', 'referente@unical.it', 'P-A'), (4, 'Sara', 'Verdi', 'sara.verdi@studenti.unical.it', NULL)");
        $this->db->esegui("INSERT INTO sos_studenti (nome, cognome, email, attivo, aggiunto_il) VALUES ('Sara', 'Verdi', 'sara.verdi@studenti.unical.it', 1, '2026-10-01 09:00:00')");
        $tipo = (int) $this->db->valore("SELECT id FROM sos_tipi WHERE nome = 'Proiettore o schermo'");
        $docente = (array) $this->db->riga('SELECT * FROM utenti WHERE id = 2');
        foreach (['A', 'B'] as $nome) {
            $this->db->esegui('INSERT INTO personale_ateneo (id, nome, cognome, email, attivo) VALUES (?, ?, ?, ?, 1)', ['P-' . $nome, 'Nome', $nome, strtolower($nome) . '.ref@unical.it']);
            $aula = $this->c->get(AuleRepository::class)->aggiungi(['nome' => 'Aula ' . $nome], 1);
            self::assertTrue($this->c->get(PersoneSos::class)->aggiungiReferente('aula', $aula, 'P-' . $nome, 1)->riuscito);
            $esito = $this->c->get(ServizioSos::class)->invia($docente, ['aula_id' => $aula, 'tipo_id' => $tipo, 'urgenza' => 'ferma', 'descrizione' => 'Guasto in aula ' . $nome], []);
            self::assertTrue($esito->esito->riuscito, $esito->esito->messaggio);
            $this->id[$nome] = $esito->id;
        }
        // la segnalazione dell'aula A è affidata allo studente
        $referente = (array) $this->db->riga('SELECT * FROM utenti WHERE id = 3');
        $affidata = $this->c->get(ServizioSos::class)->affida($this->id['A'], $referente, (int) $this->db->valore('SELECT id FROM sos_studenti'));
        self::assertTrue($affidata->riuscito, $affidata->messaggio);
    }

    private function chiama(string $metodo, array $query = [], array $post = []): Risposta
    {
        $pagina = $this->c->get(PaginaSosInterventi::class);

        return $pagina(Richiesta::finta($metodo, '/sos_interventi.php', $query, $post));
    }

    private function stato(string $aula): string
    {
        return (string) $this->db->valore('SELECT stato FROM sos_segnalazioni WHERE id = ?', [$this->id[$aula]]);
    }

    public function testLoStudenteNonApreUnaSegnalazioneNonSua(): void
    {
        $this->entra(4);
        self::assertStringContainsString('Guasto in aula A', $this->chiama('GET')->corpo(), 'quella affidata a lui c\'è');
        $altra = ['id' => (string) $this->id['B']];

        foreach ([$this->chiama('GET', $altra), $this->chiama('GET', $altra + ['frammento' => '1']), $this->chiama('POST', $altra, ['azione' => 'sto_andando'])] as $r) {
            self::assertSame(403, $r->codice());
            self::assertStringNotContainsString('Guasto in aula B', $r->corpo());
        }
        self::assertSame('inviata', $this->stato('B'));
    }

    public function testIlReferenteNonVedeLeSegnalazioniDiUnAltraAula(): void
    {
        $this->entra(3);

        $tutte = $this->chiama('GET')->corpo() . $this->chiama('GET', ['scheda' => 'in_corso'])->corpo();

        self::assertStringContainsString('Guasto in aula A', $tutte);
        self::assertStringNotContainsString('Guasto in aula B', $tutte);
        $r = $this->chiama('GET', ['id' => (string) $this->id['B']]);
        self::assertSame(403, $r->codice());
        self::assertStringNotContainsString('Guasto in aula B', $r->corpo());
    }

    public function testNonRisoltoSenzaEsitoDaErroreELoStatoNonCambia(): void
    {
        $this->entra(4);
        $studente = (array) $this->db->riga('SELECT * FROM utenti WHERE id = 4');
        self::assertTrue($this->c->get(ServizioSos::class)->staAndando($this->id['A'], $studente)->riuscito);
        $id = ['id' => (string) $this->id['A']];

        $r = $this->chiama('POST', $id, ['azione' => 'non_risolta', 'nota' => 'Non va']);

        self::assertSame(303, $r->codice());
        self::assertSame('sos_interventi.php?id=' . $this->id['A'], $r->intestazione('Location'));
        self::assertSame('in_corso', $this->stato('A'));
        self::assertStringContainsString('Scegli che cosa non ha funzionato.', $this->chiama('GET', $id)->corpo());
    }
}
