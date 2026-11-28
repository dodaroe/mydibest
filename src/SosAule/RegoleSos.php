<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Core\Container;
use App\Didattica\CampiModulo;
use App\Didattica\LetturaRisposte;
use App\Pubblicazioni\AllegatiPubblicazioni;

/**
 * I controlli sui dati di una segnalazione. Non legge il database: i campi del form, le aule e i tipi attivi glieli passa chi chiama
 * (ServizioSos::campiDelForm).
 */
final class RegoleSos
{
    /** LetturaRisposte (e le anagrafi che si porta dietro) si prende solo quando c'è un campo aggiunto da leggere. */
    public function __construct(private Container $contenitore)
    {
    }

    /**
     * @param array<string, mixed> $dati aula_id, tipo_id, urgenza, descrizione, telefono, insegnamento_id, x_<n>
     * @param array<string, mixed> $file elementi di $_FILES: «foto»
     * @param array{fissi: array<string, array<string, mixed>>, aggiunti: list<array<string, mixed>>, aule: array<int, array<string, mixed>>, tipi: array<int, array<string, mixed>>} $campi
     * @return array<string, string> errori per campo (vuoto = va bene)
     */
    public function valida(array $dati, array $file, array $campi): array
    {
        $fissi = $campi['fissi'];
        $errori = [];
        if (!isset($campi['aule'][(int) self::testo($dati, 'aula_id')])) {
            $errori['aula_id'] = 'Scegli l\'aula dall\'elenco.';
        }
        $tipo = $campi['tipi'][(int) self::testo($dati, 'tipo_id')] ?? null;
        if ($tipo === null) {
            $errori['tipo_id'] = 'Scegli il tipo di problema.';
        }
        if (!isset(Costanti::URGENZE[self::testo($dati, 'urgenza')])) {
            $errori['urgenza'] = 'Indica quanto è urgente.';
        }

        $descrizione = self::testo($dati, 'descrizione');
        if (mb_strlen($descrizione) > Costanti::MAX_DESCRIZIONE) {
            $errori['descrizione'] = 'Il testo è troppo lungo (massimo ' . Costanti::MAX_DESCRIZIONE . ' caratteri).';
        } elseif ($descrizione === '' && (!empty($tipo['chiede_testo']) || $fissi['descrizione']['obbligatorio'])) {
            $errori['descrizione'] = 'Descrivi il problema.';
        }

        if ($fissi['telefono']['visibile']) {
            $telefono = self::testo($dati, 'telefono');
            if (!self::telefonoValido($telefono)) {
                $errori['telefono'] = 'Il telefono può avere solo cifre, spazi e +.';
            } elseif ($telefono === '' && $fissi['telefono']['obbligatorio']) {
                $errori['telefono'] = 'Lascia un numero per essere richiamato.';
            }
        }

        if ($fissi['insegnamento']['visibile']) {
            $insegnamento = self::testo($dati, 'insegnamento_id');
            if ($insegnamento !== '' && !ctype_digit($insegnamento)) {
                $errori['insegnamento_id'] = 'Scegli l\'insegnamento dall\'elenco.';
            } elseif ($insegnamento === '' && $fissi['insegnamento']['obbligatorio']) {
                $errori['insegnamento_id'] = 'Indica l\'insegnamento.';
            }
        }

        if ($fissi['foto']['visibile']) {
            $foto = self::foto($file);
            if ($foto === null) {
                if ($fissi['foto']['obbligatorio']) {
                    $errori['foto'] = 'Allega una foto.';
                }
            } elseif (is_array($foto['name'] ?? null)) {
                $errori['foto'] = 'Allega una sola foto.';
            } elseif (($errore = AllegatiPubblicazioni::errore($foto, 'immagine')) !== null) {
                $errori['foto'] = $errore;
            }
        }

        return $errori + $this->aggiunti($dati, $campi['aggiunti'])['errori'];
    }

    /**
     * I valori dei campi aggiunti visibili, letti e controllati con lo stesso codice di MyForms (LetturaRisposte). I valori vuoti non si tengono.
     *
     * @param array<string, mixed> $dati
     * @param list<array<string, mixed>> $aggiunti CampiSos::aggiunti()
     * @return array{valori: array<string, string>, errori: array<string, string>}
     */
    public function aggiunti(array $dati, array $aggiunti): array
    {
        $valori = [];
        $errori = [];
        foreach ($aggiunti as $c) {
            if (!$c['visibile']) {
                continue;
            }
            $campi = CampiModulo::da((string) json_encode([[
                'etichetta' => $c['etichetta'], 'tipo' => $c['tipo'], 'opzioni' => implode(', ', $c['opzioni']), 'obbligatorio' => $c['obbligatorio'], 'aiuto' => '',
            ]], JSON_UNESCAPED_UNICODE));
            if ($campi === []) {
                continue;
            }
            $grezzo = $dati[$c['chiave']] ?? '';
            if (is_array($grezzo) && $c['tipo'] !== 'multicheck') {
                $grezzo = ''; // un elenco dove ci vuole un valore solo: si ignora
            }
            [$risposte, $segnalazioni] = $this->contenitore->get(LetturaRisposte::class)->leggi($campi, ['campo_' . $campi[0]['nome'] => $grezzo], [], false);
            if ($segnalazioni !== []) {
                $errori[$c['chiave']] = (string) $segnalazioni[0];
            }
            $v = trim((string) ($risposte[0]['valore'] ?? ''));
            if ($v !== '') {
                $valori[$c['chiave']] = $v;
            }
        }

        return ['valori' => $valori, 'errori' => $errori];
    }

    /** Vuoto va bene (se è obbligatorio lo dice chi chiama); altrimenti cifre, spazi e «+», con almeno una cifra. */
    public static function telefonoValido(string $telefono): bool
    {
        return $telefono === '' || (strlen($telefono) <= Costanti::MAX_TELEFONO && preg_match('/^[0-9 +]+$/', $telefono) === 1 && preg_match('/\d/', $telefono) === 1);
    }

    /** Un testo del form ripulito (un elenco dove ci vuole un testo vale vuoto; gli a capo di Windows contano un carattere). @param array<string, mixed> $dati */
    public static function testo(array $dati, string $chiave): string
    {
        $v = $dati[$chiave] ?? '';

        return is_scalar($v) ? trim(str_replace("\r\n", "\n", (string) $v)) : '';
    }

    /** La foto allegata, o null se la casella è rimasta vuota. @param array<string, mixed> $file @return array<string, mixed>|null */
    public static function foto(array $file): ?array
    {
        $foto = $file['foto'] ?? null;

        return is_array($foto) && ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $foto : null;
    }
}
