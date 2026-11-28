<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Core\Orologio;

/**
 * Quello che aspetta chi interviene, diviso nelle schede di sos_interventi.php: lo usano la pagina e il numero della tessera in «Il mio lavoro».
 *
 * - Chi è ammesso: referente di almeno un'aula, responsabile di riserva o studente in servizio oggi (PersoneSos).
 * - Il referente vede le segnalazioni delle sue aule (la riserva tutte); lo studente solo quelle affidate a lui.
 * - In ogni scheda prima «La lezione è ferma», poi la più vecchia.
 */
final class CodaInterventi
{
    public function __construct(private PersoneSos $persone, private SegnalazioniRepository $segnalazioni, private Orologio $orologio)
    {
    }

    /** @param array<string, mixed>|null $utente la riga di utenti */
    public function ammesso(?array $utente): bool
    {
        return $this->persone->eReferente($utente) || $this->persone->studenteDi($utente) !== null;
    }

    /**
     * @param array<string, mixed> $utente la riga di utenti
     * @return array{da_fare: list<array<string, mixed>>, in_corso: list<array<string, mixed>>, chiuse: list<array<string, mixed>>} righe di SegnalazioniRepository
     */
    public function schede(array $utente): array
    {
        $aule = $this->persone->auleDelReferente($utente);
        $studente = $this->persone->studenteDi($utente);
        $oggi = $this->orologio->adesso()->format('Y-m-d 00:00:00');
        $schede = ['da_fare' => [], 'in_corso' => [], 'chiuse' => []];
        $viste = [];
        // prima le righe da referente: chi è sia referente sia studente le vede nelle schede del referente
        foreach (array_keys(Costanti::SCHEDE) as $ruolo) {
            if ($ruolo === Costanti::REFERENTE ? $aule === [] : $studente === null) {
                continue;
            }
            foreach (Costanti::SCHEDE[$ruolo] + ['chiuse' => Costanti::STATI_CHIUSI] as $scheda => $stati) {
                $dal = $scheda === 'chiuse' ? $oggi : null;
                $righe = $ruolo === Costanti::REFERENTE
                    ? $this->segnalazioni->perReferente($aule, $stati, $dal)
                    : $this->segnalazioni->perStudente((int) $studente['id'], $stati, $dal);
                foreach ($righe as $r) {
                    if (!isset($viste[(int) $r['id']])) {
                        $viste[(int) $r['id']] = true;
                        $schede[$scheda][] = $r;
                    }
                }
            }
        }
        foreach ($schede as &$righe) {
            usort($righe, static fn (array $a, array $b): int => [$a['urgenza'] !== 'ferma', (string) $a['inviata_il'], (int) $a['id']] <=> [$b['urgenza'] !== 'ferma', (string) $b['inviata_il'], (int) $b['id']]);
        }
        unset($righe);

        return $schede;
    }
}
