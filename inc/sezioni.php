<?php
// inc/sezioni.php - Moduli del portale (Eventi e seminari, Formazione Scuola Lavoro, Prenotazioni e risorse, Didattica,
// Persone e permessi, Sito pubblico, Sistema), macroaree delle aree, tipi di area e ambiti degli eventi.
// La chiave 'orientamento' resta per compatibilità (abilitazioni modulo_orientamento, indirizzi inizio.php?sezione=orientamento):
// oggi è il modulo «Eventi e seminari», che serve l'Orientamento ma anche la ricerca e il public engagement (ambiti).
// La Formazione Scuola Lavoro è un modulo a sé ('fsl'): chi ha il modulo Orientamento o il perimetro FSL la conserva.
// Ogni area (pagine_eventi) ha un tipo (pagine_eventi.tipo_area) che la colloca in una macroarea e decide le impostazioni
// proposte e ciò che si vede. Tipo vuoto = area non ancora assegnata: si comporta come sempre (eventi generici).
// Caricato da functions.php (nell'ordine indicato lì): non includerlo da solo.

// Macroaree che contengono aree (pagine_eventi): la chiave 'calendari' resta per compatibilità con i dati
if (!defined('SEZIONI_PORTALE')) define('SEZIONI_PORTALE', [
    'orientamento' => ['nome' => 'Eventi e seminari', 'icona' => 'fa-compass',
                       'descr' => 'Welcome Week, Openlab, seminari, public engagement'],
    'fsl'          => ['nome' => 'Formazione Scuola Lavoro', 'icona' => 'fa-briefcase',
                       'descr' => 'Progetti e laboratori per le scuole con convenzione e attestati'],
    'calendari'    => ['nome' => 'Prenotazioni e risorse', 'icona' => 'fa-calendar-days',
                       'descr' => 'Aule, laboratori, sportelli e gruppi delle attività degli insegnamenti'],
]);

// Moduli: ognuno con il suo pannello (pagina iniziale e menu). Orientamento e Prenotazioni contengono aree;
// Didattica, Persone e permessi, Sito pubblico e Sistema hanno pagine proprie. 'colore' per le card e il selettore in alto.
if (!defined('MODULI_PORTALE')) define('MODULI_PORTALE', [
    'orientamento' => ['nome' => 'Eventi e seminari', 'icona' => 'fa-compass', 'colore' => '#0056B3',
                       'descr' => 'Welcome Week, Openlab, seminari di ricerca, public engagement: ogni evento ha i suoi ambiti'],
    'fsl'          => ['nome' => 'Formazione Scuola Lavoro', 'icona' => 'fa-briefcase', 'colore' => '#B30000',
                       'descr' => 'Progetti ed eventi per le scuole, convenzioni, verifica delle iscrizioni, attestati e valutazioni'],
    'calendari'    => ['nome' => 'Prenotazioni e risorse', 'icona' => 'fa-calendar-days', 'colore' => '#7c3aed',
                       'descr' => 'Aule, laboratori e sportelli a slot, gruppi delle attività degli insegnamenti'],
    'didattica'    => ['nome' => 'Didattica', 'icona' => 'fa-graduation-cap', 'colore' => '#047857',
                       'descr' => 'Modulistica, moduli online e pratiche degli studenti, sedute dei consigli, tutorato'],
    'forms'        => ['nome' => 'MyForms', 'icona' => 'fa-clipboard-list', 'colore' => '#be123c',
                       'descr' => 'Moduli online da compilare: questionari, raccolte di informazioni e iscrizioni, con risposte in Excel'],
    'amministrazione' => ['nome' => 'Amministrazione', 'icona' => 'fa-building-columns', 'colore' => '#0e7490',
                       'descr' => 'Modulistica amministrativa e procedure online del personale: richieste di pubblicazione su sito e social, documenti e moduli'],
    // Il vecchio modulo «Gestione del portale» (chiave 'portale') è diviso in tre: chi gestisce le persone, chi cura il sito pubblico, chi tiene il sistema.
    // inizio.php?sezione=portale funziona ancora (admin_header.php lo rimanda: vedi MenuModuli::moduloDelVecchioPortale)
    'persone'      => ['nome' => 'Persone e permessi', 'icona' => 'fa-users-gear', 'colore' => '#4338ca',
                       'descr' => 'Utenti e abilitazioni, uffici e persone, anagrafi di docenti, personale TA, insegnamenti, corsi di studio e strutture'],
    'sito'         => ['nome' => 'Sito pubblico', 'icona' => 'fa-globe', 'colore' => '#a21caf',
                       'descr' => 'Testata e home, menu, notizie e pagine, contatti per gli studenti, riquadri dell\'area personale, aree di tutti i moduli'],
    'sistema'      => ['nome' => 'Sistema', 'icona' => 'fa-gear', 'colore' => '#334155',
                       'descr' => 'Protocollo di Ateneo (Titulus), testi delle email, backup e registri, «Entra come…»'],
]);

