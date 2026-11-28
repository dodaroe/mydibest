<?php

declare(strict_types=1);

namespace App\SosAule;

/** Valori fissi di SOS aule: stati, urgenze, chi può fare ogni passaggio, limiti e cartella delle foto. */
final class Costanti
{
    /** Stato => etichetta per le persone e colore (una delle classi di .ui-stato: ok, attesa, azione, info, neutro). */
    public const STATI = [
        'inviata' => ['etichetta' => 'In attesa', 'colore' => 'attesa'],
        'presa' => ['etichetta' => 'Presa in carico', 'colore' => 'info'],
        'assegnata' => ['etichetta' => 'Affidata a uno studente', 'colore' => 'info'],
        'in_corso' => ['etichetta' => 'Intervento in corso', 'colore' => 'info'],
        'non_risolta' => ['etichetta' => 'Non risolta, torna al referente', 'colore' => 'azione'],
        'inoltrata' => ['etichetta' => 'Inoltrata a un altro ufficio', 'colore' => 'attesa'],
        'risolta' => ['etichetta' => 'Risolta', 'colore' => 'ok'],
        'chiusa_non_risolta' => ['etichetta' => 'Chiusa senza soluzione', 'colore' => 'neutro'],
        'annullata' => ['etichetta' => 'Annullata dal docente', 'colore' => 'neutro'],
    ];

    /** Gli stati in cui la segnalazione aspetta ancora qualcosa da qualcuno. */
    public const STATI_APERTI = ['inviata', 'presa', 'assegnata', 'in_corso', 'non_risolta', 'inoltrata'];

    /** Gli stati in cui non c'è più niente da fare. */
    public const STATI_CHIUSI = ['risolta', 'chiusa_non_risolta', 'annullata'];

    /** Le schede di chi interviene (sos_interventi.php): ruolo => scheda => stati. La terza scheda, «Chiuse oggi», è uguale per tutti: STATI_CHIUSI da stamattina. */
    public const SCHEDE = [
        self::REFERENTE => ['da_fare' => ['inviata', 'presa', 'non_risolta'], 'in_corso' => ['assegnata', 'in_corso', 'inoltrata']],
        self::STUDENTE => ['da_fare' => ['assegnata'], 'in_corso' => ['in_corso']],
    ];

    /** Urgenze, dalla più urgente. */
    public const URGENZE = [
        'ferma' => 'La lezione è ferma',
        'aiuto' => 'Posso andare avanti, ma serve aiuto',
        'prossime' => 'Lo segnalo per le prossime lezioni',
    ];

    /** I ruoli di una persona RISPETTO A UNA segnalazione. */
    public const DOCENTE = 'docente';       // chi l'ha inviata
    public const REFERENTE = 'referente';   // referente dell'aula o responsabile di riserva
    public const STUDENTE = 'studente';     // lo studente a cui è affidata
    public const COLLEGA = 'collega';       // un altro che può inviare, sulla stessa aula: aggiunge solo una nota, non è un ruolo di ServizioSos::ruoli()

    /** da_stato => a_stato => chi può fare il passaggio. Quello che non è scritto qui non si può fare. */
    public const TRANSIZIONI = [
        'inviata' => [
            'presa' => [self::REFERENTE],
            'assegnata' => [self::REFERENTE],
            'annullata' => [self::DOCENTE],
        ],
        'presa' => [
            'assegnata' => [self::REFERENTE],
            'risolta' => [self::REFERENTE],
            'inoltrata' => [self::REFERENTE],
            'chiusa_non_risolta' => [self::REFERENTE],
            'annullata' => [self::DOCENTE],
        ],
        'assegnata' => [
            'in_corso' => [self::STUDENTE],     // lo studente prima dice «Sto andando»: da qui non può chiuderla
            'risolta' => [self::REFERENTE],
            'presa' => [self::REFERENTE],       // il referente la riprende
            'assegnata' => [self::REFERENTE],   // la passa a un altro studente
            'annullata' => [self::DOCENTE],
        ],
        'in_corso' => [
            'risolta' => [self::REFERENTE, self::STUDENTE],
            'non_risolta' => [self::STUDENTE],
            'presa' => [self::REFERENTE],
            'assegnata' => [self::REFERENTE],
            'annullata' => [self::DOCENTE],
        ],
        'non_risolta' => [
            'presa' => [self::REFERENTE],
            'assegnata' => [self::REFERENTE],
            'inoltrata' => [self::REFERENTE],
            'chiusa_non_risolta' => [self::REFERENTE],
            'annullata' => [self::DOCENTE],
        ],
        'inoltrata' => [
            'presa' => [self::REFERENTE],
            'risolta' => [self::REFERENTE],
            'chiusa_non_risolta' => [self::REFERENTE],
            'annullata' => [self::DOCENTE],
        ],
        'risolta' => [
            'non_risolta' => [self::DOCENTE],   // «non è risolto», entro ORE_RIAPERTURA
        ],
    ];

    /** Le icone (Font Awesome) tra cui scegliere per tipi di problema ed esiti: classe => nome per le persone. Ci sono tutte quelle di VociDiPartenza. */
    public const ICONE = [
        'fa-video' => 'Videoproiettore', 'fa-tv' => 'Schermo', 'fa-desktop' => 'Computer', 'fa-laptop' => 'Portatile', 'fa-keyboard' => 'Tastiera',
        'fa-plug' => 'Presa e cavi', 'fa-microphone' => 'Microfono', 'fa-volume-high' => 'Audio', 'fa-wifi' => 'Rete', 'fa-print' => 'Stampante',
        'fa-key' => 'Chiave', 'fa-door-closed' => 'Porta', 'fa-lock' => 'Lucchetto', 'fa-box-open' => 'Materiale', 'fa-chair' => 'Arredi',
        'fa-lightbulb' => 'Luce', 'fa-temperature-half' => 'Temperatura', 'fa-droplet' => 'Acqua', 'fa-fire-extinguisher' => 'Sicurezza',
        'fa-screwdriver-wrench' => 'Attrezzi', 'fa-user-gear' => 'Tecnico', 'fa-triangle-exclamation' => 'Attenzione', 'fa-circle-question' => 'Altro',
    ];

    public const MAX_DESCRIZIONE = 500;
    /** Note, esito scritto, «inoltrata a». */
    public const MAX_NOTA = 500;
    public const MAX_TELEFONO = 30;
    /** Minuti di un intervento: al massimo un giorno. */
    public const MAX_MINUTI = 1440;
    /** Entro quante ore dalla chiusura il docente può dire che non è risolto. */
    public const ORE_RIAPERTURA = 24;
    /** Da quante segnalazioni dello stesso tipo, nel periodo scelto, un'aula va tra le «aule malate» del registro. */
    public const SOGLIA_AULA_MALATA = 3;
    /** Le urgenze in breve, per le card e il registro. */
    public const URGENZE_BREVI = ['ferma' => 'Lezione ferma', 'aiuto' => 'Serve aiuto', 'prossime' => 'Prossime lezioni'];

    /** Cartella delle foto (relativa alla radice del sito, bloccata al web). */
    public const CARTELLA_FOTO = 'uploads/sos/';

    public static function ammessa(string $da, string $a, string $ruolo): bool
    {
        return in_array($ruolo, self::TRANSIZIONI[$da][$a] ?? [], true);
    }

    public static function etichetta(string $stato): string
    {
        return self::STATI[$stato]['etichetta'] ?? $stato;
    }
}
