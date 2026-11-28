<?php

declare(strict_types=1);

namespace App\Portale\Pannello;

/**
 * Chi può aprire ciascuna pagina di admin/.
 *
 * La regola di ogni pagina è la stessa che la pagina applica da sola in testa al file: qui è scritta una volta sola, con i nomi dei permessi che
 * admin_header.php calcola per l'utente (is_full_admin, puo_fsl_convenzioni, can_manage_iscritti…). admin_header.php la applica prima della pagina,
 * così una pagina nuova che dimentica il controllo non resta aperta a tutti. I controlli dentro le pagine restano: sono la seconda protezione
 * (e verificano anche ciò che dipende dai dati, come il singolo form o il singolo turno).
 *
 * Ogni file di admin/, sottocartelle comprese, sta in uno solo dei tre elenchi: PAGINE (pagina d'ingresso, passa da admin_header.php), SCHEDE (inclusa da
 * un'altra pagina, ha già la guardia in testa) o FUORI (non passa da admin_header.php: cron, AJAX, scaricamenti con un proprio controllo, vecchi indirizzi).
 * Un test scorre admin/ in modo ricorsivo e fallisce se trova un file che non è in nessuno dei tre.
 */
final class PermessiPagine
{
    /** Regola «qualunque utente che ha fatto l'accesso»: la pagina mostra solo ciò che l'utente può vedere (aree sue, voci per permesso). */
    private const QUALUNQUE = ['*'];

    /**
     * Regola «chi ha almeno un modulo o un'area» (o è amministratore): la condizione di admin/index.php. Chi non la soddisfa non vede un 403:
     * admin_header.php lo rimanda alla sua area personale con un avviso (vedi {@see self::rimandaAllAreaPersonale()}).
     */
    private const SEZIONI = ['ha_sezioni'];

    /**
     * Pagina d'ingresso => permessi (basta uno).
     *
     * @var array<string, list<string>>
     */
    private const PAGINE = [
        // Solo amministratori globali
        'anagrafe_docenti.php'      => ['is_full_admin'],
        'anagrafe_insegnamenti.php' => ['is_full_admin'],
        'anagrafe_personale.php'    => ['is_full_admin'],
        'anagrafe_pta.php'          => ['is_full_admin'],
        'area_personale.php'        => ['is_full_admin'],
        'audit_log.php'             => ['is_full_admin'],
        'comunicazioni.php'         => ['is_full_admin'],
        'contatti.php'              => ['is_full_admin'],
        'log_accessi.php'           => ['is_full_admin'],
        'menu.php'                  => ['is_full_admin'],
        'nuova_area.php'            => ['is_full_admin'],
        'protocollo.php'            => ['is_full_admin'],
        'simula.php'                => ['is_full_admin'],
        'sistema.php'               => ['is_full_admin'],
        'testata.php'               => ['is_full_admin'],
        'utenti.php'                => ['is_full_admin'],

        // Dentro l'area corrente (gestore di tutta l'area o di alcune attività)
        'archivio.php'              => ['can_manage_eventi'],
        'eventi.php'                => ['can_manage_eventi'],
        'progetti.php'              => ['can_manage_eventi'],
        'form_builder.php'          => ['can_manage_form'],
        'impostazioni_area.php'     => ['can_manage_settings'],
        'iscritti.php'              => ['can_manage_iscritti'],
        'partecipanti.php'          => ['can_manage_iscritti'],
        'scanner.php'               => ['can_manage_iscritti'],
        'stampa_badge.php'          => ['can_manage_iscritti'],
        'stampa_lista_iscritti.php' => ['can_manage_iscritti'],
        'statistiche.php'           => ['can_manage_iscritti'],
        'sondaggi.php'              => ['can_manage_sondaggi'],
        // Calendari e risorse: serve gestire tutta l'area (il tipo «calendario» lo controlla la pagina, con il suo messaggio)
        'prenotazioni_risorse.php'  => ['is_area_manager'],
        'risorse.php'               => ['is_area_manager'],

        // Moduli
        'allegato_a.php'            => ['puo_fsl_convenzioni'],
        'convenzione_file.php'      => ['puo_fsl_convenzioni'],
        'fsl.php'                   => ['puo_fsl_convenzioni'],
        'fsl_scuole.php'            => ['puo_fsl_convenzioni'],
        'scuole.php'                => ['puo_fsl_scuole'],
        'didattica.php'             => ['puo_didattica'],
        'sostituzioni.php'          => ['puo_didattica_tutto'],
        'tirocini.php'              => ['is_full_admin', 'puo_didattica_tutto'],
        'uffici.php'                => ['is_full_admin', 'puo_didattica_tutto'],
        'convenzioni_tesi.php'      => ['puo_conv_tesi'],
        'tirocini_motorie.php'      => ['puo_tir_motorie'],
        'tutorato.php'              => ['puo_tutorato'],
        'email.php'                 => ['is_full_admin', 'puo_tutorato', 'puo_didattica_tutto', 'puo_fsl_convenzioni', 'puo_conv_tesi'],
        'assistenza.php'            => ['puo_assistenza'],
        'didacticus.php'            => ['puo_assistenza'],
        'forms.php'                 => ['puo_forms_vedere'],
        'forms_allegato.php'        => ['puo_forms_vedere'],
        'forms_risposte.php'        => ['puo_forms_vedere'],
        'amministrazione.php'       => ['puo_amm_entrare'],
        'pubblicazioni.php'         => ['puo_pubblicazioni'],
        'sos_aule.php'              => ['puo_amministrazione'],
        'sos_cartello.php'          => ['puo_amministrazione'],
        'report_ambiti.php'         => ['puo_orientamento'],
        'messaggi.php'              => ['ha_caselle', 'puo_fsl_convenzioni', 'puo_assistenza'],

        // Ingressi del pannello: servono sezioni da gestire (almeno un modulo o un'area); senza, si torna all'area personale
        'aree.php'                  => self::SEZIONI,
        'cerca.php'                 => self::SEZIONI,
        'dashboard.php'             => self::SEZIONI,
        'index.php'                 => self::SEZIONI,
        'inizio.php'                => self::SEZIONI,

        // Senza una regola di pannello: controlla il singolo dato (il turno), qualunque utente con l'accesso
        'stampa_qr_aula.php'        => self::QUALUNQUE,
    ];