// Pagine del pannello che appartengono a un modulo senza aree (le altre seguono l'area corrente)
if (!defined('PAGINE_MODULO')) define('PAGINE_MODULO', [
    'fsl.php' => 'fsl', 'fsl_scuole.php' => 'fsl', 'convenzione_file.php' => 'fsl', 'scuole.php' => 'fsl', 'report_ambiti.php' => 'orientamento',
    'didattica.php' => 'didattica', 'tutorato.php' => 'didattica', 'convenzioni_tesi.php' => 'didattica', 'assistenza.php' => 'didattica', 'didacticus.php' => 'didattica', 'sostituzioni.php' => 'didattica', 'tirocini.php' => 'didattica', 'tirocini_motorie.php' => 'didattica',
    'forms.php' => 'forms', 'forms_risposte.php' => 'forms',
    'amministrazione.php' => 'amministrazione', 'pubblicazioni.php' => 'amministrazione',
    'sos_aule.php' => 'amministrazione', 'sos_cartello.php' => 'amministrazione',
    // Persone e permessi
    'utenti.php' => 'persone', 'uffici.php' => 'persone', 'uffici_ateneo.php' => 'persone',
    'anagrafe_personale.php' => 'persone', 'anagrafe_docenti.php' => 'persone', 'anagrafe_pta.php' => 'persone', 'anagrafe_insegnamenti.php' => 'persone',
    // Sito pubblico
    'testata.php' => 'sito', 'menu.php' => 'sito', 'comunicazioni.php' => 'sito', 'contatti.php' => 'sito', 'area_personale.php' => 'sito', 'nuova_area.php' => 'sito',
    // Sistema
    'protocollo.php' => 'sistema', 'email.php' => 'sistema', 'sistema.php' => 'sistema', 'audit_log.php' => 'sistema', 'log_accessi.php' => 'sistema', 'simula.php' => 'sistema',
]);

// disponibile = false: tipo previsto ma non ancora utilizzabile
if (!defined('TIPI_AREA')) define('TIPI_AREA', [
    'fsl'        => ['nome' => 'Formazione Scuola Lavoro', 'sezione' => 'fsl', 'disponibile' => true,
                     'descr' => 'Progetti ed eventi per le scuole con convenzioni, elenco degli studenti e attestati: nuovi eventi e progetti con "Attività di Formazione Scuola Lavoro" già acceso.'],
    'eventi'     => ['nome' => 'Eventi e seminari', 'sezione' => 'orientamento', 'disponibile' => true,
                     'descr' => 'Eventi aperti a scuole, studenti ed esterni (es. Welcome Week).'],
    'gruppi'     => ['nome' => 'Gruppi degli insegnamenti', 'sezione' => 'calendari', 'disponibile' => true,
                     'descr' => 'Attività create a partire da un insegnamento dell\'anagrafe, con i gruppi come turni (es. Scienze Motorie).'],
    'calendario' => ['nome' => 'Aule, laboratori e sportelli', 'sezione' => 'calendari', 'disponibile' => true,
                     'descr' => 'Aule, laboratori e sportelli (appuntamenti con gli uffici) prenotabili a calendario, a slot.'],
]);

