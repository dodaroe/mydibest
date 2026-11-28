<?php

declare(strict_types=1);

namespace Tests\Integration\Portale;

use App\Core\Sito;
use App\Http\Richiesta;
use App\Http\Risposta;
use App\Portale\Pagine\PaginaSosPannello;
use App\SosAule\Costanti;

/** S2, con MariaDB: chi non può non entra; un'aula nuova ha il suo codice; rigenerando il QR il codice vecchio resta riconoscibile. */
final class PaginaSosPannelloTest extends PaginaSosBase
{
    /** @param array<string, string> $post */
    private function invia(array $post): Risposta
    {
        return ($this->c->get(PaginaSosPannello::class))(Richiesta::finta('POST', '/admin/sos_aule.php', [], $post));
    }

    public function testChiNonAmministraIlModuloENegato(): void
    {
        $this->entra(2);

        $pagina = ($this->c->get(PaginaSosPannello::class))(Richiesta::finta('GET', '/admin/sos_aule.php'));
        $invio = $this->invia(['azione' => 'aula_aggiungi', 'nome' => '5C2']);

        self::assertSame([403, 403], [$pagina->codice(), $invio->codice()]);
        self::assertSame(0, (int) $this->db->valore('SELECT COUNT(*) FROM sos_aule'));
    }

    public function testChiNonHaIlPermessoNonScaricaLaFoto(): void
    {
        // una foto vera, in una radice temporanea: così il rifiuto non dipende da un file che manca
        $radice = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'foto_sos_' . bin2hex(random_bytes(4));
        $file = $radice . '/' . Costanti::CARTELLA_FOTO . 'prova.jpg';
        mkdir(dirname($file), 0777, true);
        file_put_contents($file, 'foto finta');
        $this->c->istanza(Sito::class, new Sito($radice, 'https://portale.test/mydibest'));
        $this->db->inserisci("INSERT INTO sos_segnalazioni (codice, aula_id, tipo_id, urgenza, utente_id, nome, cognome, email, stato, inviata_il, foto)
            VALUES ('SOS-2026-0001', 1, 1, 'ferma', 2, 'Maria', 'Docente', '', 'inviata', '2026-11-28 10:00:00', ?)", [Costanti::CARTELLA_FOTO . 'prova.jpg']);
        $scarica = fn (): Risposta => ($this->c->get(PaginaSosPannello::class))(
            Richiesta::finta('GET', '/admin/sos_aule.php', ['scheda' => 'registro', 'foto' => (string) $this->db->valore('SELECT id FROM sos_segnalazioni')])
        );

        $admin = $scarica();
        $this->entra(2);
        $docente = $scarica();

        @unlink($file);
        foreach ([dirname($file), dirname($file, 2), $radice] as $cartella) {
            @rmdir($cartella);
        }
        self::assertSame([200, 403], [$admin->codice(), $docente->codice()]);
    }

    public function testUnAulaAggiuntaHaIlSuoCodice(): void
    {
        $risposta = $this->invia(['azione' => 'aula_aggiungi', 'nome' => '5C2', 'edificio' => 'Cubo 5C']);

        self::assertSame(303, $risposta->codice());
        $aula = (array) $this->db->riga('SELECT * FROM sos_aule');
        self::assertSame(['5C2', 'Cubo 5C'], [$aula['nome'], $aula['edificio']]);
        self::assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $aula['codice']);

        $elenco = ($this->c->get(PaginaSosPannello::class))(Richiesta::finta('GET', '/admin/sos_aule.php'));
        self::assertSame(200, $elenco->codice());
        self::assertStringContainsString('sos.php?aula=' . $aula['codice'], $elenco->corpo());
    }

    public function testRigenerandoIlCodiceCambiaEIlVecchioResta(): void
    {
        $this->invia(['azione' => 'aula_aggiungi', 'nome' => '5C2']);
        $prima = (array) $this->db->riga('SELECT id, codice FROM sos_aule');

        $this->invia(['azione' => 'aula_rigenera', 'id' => (string) $prima['id']]);

        $dopo = (array) $this->db->riga('SELECT codice, codici_vecchi FROM sos_aule');
        self::assertNotSame($prima['codice'], $dopo['codice']);
        self::assertSame([$prima['codice']], json_decode((string) $dopo['codici_vecchi'], true));
    }
}