    /**
     * Schede e parti incluse da un'altra pagina: non si aprono da sole (guardia «define» in testa al file).
     *
     * @var list<string>
     */
    private const SCHEDE = [
        'admin_footer.php',
        'admin_header.php',
        'amministrazione_campi.php',
        'amministrazione_home.php',
        'amministrazione_impostazioni.php',
        'amministrazione_modulistica.php',
        'didattica_accessi.php',
        'didattica_archivio.php',
        'didattica_home.php',
        'didattica_moduli.php',
        'didattica_pratiche.php',
        'didattica_sedute.php',
        'didattica_statistiche.php',
        'didattica_ufficio.php',
        // Sottocartella: i campi che le schede e le pagine includono (guardia sulla funzione di admin_header.php)
        'parziali/chi_compila_cosa.php',
        'parziali/costruttore_campi.php',
        'parziali/da_compilare_schede.php',
        'parziali/impostazioni_pubblicazioni_email.php',
        'parziali/modulo_documento_campi.php',
    ];

    /**
     * Non passano da admin_header.php: ognuno ha il suo controllo (sessione, chiave del cron, ruolo) o è solo un rimando.
     *
     * @var list<string>
     */
    private const FUORI = [
        'api_unread.php',
        'assistenza_ajax.php',
        'cerca_personale.php',
        'cron_backup.php',
        'cron_reminders.php',
        'editor_upload.php',
        'esporta_tabella.php',
        'push_ajax.php',
        'uffici_ateneo.php',
    ];

    /** @return array<string, list<string>> */
    public static function pagine(): array
    {
        return self::PAGINE;
    }

    /** @return list<string> */
    public static function schede(): array
    {
        return self::SCHEDE;
    }

    /** @return list<string> */
    public static function fuori(): array
    {
        return self::FUORI;
    }

    /**
     * «pagina», «scheda», «fuori» oppure null se il file non è in mappa.
     * Il file si indica col nome (file del primo livello di admin/) o col percorso relativo ad admin/ («parziali/x.php»): le pagine d'ingresso sono
     * solo quelle del primo livello, i file delle sottocartelle sono schede o «fuori».
     */
    public static function tipo(string $file): ?string
    {
        $file = ltrim(str_replace('\\', '/', $file), '/');
        if (!str_contains($file, '/') && isset(self::PAGINE[$file])) {
            return 'pagina';
        }
        if (in_array($file, self::SCHEDE, true)) {
            return 'scheda';
        }
        if (in_array($file, self::FUORI, true)) {
            return 'fuori';
        }

        return null;
    }

    /** I permessi che aprono la pagina (null se non è una pagina d'ingresso in mappa). «*» = qualunque utente con l'accesso. */
    public static function regola(string $file): ?array
    {
        return self::PAGINE[basename($file)] ?? null;
    }

    /** La regola della pagina è «avere almeno un modulo o un'area»: se negata, si rimanda all'area personale invece del 403. */
    public static function rimandaAllAreaPersonale(string $file): bool
    {
        return self::regola($file) === self::SEZIONI;
    }

    /**
     * La pagina si può aprire?
     *
     * @param array<string, bool|callable():bool> $permessi i permessi dell'utente, con i nomi usati dalla mappa; un valore può essere una funzione,
     *                                                       chiamata solo se la regola della pagina la richiede (per i permessi che costano una query)
     *
     * @return bool|null null se la pagina non è in mappa (decide il chiamante)
     */
    public static function consente(string $file, array $permessi): ?bool
    {
        $regola = self::regola($file);
        if ($regola === null) {
            return null;
        }
        foreach ($regola as $nome) {
            if ($nome === '*') {
                return true;
            }
            $valore = $permessi[$nome] ?? false;
            if (is_callable($valore)) {
                $valore = $valore();
            }
            if ($valore) {
                return true;
            }
        }

        return false;
    }
}
