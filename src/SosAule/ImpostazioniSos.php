<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Auth\Pubblico;
use App\Core\Database;
use Throwable;

/**
 * Le impostazioni di SOS aule (colonne sos_* di impostazioni_sistema, riga id = 1): la sola classe che le legge e le salva.
 * Una colonna che non c'è ancora (caricamento a metà) vale come il valore di partenza.
 * «aperto» nel database è l'interruttore dell'amministratore; aperto() dice se il servizio è davvero attivo: interruttore acceso E almeno una persona che riceve.
 */
final class ImpostazioniSos
{
    public const INIZIALI = [
        'aperto' => false,
        'chi' => [Pubblico::DOCENTI, Pubblico::PTA, Pubblico::PERSONALE],
        'telefono_riserva' => '',
        'orari' => '',              // vuoto = sempre
        'promemoria_ferma' => 10,   // minuti, 0 = mai
        'promemoria_altre' => 60,
        'campi' => '',              // JSON di CampiSos, vuoto = valori del codice
        'testo_introduttivo' => '',
        'conserva_foto_giorni' => 90,
    ];

    /** I numeri: [minimo, massimo]. */
    public const LIMITI = [
        'promemoria_ferma' => [0, 1440],
        'promemoria_altre' => [0, 1440],
        'conserva_foto_giorni' => [1, 1095],
    ];

    /** Impostazione => colonna di impostazioni_sistema. */
    private const COLONNE = [
        'aperto' => 'sos_aperto',
        'chi' => 'sos_chi',
        'telefono_riserva' => 'sos_telefono_riserva',
        'orari' => 'sos_orari',
        'promemoria_ferma' => 'sos_promemoria_ferma',
        'promemoria_altre' => 'sos_promemoria_altre',
        'campi' => 'sos_campi',
        'testo_introduttivo' => 'sos_testo_introduttivo',
        'conserva_foto_giorni' => 'sos_conserva_foto_giorni',
    ];

    private const GIORNI = ['lun' => 1, 'mar' => 2, 'mer' => 3, 'gio' => 4, 'ven' => 5, 'sab' => 6, 'dom' => 7];

    /** Lunghezza massima dei testi. */
    private const MAX_TESTI = ['orari' => 600, 'testo_introduttivo' => 3000, 'campi' => 60000];

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(private Database $db, private PersoneSos $persone)
    {
    }

    /** @return array<string, mixed> chiavi e tipi come INIZIALI */
    public function tutte(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        try {
            $riga = $this->db->riga('SELECT * FROM impostazioni_sistema WHERE id = 1') ?? [];
        } catch (Throwable $e) {
            error_log('[SOS aule] impostazioni non lette: ' . $e->getMessage());
            $riga = [];
        }
        $v = self::INIZIALI;
        foreach (self::COLONNE as $k => $colonna) {
            if (!array_key_exists($colonna, $riga) || $riga[$colonna] === null) {
                continue;
            }
            $x = $riga[$colonna];
            $v[$k] = match (true) {
                $k === 'aperto' => (int) $x === 1,
                $k === 'chi' => self::profili(explode(',', (string) $x)),
                isset(self::LIMITI[$k]) => max(self::LIMITI[$k][0], min(self::LIMITI[$k][1], (int) $x)),
                default => (string) $x,
            };
        }

        return $this->cache = $v;
    }

    /** Se il servizio è attivo: interruttore acceso e qualcuno che riceverebbe le segnalazioni. */
    public function aperto(): bool
    {
        return $this->tutte()['aperto'] && $this->persone->qualcunoRiceve();
    }

    /** Perché il servizio non è attivo (null se lo è): per la scheda dell'amministratore. */
    public function motivoChiusura(): ?string
    {
        if (!$this->persone->qualcunoRiceve()) {
            return 'Nessuno riceverebbe le segnalazioni: serve almeno un\'aula attiva con un referente con email, o un responsabile di riserva.';
        }

        return $this->tutte()['aperto'] ? null : 'Il servizio è spento.';
    }

    /** @return list<string> i profili di Pubblico che possono inviare */
    public function chi(): array
    {
        return $this->tutte()['chi'];
    }

