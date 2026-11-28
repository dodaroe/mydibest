<?php

declare(strict_types=1);

namespace App\Amministrazione;

use App\Core\Orologio;
use App\Pubblicazioni\ColonneDiChi;
use App\Pubblicazioni\PubblicazioniRepository;
use App\SosAule\SegnalazioniRepository;

/**
 * I numeri di «Cosa devo fare» dell'Amministrazione: un calcolo solo, usato dalla scheda del modulo (admin/amministrazione_home.php),
 * dalla scrivania del pannello (admin/inizio.php), dal menu a sinistra e dalle tessere «Il mio lavoro» dell'area personale.
 * Per le richieste di pubblicazione le definizioni sono quelle della pagina delle richieste (admin/pubblicazioni.php); i numeri si contano
 * nel database (PubblicazioniRepository::contaDaFare).
 */
final class CoseDaFareAmministrazione
{
    public function __construct(
        private PubblicazioniRepository $pubblicazioni,
        private ColonneDiChi $diChi,
        private SegnalazioniRepository $sos,
        private Orologio $orologio,
    ) {
    }

    /** SOS aule: le segnalazioni «Non risolta» che aspettano il referente da più di un giorno (si mostrano solo a chi può tutto). */
    public function sosFerme(): int
    {
        return $this->sos->contaNonRisoltePrimaDi($this->orologio->adesso()->modify('-1 day')->format('Y-m-d H:i:s'));
    }

    /**
     * - ricevute: richieste «ricevute» non ancora nel file Excel (da guardare);
     * - lavorazione: richieste «in lavorazione» non ancora nel file Excel;
     * - da_completare: righe nel file Excel con almeno una colonna degli operatori ancora vuota (Completamento); per chi «Chi compila cosa»
     *   limita alle sue colonne contano solo quelle;
     * - giorni: da quanti giorni aspetta la più vecchia delle «ricevute» (0 se non ce ne sono).
     *
     * @param array<string, mixed>|null $utente la riga di utenti di chi guarda; null = tutte le colonne
     * @return array{ricevute: int, lavorazione: int, da_completare: int, giorni: int}
     */
    public function pubblicazioni(?array $utente = null): array
    {
        $sole = $utente === null || $this->diChi->senzaLimiti($utente) ? null : $this->diChi->scrivibili($utente);

        return $this->pubblicazioni->contaDaFare($sole);
    }
}
