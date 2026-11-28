<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Core\Orologio;

/**
 * I compiti automatici di SOS aule, chiamati da cron_background.php:
 * - promemoria: le segnalazioni «In attesa» da più dei minuti scelti (diversi per «La lezione è ferma» e per le altre urgenze, 0 = mai) ricevono
 *   un solo promemoria, a referenti dell'aula e riserva;
 * - foto: quelle delle segnalazioni chiuse da più dei giorni di conservazione si cancellano; la segnalazione resta.
 * Girano anche a servizio spento: le segnalazioni già aperte aspettano comunque qualcuno.
 */
final class CronSos
{
    public function __construct(
        private SegnalazioniRepository $segnalazioni,
        private ImpostazioniSos $impostazioni,
        private NotificheSos $notifiche,
        private FotoSos $foto,
        private Orologio $orologio,
    ) {
    }

    /** Le righe da stampare nel resoconto del cron (vuoto se non c'era niente da fare). */
    public function cron(): string
    {
        $promemoria = $this->promemoria();
        $foto = $this->cancellaFotoVecchie();

        return ($promemoria > 0 ? "- SOS aule: promemoria mandati per $promemoria segnalazioni in attesa.\n" : '')
            . ($foto > 0 ? "- SOS aule: $foto foto di segnalazioni chiuse cancellate.\n" : '');
    }

    /** @return int quante segnalazioni hanno avuto il promemoria */
    public function promemoria(): int
    {
        $imp = $this->impostazioni->tutte();
        $adesso = $this->orologio->adesso();
        $limite = static fn (int $minuti): ?string => $minuti > 0 ? $adesso->modify("-$minuti minutes")->format('Y-m-d H:i:s') : null;
        $quante = 0;
        foreach ($this->segnalazioni->daSollecitare($limite((int) $imp['promemoria_ferma']), $limite((int) $imp['promemoria_altre'])) as $s) {
            // prima si segna e poi si manda: due cron sovrapposti non mandano due volte
            if ($this->segnalazioni->segnaPromemoria((int) $s['id'], $adesso->format('Y-m-d H:i:s'))) {
                $this->notifiche->promemoria((int) $s['id'], max(0, intdiv($adesso->getTimestamp() - (int) strtotime((string) $s['inviata_il']), 60)));
                $quante++;
            }
        }

        return $quante;
    }

    /** @return int quante foto sono state cancellate */
    public function cancellaFotoVecchie(): int
    {
        $giorni = max(1, (int) $this->impostazioni->tutte()['conserva_foto_giorni']);
        $quante = 0;
        foreach ($this->segnalazioni->fotoDiChiusePrimaDi($this->orologio->adesso()->modify("-$giorni days")->format('Y-m-d H:i:s')) as $s) {
            $percorso = (string) $s['foto'];
            $this->foto->elimina($percorso);
            // se il file c'è ancora (permessi) la riga resta com'è, e si riprova al giro dopo
            if ($this->foto->percorsoAssoluto($percorso) !== null) {
                error_log('[SOS aule] foto non cancellata (segnalazione ' . $s['id'] . ')');
                continue;
            }
            $this->segnalazioni->togliFoto((int) $s['id']);
            $quante++;
        }

        return $quante;
    }
}
