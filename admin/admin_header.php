<?php
// admin_header.php - Guscio Superiore, Sicurezza, RBAC e Menu Laterale
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once '../config.php'; // apre la sessione con i parametri sicuri del cookie
require_once '../functions.php';
require_once __DIR__ . '/../inc/ui.php';

// 1. SINCRONIZZAZIONE SSO E CONTROLLO ACCESSO
// [confronto-testata:inizio] da qui a [confronto-testata:fine] la prova tests/Integration/Portale/ContestoPannelloTest.php esegue questo pezzo di testata
// (per una persona collegata) e lo confronta con App\Portale\Pannello\ContestoPannello. Non spostare né togliere i due marcatori senza aggiornare la prova.
sync_sso_user($conn);
// Chi sta aprendo il pannello, in quale area e con quali permessi lo calcola App\Portale\Pannello\ContestoPannello (una volta per richiesta): la testata
// non ricalcola niente, assegna alle variabili di sempre ($utente_admin, $filtro_p, $page_cfg, $permessi…) quello che il contesto ha già deciso.
$richiesta_testata = \App\Http\Richiesta::daGlobali();
$contesto_testata = \App\Core\App::get(\App\Portale\Pannello\ContestoPannello::class);
$accesso_testata = \App\Core\App::get(\App\Portale\Pannello\AccessoPannello::class);
$pagina_ingresso = basename((string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '')); // la pagina aperta, anche quando ne include un'altra
$u_id_curr = $_SESSION['utente_id'] ?? null;
$utente_admin = $contesto_testata->utente($richiesta_testata);

// Chi decide l'accesso: se la pagina arriva dal Nucleo (una rotta, src/Portale/Instradamento/Rotte.php) l'ha già deciso lui, e questa testata, che ne è
// solo il vestito (anche del 403), non lo decide una seconda volta. Una pagina-script (non ancora una rotta) non ha nessuna dichiarazione del Nucleo:
// decide la testata, con le STESSE regole e le stesse risposte (App\Portale\Pannello\AccessoPannello).
$accesso_deciso_dal_nucleo = $utente_admin !== null && $accesso_testata->giaDecisoPer($pagina_ingresso);
if (!$accesso_deciso_dal_nucleo) {
    // Non collegato: rimando al login SSO (con il ritorno a questa pagina), oppure «Autenticazione richiesta» se il login è già stato tentato e non è riuscito
    $risposta_accesso = $accesso_testata->seNonCollegata($richiesta_testata, $pagina_ingresso);
    if ($risposta_accesso !== null) {
        $risposta_accesso->emetti();
        exit;
    }
}

// «È amministratore» lo decide App\Auth\Permessi dalla riga del database (non dalla sessione): la stessa regola di middleware.php e master_template.php
$is_full_admin = $contesto_testata->permessi($richiesta_testata)->eAmministratore();

// ==============================================================================
// GESTIONE AZIONI GLOBALI AREA DI LAVORO
// ==============================================================================
if (isset($_POST['toggle_visibilita_pagina']) && $is_full_admin) {
    csrf_verify($_POST['csrf_token'] ?? '');
    $st_vis = (int)$_POST['stato_visibile'] === 1 ? 1 : 0;
    $p_id_toggle = (int)$_POST['pagina_id'];
    \App\Core\App::get(\App\Portale\AreeRepository::class)->impostaVisibile($p_id_toggle, $st_vis === 1);
    flash_set($st_vis ? "Area visibile al pubblico." : "Area nascosta al pubblico: la vedono solo i gestori.");
    vai_a("aree.php?p_id=$p_id_toggle");
}

if (isset($_POST['del_pagina_completa']) && $is_full_admin) {
    csrf_verify($_POST['csrf_token'] ?? '');
    $p_id_del = (int)$_POST['pagina_id_del'];
    $aree_repo = \App\Core\App::get(\App\Portale\AreeRepository::class);
    if ($p_info_del = $aree_repo->slugETitolo($p_id_del)) {
        // Conferma obbligatoria: il nome dell'area scritto a mano (senza distinguere maiuscole e spazi in più)
        $norm_nome = fn($s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string)$s)));
        if ($norm_nome($_POST['conferma_nome'] ?? '') !== $norm_nome($p_info_del['titolo'])) {
            flash_set("Area non eliminata: per confermare scrivi esattamente il nome dell'area.", 'danger');
            vai_a('aree.php');
        }
        $slug_del = $p_info_del['slug'];
        // Ogni evento con turni, prenotazioni, messaggi, campi form e sondaggi (niente dati orfani)
        foreach ($aree_repo->idAttivita($p_id_del) as $ev_id_del) {
            elimina_evento($conn, $ev_id_del);
        }
        $slug_del_safe = preg_replace('/[^a-z0-9_]/', '', $slug_del);
        $menu_url_del = $slug_del_safe . '.php';
        $aree_repo->eliminaDatiDellArea($p_id_del, $menu_url_del);
        // Vecchi file segnaposto (creati dalle versioni precedenti): si eliminano SOLO se sono esattamente
        // lo stub generato per quest'area, mai un altro file del sito con lo stesso nome.
        foreach (["$slug_del_safe.php" => 'master_template.php', "{$slug_del_safe}_archivio.php" => 'master_archivio.php'] as $file_stub => $master) {
            $percorso = dirname(__DIR__) . '/' . $file_stub;
            if ($slug_del_safe === '' || !is_file($percorso) || filesize($percorso) > 200) continue;
            $contenuto = preg_replace('/\s+/', '', (string)file_get_contents($percorso));
            if ($contenuto === "<?php\$page_slug='$slug_del_safe';require_once'$master';?>"
                || $contenuto === "<?php\$page_slug='$slug_del_safe';require_once'$master';") {
                \App\Core\File::togli($percorso);
            }
        }
        
        if (function_exists('registra_log_audit')) registra_log_audit($conn, "Eliminazione Area", ["Area" => $p_info_del['titolo'], "Slug" => $slug_del]);
        flash_set("Area \"" . $p_info_del['titolo'] . "\" eliminata definitivamente.", 'warning');
        vai_a('aree.php');
    }
}

// ==============================================================================
// 2. AREE DI LAVORO DELL'UTENTE
// Un'area compare se l'utente la gestisce tutta, se gestisce almeno un'attività oppure se ha un perimetro
// che la riguarda (tutti i progetti / tutti gli eventi dell'area, attività di Formazione Scuola Lavoro).
// La regola e la scelta dell'area aperta (?p_id= se la gestisce, altrimenti la prima) stanno in ContestoPannello.
// ==============================================================================
$pagine_disponibili = $contesto_testata->areeDisponibili($richiesta_testata); // le aree che la persona gestisce: stessa regola dell'area personale («Il mio lavoro»)
$filtro_p = $contesto_testata->area($richiesta_testata);
$page_cfg = $contesto_testata->rigaArea($richiesta_testata);

