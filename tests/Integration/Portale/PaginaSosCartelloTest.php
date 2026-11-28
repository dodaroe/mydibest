<?php

declare(strict_types=1);

namespace Tests\Integration\Portale;

use App\Http\Richiesta;
use App\Portale\Pagine\PaginaSosCartello;
use App\SosAule\AuleRepository;

/** S2, con MariaDB: il cartello di un'aula porta il codice del suo QR e il telefono di riserva. */
final class PaginaSosCartelloTest extends PaginaSosBase
{
    public function testIlCartelloHaIlCodiceDellAulaEIlTelefonoDiRiserva(): void
    {
        $aule = $this->c->get(AuleRepository::class);
        $id = $aule->aggiungi(['nome' => '5C2', 'edificio' => 'Cubo 5C'], 1);
        $codice = (string) $aule->perId($id)['codice'];
        $this->db->esegui("UPDATE impostazioni_sistema SET sos_telefono_riserva = '0984 491234' WHERE id = 1");

        $risposta = ($this->c->get(PaginaSosCartello::class))(Richiesta::finta('GET', '/admin/sos_cartello.php', ['aula' => (string) $id]));

        self::assertSame(200, $risposta->codice());
        self::assertStringContainsString('data-qr="https://portale.test/mydibest/sos.php?aula=' . $codice . '"', $risposta->corpo());
        self::assertStringContainsString('0984 491234', $risposta->corpo());
    }
}