    /**
     * Salva le impostazioni presenti in $dati (le altre restano): se anche una sola non va, non si salva niente.
     *
     * @param array<string, mixed> $dati
     * @return array{errori: array<string, string>, cambi: array<string, array{da: mixed, a: mixed}>}
     */
    public function aggiorna(array $dati): array
    {
        $valori = [];
        $errori = [];
        foreach ($dati as $k => $x) {
            if (!isset(self::COLONNE[$k])) {
                continue;
            }
            if ($k === 'aperto') {
                $valori[$k] = (bool) $x;
            } elseif ($k === 'chi') {
                $valori[$k] = self::profili(array_map('strval', (array) $x));
                if ($valori[$k] === []) {
                    $errori[$k] = 'Scegli almeno un profilo che può inviare la segnalazione.';
                }
            } elseif (isset(self::LIMITI[$k])) {
                [$min, $max] = self::LIMITI[$k];
                $s = trim((string) $x);
                if ($s === '' || !ctype_digit($s) || (int) $s < $min || (int) $s > $max) {
                    $errori[$k] = 'Scrivi un numero intero da ' . $min . ' a ' . $max . '.';
                } else {
                    $valori[$k] = (int) $s;
                }
            } elseif ($k === 'telefono_riserva') {
                $valori[$k] = trim((string) $x);
                if (!RegoleSos::telefonoValido($valori[$k])) {
                    $errori[$k] = 'Il telefono può avere solo cifre, spazi e +.';
                }
            } else {
                // «campi» è il JSON di CampiSos; gli altri sono testo semplice
                $valori[$k] = $k === 'campi' ? (string) $x : trim(strip_tags(str_replace("\r\n", "\n", (string) $x)));
                if (mb_strlen($valori[$k]) > self::MAX_TESTI[$k]) {
                    $errori[$k] = 'Il testo è troppo lungo (massimo ' . self::MAX_TESTI[$k] . ' caratteri).';
                } elseif ($k === 'orari' && ($riga = self::rigaOrariSbagliata($valori[$k])) !== null) {
                    $errori[$k] = 'La riga «' . $riga . '» non è scritta come «lun-ven 8-19» (un giorno o due giorni col trattino, uno spazio, dalle-alle).';
                }
            }
        }
        if ($errori) {
            return ['errori' => $errori, 'cambi' => []];
        }
        $attuali = $this->tutte();
        if (($valori['aperto'] ?? false) && !$attuali['aperto'] && !$this->persone->qualcunoRiceve()) {
            return ['errori' => ['aperto' => (string) $this->motivoChiusura()], 'cambi' => []];
        }
        $cambi = [];
        $set = [];
        $parametri = [];
        foreach ($valori as $k => $nuovo) {
            if ($nuovo === $attuali[$k]) {
                continue;
            }
            $cambi[$k] = ['da' => $attuali[$k], 'a' => $nuovo];
            $set[] = self::COLONNE[$k] . ' = ?';
            $parametri[] = match (true) {
                $k === 'chi' => implode(',', $nuovo),
                $k === 'aperto' => $nuovo ? 1 : 0,
                default => $nuovo,
            };
        }
        if ($set) {
            if ($this->db->esegui('UPDATE impostazioni_sistema SET ' . implode(', ', $set) . ' WHERE id = 1', $parametri) < 0) {
                return ['errori' => ['_' => 'Non è stato possibile salvare le impostazioni: riprova tra qualche minuto.'], 'cambi' => []];
            }
            $this->cache = null;
        }

        return ['errori' => [], 'cambi' => $cambi];
    }

    /**
     * Gli orari di presidio: una riga per fascia, «giorni ore». Giorni: «lun» oppure «lun-ven» (lun mar mer gio ven sab dom); ore: «8-19» o «8:30-13:30».
     * Le righe vuote non contano; testo vuoto = presidio sempre.
     *
     * @return list<array{dal: int, al: int, inizio: int, fine: int}>|null giorni 1-7 (lunedì = 1) e minuti dalla mezzanotte; null se una riga non si capisce
     */
    public static function fasce(string $testo): ?array
    {
        $fasce = [];
        foreach (preg_split('/\R/', $testo) ?: [] as $riga) {
            if (trim($riga) !== '') {
                $f = self::fascia($riga);
                if ($f === null) {
                    return null;
                }
                $fasce[] = $f;
            }
        }

        return $fasce;
    }

    /** Se in quel momento non c'è nessuno di presidio. Orari vuoti (o scritti male) = presidio sempre. */
    public function fuoriOrario(\DateTimeInterface $quando): bool
    {
        $fasce = self::fasce((string) $this->tutte()['orari']);
        if (!$fasce) {
            return false;
        }
        $giorno = (int) $quando->format('N');
        $minuti = (int) $quando->format('G') * 60 + (int) $quando->format('i');
        foreach ($fasce as $f) {
            if ($giorno >= $f['dal'] && $giorno <= $f['al'] && $minuti >= $f['inizio'] && $minuti < $f['fine']) {
                return false;
            }
        }

        return true;
    }

    private static function rigaOrariSbagliata(string $testo): ?string
    {
        foreach (preg_split('/\R/', $testo) ?: [] as $riga) {
            if (trim($riga) !== '' && self::fascia($riga) === null) {
                return trim($riga);
            }
        }

        return null;
    }

    /** @return array{dal: int, al: int, inizio: int, fine: int}|null */
    private static function fascia(string $riga): ?array
    {
        $giorno = '(' . implode('|', array_keys(self::GIORNI)) . ')';
        $ora = '(\d{1,2})(?:[:.](\d{2}))?';
        if (!preg_match('/^' . $giorno . '(?:\s*-\s*' . $giorno . ')?\s+' . $ora . '\s*-\s*' . $ora . '$/', mb_strtolower(trim($riga)), $m, PREG_UNMATCHED_AS_NULL)) {
            return null;
        }
        $dal = self::GIORNI[$m[1]];
        $al = $m[2] === null ? $dal : self::GIORNI[$m[2]];
        $inizio = (int) $m[3] * 60 + (int) $m[4];
        $fine = (int) $m[5] * 60 + (int) $m[6];
        if ($al < $dal || (int) $m[4] > 59 || (int) $m[6] > 59 || $fine > 24 * 60 || $fine <= $inizio) {
            return null;
        }

        return ['dal' => $dal, 'al' => $al, 'inizio' => $inizio, 'fine' => $fine];
    }

    /** @param list<string> $scelti @return list<string> solo profili veri, mai «anonimi» (serve l'accesso) */
    private static function profili(array $scelti): array
    {
        return array_values(array_intersect(array_diff(Pubblico::PROFILI, [Pubblico::ANONIMI]), array_map('trim', $scelti)));
    }
}
