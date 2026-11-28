<?php

declare(strict_types=1);

namespace App\Portale\Pannello;

/**
 * Le voci fisse del menu del pannello, una sola volta e divise per modulo.
 *
 * Ogni voce sta in un solo modulo (chiavi di MODULI_PORTALE in inc/sezioni.php) e dice chi la vede con i nomi dei permessi che admin_header.php
 * calcola per l'utente (gli stessi di {@see PermessiPagine}: basta uno). admin_header.php disegna il menu da qui, inizio.php ne ricava le schede
 * della scrivania; un test controlla che ogni voce abbia un solo modulo e che ogni pagina esista, e uno script (strumenti/prove/menu_per_profilo.php)
 * stampa le voci che vede un profilo.
 *
 * Non ci sono qui le voci che dipendono dall'area aperta (Dashboard, Eventi, Iscritti…): seguono l'area corrente, non il modulo.
 *
 * Campi di una voce:
 *  - id        chiave breve, unica nel modulo;
 *  - href      indirizzo della pagina (con il ?tab= o ?vista= se serve): non cambia mai;
 *  - icona, testo;
 *  - permessi  chi la vede (basta uno);
 *  - pagine    file che la accendono nel menu (la prima è il link, salvo diversa indicazione);
 *  - tab       (Didattica) scheda di didattica.php che la accende;
 *  - vista     (Persone) valori di ?vista= di anagrafe_personale.php che la accendono;
 *  - gruppo    id del gruppo richiudibile in cui sta (vedi {@see self::gruppi()}), assente = fuori dai gruppi;
 *  - descr     una riga per la scheda della scrivania (assente = nessuna scheda); scheda = titolo della scheda se diverso dal testo;
 *  - a_mano    true = la voce è disegnata a mano da admin_header.php (conta il numero o il testo cambia), qui è solo elencata;
 *  - testo_se_no  [permesso, testo]: testo da mostrare a chi NON ha quel permesso.
 */
final class MenuModuli
{
    /**
     * Gruppi richiudibili del menu di un modulo: id => [icona, titolo].
     *
     * @var array<string, array<string, array{0: string, 1: string}>>
     */
    private const GRUPPI = [
        'didattica' => [
            'pratiche' => ['fa-inbox', 'Pratiche e Consiglio'],
            'tirocini' => ['fa-briefcase', 'Tirocini e tesi'],
            'tutorato' => ['fa-user-graduate', 'Tutorato'],
            'sportello' => ['fa-headset', 'Sportello'],
        ],
    ];

