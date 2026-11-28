<?php

declare(strict_types=1);

namespace App\SosAule;

/**
 * I numeri del registro di SOS aule, calcolati dalle segnalazioni del periodo e dalle loro cronologie. Nessun accesso al database.
 *
 * - Tempo fino alla presa in carico: dall'invio alla prima riga di cronologia «presa» o «affidata a uno studente».
 * - Tempo fino alla chiusura: dall'invio all'ultima chiusura, solo per quelle che oggi sono risolte o chiuse senza soluzione (le annullate non contano).
 * - Risolte al primo intervento: risolte che non sono mai state «non risolte» (né dallo studente né da chi ha segnalato).
 * - Aule malate: stessa aula e stesso tipo di problema da Costanti::SOGLIA_AULA_MALATA segnalazioni in su, senza contare le annullate.
 */
final class NumeriSos
{
    /**
     * @param list<array<string, mixed>> $segnalazioni con id, aula_id, aula_nome, tipo_id, tipo_nome, stato, inviata_il
     * @param array<int, list<array<string, mixed>>> $cronologie id della segnalazione => righe dalla più vecchia (quando, a_stato)
     * @return array{quante: int, perAula: list<array{nome: string, quante: int}>, perTipo: list<array{nome: string, quante: int}>,
     *               presa: array{minuti: int|null, quante: int}, chiusura: array{minuti: int|null, quante: int}, risolte: int, alPrimo: int,
     *               malate: list<array{aula: string, tipo: string, quante: int}>}
     */
    public static function calcola(array $segnalazioni, array $cronologie): array
    {
        $perAula = [];
        $perTipo = [];
        $coppie = [];
        $presa = [];
        $chiusura = [];
        $risolte = 0;
        $alPrimo = 0;
        foreach ($segnalazioni as $s) {
            $aula = (string) $s['aula_nome'];
            $tipo = (string) $s['tipo_nome'];
            $perAula[(int) $s['aula_id']] ??= ['nome' => $aula, 'quante' => 0];
            $perAula[(int) $s['aula_id']]['quante']++;
            $perTipo[(int) $s['tipo_id']] ??= ['nome' => $tipo, 'quante' => 0];
            $perTipo[(int) $s['tipo_id']]['quante']++;
            $stato = (string) $s['stato'];
            if ($stato !== 'annullata') {
                $k = (int) $s['aula_id'] . '|' . (int) $s['tipo_id'];
                $coppie[$k] ??= ['aula' => $aula, 'tipo' => $tipo, 'quante' => 0];
                $coppie[$k]['quante']++;
            }

            $inviata = (int) strtotime((string) $s['inviata_il']);
            $presaIl = null;
            $chiusaIl = null;
            $tornata = false;
            foreach ($cronologie[(int) $s['id']] ?? [] as $c) {
                $a = (string) $c['a_stato'];
                if ($presaIl === null && ($a === 'presa' || $a === 'assegnata')) {
                    $presaIl = (int) strtotime((string) $c['quando']);
                }
                if (($stato === 'risolta' || $stato === 'chiusa_non_risolta') && $a === $stato) {
                    $chiusaIl = (int) strtotime((string) $c['quando']);
                }
                $tornata = $tornata || $a === 'non_risolta';
            }
            if ($presaIl !== null) {
                $presa[] = max(0, $presaIl - $inviata);
            }
            if ($chiusaIl !== null) {
                $chiusura[] = max(0, $chiusaIl - $inviata);
            }
            if ($stato === 'risolta') {
                $risolte++;
                $alPrimo += $tornata ? 0 : 1;
            }
        }

        $perQuante = static fn (array $a, array $b): int => [$b['quante'], $a['nome'] ?? $a['aula'], $a['tipo'] ?? ''] <=> [$a['quante'], $b['nome'] ?? $b['aula'], $b['tipo'] ?? ''];
        $perAula = array_values($perAula);
        $perTipo = array_values($perTipo);
        $malate = array_values(array_filter($coppie, static fn (array $c): bool => $c['quante'] >= Costanti::SOGLIA_AULA_MALATA));
        usort($perAula, $perQuante);
        usort($perTipo, $perQuante);
        usort($malate, $perQuante);

        $media = static fn (array $secondi): array => ['minuti' => $secondi === [] ? null : (int) round(array_sum($secondi) / count($secondi) / 60), 'quante' => count($secondi)];

        return [
            'quante' => count($segnalazioni), 'perAula' => $perAula, 'perTipo' => $perTipo,
            'presa' => $media($presa), 'chiusura' => $media($chiusura), 'risolte' => $risolte, 'alPrimo' => $alPrimo, 'malate' => $malate,
        ];
    }

    /** «12 minuti», «1 ora e 20 minuti», «2 giorni e 3 ore»; null = «—». */
    public static function durata(?int $minuti): string
    {
        if ($minuti === null) {
            return '—';
        }
        $parte = static fn (int $n, string $uno, string $tanti): string => $n === 0 ? '' : $n . ' ' . ($n === 1 ? $uno : $tanti);
        [$grande, $piccola] = $minuti >= 1440
            ? [$parte(intdiv($minuti, 1440), 'giorno', 'giorni'), $parte(intdiv($minuti % 1440, 60), 'ora', 'ore')]
            : [$parte(intdiv($minuti, 60), 'ora', 'ore'), $parte($minuti % 60, 'minuto', 'minuti')];

        return implode(' e ', array_filter([$grande, $piccola])) ?: 'meno di un minuto';
    }
}
