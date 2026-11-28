<?php

declare(strict_types=1);

namespace Tests\Unit\SosAule;

use App\SosAule\NumeriSos;
use PHPUnit\Framework\TestCase;

/** S6: i tempi medi del registro, da una cronologia finta. */
final class NumeriSosTest extends TestCase
{
    public function testTempoMedioFinoAllaPresaEAllaChiusura(): void
    {
        $segnalazione = static fn (int $id, string $stato): array => ['id' => $id, 'aula_id' => 1, 'aula_nome' => '5C2', 'tipo_id' => 1, 'tipo_nome' => 'Proiettore', 'stato' => $stato, 'inviata_il' => '2026-11-20 10:00:00'];
        $riga = static fn (string $ora, string $a): array => ['quando' => '2026-11-20 ' . $ora . ':00', 'a_stato' => $a];

        $n = NumeriSos::calcola(
            [$segnalazione(1, 'risolta'), $segnalazione(2, 'risolta'), $segnalazione(3, 'inviata')],
            [
                // presa dopo 10 minuti (poi affidata: conta la prima), chiusa dopo 40
                1 => [$riga('10:00', 'inviata'), $riga('10:10', 'presa'), $riga('10:15', 'assegnata'), $riga('10:40', 'risolta')],
                // affidata dopo 20; chiusa, riaperta e richiusa: conta l'ultima chiusura, 100 minuti
                2 => [$riga('10:00', 'inviata'), $riga('10:20', 'assegnata'), $riga('10:30', 'risolta'), $riga('10:50', 'non_risolta'), $riga('11:00', 'presa'), $riga('11:40', 'risolta')],
                3 => [$riga('10:00', 'inviata'), $riga('10:05', '')],
            ]
        );

        self::assertSame(['minuti' => 15, 'quante' => 2], $n['presa']);
        self::assertSame(['minuti' => 70, 'quante' => 2], $n['chiusura']);
        self::assertSame([2, 1], [$n['risolte'], $n['alPrimo']]);
        self::assertSame('1 ora e 10 minuti', NumeriSos::durata($n['chiusura']['minuti']));
        self::assertSame([['aula' => '5C2', 'tipo' => 'Proiettore', 'quante' => 3]], $n['malate']);
    }
}