    /**
     * Voci per modulo, nell'ordine del menu.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private const VOCI = [
        'orientamento' => [
            ['id' => 'report_ambiti', 'href' => 'report_ambiti.php', 'icona' => 'fa-chart-column', 'testo' => 'Report per ambito', 'permessi' => ['puo_orientamento'], 'pagine' => ['report_ambiti.php'],
                'descr' => 'Terza missione e public engagement: eventi, ore, partecipanti e scuole per ambito e per anno, in Excel', 'a_mano' => true],
        ],
        'fsl' => [
            ['id' => 'fsl_scuole', 'href' => 'fsl_scuole.php', 'icona' => 'fa-school', 'testo' => 'Scuole e convenzioni', 'permessi' => ['puo_fsl_convenzioni'], 'pagine' => ['fsl_scuole.php', 'convenzione_file.php'],
                'descr' => 'Una scheda per ogni scuola: convenzione e cosa fare, eventi di tutte le sedi, docenti referenti, firma del Direttore e protocollo'],
            ['id' => 'numeri', 'href' => 'fsl.php', 'icona' => 'fa-chart-column', 'testo' => 'Numeri e report', 'permessi' => ['puo_fsl'], 'pagine' => ['fsl.php'],
                'descr' => 'Riepilogo per anno scolastico, valutazioni delle scuole e report per il Ministero, con le tabelle in Excel, PDF e CSV'],
            ['id' => 'scuole', 'href' => 'scuole.php', 'icona' => 'fa-building-columns', 'testo' => 'Anagrafe delle scuole', 'permessi' => ['puo_fsl_scuole'], 'pagine' => ['scuole.php'],
                'descr' => 'Scuole del Ministero, abbinamento delle scuole scritte a mano, scuole collegate'],
        ],
        'calendari' => [],
        'didattica' => [
            ['id' => 'cose', 'href' => 'didattica.php?tab=home', 'icona' => 'fa-list-check', 'testo' => 'Cosa devo fare', 'permessi' => ['puo_didattica_tutto'], 'pagine' => [], 'tab' => 'home', 'gruppo' => 'pratiche'],
            ['id' => 'pratiche', 'href' => 'didattica.php?tab=pratiche', 'icona' => 'fa-inbox', 'testo' => 'Pratiche degli studenti', 'permessi' => ['puo_didattica_tutto'], 'pagine' => [], 'tab' => 'pratiche', 'gruppo' => 'pratiche',
                'descr' => 'Moduli online, pratiche con stato, istruttoria e messaggi'],
            ['id' => 'sedute', 'href' => 'didattica.php?tab=sedute', 'icona' => 'fa-gavel', 'testo' => 'Sedute e verbali', 'testo_se_no' => ['puo_didattica_tutto', 'Sedute del consiglio'], 'permessi' => ['puo_didattica'], 'pagine' => [], 'tab' => 'sedute', 'gruppo' => 'pratiche',
                'descr' => 'Consigli dei corsi di studio, presenze, convalide e piani di studio, verbale in Word ed Excel'],
            ['id' => 'moduli', 'href' => 'didattica.php?tab=moduli', 'icona' => 'fa-file-lines', 'testo' => 'Moduli e documenti', 'permessi' => ['puo_didattica_tutto'], 'pagine' => [], 'tab' => 'moduli', 'gruppo' => 'pratiche',
                'descr' => 'Modulistica: documenti da scaricare e moduli online guidati'],
            ['id' => 'statistiche', 'href' => 'didattica.php?tab=statistiche', 'icona' => 'fa-chart-column', 'testo' => 'Statistiche', 'permessi' => ['puo_didattica_tutto'], 'pagine' => [], 'tab' => 'statistiche', 'gruppo' => 'pratiche'],

            ['id' => 'tirocini', 'href' => 'tirocini.php', 'icona' => 'fa-briefcase', 'testo' => 'Tirocini', 'permessi' => ['puo_didattica_tutto'], 'pagine' => ['tirocini.php'], 'gruppo' => 'tirocini'],
            ['id' => 'tirocini_motorie', 'href' => 'tirocini_motorie.php', 'icona' => 'fa-person-running', 'testo' => 'Tirocini Scienze motorie', 'permessi' => ['puo_tir_motorie'], 'pagine' => ['tirocini_motorie.php'], 'gruppo' => 'tirocini'],
            ['id' => 'convenzioni_tesi', 'href' => 'convenzioni_tesi.php', 'icona' => 'fa-handshake', 'testo' => 'Convenzioni di tesi', 'permessi' => ['puo_conv_tesi'], 'pagine' => ['convenzioni_tesi.php'], 'gruppo' => 'tirocini',
                'descr' => 'Convenzioni con enti e aziende per svolgere la tesi: modifiche agli articoli, protocollo in entrata e in uscita, firma del Direttore, archivio ed elenco pubblico'],

            ['id' => 'tutorato', 'href' => 'tutorato.php?vista=cosa', 'icona' => 'fa-user-graduate', 'testo' => 'Tutorato · lettere di incarico', 'permessi' => ['puo_tutorato'], 'pagine' => ['tutorato.php'], 'gruppo' => 'tutorato',
                'descr' => 'Bandi e lettere dei vincitori: conferma con SPID/CIE, firme PAdES del docente e del direttore, protocollo'],
            ['id' => 'sostituzioni', 'href' => 'sostituzioni.php', 'icona' => 'fa-people-arrows', 'testo' => 'Sostituzioni', 'permessi' => ['puo_didattica_tutto'], 'pagine' => ['sostituzioni.php'], 'gruppo' => 'tutorato'],

            ['id' => 'ricevimento', 'href' => 'didattica.php?tab=ufficio', 'icona' => 'fa-user-clock', 'testo' => 'Ricevimento', 'permessi' => ['puo_didattica_tutto'], 'pagine' => [], 'tab' => 'ufficio', 'gruppo' => 'sportello',
                'descr' => 'Sportello a appuntamenti dell’Ufficio didattico'],
            ['id' => 'assistenza', 'href' => 'assistenza.php', 'icona' => 'fa-headset', 'testo' => 'Assistenza', 'permessi' => ['puo_assistenza'], 'pagine' => ['assistenza.php'], 'gruppo' => 'sportello',
                'descr' => 'Le chat di studenti, personale e utenti esterni: da prendere, in carico a te, chiuse'],
            ['id' => 'didacticus', 'href' => 'didacticus.php', 'icona' => 'fa-robot', 'testo' => 'Domande e risposte', 'permessi' => ['puo_assistenza'], 'pagine' => ['didacticus.php'], 'gruppo' => 'sportello',
                'descr' => 'Le domande frequenti del sito e le risposte dell’assistente virtuale: argomenti, pubblico, link, allegati e domande a cui non ha saputo rispondere'],
        ],
        'forms' => [
            ['id' => 'forms', 'href' => 'forms.php', 'icona' => 'fa-clipboard-list', 'testo' => 'MyForms', 'permessi' => ['puo_forms_vedere'], 'pagine' => ['forms.php', 'forms_risposte.php'], 'a_mano' => true],
        ],
        'amministrazione' => [
            ['id' => 'cose', 'href' => 'amministrazione.php?tab=home', 'icona' => 'fa-list-check', 'testo' => 'Cosa devo fare', 'permessi' => ['puo_amm_entrare'], 'pagine' => [], 'a_mano' => true],
            ['id' => 'modulistica', 'href' => 'amministrazione.php?tab=modulistica', 'icona' => 'fa-file-lines', 'testo' => 'Modulistica', 'permessi' => ['puo_amministrazione'], 'pagine' => [], 'a_mano' => true],
            ['id' => 'pubblicazioni', 'href' => 'pubblicazioni.php?vista=arrivate', 'icona' => 'fa-bullhorn', 'testo' => 'Richieste di pubblicazione', 'permessi' => ['puo_pubblicazioni'], 'pagine' => ['pubblicazioni.php'], 'a_mano' => true],
            ['id' => 'sos_aule', 'href' => 'sos_aule.php', 'icona' => 'fa-life-ring', 'testo' => 'SOS aule', 'permessi' => ['puo_amministrazione'], 'pagine' => ['sos_aule.php', 'sos_cartello.php'], 'a_mano' => true],
        ],
        'persone' => [
            ['id' => 'utenti', 'href' => 'utenti.php', 'icona' => 'fa-users-cog', 'testo' => 'Utenti e abilitazioni', 'permessi' => ['is_full_admin'], 'pagine' => ['utenti.php'],
                'descr' => 'Amministratori, abilitati per area, per modulo e per la FSL'],
            // L'ha anche chi lavora in tutta la Didattica senza essere amministratore (prima era una voce del menu della Didattica)
            ['id' => 'uffici', 'href' => 'uffici.php', 'icona' => 'fa-sitemap', 'testo' => 'Uffici e persone', 'permessi' => ['is_full_admin', 'puo_didattica_tutto'], 'pagine' => ['uffici.php', 'uffici_ateneo.php'],
                'descr' => 'Uffici del Dipartimento e di Ateneo (Direzione, protocollo, corsi di studio, pagamenti…) con le persone scelte dall’anagrafe: si richiamano in pratiche, tutorato, risorse e convenzioni'],
            ['id' => 'docenti', 'href' => 'anagrafe_docenti.php', 'icona' => 'fa-chalkboard-user', 'testo' => 'Docenti', 'permessi' => ['is_full_admin'], 'pagine' => ['anagrafe_docenti.php'], 'vista' => ['docenti'],
                'scheda' => 'Anagrafi', 'descr' => 'Docenti, personale TA, insegnamenti, corsi di studio e strutture dal portale di Ateneo'],
            ['id' => 'pta', 'href' => 'anagrafe_pta.php', 'icona' => 'fa-user-tie', 'testo' => 'Personale TA', 'permessi' => ['is_full_admin'], 'pagine' => ['anagrafe_pta.php'], 'vista' => ['pta']],
            ['id' => 'insegnamenti', 'href' => 'anagrafe_insegnamenti.php', 'icona' => 'fa-book-open', 'testo' => 'Insegnamenti', 'permessi' => ['is_full_admin'], 'pagine' => ['anagrafe_insegnamenti.php'], 'vista' => ['insegnamenti']],
            ['id' => 'corsi', 'href' => 'anagrafe_personale.php?vista=corsi', 'icona' => 'fa-graduation-cap', 'testo' => 'Corsi di studio', 'permessi' => ['is_full_admin'], 'pagine' => [], 'vista' => ['corsi']],
            ['id' => 'strutture', 'href' => 'anagrafe_personale.php?vista=strutture', 'icona' => 'fa-sitemap', 'testo' => 'Strutture e aggiornamento', 'permessi' => ['is_full_admin'], 'pagine' => [], 'vista' => ['strutture', 'altro']],
        ],
        'sito' => [
            ['id' => 'testata', 'href' => 'testata.php', 'icona' => 'fa-image', 'testo' => 'Testata e home', 'permessi' => ['is_full_admin'], 'pagine' => ['testata.php'],
                'descr' => 'Logo, testata, carosello e widget della home'],
            ['id' => 'menu', 'href' => 'menu.php', 'icona' => 'fa-link', 'testo' => 'Menu', 'permessi' => ['is_full_admin'], 'pagine' => ['menu.php'],
                'descr' => 'Voci del menu di navigazione del sito pubblico'],
            ['id' => 'comunicazioni', 'href' => 'comunicazioni.php', 'icona' => 'fa-bullhorn', 'testo' => 'Notizie e pagine', 'permessi' => ['is_full_admin'], 'pagine' => ['comunicazioni.php'],
                'descr' => 'Pubblica una notizia per studenti, scuole, aziende o docenti: titolo, testo e a chi si rivolge'],
            ['id' => 'contatti', 'href' => 'contatti.php', 'icona' => 'fa-address-card', 'testo' => 'Contatti per gli studenti', 'permessi' => ['is_full_admin'], 'pagine' => ['contatti.php'],
                'descr' => 'La pagina «Chi contattare per cosa»: recapiti, orari e dove si trovano gli uffici'],
            ['id' => 'area_personale', 'href' => 'area_personale.php', 'icona' => 'fa-table-cells-large', 'testo' => 'Area personale (riquadri)', 'permessi' => ['is_full_admin'], 'pagine' => ['area_personale.php'],
                'descr' => 'Cosa trova ogni profilo (studente, docente, Direttore, personale, gestore, amministratore, esterno) nella sua Area personale: riquadri da accendere, spostare e scrivere'],
            ['id' => 'aree', 'href' => 'aree.php', 'icona' => 'fa-layer-group', 'testo' => 'Aree di tutti i moduli', 'permessi' => ['is_full_admin'], 'pagine' => ['aree.php', 'nuova_area.php'],
                'descr' => 'Crea, nascondi o elimina le aree e scegli il loro tipo'],
        ],
        'sistema' => [
            ['id' => 'protocollo', 'href' => 'protocollo.php', 'icona' => 'fa-stamp', 'testo' => 'Protocollo (Titulus)', 'permessi' => ['is_full_admin'], 'pagine' => ['protocollo.php'],
                'descr' => 'Collegamento al protocollo di Ateneo: servizio e utenza, configurazione per ogni tipo di richiesta (documento, iter, UO, titolario, fascicolo) e registro degli invii'],
            ['id' => 'email', 'href' => 'email.php', 'icona' => 'fa-envelope-open-text', 'testo' => 'Testi delle email', 'permessi' => ['is_full_admin'], 'pagine' => ['email.php'],
                'descr' => 'I testi delle email che il portale invia'],
            ['id' => 'sistema', 'href' => 'sistema.php', 'icona' => 'fa-gear', 'testo' => 'Sistema e registri', 'permessi' => ['is_full_admin'], 'pagine' => ['sistema.php', 'audit_log.php', 'log_accessi.php'],
                'descr' => 'Email, backup, controllo notturno, registro operazioni e accessi'],
            ['id' => 'simula', 'href' => 'simula.php', 'icona' => 'fa-user-secret', 'testo' => 'Entra come…', 'permessi' => ['is_full_admin'], 'pagine' => ['simula.php'],
                'descr' => 'Guarda il portale con gli occhi di un’altra persona, per capire cosa vede'],
        ],
    ];

    /** Moduli che hanno solo pagine proprie (niente aree): la loro visibilità dipende dalle voci, non dalle aree. */
    private const MODULI_SENZA_AREE = ['persone', 'sito', 'sistema'];

