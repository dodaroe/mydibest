<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Core\Sito;
use App\Infrastructure\Storage\Upload;
use App\Pubblicazioni\AllegatiPubblicazioni;

/**
 * Le foto delle segnalazioni: gli stessi controlli e lo stesso salvataggio degli allegati delle richieste di pubblicazione (AllegatiPubblicazioni,
 * tipo «immagine»: JPG o PNG controllati sul contenuto), in una cartella loro, bloccata al web.
 *
 * $soloFileCaricati = false serve solo alle prove automatiche.
 */
final class FotoSos
{
    private AllegatiPubblicazioni $allegati;

    public function __construct(private Upload $upload, private Sito $sito, private string $cartella = Costanti::CARTELLA_FOTO, bool $soloFileCaricati = true)
    {
        $this->allegati = new AllegatiPubblicazioni($upload, $sito, $cartella, $soloFileCaricati);
    }

    /** @param array<string, mixed> $file elemento di $_FILES @return string|null percorso relativo alla radice del sito */
    public function salva(array $file): ?string
    {
        // il .htaccess si scrive qui, prima, con il motivo giusto (AllegatiPubblicazioni non lo riscrive)
        $this->upload->cartellaProtetta($this->sito->radice() . '/' . $this->cartella, 'Foto delle segnalazioni SOS aule: si vedono solo dalle pagine del portale');

        return $this->allegati->salva($file, 'immagine');
    }

    public function percorsoAssoluto(string $relativo): ?string
    {
        return $this->allegati->percorsoAssoluto($relativo);
    }

    public function elimina(string $relativo): void
    {
        $this->allegati->elimina($relativo);
    }
}