// ==============================================================================
// 3. PERMESSI NELL'AREA CORRENTE
// Dentro il proprio perimetro si gestisce tutto: attività, iscritti, sondaggi, moduli, attestati, statistiche.
// - tutta l'area: come un amministratore, ma solo per quell'area (anche Impostazioni area);
// - attività scelte, tutti i progetti, tutti gli eventi, attività FSL: solo quelle ($sql_filtro_eventi_rbac).
// Le vecchie abilitazioni con solo alcune sezioni (eventi, iscritti…) valgono ora per tutto il perimetro.
// ==============================================================================
$uid_rbac = (int)$u_id_curr;
// I permessi li calcola App\Auth\Permessi (identità + area corrente), una volta per richiesta, e li consegna ContestoPannello; le variabili qui sotto sono
// le stesse di sempre, lette dalle pagine con global. Le regole (modulo FSL, uffici, Didattica, MyForms, Amministrazione, gestore d'area…) e i commenti
// che le spiegano stanno in quella classe.
$permessi = $contesto_testata->permessi($richiesta_testata);
$puo_fsl             = $permessi->fsl();
$puo_fsl_convenzioni = $permessi->fslConvenzioni();
$puo_fsl_scuole      = $permessi->fslScuole();
$puo_didattica_tutto = $permessi->didatticaTutto();
$consigli_referente  = $permessi->consigliReferente();
$puo_didattica       = $permessi->didattica();
$puo_conv_tesi       = $permessi->convenzioniTesi();
$puo_tir_motorie     = $permessi->tirociniMotorie();
$puo_tutorato        = $permessi->tutorato();
$puo_forms           = $permessi->forms();
$puo_forms_cogestore = $permessi->formsCogestore();
$puo_forms_vedere    = $permessi->formsVedere();
$perm_amm            = \App\Core\App::per($conn)->get(\App\Amministrazione\PermessiAmministrazione::class); // le schede di amministrazione.php lo usano
$puo_amministrazione = $permessi->amministrazione();
$puo_pubblicazioni   = $permessi->pubblicazioni();
$puo_amm_entrare     = $permessi->ammEntrare();
$puo_assistenza      = $permessi->assistenza();

$can_manage_eventi    = $permessi->gestisceEventi();
$can_manage_iscritti  = $permessi->gestisceIscritti();
$can_manage_sondaggi  = $permessi->gestisceSondaggi();
$can_manage_form      = $permessi->gestisceForm();
$can_manage_settings  = $permessi->gestisceImpostazioni();
$is_area_manager      = $permessi->gestoreArea();
$puo_creare_eventi    = $permessi->creaEventi();
$puo_creare_progetti  = $permessi->creaProgetti();
$allowed_events_ids   = $permessi->attivitaPermesse();
// [confronto-testata:fine]

// Abilitati senza aree (solo una parte della FSL, il modulo Didattica o MyForms): la loro pagina di partenza è quella
if (!$is_full_admin && !$pagine_disponibili && in_array(basename($_SERVER['PHP_SELF']), ['index.php', 'inizio.php', 'dashboard.php', 'aree.php'], true)
    && ($puo_fsl_convenzioni || $puo_fsl_scuole || $puo_didattica || $puo_conv_tesi || $puo_assistenza || $puo_tir_motorie || $puo_forms_vedere || $puo_amm_entrare)) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Location: ' . ($puo_fsl_convenzioni ? 'fsl_scuole.php' : ($puo_fsl_scuole ? 'scuole.php' : ($puo_didattica ? 'didattica.php' : ($puo_conv_tesi ? 'convenzioni_tesi.php' : ($puo_assistenza ? 'assistenza.php' : ($puo_tir_motorie ? 'tirocini_motorie.php' : ($puo_forms_vedere ? 'forms.php' : 'amministrazione.php'))))))));
    exit;
}

// MyForms non ha aree: la testata del modulo («MODULO · MyForms») apre direttamente l'elenco dei form
if ($puo_forms_vedere && basename($_SERVER['PHP_SELF']) === 'inizio.php' && ($_GET['sezione'] ?? '') === 'forms') {
    while (ob_get_level() > 0) ob_end_clean();
    header('Location: forms.php');
    exit;
}

// Neppure l'Amministrazione ha aree: la testata del modulo apre direttamente «Cosa devo fare» (amministrazione.php).
// Il vecchio indirizzo ?sezione=pubblicazioni (le richieste di pubblicazione erano un modulo a sé) porta allo stesso posto.
if ($puo_amm_entrare && basename($_SERVER['PHP_SELF']) === 'inizio.php' && in_array($_GET['sezione'] ?? '', ['amministrazione', 'pubblicazioni'], true)) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Location: amministrazione.php');
    exit;
}

// La Didattica non ha una pagina di ingresso a parte: il modulo si apre direttamente su «Cosa devo fare» (didattica.php)
if ($puo_didattica && basename($_SERVER['PHP_SELF']) === 'inizio.php' && ($_GET['sezione'] ?? '') === 'didattica') {
    while (ob_get_level() > 0) ob_end_clean();
    header('Location: didattica.php?p_id=' . (int)($_GET['p_id'] ?? 0));
    exit;
}

// ==============================================================================
// 4. MODULI: quelli a cui l'utente ha accesso e quello su cui sta lavorando
// Il modulo corrente decide il menu: le pagine di un modulo senza aree (PAGINE_MODULO) lo indicano da sole,
// la pagina iniziale lo riceve con ?sezione=…, tutte le altre seguono l'area corrente.
// ==============================================================================
// Le voci fisse di ogni modulo e chi le vede stanno in App\Portale\Pannello\MenuModuli (stessi nomi di permesso di PermessiPagine, che Permessi dichiara)
$perm_menu = $permessi->dichiarati();
$moduli_utente = $permessi->moduli();

// Il vecchio modulo «Gestione del portale»: l'indirizzo inizio.php?sezione=portale funziona ancora (persone e permessi, o FSL per chi aveva solo «Scuole», o la scrivania)
if (basename($_SERVER['PHP_SELF']) === 'inizio.php' && ($_GET['sezione'] ?? '') === 'portale') {
    $dest_portale = \App\Portale\Pannello\MenuModuli::moduloDelVecchioPortale($perm_menu);
    while (ob_get_level() > 0) ob_end_clean();
    header('Location: inizio.php?' . ($dest_portale !== '' ? 'sezione=' . $dest_portale . '&' : '') . 'p_id=' . (int)$filtro_p);
    exit;
}

$pagina_corrente = basename($_SERVER['PHP_SELF']);
if (isset(PAGINE_MODULO[$pagina_corrente])) $modulo_corrente = PAGINE_MODULO[$pagina_corrente];
elseif ($pagina_corrente === 'inizio.php') $modulo_corrente = isset(MODULI_PORTALE[$_GET['sezione'] ?? '']) ? $_GET['sezione'] : '';
elseif (in_array($pagina_corrente, ['aree.php', 'index.php'], true)) $modulo_corrente = '';
else $modulo_corrente = $page_cfg ? modulo_di_area($page_cfg) : '';
// Chi apre una pagina di Persone, Sito o Sistema senza avere quel modulo (i testi delle email si aprono anche da Didattica e FSL) non vede un modulo che non è suo
if (in_array($modulo_corrente, ['persone', 'sito', 'sistema'], true) && !in_array($modulo_corrente, $moduli_utente, true)) $modulo_corrente = '';

if (!function_exists('schede_sistema')) {
    // Schede comuni di "Sistema e registri" (una sola voce di menu per tre pagine)
    function schede_sistema(string $attiva): void {
        global $filtro_p;
        $schede = ['sistema.php' => ['fa-envelope', 'Email e controlli'], 'audit_log.php' => ['fa-user-secret', 'Registro operazioni'], 'log_accessi.php' => ['fa-right-to-bracket', 'Accessi SSO']];
        echo '<ul class="nav nav-pills gap-1 mb-3 flex-wrap" style="--bs-nav-pills-link-active-bg:#1e293b;">';
        foreach ($schede as $file => [$ico, $nome]) {
            $on = $file === $attiva;
            echo '<li class="nav-item"><a class="nav-link fw-bold' . ($on ? ' active' : ' bg-light text-dark') . ' ui-fs-85" href="' . $file . '?p_id=' . (int)$filtro_p . '"' . ($on ? ' aria-current="page"' : '') . '><i class="fa ' . $ico . ' me-1" aria-hidden="true"></i>' . $nome . '</a></li>';
        }
        echo '</ul>';
    }
}