    /** @return list<string> chiavi dei moduli che hanno voci fisse */
    public static function moduli(): array
    {
        return array_keys(self::VOCI);
    }

    /**
     * Tutte le voci di un modulo (senza guardare i permessi).
     *
     * @return list<array<string, mixed>>
     */
    public static function voci(string $modulo): array
    {
        return self::VOCI[$modulo] ?? [];
    }

    /**
     * Gruppi richiudibili del modulo: id => [icona, titolo] (vuoto se il modulo non ne ha).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function gruppi(string $modulo): array
    {
        return self::GRUPPI[$modulo] ?? [];
    }

    /**
     * Le voci del modulo che l'utente vede.
     *
     * @param array<string, bool|callable():bool> $permessi come in {@see PermessiPagine::consente()}
     *
     * @return list<array<string, mixed>>
     */
    public static function vociVisibili(string $modulo, array $permessi): array
    {
        return array_values(array_filter(self::voci($modulo), static fn (array $v): bool => self::vede($v, $permessi)));
    }

    /** Il testo della voce per questo utente (alcune voci cambiano nome per chi non ha un permesso). */
    public static function testo(array $voce, array $permessi): string
    {
        if (isset($voce['testo_se_no']) && !self::ha($permessi, (string)$voce['testo_se_no'][0])) {
            return (string)$voce['testo_se_no'][1];
        }

        return (string)$voce['testo'];
    }

