<?php

declare(strict_types=1);

namespace Tests\Unit\SosAule;

use App\Core\Container;
use App\SosAule\CampiSos;
use App\SosAule\Costanti;
use App\SosAule\PersoneSos;
use App\SosAule\RegoleSos;
use PHPUnit\Framework\TestCase;

/** S1, senza database: chi può fare quale passaggio, i controlli sulla descrizione e la regola dello studente. */
final class RegoleSosTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string, 2: string, 3: bool}> */
    public static function transizioni(): array
    {
        return [
            'il docente annulla quella in attesa' => ['inviata', 'annullata', Costanti::DOCENTE, true],
            'il docente non la prende in carico' => ['inviata', 'presa', Costanti::DOCENTE, false],
            'il referente la prende in carico' => ['inviata', 'presa', Costanti::REFERENTE, true],
            'il referente non riapre quella risolta' => ['risolta', 'non_risolta', Costanti::REFERENTE, false],
            'lo studente sta andando' => ['assegnata', 'in_corso', Costanti::STUDENTE, true],
            'lo studente non chiude quella solo affidata' => ['assegnata', 'risolta', Costanti::STUDENTE, false],
            'lo studente non la dà per non risolta prima di andare' => ['assegnata', 'non_risolta', Costanti::STUDENTE, false],
            'il referente segna risolta quella affidata' => ['assegnata', 'risolta', Costanti::REFERENTE, true],
            'lo studente non prende quella in attesa' => ['inviata', 'presa', Costanti::STUDENTE, false],
        ];
    }

    /** @dataProvider transizioni */
    public function testChiPuoFareIlPassaggio(string $da, string $a, string $ruolo, bool $ammesso): void
    {
        self::assertSame($ammesso, Costanti::ammessa($da, $a, $ruolo));
    }

    public function testDescrizioneAl500EAltroSenzaDescrizione(): void
    {
        $campi = [
            'fissi' => CampiSos::FISSI,
            'aggiunti' => [],
            'aule' => [1 => ['id' => 1]],
            'tipi' => [1 => ['id' => 1, 'chiede_testo' => 0], 9 => ['id' => 9, 'chiede_testo' => 1]],
        ];
        $dati = ['aula_id' => '1', 'tipo_id' => '1', 'urgenza' => 'ferma'];
        $regole = new RegoleSos(new Container());

        self::assertSame([], $regole->valida($dati + ['descrizione' => str_repeat('è', 500)], [], $campi), '500 caratteri accentati (1000 byte) passano');
        self::assertSame(['descrizione'], array_keys($regole->valida($dati + ['descrizione' => str_repeat('è', 501)], [], $campi)));
        self::assertSame(['descrizione'], array_keys($regole->valida(['tipo_id' => '9', 'descrizione' => '   '] + $dati, [], $campi)), '«Altro» chiede il testo');
    }

    public function testLoStudenteValeSoloConLEmailVerificata(): void
    {
        $studenti = [['id' => 1, 'email' => 'sara.verdi@studenti.unical.it', 'attivo' => 1, 'dal' => '2026-10-01', 'al' => '2026-12-31']];
        $utente = ['id' => 7, 'email' => 'Sara.Verdi@studenti.unical.it', 'email_personalizzata' => 0, 'email_verificata_il' => null];

        self::assertSame(1, PersoneSos::studenteValido($utente, $studenti, '2026-11-28')['id'] ?? null);
        self::assertNull(PersoneSos::studenteValido(['email_personalizzata' => 1] + $utente, $studenti, '2026-11-28'), 'scritta a mano nel profilo e mai confermata: niente');
        self::assertNull(PersoneSos::studenteValido($utente, $studenti, '2027-01-01'), 'fuori dal periodo: niente');
    }
}