// Filtro sulle attività per chi non gestisce tutta l'area
$sql_filtro_eventi_rbac = "";
if (!$is_full_admin && !$is_area_manager) {
    $sql_filtro_eventi_rbac = $allowed_events_ids ? " AND e.id IN (" . implode(',', $allowed_events_ids) . ") " : " AND e.id = -1 ";
}

$sys = \App\Core\App::get(\App\Sistema\ImpostazioniSistemaRepository::class)->riga();
// Fase 3: lettura da cache locale (stessa di header.php/footer.php). Sempre coerente con
// il DB perché testata.php invalida la cache subito dopo ogni salvataggio delle impostazioni.
$cfg_p = get_configurazione_portale($conn);
$current_page = basename($_SERVER['PHP_SELF']);

// Un solo numero per tutte le aree e le risposte delle scuole sulle convenzioni FSL (la casella Messaggi è unica)
$unread_count = \App\Core\App::get(\App\Iscritti\CasellaMessaggi::class)->nonLetti((int)$uid_rbac, $is_full_admin, $puo_fsl_convenzioni, $puo_assistenza);
?>
<!DOCTYPE html>
<html lang="it" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione - MyDiBEST</title>
    <link rel="icon" type="image/x-icon" href="../<?php echo !empty($cfg_p['favicon_path']) ? $cfg_p['favicon_path'] : 'favicon.ico'; ?>">
    <link rel="stylesheet" href="../assets/css/dibest-colori.css?v=<?php echo (int)\App\Core\File::modificato(__DIR__ . '/../assets/css/dibest-colori.css'); ?>">
    <link href="<?php echo url_vendor('jsdelivr/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css'); ?>" rel="stylesheet">
    <link href="<?php echo url_vendor('datatables/1.13.6/css/dataTables.bootstrap5.min.css'); ?>" rel="stylesheet">
    <link href="<?php echo url_vendor('jsdelivr/npm/select2@4.1.0-rc.0/dist/css/select2.min.css'); ?>" rel="stylesheet" />
    <link rel="stylesheet" href="<?php echo url_vendor('cdnjs/ajax/libs/font-awesome/6.4.0/css/all.min.css'); ?>">
    <link rel="stylesheet" href="../assets/css/portale-ui.css?v=<?php echo (int)\App\Core\File::modificato(__DIR__ . '/../assets/css/portale-ui.css'); ?>">
    <link rel="stylesheet" href="../assets/css/pannello.css?v=<?php echo (int)\App\Core\File::modificato(__DIR__ . '/../assets/css/pannello.css'); ?>">
    <script src="<?php echo url_vendor('jquery/jquery-3.7.1.min.js'); ?>" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>
    <?php echo ui_tema_script(true); // tema chiaro/scuro: stessa scelta del sito, applicata prima del disegno ?>
    <style>
        body { background-color: var(--pn-sfondo); overflow-x: hidden; }
        #wrapper { display: flex; width: 100%; min-height: 100vh; }
        #sidebar { width: 260px; min-height: 100vh; transition: all 0.3s ease; z-index: 1000; background: #1e293b; }
        #page-content-wrapper { flex-grow: 1; width: 100%; min-width: 0; transition: all 0.3s ease; }
        #sidebar .nav-pills .nav-link { color: #cbd5e1; border-radius: 8px; margin-bottom: 5px; text-align: left; font-weight: 600; padding: 10px 15px; }
        #sidebar .nav-pills .nav-link.active { background-color: var(--pn-mod-lato, var(--dibest-rosso-scuro)); color: #fff; }
        #sidebar .nav-pills .nav-link:hover:not(.active) { background-color: rgba(255,255,255,0.1); color: #fff; }
        #sidebar .nav > li { width: 100%; min-width: 0; }
        /* Gruppi richiudibili del menu */
        .side-grp > summary { list-style: none; cursor: pointer; display: flex; align-items: center; }
        .side-grp > summary::-webkit-details-marker { display: none; }
        .side-grp-freccia { margin-left: auto; font-size: .7rem; opacity: .7; transition: transform .15s; }
        .side-grp[open] > summary .side-grp-freccia { transform: rotate(90deg); }
        .side-grp-voci { padding-left: 14px; border-left: 1px solid rgba(255,255,255,.12); margin-left: 24px; }
        .side-grp-voci .nav-link { padding: 7px 12px; font-weight: 500; font-size: .9rem; margin-bottom: 2px; }
        .side-grp > summary:focus-visible { outline: 2px solid #93c5fd; outline-offset: 1px; }
        .area-top { border: 2px solid; border-radius: 999px; padding: 4px 12px; background: var(--dibest-sfondo-scheda); max-width: 42vw; }
        .area-top:hover { background: var(--dibest-sfondo-lieve); }
        .area-top-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .area-top-nome { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; min-width: 0; }
        /* Barra in alto: gli !important qui sotto servono: battono utilità di Bootstrap che sono a loro volta !important (me-2, d-md-inline-block, flex-nowrap).
           Sotto i 1400 px i pulsanti mostrano solo l'icona (l'etichetta resta per chi usa lo screen reader), il nome e la ricerca si stringono */
        @media (max-width: 1399.98px) {
            .navbar-azioni .pn-et, .navbar-azioni .ui-tema-btn span { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0; }
            .navbar-azioni .btn.me-2 { margin-right: 0 !important; }
        }
        @media (max-width: 1199.98px) {
            .navbar-azioni > span.d-md-inline-block { display: none !important; }
            .navbar-azioni .ui-w-200 { width: 130px; }
            .navbar-azioni .pn-cerca-tasto { display: none; }
            #page-content-wrapper > .navbar .area-top { max-width: 30vw; }
        }
        .navbar-azioni { min-width: 0; flex-shrink: 1; }
        @media (max-width: 575.98px) {
            /* Telefono: Menu + area sulla prima riga, pulsanti sulla seconda */
            #page-content-wrapper > .navbar { flex-wrap: wrap !important; }
            #page-content-wrapper > .navbar .area-top { flex: 1 1 0; max-width: none; justify-content: center; }
            .navbar-azioni { width: 100%; flex-wrap: wrap; justify-content: flex-end; }
        }
        /* Telefono: tabelle larghe scorrono dentro la loro scheda, barre di pulsanti vanno a capo, menu a tendina non esce dallo schermo */
        @media (max-width: 767.98px) {
            #page-content-wrapper table.table:not(.table-responsive .table) { display: block; max-width: 100%; overflow-x: auto; }
            #page-content-wrapper .d-flex:has(> .ms-auto), .menu-node-header { flex-wrap: wrap; }
            #page-content-wrapper .form-select { max-width: 100%; }
            #dashArea, #formFiltroEv .form-select { min-width: 0 !important; width: 100% !important; }
            #formFiltroEv { flex: 1 1 100%; }
        }
        /* Contrasto: etichette grigie sulla barra scura e pulsanti chiari */
        #sidebar .text-secondary { color: #b4c0d0 !important; }
        html:not([data-bs-theme="dark"]) .btn-outline-secondary { --bs-btn-color: #475569; --bs-btn-border-color: #64748b; }
        .btn-info { --bs-btn-color: #000; --bs-btn-hover-color: #000; --bs-btn-active-color: #000; color: #000; }
        html:not([data-bs-theme="dark"]) .text-info { color: #0b6b80 !important; }
        html:not([data-bs-theme="dark"]) .text-warning { color: #a15c00 !important; }
        #sidebar .text-info { color: #7dd3fc !important; }
        #sidebar .text-warning { color: #fbbf24 !important; }
        .side-num { margin-left: auto; min-width: 22px; padding: 0 7px; border-radius: 999px; background: #f59e0b; color: #1f2937; font-size: .72rem; font-weight: 800; line-height: 20px; text-align: center; }
        #sidebar .nav-link { display: flex; align-items: center; }
        .side-area { background: rgba(255,255,255,0.06); overflow: hidden; }
        .side-modulo { background: rgba(255,255,255,0.08); color: #fff; }
        .side-modulo:hover, .side-modulo.attivo { background: rgba(255,255,255,0.16); }
        .side-altro-modulo { font-weight: 500 !important; font-size: .9rem; padding: 7px 15px !important; }
        @media (max-width: 991.98px) {
            #sidebar { margin-left: -260px; position: fixed; height: 100%; overflow-y: auto; }
            #sidebar.active { margin-left: 0; box-shadow: 5px 0 15px rgba(0,0,0,0.5); }
            #sidebarOverlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; }
            #sidebarOverlay.active { display: block; }
        }
        /* Misure usate solo qui */
        .ui-h-100vh { height: 100vh; }
        .ui-maxw-1400 { max-width: 1400px; }
        .ui-maxw-500 { max-width: 500px; }
        .ui-z-998 { z-index: 998; }
</style>
</head>
<?php
// Colore del modulo corrente per le pagine (schede, pulsanti principali, iter): assets/css/pannello.css
$colore_modulo = colore_valido(MODULI_PORTALE[$modulo_corrente]['colore'] ?? '', '#990000');
$colore_lato = ['sistema' => '#64748b', 'persone' => '#818cf8', 'sito' => '#e879f9'][$modulo_corrente] ?? $colore_modulo; // l'ardesia, l'indaco e il fucsia scuri non si vedono sul menu scuro
?>
<body style="--pn-mod: <?php echo $colore_modulo; ?>; --pn-mod-testo: <?php echo colore_testo_su($colore_modulo); ?>; --pn-mod-lato: <?php echo $colore_lato; ?>;">
<a href="#contenuto-pannello" class="pn-salta">Vai al contenuto</a>

<div id="wrapper" class="no-print">

    <!-- SIDEBAR DINAMICA (RBAC) -->
    <div id="sidebar" class="text-white shadow">
        <div class="p-4 border-bottom border-secondary d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-danger m-0" style="color:#ff4d4d !important;">MyDiBEST<br><small class="text-white fs-6">Gestione</small></h5>
            <button class="btn btn-sm btn-outline-light d-lg-none" id="closeSidebar"><i class="fa fa-times"></i></button>
        </div>
        
        <div class="p-3">
            <ul class="nav nav-pills flex-column">
                <?php
                // Voce del menu; $attive = pagine che la accendono (la prima è il link)
                $voce_attiva = null; // [testo, indirizzo] della prima voce accesa: è la «sezione» del percorso in alto
                $voce_menu = function (array $attive, string $href, string $icona, string $testo, bool $accesa = false, int $numero = 0) use ($current_page, $filtro_p, &$voce_attiva) {
                    $on = $accesa || in_array($current_page, $attive, true);
                    if ($on && $voce_attiva === null) $voce_attiva = [$testo, $href . (str_contains($href, '?') ? '&' : '?') . 'p_id=' . (int)$filtro_p];
                    echo '<li class="nav-item"><a class="nav-link w-100' . ($on ? ' active' : '') . '" href="' . htmlspecialchars($href) . (str_contains($href, '?') ? '&amp;' : '?') . 'p_id=' . (int)$filtro_p . '"' . ($on ? ' aria-current="page"' : '') . '>'
                       . '<i class="fa ' . $icona . ' me-2 text-center ui-w-20" aria-hidden="true"></i> ' . htmlspecialchars($testo)
                       . ($numero > 0 ? '<span class="side-num" title="Da fare">' . $numero . '</span>' : '') . '</a></li>';
                };
                // Gruppo richiudibile: aperto se contiene la pagina corrente, altrimenti come l'ha lasciato l'utente (localStorage)
                $apri_gruppo = function (string $id, string $icona, string $titolo, array $pagine, ?bool $aperto = null) use ($current_page) {
                    $qui = $aperto ?? in_array($current_page, $pagine, true);
                    echo '<li class="nav-item side-grp-li"><details class="side-grp" data-grp="' . $id . '"' . ($qui ? ' open data-qui="1"' : '') . '>'
                       . '<summary class="nav-link w-100"><i class="fa ' . $icona . ' me-2 text-center ui-w-20" aria-hidden="true"></i> ' . htmlspecialchars($titolo)
                       . '<i class="fa fa-chevron-right side-grp-freccia" aria-hidden="true"></i></summary><ul class="nav nav-pills flex-column side-grp-voci">';
                };
                $chiudi_gruppo = function () { echo '</ul></details></li>'; };

                // Voci fisse dei moduli (App\Portale\Pannello\MenuModuli): stessa regola di permesso per il menu, le schede della scrivania e i test
                $tab_did = $current_page === 'didattica.php' ? (in_array($_GET['tab'] ?? '', ['pratiche', 'sedute', 'moduli', 'ufficio', 'statistiche'], true) ? $_GET['tab'] : 'home') : '';
                if (!$puo_didattica_tutto) $tab_did = $current_page === 'didattica.php' ? 'sedute' : '';
                $vista_ana = $current_page === 'anagrafe_personale.php' ? (string)($_GET['vista'] ?? 'docenti') : '';
                $voce_accesa = fn(array $v): bool => (isset($v['tab']) && $tab_did === $v['tab']) || (isset($v['vista']) && in_array($vista_ana, $v['vista'], true)) || in_array($current_page, $v['pagine'], true);
                // Numeri accanto alle voci: se non si riescono a contare (file non ancora caricato) il menu resta senza numeri
                $numero_voce = function (string $modulo, string $id) use ($puo_didattica_tutto, $consigli_referente, $uid_rbac): int {
                    try {
                        switch ($modulo . '.' . $id) {
                            case 'fsl.fsl_scuole': return (int)\App\Core\App::get(\App\Portale\CoseDaFare::class)->convenzioniDaPrendereInCarico();
                            case 'didattica.pratiche': return $puo_didattica_tutto ? (int)\App\Core\App::get(\App\Portale\CoseDaFare::class)->praticheDaSmistare()['n'] : 0;
                            case 'didattica.sedute': return (int)\App\Core\App::get(\App\Portale\CoseDaFare::class)->verbaliInFirma($puo_didattica_tutto ? null : $consigli_referente);
                            case 'didattica.assistenza': return (int)\App\Core\App::get(\App\Assistenza\ServizioAssistenza::class)->contatori($uid_rbac)['da_prendere'];
                            case 'didattica.didacticus': return (int)\App\Core\App::get(\App\Assistenza\Didacticus\MancateRepository::class)->contaDaVedere();
                            case 'didattica.convenzioni_tesi': return (int)\App\Core\App::get(\App\ConvenzioniTesi\ServizioConvenzioniTesi::class)->daFare();
                            case 'didattica.tutorato': return (int)\App\Core\App::get(\App\Portale\CoseDaFare::class)->tutoratoDaValidare();
                        }
                    } catch (\Throwable $e) { error_log('Menu, numero di ' . $modulo . '.' . $id . ': ' . $e->getMessage()); }
                    return 0;
                };
                // Disegna le voci visibili di un modulo (o di un suo gruppo); quelle «a mano» le disegna il modulo stesso
                $disegna_voci = function (string $modulo, ?string $gruppo = null) use ($perm_menu, $voce_menu, $voce_accesa, $numero_voce) {
                    foreach (\App\Portale\Pannello\MenuModuli::vociVisibili($modulo, $perm_menu) as $v) {
                        if (!empty($v['a_mano']) || ($v['gruppo'] ?? null) !== $gruppo) continue;
                        $voce_menu($v['pagine'], $v['href'], $v['icona'], \App\Portale\Pannello\MenuModuli::testo($v, $perm_menu), $voce_accesa($v), $numero_voce($modulo, $v['id']));
                    }
                };
                ?>
                <?php if (count($moduli_utente) > 1 || count($pagine_disponibili) > 1) $voce_menu([], 'inizio.php', 'fa-house', 'La mia scrivania', $current_page === 'inizio.php' && $modulo_corrente === ''); ?>

                <?php if ($modulo_corrente !== ''): $mod_c = MODULI_PORTALE[$modulo_corrente]; ?>
                <!-- ── Modulo corrente: il menu mostra solo ciò che gli appartiene ── -->
                <li class="nav-item mt-3 mb-1">
                    <a href="inizio.php?sezione=<?php echo urlencode($modulo_corrente); ?>&amp;p_id=<?php echo $filtro_p; ?>" class="side-modulo d-flex align-items-center gap-2 px-3 py-2 rounded text-decoration-none<?php echo $current_page === 'inizio.php' ? ' attivo' : ''; ?>" style="border-left:4px solid <?php echo $mod_c['colore']; ?>;" <?php echo $current_page === 'inizio.php' ? 'aria-current="page"' : ''; ?>>
                        <i class="fa <?php echo $mod_c['icona']; ?> ui-w-20" aria-hidden="true"></i>
                        <span class="ui-minw-0"><span class="d-block text-secondary fw-bold text-uppercase ui-fs-80 ui-ls-06em">Modulo</span><span class="fw-bold text-white"><?php echo htmlspecialchars($mod_c['nome']); ?></span></span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (in_array($modulo_corrente, ['orientamento', 'fsl', 'calendari', ''], true) && ($is_full_admin || count($pagine_disponibili) > 1)): // un gestore con una sola area non ha nulla da scegliere ?>
                <li class="nav-item mb-1">
                    <a class="nav-link w-100 <?php echo in_array($current_page, ['aree.php'], true) ? 'active' : ''; ?>" href="aree.php?p_id=<?php echo $filtro_p; ?>">
                        <i class="fa fa-layer-group me-2 text-center ui-w-20" aria-hidden="true"></i> Aree
                        <span class="badge rounded-pill bg-secondary ms-1 ui-fs-80"><?php echo count($pagine_disponibili); ?></span>
                    </a>
                </li>
                <?php endif; ?>

                <?php if ($filtro_p > 0 && $page_cfg && in_array($modulo_corrente, ['orientamento', 'fsl', 'calendari'], true) && modulo_di_area($page_cfg) === $modulo_corrente): $col_side = colore_valido($page_cfg['colore_primario'] ?? '', '#0056B3'); ?>
                <!-- ── Area corrente: tutto ciò che riguarda l'area ── -->
                <li class="nav-item mt-2 mb-2">
                    <div class="side-area px-3 py-2 rounded" style="border-left:4px solid <?php echo $col_side; ?>;">
                        <div class="text-secondary fw-bold text-uppercase ui-fs-80 ui-ls-06em">Area</div>
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <span class="fw-bold text-white text-truncate ui-minw-0" title="<?php echo htmlspecialchars($page_cfg['titolo']); ?>"><?php echo htmlspecialchars($page_cfg['titolo']); ?></span>
                            <?php if (count($pagine_disponibili) > 1): ?><a href="inizio.php?sezione=<?php echo urlencode($modulo_corrente); ?>&amp;p_id=<?php echo $filtro_p; ?>" class="small text-info text-decoration-none text-nowrap">Cambia</a><?php endif; ?>
                        </div>
                        <?php if ((int)($page_cfg['visibile'] ?? 1) === 0): ?><span class="badge bg-warning text-dark mt-1 ui-fs-80"><i class="fa fa-eye-slash me-1" aria-hidden="true"></i>Nascosta al pubblico</span><?php endif; ?>
                    </div>
                </li>
                <?php
                // Quali voci servono: progetti ed eventi solo se ce ne sono (o se si possono creare) nel perimetro dell'utente
                $tipi_vis = \App\Core\App::get(\App\Portale\AreeRepository::class)->tipiAttivita((int)$filtro_p, $sql_filtro_eventi_rbac);
                $vede_eventi = $can_manage_eventi && ($is_area_manager || $puo_creare_eventi || $tipi_vis['evento']);
                $vede_progetti = $can_manage_eventi && (($page_cfg['layout_template'] ?? '') === 'progetti' || $tipi_vis['progetto'] || $puo_creare_progetti && !$is_area_manager);
                // Studenti e attestati: progetti per le scuole o eventi per le classi
                $mostra_classi = false;
                if ($can_manage_iscritti) {
                    $mostra_classi = \App\Core\App::get(\App\Portale\AreeRepository::class)->haAttivitaConClassi((int)$filtro_p, $sql_filtro_eventi_rbac);
                }
                ?>
                <?php if (tipo_area($page_cfg) === 'calendario'): // Calendari e risorse: prenotazioni a slot invece di eventi e turni
                    $n_appr = 0;
                    if ($is_area_manager) {
                        $n_appr = \App\Core\App::get(\App\Portale\AreeRepository::class)->prenotazioniDaApprovare((int)$filtro_p);
                        $voce_menu(['prenotazioni_risorse.php'], 'prenotazioni_risorse.php', 'fa-calendar-check', 'Prenotazioni', false, $n_appr);
                        $voce_menu(['risorse.php'], 'risorse.php', 'fa-door-open', 'Risorse, orari e chiusure');
                    }
                ?>
                <?php else: ?>
                <?php $cosa_ev_attiva = $current_page === 'dashboard.php' && ($_GET['vista'] ?? '') === 'cosa';
                if ($can_manage_iscritti || $can_manage_sondaggi) $voce_menu([], 'dashboard.php?vista=cosa', 'fa-list-check', 'Cosa devo fare', $cosa_ev_attiva);
                $voce_menu($cosa_ev_attiva ? [] : ['dashboard.php', 'index.php'], 'dashboard.php', 'fa-gauge-high', 'Dashboard'); ?>
                <?php if ($can_manage_eventi || $can_manage_form): $apri_gruppo('attivita', 'fa-calendar-alt', 'Attività', ['eventi.php', 'progetti.php', 'form_builder.php', 'archivio.php']); ?>
                    <?php if ($vede_eventi) $voce_menu(['eventi.php'], 'eventi.php', 'fa-calendar-day', 'Eventi e turni'); ?>
                    <?php if ($vede_progetti) $voce_menu(['progetti.php'], 'progetti.php', 'fa-diagram-project', 'Progetti'); ?>
                    <?php if ($can_manage_form) $voce_menu(['form_builder.php'], 'form_builder.php', 'fa-list-check', 'Moduli di iscrizione'); ?>
                    <?php if ($can_manage_eventi) $voce_menu(['archivio.php'], 'archivio.php', 'fa-box-archive', 'Archivio'); ?>
                <?php $chiudi_gruppo(); endif; ?>

                <?php if ($can_manage_iscritti || $can_manage_sondaggi): $apri_gruppo('partecipanti', 'fa-users', 'Partecipanti', ['iscritti.php', 'scanner.php', 'stampa_badge.php', 'stampa_lista_iscritti.php', 'partecipanti.php', 'sondaggi.php']); ?>
                    <?php if ($can_manage_iscritti) $voce_menu(['iscritti.php', 'scanner.php', 'stampa_badge.php', 'stampa_lista_iscritti.php'], 'iscritti.php', 'fa-user-check', 'Iscritti e presenze'); ?>
                    <?php if ($mostra_classi) $voce_menu(['partecipanti.php'], 'partecipanti.php', 'fa-graduation-cap', 'Studenti e attestati'); ?>
                    <?php if ($can_manage_sondaggi) $voce_menu(['sondaggi.php'], 'sondaggi.php', 'fa-star', 'Sondaggi'); ?>
                <?php $chiudi_gruppo(); endif; ?>

                <?php if ($can_manage_iscritti) $voce_menu(['statistiche.php'], 'statistiche.php', 'fa-chart-pie', 'Statistiche'); ?>
                <?php endif; // fine voci per tipo di area ?>
                <?php if ($can_manage_settings) $voce_menu(['impostazioni_area.php'], 'impostazioni_area.php', 'fa-paint-brush', 'Impostazioni area'); ?>
                <?php endif; // fine area corrente ?>

                <?php if ($modulo_corrente === 'orientamento' && $permessi->orientamento()): ?>
                    <!-- ── Modulo Eventi e seminari: report per ambito di tutte le aree ── -->
                    <li class="nav-item mt-3 mb-1"><div class="text-secondary small fw-bold px-3 text-uppercase ui-fs-80 ui-ls-06em">Tutte le aree</div></li>
                    <?php $voce_menu(['report_ambiti.php'], 'report_ambiti.php', 'fa-chart-column', 'Report per ambito'); ?>
                <?php endif; ?>
                <?php if ($modulo_corrente === 'fsl' && ($puo_fsl_convenzioni || $puo_fsl_scuole)): ?>
                    <!-- ── Modulo Formazione Scuola Lavoro: pannello comune a tutte le aree FSL e anagrafe delle scuole ── -->
                    <li class="nav-item mt-3 mb-1"><div class="text-secondary small fw-bold px-3 text-uppercase ui-fs-80 ui-ls-06em">Tutte le aree FSL</div></li>
                    <?php $disegna_voci('fsl'); // Scuole e convenzioni (col numero delle cose da fare), Numeri e report, Anagrafe delle scuole ?>
                <?php endif; ?>

                <?php if ($modulo_corrente === 'didattica' && ($puo_didattica || $puo_conv_tesi || $puo_assistenza || $puo_tir_motorie)): ?>
                    <?php // Quattro gruppi richiudibili (stato ricordato nel browser dallo script in fondo al menu); si apre da solo quello con la pagina corrente
                    foreach (\App\Portale\Pannello\MenuModuli::gruppi('didattica') as $id_g => [$ico_g, $tit_g]) {
                        $voci_g = array_filter(\App\Portale\Pannello\MenuModuli::vociVisibili('didattica', $perm_menu), fn($v) => ($v['gruppo'] ?? '') === $id_g);
                        if (!$voci_g) continue;
                        $apri_gruppo('did-' . $id_g, $ico_g, $tit_g, [], (bool)array_filter($voci_g, $voce_accesa));
                        $disegna_voci('didattica', $id_g);
                        $chiudi_gruppo();
                    } ?>
                <?php endif; ?>

                <?php if ($modulo_corrente === 'forms' && $puo_forms_vedere): ?>
                    <?php $voce_menu(['forms.php', 'forms_risposte.php'], 'forms.php', 'fa-clipboard-list', 'MyForms'); ?>
                <?php endif; ?>

                <?php if ($modulo_corrente === 'amministrazione' && $puo_amm_entrare): ?>
                    <?php // Le voci per chi può tutto (Modulistica, SOS aule…) si aggiungono qui, con la loro scheda di amministrazione.php (SCHEDE_AMMINISTRAZIONE)
                    $tab_amm = $current_page === 'amministrazione.php' ? ($puo_amministrazione && in_array($_GET['tab'] ?? '', ['modulistica', 'impostazioni', 'campi'], true) ? (string)$_GET['tab'] : 'home') : '';
                    $n_pubblicazioni_menu = 0;
                    try {
                        if ($puo_pubblicazioni) {
                            $n_pubblicazioni_menu = \App\Core\App::get(\App\Amministrazione\CoseDaFareAmministrazione::class)->pubblicazioni($utente_admin)['ricevute'];
                        }
                    } catch (\Throwable $e) { error_log('Menu, richieste di pubblicazione: ' . $e->getMessage()); }
                    $voce_menu([], 'amministrazione.php?tab=home', 'fa-list-check', 'Cosa devo fare', $tab_amm === 'home');
                    if ($puo_amministrazione) $voce_menu([], 'amministrazione.php?tab=modulistica', 'fa-file-lines', 'Modulistica', $tab_amm === 'modulistica');
                    // «Da compilare» (scheda di pubblicazioni.php), «Impostazioni» e «Campi e colonne» (pulsanti nella sua testata) sono parti delle richieste:
                    // niente voce propria, si accende quella delle richieste
                    if ($puo_pubblicazioni) $voce_menu([], 'pubblicazioni.php?vista=arrivate', 'fa-bullhorn', 'Richieste di pubblicazione', $current_page === 'pubblicazioni.php' || in_array($tab_amm, ['impostazioni', 'campi'], true), $n_pubblicazioni_menu);
                    if ($puo_amministrazione) $voce_menu(['sos_aule.php', 'sos_cartello.php'], 'sos_aule.php', 'fa-life-ring', 'SOS aule'); ?>
                <?php endif; ?>

                <?php if (in_array($modulo_corrente, ['persone', 'sito', 'sistema'], true)): // moduli di sole pagine proprie: elenco semplice, le voci sono in MenuModuli ?>
                    <?php $disegna_voci($modulo_corrente); ?>
                <?php endif; ?>

                <?php if (count($moduli_utente) > 1): ?>
                    <!-- ── Altri moduli: si passa da uno all'altro senza tornare all'inizio ── -->
                    <li class="nav-item mt-4 mb-1"><div class="text-secondary small fw-bold px-3 text-uppercase ui-fs-80 ui-ls-06em">Altri moduli</div></li>
                    <?php foreach ($moduli_utente as $k_m): if ($k_m === $modulo_corrente) continue; $m_m = MODULI_PORTALE[$k_m]; ?>
                        <li class="nav-item"><a class="nav-link w-100 side-altro-modulo" href="inizio.php?sezione=<?php echo urlencode($k_m); ?>&amp;p_id=<?php echo $filtro_p; ?>"><i class="fa <?php echo $m_m['icona']; ?> me-2 text-center pn-mc-lato" style="width:20px;--mc:<?php echo $m_m['colore']; ?>;" aria-hidden="true"></i> <?php echo htmlspecialchars($m_m['nome']); ?></a></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
            <script nonce="<?php echo csp_nonce(); ?>">
            // Gruppi del menu: si ricorda quali l'utente ha aperto o chiuso (quello della pagina corrente resta aperto)
            (function () {
                var chiave = 'adminMenuGruppi', stato = {};
                try { stato = JSON.parse(localStorage.getItem(chiave) || '{}') || {}; } catch (e) {}
                document.querySelectorAll('#sidebar .side-grp').forEach(function (d) {
                    var g = d.dataset.grp;
                    if (!d.dataset.qui && stato[g] === 1) d.open = true;
                    d.addEventListener('toggle', function () {
                        stato[g] = d.open ? 1 : 0;
                        try { localStorage.setItem(chiave, JSON.stringify(stato)); } catch (e) {}
                    });
                });
            })();
            </script>
        </div>
    </div>
    
    <div id="sidebarOverlay"></div>

    <div id="page-content-wrapper">
        <nav class="navbar navbar-light bg-white border-bottom shadow-sm px-3 py-2 d-flex justify-content-between flex-nowrap gap-2 sticky-top ui-z-998">
            <button class="btn btn-dark d-lg-none flex-shrink-0" id="sidebarToggle"><i class="fa fa-bars"></i> Menu</button>
            <?php if ($modulo_corrente !== '' && !in_array($modulo_corrente, ['orientamento', 'fsl', 'calendari'], true) || ($page_cfg && $modulo_corrente !== '' && modulo_di_area($page_cfg) !== $modulo_corrente)): $m_top = MODULI_PORTALE[$modulo_corrente]; ?>
                <a href="inizio.php?sezione=<?php echo urlencode($modulo_corrente); ?>&amp;p_id=<?php echo $filtro_p; ?>" class="area-top text-decoration-none d-flex align-items-center gap-2" title="Modulo su cui stai lavorando" style="border-color: <?php echo $m_top['colore']; ?>;">
                    <i class="fa <?php echo $m_top['icona']; ?> pn-mc-testo" style="--mc: <?php echo $m_top['colore']; ?>;" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline text-secondary ui-fs-80 ui-fw-700 ui-tt-uppercase ui-ls-05em">Modulo</span>
                    <span class="area-top-nome fw-bold pn-mc-testo" style="--mc: <?php echo $m_top['colore']; ?>;"><?php echo htmlspecialchars($m_top['nome']); ?></span>
                </a>
            <?php elseif ($filtro_p > 0 && $page_cfg): $col_top = colore_valido($page_cfg['colore_primario'] ?? '', '#0056B3'); $link_top = count($pagine_disponibili) > 1 || $is_full_admin; ?>
                <<?php echo $link_top ? 'a href="inizio.php?sezione=' . urlencode(modulo_di_area($page_cfg)) . '&amp;p_id=' . $filtro_p . '"' : 'span'; ?> class="area-top text-decoration-none d-flex align-items-center gap-2" title="Area su cui stai lavorando<?php echo $link_top ? ' — clicca per cambiarla' : ''; ?>" style="border-color: <?php echo $col_top; ?>;">
                    <span class="area-top-dot" style="background: <?php echo $col_top; ?>;" aria-hidden="true"></span>
                    <span class="d-none d-sm-inline text-secondary ui-fs-80 ui-fw-700 ui-tt-uppercase ui-ls-05em">Area</span>
                    <span class="area-top-nome fw-bold pn-mc-testo" style="--mc: <?php echo $col_top; ?>;"><?php echo htmlspecialchars($page_cfg['titolo']); ?></span>
                    <?php if ((int)($page_cfg['visibile'] ?? 1) === 0): ?><span class="badge bg-warning text-dark ui-fs-80" title="Nascosta al pubblico"><i class="fa fa-eye-slash"></i></span><?php endif; ?>
                    <?php if ($link_top): ?><i class="fa fa-right-left text-secondary ui-fs-80" aria-hidden="true"></i><?php endif; ?>
                </<?php echo $link_top ? 'a' : 'span'; ?>>
            <?php endif; ?>
            <div class="ms-auto d-flex align-items-center gap-2 navbar-azioni">
                <form method="GET" action="cerca.php" class="d-none d-md-flex m-0 position-relative" role="search">
                    <input type="hidden" name="p_id" value="<?php echo (int)$filtro_p; ?>">
                    <input class="form-control form-control-sm ui-w-200" type="search" name="q" id="pnCerca" maxlength="100" placeholder="Cerca ovunque…" aria-label="Cerca ovunque nel pannello (scorciatoia Ctrl K)" aria-keyshortcuts="Control+K Meta+K" value="<?php echo basename($_SERVER['SCRIPT_NAME'] ?? '') === 'cerca.php' ? htmlspecialchars((string)($_GET['q'] ?? ''), ENT_QUOTES, 'UTF-8') : ''; ?>">
                    <kbd class="pn-cerca-tasto" id="pnCercaTasto" aria-hidden="true">Ctrl K</kbd>
                </form>
                <a href="cerca.php?p_id=<?php echo (int)$filtro_p; ?>" class="btn btn-outline-secondary btn-sm d-md-none" title="Cerca ovunque"><i class="fa fa-magnifying-glass" aria-hidden="true"></i></a>
                <span class="text-muted small d-none d-md-inline-block"><i class="fa fa-user-shield text-danger me-1"></i> <strong><?php echo htmlspecialchars($utente_admin['nome'] ?? ''); ?></strong></span>
                
                <a href="../area_personale.php" class="btn btn-outline-primary btn-sm fw-bold" title="Torna alla tua area personale: da lì trovi tutto quello che ti aspetta"><i class="fa fa-table-cells-large" aria-hidden="true"></i> <span class="d-none d-sm-inline pn-et">Area personale</span></a>

                <a href="messaggi.php?p_id=<?php echo $filtro_p; ?>" class="btn btn-outline-dark btn-sm fw-bold me-2 shadow-sm" style="background-color: #ffffff;" title="Messaggi">
                    <i class="fa fa-envelope me-1" aria-hidden="true"></i><span class="pn-et">Messaggi</span>
                    <span id="badgeUnreadWrap" <?php echo (isset($unread_count) && $unread_count > 0) ? '' : 'style="display:none;"'; ?>>
                        <span id="badgeUnread" class="badge bg-danger ms-1 text-white shadow-sm ui-bg-rosso-badge"><?php echo 'Nuovi (' . ($unread_count ?? 0) . ')'; ?></span>
                    </span>
                </a>

                <?php echo ui_tema_pulsante(); // Tema chiaro/scuro: lo stesso pulsante dell'area personale ?>
                <a href="scanner.php?p_id=<?php echo $filtro_p; ?>" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm" title="Apri Scanner Check-in"><i class="fa fa-qrcode" aria-hidden="true"></i> <span class="d-none d-sm-inline pn-et">Scanner</span></a>
                <a href="../guida.php" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm fw-bold" title="Le guide passo passo: come si fa"><i class="fa fa-circle-question" aria-hidden="true"></i> <span class="d-none d-sm-inline pn-et">Come si fa?</span></a>
                <a href="../index.php" target="_blank" class="btn btn-outline-secondary btn-sm" title="Vai al sito"><i class="fa fa-external-link-alt" aria-hidden="true"></i> <span class="d-none d-sm-inline pn-et">Visita Sito</span></a>
                <a href="../esci.php" class="btn btn-danger btn-sm" title="Esci" aria-label="Esci"><i class="fa fa-sign-out-alt" aria-hidden="true"></i></a>
            </div>
        </nav>
        <?php
        // Percorso «Modulo › Sezione › Elemento»: modulo da MODULI_PORTALE, sezione = voce di menu accesa (anche le schede ?tab= e ?vista=),
        // elemento = $percorso_elemento che la pagina può impostare (es. il codice di una pratica). La pagina lo sa solo dopo questo punto:
        // qui resta un segnaposto, che admin_footer.php sostituisce con il percorso completo (pn_briciole_html).
        $briciole_base = [];
        if ($modulo_corrente !== '' && isset(MODULI_PORTALE[$modulo_corrente])) {
            $briciole_base[] = [MODULI_PORTALE[$modulo_corrente]['nome'], 'inizio.php?sezione=' . urlencode($modulo_corrente) . '&p_id=' . (int)$filtro_p];
            if ($voce_attiva !== null && $voce_attiva[0] !== MODULI_PORTALE[$modulo_corrente]['nome']) $briciole_base[] = $voce_attiva;
        }
        if (!function_exists('pn_briciole_html')) {
            // Il percorso in HTML (vuoto se ha meno di due pezzi): l'ultimo pezzo non è un link e porta aria-current
            function pn_briciole_html(): string {
                $pezzi = $GLOBALS['briciole_base'] ?? [];
                $elemento = trim((string)($GLOBALS['percorso_elemento'] ?? ''));
                if ($elemento !== '') $pezzi[] = [$elemento, ''];
                if (count($pezzi) < 2) return '';
                $ultimo = count($pezzi) - 1;
                $html = '<nav class="pn-briciole" aria-label="Percorso"><ol>';
                foreach ($pezzi as $i => [$testo, $href]) {
                    $t = htmlspecialchars((string)$testo, ENT_QUOTES, 'UTF-8');
                    $html .= '<li>' . ($i === $ultimo || $href === '' ? '<span' . ($i === $ultimo ? ' aria-current="page"' : '') . '>' . $t . '</span>' : '<a href="' . htmlspecialchars((string)$href, ENT_QUOTES, 'UTF-8') . '">' . $t . '</a>') . '</li>';
                }
                return $html . '</ol></nav>';
            }
        }
        echo '<!--PN_BRICIOLE-->';
        ?>

        <div class="container-fluid p-4 ui-maxw-1400" id="contenuto-pannello" role="main" tabindex="-1">
            <?php echo isset($leggi_avviso) ? $leggi_avviso() : flash_html(); // $leggi_avviso: lo dà il Nucleo (Vista::avviso) alle pagine che serve lui ?>
<?php
// Chi può aprire questa pagina: la mappa App\Portale\Pannello\PermessiPagine, applicata prima del codice della pagina.
// Una pagina che non è in mappa si apre solo da amministratore globale (e si scrive nel log, così si aggiunge la regola).
// I controlli dentro le singole pagine restano: sono la seconda protezione.
if (!function_exists('nega_accesso_pannello')) {
    // «Accesso negato» nello stile del pannello, con stato 403 (prima restava 200). $messaggio = il testo intero; vuoto = «Accesso negato.»
    function nega_accesso_pannello(string $messaggio = ''): never {
        http_response_code(403);
        echo "<div class='alert alert-danger fw-bold shadow-sm m-4'><i class='fa fa-ban me-2'></i> " . htmlspecialchars($messaggio !== '' ? $messaggio : 'Accesso negato.', ENT_QUOTES, 'UTF-8') . "</div>";
        // chi è fuori solo perché l'email scelta a mano non è confermata lo scopre qui, da qualunque pagina arrivi il no (AccessoPannello::avvisoEmail)
        $avviso_email = isset($GLOBALS['accesso_testata'], $GLOBALS['richiesta_testata']) ? $GLOBALS['accesso_testata']->avvisoEmail($GLOBALS['richiesta_testata']) : '';
        if ($avviso_email !== '') echo '<div class="m-4">' . $avviso_email . '</div>';
        require_once __DIR__ . '/admin_footer.php';
        exit;
    }
}
// Se l'accesso lo ha già deciso il Nucleo ($accesso_deciso_dal_nucleo, in cima) qui non si decide di nuovo: la guardia scatta una volta sola.
if ($accesso_deciso_dal_nucleo) {
    // Se il Nucleo ha detto no, la pagina è soltanto l'avviso dentro questo layout: la testata finisce qui (come per un 403 deciso da lei) e il
    // chiamante (App\Http\Vista) aggiunge l'avviso e il piè di pagina. `return` da un file incluso lo termina senza fermare lo script.
    if ($accesso_testata->negatoPer($pagina_ingresso)) return;
} else {
    $esito_accesso = $accesso_testata->perPagina($richiesta_testata, $pagina_ingresso);
    if ($esito_accesso instanceof \App\Http\Risposta) { // pagina d'ingresso di chi non ha sezioni: rimando all'area personale
        $esito_accesso->emetti();
        exit;
    }
    if ($esito_accesso === false) nega_accesso_pannello($accesso_testata->messaggioDelNo($pagina_ingresso));
}
?>


<!-- Modal avviso scadenza sessione -->
<div class="modal fade" id="modSessionTimeout" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning text-dark py-2">
                <h6 class="modal-title fw-bold"><i class="fa fa-clock me-1"></i> Sessione in scadenza</h6>
            </div>
            <div class="modal-body text-center">
                <p class="mb-1">La tua sessione scadrà tra</p>
                <p class="fw-bold fs-4 text-danger mb-1" id="sessionCountdown">2:00</p>
                <p class="text-muted small">Vuoi restare connesso?</p>
            </div>
            <div class="modal-footer py-2 justify-content-center gap-2">
                <button class="btn btn-success btn-sm fw-bold px-4" data-on-click="renewSession"><i class="fa fa-rotate-right me-1"></i> Sì, rinnova</button>
                <a href="../esci.php" class="btn btn-danger btn-sm fw-bold"><i class="fa fa-sign-out-alt me-1"></i> Esci</a>
            </div>
        </div>
    </div>
</div>


<script nonce="<?php echo csp_nonce(); ?>">
// ── Session timeout (avviso 2 min prima dei 30 min di inattività) ─────────────
(function() {
    var SESSION_MINUTES = 30;
    var WARN_BEFORE_SEC = 120;
    var inactivityTimer, countdownTimer;
    var modal = null;
    var secondsLeft = WARN_BEFORE_SEC;

    function getModal() {
        if (!modal && typeof bootstrap !== 'undefined') {
            modal = new bootstrap.Modal(document.getElementById('modSessionTimeout'));
        }
        return modal;
    }

    function startCountdown() {
        secondsLeft = WARN_BEFORE_SEC;
        var el = document.getElementById('sessionCountdown');
        clearInterval(countdownTimer);
        countdownTimer = setInterval(function() {
            secondsLeft--;
            if (el) {
                var m = Math.floor(secondsLeft / 60);
                var s = secondsLeft % 60;
                el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            }
            if (secondsLeft <= 0) {
                clearInterval(countdownTimer);
                window.location.href = '../esci.php';
            }
        }, 1000);
        var m = getModal();
        if (m) m.show();
    }

    function resetTimer() {
        clearTimeout(inactivityTimer);
        inactivityTimer = setTimeout(startCountdown, (SESSION_MINUTES * 60 - WARN_BEFORE_SEC) * 1000);
    }

    window.renewSession = function() {
        clearInterval(countdownTimer);
        var m = getModal();
        if (m) m.hide();
        fetch(window.location.href, { method: 'HEAD', credentials: 'same-origin' });
        resetTimer();
    };

    ['click', 'keydown', 'mousemove', 'touchstart'].forEach(function(e) {
        document.addEventListener(e, resetTimer, { passive: true });
    });
    resetTimer();
})();

// ── Badge messaggi in tempo reale (polling ogni 30s) ─────────────────────────
(function() {
    var pId = <?php echo (int)$filtro_p; ?>;

    function aggiornaBadge(n) {
        var el = document.getElementById('badgeUnread');
        var wrap = document.getElementById('badgeUnreadWrap');
        if (!el || !wrap) return;
        if (n > 0) {
            el.textContent = 'Nuovi (' + n + ')';
            wrap.style.display = '';
        } else {
            wrap.style.display = 'none';
        }
    }

    function fetchUnread() {
        fetch('api_unread.php?p_id=' + pId, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.auth !== false) aggiornaBadge(d.unread); })
            .catch(function() {});
    }

    setInterval(fetchUnread, 30000);
})();

</script>