    /**
     * I moduli del pannello che l'utente può aprire (le aree che gestisce stanno in $moduliArea: sono i moduli delle sue aree).
     * Regole come prima della riorganizzazione, più i tre moduli di sole pagine proprie (Persone e permessi, Sito pubblico, Sistema):
     * compaiono solo a chi ha almeno una voce. «Scuole» è ora una voce della FSL: chi ha solo quella entra nella FSL.
     *
     * @param array<string, bool|callable():bool> $permessi
     * @param list<string>                        $moduliArea
     *
     * @return list<string> senza ordine né doppioni
     */
    public static function moduliVisibili(array $permessi, array $moduliArea = []): array
    {
        $m = array_fill_keys($moduliArea, true);
        if (self::ha($permessi, 'puo_fsl_convenzioni') || self::ha($permessi, 'puo_fsl_scuole')) {
            $m['fsl'] = true;
        }
        if (self::ha($permessi, 'puo_didattica') || self::ha($permessi, 'puo_conv_tesi') || self::ha($permessi, 'puo_assistenza') || self::ha($permessi, 'puo_tir_motorie')) {
            $m['didattica'] = true;
        }
        if (self::ha($permessi, 'puo_forms_vedere')) {
            $m['forms'] = true;
        }
        if (self::ha($permessi, 'puo_amm_entrare')) {
            $m['amministrazione'] = true;
        }
        foreach (self::MODULI_SENZA_AREE as $modulo) {
            if (self::vociVisibili($modulo, $permessi) !== []) {
                $m[$modulo] = true;
            }
        }

        return array_keys($m);
    }

    /**
     * Il vecchio modulo «Gestione del portale» non c'è più, ma inizio.php?sezione=portale funziona ancora: porta alle persone e ai permessi
     * (dove c'erano le anagrafi e gli utenti) per l'amministratore, alla Formazione Scuola Lavoro (dov'è ora «Scuole») per chi aveva solo quella,
     * alla scrivania (stringa vuota) per gli altri.
     */
    public static function moduloDelVecchioPortale(array $permessi): string
    {
        if (self::ha($permessi, 'is_full_admin')) {
            return 'persone';
        }

        return self::ha($permessi, 'puo_fsl_scuole') ? 'fsl' : '';
    }

    private static function vede(array $voce, array $permessi): bool
    {
        foreach ($voce['permessi'] as $nome) {
            if (self::ha($permessi, $nome)) {
                return true;
            }
        }

        return false;
    }

    private static function ha(array $permessi, string $nome): bool
    {
        $valore = $permessi[$nome] ?? false;
        if (is_callable($valore)) {
            $valore = $valore();
        }

        return (bool)$valore;
    }
}
