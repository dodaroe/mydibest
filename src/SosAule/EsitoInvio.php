<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Core\Esito;

/** L'esito di un invio: l'Esito di sempre, più l'id della segnalazione creata e gli errori campo per campo (per ripopolare il form). */
final class EsitoInvio
{
    /** @param array<string, string> $errori */
    public function __construct(
        public readonly Esito $esito,
        public readonly int $id = 0,
        public readonly array $errori = [],
    ) {
    }
}