// Ambiti degli eventi: un evento può averne più d'uno (es. la Notte dei ricercatori è orientamento e public engagement).
// Senza ambiti propri vale quello dell'area (pagine_eventi.ambito) o, se manca, quello del tipo di area.
if (!defined('AMBITI_EVENTO')) define('AMBITI_EVENTO', [
    'orientamento'   => ['nome' => 'Orientamento', 'icona' => 'fa-compass', 'colore' => '#0056B3', 'descr' => 'Per futuri studenti e scuole'],
    'ricerca'        => ['nome' => 'Ricerca', 'icona' => 'fa-flask', 'colore' => '#7c3aed', 'descr' => 'Seminari e conferenze scientifiche'],
    'terza_missione' => ['nome' => 'Public engagement', 'icona' => 'fa-people-group', 'colore' => '#b45309', 'descr' => 'Eventi aperti alla cittadinanza, terza missione'],
    'didattica'      => ['nome' => 'Didattica', 'icona' => 'fa-graduation-cap', 'colore' => '#047857', 'descr' => 'Per gli studenti iscritti'],
]);

// Le funzioni stanno in src/Portale/ (Sezioni; HTML in Vista\Sezioni): qui restano come facciate.
if (!function_exists('tipo_area')) {
    // Tipo dell'area ('' se non assegnato)
    function tipo_area(?array $pagina): string {
        return \App\Portale\Sezioni::tipoArea($pagina);
    }
}

if (!function_exists('sezione_area')) {
    // Macroarea dell'area ('' se il tipo non è assegnato)
    function sezione_area(?array $pagina): string {
        return \App\Portale\Sezioni::sezioneArea($pagina);
    }
}

if (!function_exists('raggruppa_aree_per_sezione')) {
    // Aree divise per macroarea, nell'ordine di SEZIONI_PORTALE; in fondo ('') quelle non assegnate
    function raggruppa_aree_per_sezione(array $aree): array {
        return \App\Portale\Sezioni::raggruppaAreePerSezione($aree);
    }
}

if (!function_exists('modulo_di_area')) {
    // Modulo di un'area: quello della sua macroarea; le aree senza tipo (eventi generici) stanno in Orientamento
    function modulo_di_area(?array $pagina): string {
        return \App\Portale\Sezioni::moduloDiArea($pagina);
    }
}

if (!function_exists('html_scelta_tipo_area')) {
    // Tendina del tipo di area, raggruppata per macroarea
    function html_scelta_tipo_area(string $name, string $valore, string $attr = '', string $classi = 'form-select form-select-sm'): string {
        return \App\Portale\Vista\Sezioni::sceltaTipoArea($name, $valore, $attr, $classi);
    }
}

if (!function_exists('ambito_area')) {
    // Ambito predefinito degli eventi dell'area: quello scelto nelle impostazioni, altrimenti dal tipo di area
    function ambito_area(?array $pagina): string {
        return \App\Portale\Sezioni::ambitoArea($pagina);
    }
    // Ambiti di un evento (chiavi di AMBITI_EVENTO, almeno uno)
    function ambiti_evento(array $evento, ?array $pagina = null): array {
        return \App\Portale\Sezioni::ambitiEvento($evento, $pagina);
    }
    // Valore da salvare in eventi.ambiti dalle caselle del modulo ($post['ambiti'][]); '' = come l'area
    function ambiti_da_post(array $post, ?array $pagina = null): string {
        return \App\Portale\Sezioni::ambitiDaPost($post, $pagina);
    }
    // Caselle degli ambiti per il modulo dell'evento
    function html_scelta_ambiti(array $selezionati, string $id = 'evAmbiti'): string {
        return \App\Portale\Vista\Sezioni::sceltaAmbiti($selezionati, $id);
    }
    // Etichette colorate degli ambiti (pagine pubbliche)
    function html_badge_ambiti(array $ambiti, string $classe = ''): string {
        return \App\Portale\Vista\Sezioni::badgeAmbiti($ambiti, $classe);
    }
}
