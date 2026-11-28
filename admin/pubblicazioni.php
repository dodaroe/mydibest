<?php
// pubblicazioni.php - Richieste di pubblicazione (pannello degli operatori): due schede, «Richieste arrivate» (le approvate non ancora nel file Excel; sopra,
// chiusa, «In attesa di approvazione» in sola lettura) e
// «Tabella di monitoraggio» (solo le righe nel file Excel, con filtri e scaricamento in Excel, PDF e CSV); la scheda di una richiesta (?id=N) con quello che
// ha inviato il docente e le colonne degli operatori; la riga a mano (?nuova=1); le impostazioni (?vista=impostazioni): chi ha tutto il modulo Amministrazione
// va alla scheda «Impostazioni» (amministrazione.php?tab=impostazioni, con tutte le impostazioni), chi ha solo le richieste vede qui la sola parte «Email»
// (indirizzi che ricevono le richieste e ricevuta a chi invia), con lo stesso parziale della scheda (admin/parziali/impostazioni_pubblicazioni_email.php).
// «Da compilare» (?vista=da_compilare), di partenza a schede (admin/parziali/da_compilare_schede.php): una per richiesta con solo le colonne di chi guarda,
// linguette «Da fare · Fatte» (&fatte=1), 20 per pagina e un «Salva» per scheda. Chi vede tutto ha anche «Tabella completa» (&forma=tabella): le righe del file
// Excel con le colonne degli operatori in una tabella sola, celle vuote evidenziate, filtro «Solo le righe con celle vuote» (acceso di partenza), pagine da 50
// righe e un solo «Salva» per pagina. In tutte e due le forme chi vede tutto ha «Solo le mie colonne» (le assegnate; senza assegnazioni il nome dell'utente
// tra gli «a cura di»). Il salvataggio è sempre ServizioPubblicazioni::aggiornaOperatoriInBlocco: tutto o niente, errori accanto al campo, registro delle
// modifiche per ogni richiesta; niente salvataggio automatico.
// «Chi compila cosa» (ColonneDiChi, si imposta in Amministrazione › Campi e colonne): chi non ha tutto il modulo, quando ci sono assegnazioni salvate, vede e
// compila nella scheda, in «Da compilare», nel registro e nello scaricamento solo le sue colonne (più quelle di chi invia); il rifiuto sta in ServizioPubblicazioni.
// La scheda scelta resta nell'indirizzo (?vista=arrivate | monitoraggio | da_compilare), così dopo un salvataggio si torna al punto giusto.
// Permesso controllato a OGNI richiesta, anche GET e scaricamenti (ServizioPubblicazioni::puoGestire = PermessiAmministrazione::puoPubblicazioni: amministratori,
// abilitati al modulo Amministrazione e abilitati a «Amministrazione · Richieste di pubblicazione»). La pagina fa parte del modulo Amministrazione (menu, testata, percorso).
// Le regole stanno in src/Pubblicazioni/ (ServizioPubblicazioni, PubblicazioniRepository, ColonneMonitoraggio, EsportazioneMonitoraggio, Completamento,
// RegistroModifiche, VistaRichiesta); gli allegati si scaricano da allegato_pubblicazione.php (che ricontrolla lo stesso permesso).
require_once 'admin_header.php';

use App\Pubblicazioni\AllegatiPubblicazioni;
use App\Pubblicazioni\ApprovazionePubblicazioni;
use App\Pubblicazioni\ColonneDiChi;
use App\Pubblicazioni\ColonneMonitoraggio;
use App\Pubblicazioni\CompilazioneInTabella;
use App\Pubblicazioni\Completamento;
use App\Pubblicazioni\ConfigurazioneCampi;
use App\Pubblicazioni\Costanti;
use App\Pubblicazioni\EsportazioneMonitoraggio;
use App\Pubblicazioni\ImpostazioniPubblicazioni;
use App\Pubblicazioni\LimitiCaricamento;
use App\Pubblicazioni\PubblicazioniRepository;
use App\Pubblicazioni\RegistroModifiche;
use App\Pubblicazioni\ServizioPubblicazioni;
use App\Pubblicazioni\VistaRichiesta;

$app_pb = \App\Core\App::per($conn);
$sv = $app_pb->get(ServizioPubblicazioni::class);
if (!$sv->puoGestire($utente_admin)) nega_accesso();

$repo = $app_pb->get(PubblicazioniRepository::class);
$cfg_pb = $app_pb->get(ConfigurazioneCampi::class); // campi e colonne come li ha impostati l'amministratore (Amministrazione › Campi e colonne)
$utentiRepo = $app_pb->get(\App\Auth\UtenteRepository::class);
// «Chi compila cosa»: quello che questa persona vede e compila. $col_viste = null vuol dire tutto. Il salvataggio lo ricontrolla ServizioPubblicazioni.
$di_chi = $app_pb->get(ColonneDiChi::class);
$col_viste = $di_chi->senzaLimiti($utente_admin) ? null : $di_chi->visibili($utente_admin);
$col_sue = $di_chi->scrivibili($utente_admin);
$col_conta = $col_viste === null ? null : $col_sue; // i «N da completare»: chi è limitato conta solo le sue colonne
$h =fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$adesso_pb = $app_pb->get(\App\Core\Orologio::class)->adesso();
$limiti = LimitiCaricamento::dalServer($app_pb->get(ImpostazioniPubblicazioni::class)->fileMassimi());

/** Rimanda a un'altra pagina (il pannello ha già cominciato a scrivere: si butta via e si usa l'intestazione). */
function pub_vai(string $url): never
{
    vai_a($url);
}

$dataOra = fn(?string $d) => $d ? date('d/m/Y \a\l\l\e H:i', strtotime($d)) : '';
$nomeUtente = function ($id) use ($utentiRepo): string {
    $u = (int)$id > 0 ? $utentiRepo->perId((int)$id) : null;
    $n = $u ? trim(trim((string)($u['nome'] ?? '')) . ' ' . trim((string)($u['cognome'] ?? ''))) : '';
    return $n !== '' ? $n : ((int)$id > 0 ? 'Utente #' . (int)$id : '');
};
$TONI_STATO = ['da_approvare' => 'info', 'ricevuta' => 'attesa', 'lavorazione' => 'lavoro', 'pubblicata' => 'ok', 'respinta' => 'blocco'];
$etichettaStato = fn(string $s) => pn_stato(Costanti::STATI[$s] ?? $s, $TONI_STATO[$s] ?? 'info');

$id = (int)($_GET['id'] ?? 0);
$vistaGet = (string)($_GET['vista'] ?? '');
$vista = in_array($vistaGet, ['arrivate', 'monitoraggio', 'da_compilare', 'impostazioni'], true) ? $vistaGet : 'arrivate';
$nuova = isset($_GET['nuova']);
$url_lista = fn(string $v = 'arrivate') => 'pubblicazioni.php?vista=' . $v;
// «Da compilare»: filtri e pagina (da GET, o da POST dopo un salvataggio: il filtro «righe con celle vuote» è acceso se non si dice altrimenti)
$src_ct = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$ct_mie = ($src_ct['mie'] ?? '0') === '1';
$ct_vuote = ($src_ct['vuote'] ?? '1') === '1';
$ct_pag = max(1, (int)($src_ct['pag'] ?? 1));
$ct_tabella = ($src_ct['forma'] ?? '') === 'tabella'; // «Tabella completa» (solo per chi vede tutto), altrimenti le schede
$ct_fatte = ($src_ct['fatte'] ?? '0') === '1';
$url_compila = fn(bool $mie, bool $vuote, int $pag = 1) => 'pubblicazioni.php?vista=da_compilare&forma=tabella&mie=' . ($mie ? 1 : 0) . '&vuote=' . ($vuote ? 1 : 0) . ($pag > 1 ? '&pag=' . $pag : '');
$url_schede = fn(bool $mie, bool $fatte, int $pag = 1) => 'pubblicazioni.php?vista=da_compilare' . ($mie ? '&mie=1' : '') . ($fatte ? '&fatte=1' : '') . ($pag > 1 ? '&pag=' . $pag : '');
$errori_tab = []; // id della richiesta => (chiave della colonna | «_») => messaggio, dopo un salvataggio «Da compilare» rifiutato
$url_scheda = fn(int $i, string $v = '') => 'pubblicazioni.php?id=' . $i . ($v !== '' ? '&vista=' . $v : '');
// Il percorso «Amministrazione › Richieste di pubblicazione › …» lo mette il pannello (briciole); qui si dà solo l'ultimo pezzo con $percorso_elemento

$errori_nuova = [];
$errori_col = [];
$e_post = false; // la scheda riparte da quanto scritto (dopo un errore)

// ── Azioni (POST) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($limiti->invioTroppoGrande($_SERVER, $_POST, $_FILES)) {
        // PHP ha scartato tutto perché l'invio supera post_max_size: nessun dato da controllare
        flash_set($limiti->messaggioTroppoGrande(), 'danger');
        pub_vai($nuova ? 'pubblicazioni.php?nuova=1' : $url_lista('monitoraggio'));
    }
    csrf_verify($_POST['csrf_token'] ?? '');
    $azione = (string)($_POST['azione'] ?? '');
    $pid = (int)($_POST['id'] ?? 0);
    $da_scheda = ($_POST['da'] ?? '') === 'scheda';
    $dopo = fn() => $da_scheda && $pid > 0 ? $url_scheda($pid, in_array($_POST['vista'] ?? '', ['arrivate', 'monitoraggio'], true) ? (string)$_POST['vista'] : '') : $url_lista('arrivate');

    if ($azione === 'invia_excel') {
        $esito = $sv->inviaAlMonitoraggio($pid, $utente_admin);
        flash_set($esito->messaggio, $esito->riuscito ? 'success' : 'danger');
        pub_vai($dopo());
    }
    if ($azione === 'togli_excel') {
        $esito = $sv->togliDalMonitoraggio($pid, $utente_admin);
        flash_set($esito->messaggio, $esito->riuscito ? 'success' : 'danger');
        pub_vai($da_scheda ? $url_scheda($pid, 'arrivate') : $url_lista('monitoraggio'));
    }
    if ($azione === 'elimina') {
        $esito = $sv->elimina($pid, $utente_admin);
        flash_set($esito->messaggio, $esito->riuscito ? 'warning' : 'danger');
        pub_vai($url_lista('arrivate'));
    }
    if ($azione === 'salva' && $pid > 0) {
        // Solo le colonne degli operatori e lo stato: tutto il resto del POST si ignora
        $dati = array_intersect_key($_POST, array_flip(array_merge($cfg_pb->colonneOperatori(), ['stato'])));
        $errori_col = ServizioPubblicazioni::erroriColonne($dati, $cfg_pb);
        $p_att = $repo->perId($pid);
        if ($p_att && (int)$p_att['nel_monitoraggio'] !== 1) $errori_col = []; // fuori dal file Excel le colonne non si salvano: nessun controllo
        if ($errori_col) {
            $e_post = true; // la scheda si riapre con quanto scritto e l'errore accanto al campo
            $id = $pid;
        } else {
            $esito = $sv->aggiornaOperatori($pid, $dati, $utente_admin);
            flash_set($esito->messaggio, $esito->riuscito ? 'success' : 'danger');
            pub_vai($url_scheda($pid, in_array($_POST['vista'] ?? '', ['arrivate', 'monitoraggio'], true) ? (string)$_POST['vista'] : ''));
        }
    }
    if ($azione === 'salva_tabella') {
        // Vista «Da compilare»: le celle cambiate (c[id][colonna]) di una scheda («scheda» = la richiesta) o di più righe della tabella, tutto o niente.
        // Il permesso è già stato controllato in cima alla pagina.
        $celle = [];
        foreach (is_array($_POST['c'] ?? null) ? $_POST['c'] : [] as $rid => $cc) {
            if (is_array($cc) && (int)$rid > 0) $celle[(int)$rid] = $cc;
        }
        $ct_scheda = (int)($_POST['scheda'] ?? 0);
        if ($ct_scheda > 0) $celle = array_intersect_key($celle, [$ct_scheda => true]); // il «Salva» di una scheda salva solo quella richiesta
        $esito_t = $sv->aggiornaOperatoriInBlocco($celle, $utente_admin);
        if (!$esito_t['errori'] && $ct_scheda > 0) {
            if ($esito_t['salvate'] === 0) {
                flash_set('Non c\'era nulla da salvare.', 'info');
                pub_vai($url_schede($ct_mie, $ct_fatte, $ct_pag));
            }
            // «Salvata» lo dice la pagina; l'ancora porta alla scheda, se è rimasta nello stesso elenco
            pub_vai($url_schede($ct_mie, $ct_fatte, $ct_pag) . '&salvata=' . $ct_scheda . '#r' . $ct_scheda);
        }
        if (!$esito_t['errori']) {
            flash_set($esito_t['salvate'] === 0 ? 'Non c\'era nulla da salvare.' : ($esito_t['salvate'] === 1 ? 'Salvate le modifiche a 1 richiesta.' : 'Salvate le modifiche a ' . $esito_t['salvate'] . ' richieste.'), $esito_t['salvate'] === 0 ? 'info' : 'success');
            pub_vai($url_compila($ct_mie, $ct_vuote, $ct_pag));
        }
        // errore: non è stato salvato niente; la pagina si ridisegna con quanto scritto e l'errore accanto al campo
        $vista = 'da_compilare';
        $id = 0;
        $errori_tab = $esito_t['errori'];
        $e_post = true;
    }
    if ($azione === 'nuova') {
        $file = [
            'locandina' => AllegatiPubblicazioni::elenco($_FILES['locandina'] ?? []),
            'allegati' => AllegatiPubblicazioni::elenco($_FILES['allegati'] ?? []),
            'immagini' => AllegatiPubblicazioni::elenco($_FILES['immagini'] ?? []),
        ];
        $esito = $sv->creaManuale($_POST, $utente_admin, $file);
        if ($esito['id'] > 0) {
            flash_set('Riga aggiunta: è già nel file Excel.', 'success');
            pub_vai($url_scheda($esito['id'], 'monitoraggio'));
        }
        $errori_nuova = $esito['errori'];
        $nuova = true;
        $id = 0;
    }
    if ($azione === 'impostazioni') {
        // Solo la parte «Email» (indirizzi e ricevuta), per chi gestisce le richieste: il resto delle impostazioni è nella scheda dell'Amministrazione
        $dati_imp = ImpostazioniPubblicazioni::daPost($_POST, ['email']);
        $esito_imp = $app_pb->get(ImpostazioniPubblicazioni::class)->aggiorna($dati_imp);
        if (!$esito_imp['errori']) {
            if ($esito_imp['cambi']) registra_log_audit($conn, 'Richiesta di pubblicazione: impostazioni modificate', ImpostazioniPubblicazioni::cambiInParole($esito_imp['cambi']));
            flash_set($esito_imp['cambi'] ? 'Impostazioni delle email salvate.' : 'Non c\'era nulla da cambiare.', $esito_imp['cambi'] ? 'success' : 'info');
            pub_vai('pubblicazioni.php?vista=impostazioni');
        }
        // errore: la pagina si ridisegna con quello che è stato scritto, così non si perde
        $vista = 'impostazioni';
        $id = 0;
        $imp_err = $esito_imp['errori'];
        $imp_val = $dati_imp + $app_pb->get(ImpostazioniPubblicazioni::class)->tutte();
        $e_post = true;
    }
    if (!$e_post && !$nuova) pub_vai($url_lista('arrivate'));
}

// ── Filtri della tabella di monitoraggio (anche per lo scaricamento) ──
$dataValida = fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4)) ? $d : '';
$statoGet = (string)($_GET['stato'] ?? '');
$filtri = [
    'q' => mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100),
    'stato' => isset(Costanti::STATI[$statoGet]) ? $statoGet : '',
    'dal' => $dataValida((string)($_GET['dal'] ?? '')),
    'al' => $dataValida((string)($_GET['al'] ?? '')),
];
$filtri_attivi = (bool)array_filter($filtri, fn($v) => $v !== '');
$filtriRepo = ['monitoraggio' => 'si', 'testo' => $filtri['q'], 'stato' => $filtri['stato'], 'dal' => $filtri['dal'], 'al' => $filtri['al']];

// ── Scarica Excel, PDF o CSV della tabella di monitoraggio (con i filtri scelti) ──
if (isset($_GET['scarica']) && $id === 0 && !$nuova) {
    $formato = (string)$_GET['scarica'];
    $qs_lista = 'pubblicazioni.php?' . http_build_query(['vista' => 'monitoraggio'] + array_filter($filtri, fn($v) => $v !== ''));
    if (!isset(\App\Infrastructure\Documenti\TabellaEsportabile::FORMATI[$formato])) {
        flash_set('Formato non disponibile.', 'danger');
        pub_vai($qs_lista);
    }
    if ($formato === 'xlsx' && !class_exists(\ZipArchive::class)) {
        flash_set('Su questo server il file Excel non si può creare: scarica il CSV, che si apre con Excel.', 'warning');
        pub_vai($qs_lista);
    }
    $righe_exp = $repo->elenco($filtriRepo);
    // Solo nel CSV: l'apostrofo davanti alle celle che sembrano formule le tiene come testo in Excel (l'xlsx scrive ogni cella come testo, il PDF non ha formule)
    // Chi vede solo le sue colonne scarica quelle (più quelle di chi invia), e il file lo dice, per non scambiarlo con la tabella completa
    $tab = EsportazioneMonitoraggio::tabella($righe_exp, $formato === 'csv', $cfg_pb, $col_viste);
    $sotto = count($tab['righe']) . ' righe · stampato il ' . date('d/m/Y');
    if ($col_viste !== null) $sotto .= ' · copia parziale: solo le colonne di chi invia e quelle assegnate a te';
    if ($filtri_attivi) {
        $sotto .= ' · filtri: ' . implode(', ', array_filter([
            $filtri['q'] !== '' ? 'testo «' . $filtri['q'] . '»' : '',
            $filtri['stato'] !== '' ? 'stato ' . (Costanti::STATI[$filtri['stato']] ?? '') : '',
            $filtri['dal'] !== '' ? 'evento dal ' . date('d/m/Y', strtotime($filtri['dal'])) : '',
            $filtri['al'] !== '' ? 'evento al ' . date('d/m/Y', strtotime($filtri['al'])) : '',
        ]));
    }
    // Il PDF ha una ventina di colonne: più spazio ai testi, meno alle date e alle caselle SI/NO
    $pesi = ['data_evento' => 1.1, 'richiedente' => 1.5, 'titolo' => 2.5, 'sottotitolo' => 2.5, 'descrizione' => 3.5, 'modalita' => 1.2, 'stato' => 1.2,
        'f_pubblicazione_social' => 0.8, 'o_piattaforma_pe' => 0.8, 'u_produzione_locandina' => 0.8, 'v_n_partecipanti' => 0.9];
    $chiavi_exp = $col_viste === null ? $cfg_pb->chiaviExcel() : array_values(array_intersect($cfg_pb->chiaviExcel(), $col_viste));
    $larghezze = array_map(fn($k) => $pesi[$k] ?? 1.4, $chiavi_exp);
    registra_log_audit($conn, 'Richieste di pubblicazione: tabella di monitoraggio scaricata', ['Formato' => $formato, 'Righe' => count($tab['righe']), 'Filtri' => $filtri_attivi ? 'sì' : 'no', 'Colonne' => $col_viste === null ? 'tutte' : 'solo le proprie e quelle di chi invia']);
    while (ob_get_level() > 0) ob_end_clean();
    (new \App\Infrastructure\Documenti\TabellaEsportabile('Tabella di monitoraggio', $tab['intestazioni'], $tab['righe'], $sotto, $larghezze))
        ->scarica($formato, 'Tabella_di_monitoraggio_' . ($col_viste === null ? '' : 'mie_colonne_') . date('Y-m-d'));
    http_response_code(400);
    exit('Formato non disponibile.');
}

$p = $id > 0 ? $repo->perId($id) : null;
if ($id > 0 && !$p) {
    flash_set('Richiesta non trovata: forse è stata eliminata.', 'danger');
    pub_vai($url_lista('arrivate'));
}
?>
<style>
.pb-num { font-size: 1.6rem; font-weight: 800; line-height: 1.1; }
.pb-box { border: 1px solid var(--bs-border-color, var(--dibest-bordo-scheda)); border-radius: 12px; padding: .75rem 1rem; height: 100%; }
.pb-box.avviso .pb-num { color: #b45309; }
.pb-fascia { border: 1px solid #a5f3fc; background: #ecfeff; border-radius: 12px; padding: .75rem 1rem; }
.pb-fascia.dentro { border-color: #86efac; background: #f0fdf4; }
[data-bs-theme="dark"] .pb-fascia { background: #12303a; border-color: #155e75; }
[data-bs-theme="dark"] .pb-fascia.dentro { background: #12311f; border-color: #166534; }
.pb-gruppo { border: 1px solid var(--bs-border-color, var(--dibest-bordo-scheda)); border-radius: 12px; padding: .75rem 1rem; margin-bottom: .9rem; }
.pb-gruppo-titolo { font-weight: 700; font-size: .85rem; text-transform: uppercase; letter-spacing: .04em; color: var(--dibest-neutro-testo); margin-bottom: .5rem; }
.pb-etichetta { display: inline-block; border-radius: 6px; padding: 2px 8px; font-size: .8rem; font-weight: 700; margin-bottom: .25rem; max-width: 100%; }
.pb-errore { color: #b91c1c; font-weight: 700; font-size: .85rem; margin-top: .2rem; }
.pb-dl dt { color: var(--dibest-neutro-testo); font-weight: 600; font-size: .85rem; }
.pb-dl dd { overflow-wrap: anywhere; margin-bottom: .6rem; }
.pb-titolo-cella { max-width: 340px; }
.pb-attesa { height: auto; }
.pb-attesa summary { cursor: pointer; }
.pb-attesa .table td, .pb-attesa .table th { color: var(--dibest-neutro-testo); background: transparent; }
</style>

<?php if ($vista === 'impostazioni' && $id === 0 && !$nuova): ?>
    <?php
    // Chi ha tutto il modulo trova tutte le impostazioni nella scheda dell'Amministrazione: il vecchio indirizzo porta lì
    if ($puo_amministrazione && $_SERVER['REQUEST_METHOD'] !== 'POST') pub_vai('amministrazione.php?tab=impostazioni');
    $imp = $app_pb->get(ImpostazioniPubblicazioni::class);
    $imp_err = $imp_err ?? [];
    $imp_val = $imp_val ?? $imp->tutte();
    $indirizzi = $imp->indirizzi();
    $percorso_elemento = 'Impostazioni';
    echo pn_testata('Impostazioni delle richieste', 'fa-gear', 'A chi arrivano le richieste di pubblicazione e la ricevuta a chi le invia.',
        '<a class="btn btn-outline-secondary btn-sm" href="' . $h($url_lista('arrivate')) . '"><i class="fa fa-arrow-left me-1" aria-hidden="true"></i>Torna alle richieste</a>');
    ?>
    <div class="card border-0 shadow-sm"><div class="card-body">
        <form method="POST" action="pubblicazioni.php" class="m-0"><?php csrf_field(); ?>
            <input type="hidden" name="azione" value="impostazioni">
            <h2 class="h6 fw-bold mb-2">Email</h2>
            <?php if ($imp_err): ?><div class="alert alert-danger py-2 small" role="alert">Le impostazioni non sono state salvate: correggi quello che è segnato qui sotto.</div><?php endif; ?>
            <?php require __DIR__ . '/parziali/impostazioni_pubblicazioni_email.php'; ?>
            <div class="small text-secondary mt-2 mb-3">Indirizzi di adesso: <strong><?php echo $indirizzi ? $h(implode(', ', $indirizzi)) : 'nessuno'; ?></strong>. Chi può inviare la richiesta, apertura, testi e limiti li cambia chi gestisce tutto il modulo Amministrazione.</div>
            <button type="submit" class="btn btn-primary fw-bold"><i class="fa fa-floppy-disk me-1" aria-hidden="true"></i>Salva</button>
        </form>
    </div></div>

<?php elseif ($nuova): ?>
    <?php
    $v = fn(string $k) => $h(is_array($_POST[$k] ?? '') ? '' : ($_POST[$k] ?? ''));
    $spunta = fn(string $k) => !empty($_POST[$k]) ? ' checked' : '';
    $err_n = fn(string $k) => isset($errori_nuova[$k]) ? '<div class="pb-errore" role="alert">' . $h($errori_nuova[$k]) . '</div>' : '';
    $percorso_elemento = 'Nuova riga a mano';
    echo pn_testata('Nuova riga a mano', 'fa-plus', 'Per un\'iniziativa arrivata per altre vie: la riga entra subito nel file Excel. Servono solo data dell\'evento, richiedente e titolo.',
        '<a class="btn btn-outline-secondary btn-sm" href="' . $h($url_lista('monitoraggio')) . '"><i class="fa fa-arrow-left me-1" aria-hidden="true"></i>Torna alla tabella</a>');
    ?>
    <?php if ($errori_nuova): ?>
        <div class="alert alert-danger" role="alert"><strong>Controlla i dati:</strong><ul class="mb-0"><?php foreach ($errori_nuova as $m_e): ?><li><?php echo $h($m_e); ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="POST" action="pubblicazioni.php?nuova=1" enctype="multipart/form-data" class="card border-0 shadow-sm"><div class="card-body">
        <?php csrf_field(); ?><input type="hidden" name="azione" value="nuova">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label fw-bold" for="pnData">Data dell'evento <span class="text-danger">*</span></label>
                <input type="date" class="form-control" id="pnData" name="data_evento" value="<?php echo $v('data_evento'); ?>" required><?php echo $err_n('data_evento'); ?></div>
            <div class="col-md-8"><label class="form-label fw-bold" for="pnRic">Richiedente (Cognome Nome) <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="pnRic" name="richiedente" maxlength="200" value="<?php echo $v('richiedente'); ?>" required><?php echo $err_n('richiedente'); ?></div>
            <div class="col-12"><label class="form-label fw-bold" for="pnTit">Titolo <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="pnTit" name="titolo" maxlength="<?php echo (int)Costanti::MAX_TITOLO; ?>" value="<?php echo $v('titolo'); ?>" required><?php echo $err_n('titolo'); ?></div>
            <div class="col-12"><label class="form-label fw-bold" for="pnSot">Sottotitolo <span class="small fw-normal text-secondary">(massimo <?php echo (int)Costanti::MAX_SOTTOTITOLO; ?> caratteri)</span></label>
                <input type="text" class="form-control" id="pnSot" name="sottotitolo" value="<?php echo $v('sottotitolo'); ?>" data-max="<?php echo (int)Costanti::MAX_SOTTOTITOLO; ?>" data-conta="pnSotN">
                <div class="small text-secondary" id="pnSotN"></div><?php echo $err_n('sottotitolo'); ?></div>
            <div class="col-12"><label class="form-label fw-bold" for="pnDes">Breve descrizione <span class="small fw-normal text-secondary">(testo semplice, massimo <?php echo (int)Costanti::MAX_DESCRIZIONE; ?> caratteri)</span></label>
                <textarea class="form-control" id="pnDes" name="descrizione" rows="3" data-max="<?php echo (int)Costanti::MAX_DESCRIZIONE; ?>" data-conta="pnDesN"><?php echo $v('descrizione'); ?></textarea>
                <div class="small text-secondary" id="pnDesN"></div><?php echo $err_n('descrizione'); ?></div>
            <div class="col-md-4"><label class="form-label fw-bold" for="pnMod">Modalità di svolgimento</label>
                <select class="form-select" id="pnMod" name="modalita"><option value="">—</option>
                    <?php foreach (Costanti::MODALITA as $k_m => $l_m): ?><option value="<?php echo $h($k_m); ?>"<?php echo ($_POST['modalita'] ?? '') === $k_m ? ' selected' : ''; ?>><?php echo $h($l_m); ?></option><?php endforeach; ?>
                </select><?php echo $err_n('modalita'); ?></div>
            <div class="col-md-8"><label class="form-label fw-bold" for="pnUrl">URL della pagina</label>
                <input type="url" class="form-control" id="pnUrl" name="url_docente" maxlength="1000" placeholder="https://…" value="<?php echo $v('url_docente'); ?>"><?php echo $err_n('url_docente'); ?></div>
            <div class="col-12"><label class="form-label fw-bold" for="pnAlt">URL/Link ad altre pagine <span class="small fw-normal text-secondary">(uno per riga)</span></label>
                <textarea class="form-control" id="pnAlt" name="altri_link_docente" rows="2"><?php echo $v('altri_link_docente'); ?></textarea><?php echo $err_n('altri_link_docente'); ?></div>
            <div class="col-12"><span class="form-label fw-bold d-block">Evento rivolto a</span>
                <?php foreach (Costanti::DESTINATARI as $k_d => [$l_d]): ?>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" id="pn_<?php echo $h($k_d); ?>" name="<?php echo $h($k_d); ?>" value="1"<?php echo $spunta($k_d); ?>><label class="form-check-label" for="pn_<?php echo $h($k_d); ?>"><?php echo $h($l_d); ?></label></div>
                <?php endforeach; ?>
                <div class="small text-secondary">Le caselle spuntate scrivono «SI» nelle colonne dei destinatari della tabella.</div></div>
            <div class="col-md-6"><label class="form-label fw-bold" for="pnTag">Tag <span class="small fw-normal text-secondary">(separati da virgola)</span></label>
                <input type="text" class="form-control" id="pnTag" name="tag" maxlength="500" value="<?php echo $v('tag'); ?>"><?php echo $err_n('tag'); ?></div>
            <div class="col-md-6"><label class="form-label fw-bold" for="pnIni">Data inizio pubblicazione</label>
                <input type="date" class="form-control" id="pnIni" name="data_inizio_pubblicazione" value="<?php echo $v('data_inizio_pubblicazione'); ?>"><?php echo $err_n('data_inizio_pubblicazione'); ?></div>
            <div class="col-12"><label class="form-label fw-bold" for="pnNote">Note</label>
                <textarea class="form-control" id="pnNote" name="note" rows="2"><?php echo $v('note'); ?></textarea><?php echo $err_n('note'); ?></div>
            <?php foreach ($cfg_pb->campiExtra() as $x_n): if (!$x_n['visibile']) { continue; } // i campi nuovi dell'amministratore: nessuno è obbligatorio qui, ma il formato si controlla
                $k_x = $x_n['chiave']; $s_x = $_POST[$k_x] ?? ''; ?>
                <div class="col-12"><?php if (in_array($x_n['tipo'], ['radio', 'multicheck'], true)): ?>
                    <span class="form-label fw-bold d-block"><?php echo $h($x_n['etichetta']); ?></span>
                    <?php foreach ($x_n['opzioni'] as $i_x => $o_x): $id_x = 'pn_' . $k_x . '_' . ($i_x + 1);
                        $on_x = $x_n['tipo'] === 'radio' ? (!is_array($s_x) && (string)$s_x === $o_x) : (is_array($s_x) && in_array($o_x, array_map('strval', $s_x), true)); ?>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="<?php echo $x_n['tipo'] === 'radio' ? 'radio' : 'checkbox'; ?>" id="<?php echo $h($id_x); ?>" name="<?php echo $h($k_x . ($x_n['tipo'] === 'radio' ? '' : '[]')); ?>" value="<?php echo $h($o_x); ?>"<?php echo $on_x ? ' checked' : ''; ?>><label class="form-check-label" for="<?php echo $h($id_x); ?>"><?php echo $h($o_x); ?></label></div>
                    <?php endforeach; ?>
                <?php elseif ($x_n['tipo'] === 'checkbox'): ?>
                    <div class="form-check"><input class="form-check-input" type="checkbox" id="pn_<?php echo $h($k_x); ?>" name="<?php echo $h($k_x); ?>" value="1"<?php echo !is_array($s_x) && $s_x !== '' ? ' checked' : ''; ?>><label class="form-check-label fw-bold" for="pn_<?php echo $h($k_x); ?>"><?php echo $h($x_n['etichetta']); ?></label></div>
                <?php else: ?>
                    <label class="form-label fw-bold" for="pn_<?php echo $h($k_x); ?>"><?php echo $h($x_n['etichetta']); ?></label>
                    <?php if ($x_n['tipo'] === 'textarea'): ?>
                        <textarea class="form-control" id="pn_<?php echo $h($k_x); ?>" name="<?php echo $h($k_x); ?>" rows="2"><?php echo $v($k_x); ?></textarea>
                    <?php else: ?>
                        <input type="<?php echo $x_n['tipo'] === 'date' ? 'date' : ($x_n['tipo'] === 'number' ? 'number' : ($x_n['tipo'] === 'url' ? 'url' : 'text')); ?>" class="form-control"<?php echo $x_n['tipo'] === 'number' ? ' step="any"' : ''; ?> id="pn_<?php echo $h($k_x); ?>" name="<?php echo $h($k_x); ?>" value="<?php echo is_array($s_x) ? '' : $v($k_x); ?>">
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (trim((string)$x_n['aiuto']) !== ''): ?><div class="small text-secondary"><?php echo $h($x_n['aiuto']); ?></div><?php endif; ?>
                <?php echo $err_n($k_x); ?></div>
            <?php endforeach; ?>
            <div class="col-12"><label class="form-label fw-bold" for="pnLoc">Locandina <span class="small fw-normal text-secondary">(un file PDF, JPG o PNG, obbligatorio)</span></label>
                <input type="file" class="form-control" id="pnLoc" name="locandina" required accept=".pdf,.jpg,.jpeg,.png"><?php echo $err_n('locandina'); ?></div>
            <div class="col-md-6"><label class="form-label fw-bold" for="pnAll">Altri allegati <span class="small fw-normal text-secondary">(PDF, JPG o PNG, facoltativi)</span></label>
                <input type="file" class="form-control" id="pnAll" name="allegati[]" multiple accept=".pdf,.jpg,.jpeg,.png"><?php echo $err_n('allegati'); ?></div>
            <div class="col-md-6"><label class="form-label fw-bold" for="pnImm">Ulteriori immagini <span class="small fw-normal text-secondary">(JPG o PNG, facoltative)</span></label>
                <input type="file" class="form-control" id="pnImm" name="immagini[]" multiple accept=".jpg,.jpeg,.png"><?php echo $err_n('immagini'); ?></div>
            <div class="col-12 small text-secondary"><?php echo $h($limiti->frase()); ?></div>
            <div class="col-md-4"><label class="form-label fw-bold" for="pnSta">Stato</label>
                <select class="form-select" id="pnSta" name="stato">
                    <?php foreach (Costanti::STATI as $k_s => $l_s): if ($k_s === 'respinta' || $k_s === 'da_approvare') continue; ?><option value="<?php echo $h($k_s); ?>"<?php echo ($_POST['stato'] ?? 'ricevuta') === $k_s ? ' selected' : ''; ?>><?php echo $h($l_s); ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
            <button type="submit" class="btn btn-primary fw-bold"><i class="fa fa-plus me-1" aria-hidden="true"></i>Aggiungi al file Excel</button>
            <a class="btn btn-outline-secondary" href="<?php echo $h($url_lista('monitoraggio')); ?>">Annulla</a>
        </div>
    </div></form>
    <script nonce="<?php echo csp_nonce(); ?>">
    // Contatori dei caratteri: «12 / 75», rosso oltre il limite
    document.querySelectorAll('[data-conta]').forEach(function (c) {
        var out = document.getElementById(c.dataset.conta), max = parseInt(c.dataset.max, 10);
        function aggiorna() { var n = c.value.trim().length; out.textContent = n + ' / ' + max; out.classList.toggle('text-danger', n > max); out.classList.toggle('fw-bold', n > max); }
        c.addEventListener('input', aggiorna); aggiorna();
    });
    </script>

<?php elseif ($p): ?>
    <?php
    // ═════ Scheda di una richiesta ═════
    $dentro = (int)$p['nel_monitoraggio'] === 1;
    $manuale = ($p['origine'] ?? '') === 'manuale';
    $allegati = $repo->allegati((int)$p['id']);
    $registro = $app_pb->get(RegistroModifiche::class)->perRichiesta((int)$p['id'], 100, $col_viste);
    $vista_s = $dentro ? 'monitoraggio' : 'arrivate'; // la scheda in cui sta ora la riga: lì si torna
    $valoreCol = fn(string $k) => $e_post ? (string)($_POST[$k] ?? '') : (string)($p[$k] ?? '');
    $statoScelto = $e_post && isset(Costanti::STATI[(string)($_POST['stato'] ?? '')]) ? (string)$_POST['stato'] : (string)$p['stato'];
    $creatoDa = '';
    foreach (array_reverse($registro) as $voce) { if ($manuale && $voce['titolo'] === 'Riga aggiunta a mano') { $creatoDa = $voce['chi'] . ' il ' . $dataOra($voce['quando']); break; } }
    $nM = Completamento::numeroMancanti($p, $cfg_pb, $col_conta);

    echo pn_indietro($url_lista($vista_s), $vista_s === 'monitoraggio' ? 'Torna alla tabella di monitoraggio' : 'Torna alle richieste arrivate');
    $percorso_elemento = (string)$p['titolo'];
    echo pn_testata((string)$p['titolo'], 'fa-bullhorn',
        ($manuale ? 'Riga aggiunta a mano' . ($creatoDa !== '' ? ' da ' . $creatoDa : '') . '. ' : 'Richiesta di ' . $p['richiedente'] . ', inviata il ' . $dataOra($p['inviata_il']) . '. ')
        . 'Data dell\'evento: ' . (EsportazioneMonitoraggio::data((string)$p['data_evento']) ?: 'non indicata') . '.',
        $etichettaStato((string)$p['stato']));
    ?>

    <?php
    // Chi ha deciso e quando. Le richieste di prima dell'approvazione non hanno una decisione registrata; le righe a mano e quelle in attesa non ne hanno bisogno.
    $motivo_s = trim((string)($p['respinta_motivo'] ?? ''));
    $respinta_s = $p['stato'] === 'respinta' && $motivo_s !== ''; // respinta da chi approva (PubblicazioniRepository::approvata)
    if (!empty($p['decisa_il'])): ?>
        <p class="mb-3"><i class="fa <?php echo $respinta_s ? 'fa-circle-xmark text-danger' : 'fa-circle-check text-success'; ?> me-1" aria-hidden="true"></i><strong><?php echo $respinta_s ? 'Respinta' : 'Approvata'; ?> da <?php echo $h($nomeUtente($p['decisa_da']) ?: 'un approvatore'); ?></strong> il <?php echo $h($dataOra($p['decisa_il'])); ?>.<?php echo $respinta_s ? ' Motivo: ' . $h($motivo_s) : ''; ?></p>
    <?php elseif (!$manuale && !in_array($p['stato'], ['da_approvare', 'respinta'], true)): ?>
        <p class="mb-3 text-secondary"><i class="fa fa-circle-check me-1" aria-hidden="true"></i>Approvata: prima dell'approvazione.</p>
    <?php endif; ?>

    <!-- Fascia: nel file Excel o no -->
    <div class="pb-fascia<?php echo $dentro ? ' dentro' : ''; ?> d-flex flex-wrap align-items-center gap-2 mb-3">
        <?php if ($dentro): ?>
            <div class="flex-grow-1"><i class="fa fa-table-list me-1 text-success" aria-hidden="true"></i><strong>Nel file Excel</strong> dal <?php echo $h(EsportazioneMonitoraggio::data((string)$p['monitoraggio_dal']) ?: '—'); ?><?php echo !empty($p['monitoraggio_da']) ? ' (da ' . $h($nomeUtente($p['monitoraggio_da'])) . ')' : ''; ?>.<?php if ($col_conta !== []): ?> <span class="small text-secondary"><?php echo $h(Completamento::testo($nM)); ?>.</span><?php endif; ?></div>
            <form method="POST" action="pubblicazioni.php" class="m-0"><?php csrf_field(); ?>
                <input type="hidden" name="azione" value="togli_excel"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><input type="hidden" name="da" value="scheda">
                <button type="submit" class="btn btn-outline-secondary btn-sm fw-bold" data-confirm="Togliere la riga dal file Excel? Le colonne che hai compilato restano salvate: se la rimetti le ritrovi."><i class="fa fa-arrow-right-from-bracket me-1" aria-hidden="true"></i>Togli dal file Excel</button>
            </form>
        <?php else: ?>
            <div class="flex-grow-1"><i class="fa fa-inbox me-1 text-info" aria-hidden="true"></i><strong>Non ancora nel file Excel</strong><?php echo $p['stato'] === 'respinta' ? ' <span class="small text-secondary">· richiesta respinta: non si può inviare al file Excel.</span>' : ''; ?></div>
            <form method="POST" action="pubblicazioni.php" class="m-0"><?php csrf_field(); ?>
                <input type="hidden" name="azione" value="invia_excel"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><input type="hidden" name="da" value="scheda">
                <button type="submit" class="btn btn-primary btn-sm fw-bold"<?php echo $p['stato'] === 'respinta' ? ' disabled title="Una richiesta respinta non si può inviare al file Excel."' : ' data-confirm="Inviare la richiesta al file Excel? Le colonne dell\'indirizzo (D, E) e dei destinatari si precompilano con quello che ha scritto il docente."'; ?>><i class="fa fa-table-list me-1" aria-hidden="true"></i>Invia al file Excel</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Quello che ha inviato il docente -->
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <h2 class="h6 fw-bold mb-0 flex-grow-1"><?php echo $manuale ? 'Dati della riga' : 'Quello che ha inviato il docente'; ?></h2>
            <button type="button" class="btn btn-outline-secondary btn-sm fw-bold" id="pbCopia" data-testo="<?php echo $h(VistaRichiesta::testoDaCopiare($p)); ?>"><i class="fa fa-copy me-1" aria-hidden="true"></i><span>Copia il testo</span></button>
        </div>
        <p class="small text-secondary">In sola lettura. «Copia il testo» mette negli appunti titolo, sottotitolo e descrizione senza formattazione, pronti da incollare.</p>
        <dl class="row pb-dl mb-0">
            <?php foreach (VistaRichiesta::righe($p, $allegati, '../', $cfg_pb) as [$et, $val]): ?>
                <dt class="col-sm-3"><?php echo $h($et); ?></dt><dd class="col-sm-9"><?php echo $val; /* già «escapato» da VistaRichiesta */ ?></dd>
            <?php endforeach; ?>
        </dl>
    </div></div>

    <!-- Colonne degli operatori e stato -->
    <form method="POST" action="pubblicazioni.php?id=<?php echo (int)$p['id']; ?>&amp;vista=<?php echo $h($vista_s); ?>" class="card border-0 shadow-sm mb-3" id="pbForm"><div class="card-body">
        <?php csrf_field(); ?>
        <input type="hidden" name="azione" value="salva"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>"><input type="hidden" name="vista" value="<?php echo $h($vista_s); ?>">
        <h2 class="h6 fw-bold">Tabella di monitoraggio</h2>
        <?php if ($errori_col): ?>
            <div class="alert alert-danger py-2" role="alert">Controlla i campi indicati: non è stato salvato niente. Quello che avevi scritto è ancora qui.</div>
        <?php endif; ?>

        <div class="row g-2 align-items-end mb-3">
            <div class="col-sm-5 col-md-4">
                <label class="form-label fw-bold small mb-1" for="pbStato">Stato della richiesta</label>
                <select class="form-select" id="pbStato" name="stato" data-nel-excel="<?php echo $dentro ? '1' : '0'; ?>" data-stato-attuale="<?php echo $h($p['stato']); ?>">
                    <?php $decide_chi_approva = !PubblicazioniRepository::approvata($p); // in attesa o respinta da chi approva: lo stato resta quello
                    foreach (Costanti::STATI as $k_s => $l_s): if (($k_s === 'da_approvare' || $decide_chi_approva) && $k_s !== $p['stato']) continue; ?><option value="<?php echo $h($k_s); ?>"<?php echo $statoScelto === $k_s ? ' selected' : ''; ?>><?php echo $h($l_s); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-7 col-md-8 small text-secondary"><?php echo $decide_chi_approva ? 'Lo stato di questa richiesta lo decide chi approva: dalla scheda non si cambia.' : 'Lo stato si può cambiare sempre. Passando a «Respinta» una riga che è nel file Excel, la riga esce dal file.'; ?></div>
        </div>

        <?php if (!$dentro): ?>
            <p class="text-secondary mb-3"><i class="fa fa-circle-info me-1" aria-hidden="true"></i>Le colonne della tabella di monitoraggio si compilano dopo aver premuto Invia al file Excel.</p>
        <?php else: ?>
            <?php
            $docenteScrive = ['d_url_pubblicazione' => (string)($p['url_docente'] ?? ''), 'e_url_altre_pagine' => (string)($p['altri_link_docente'] ?? '')];
            $norm = fn(string $s) => trim((string)preg_replace('/\s+/u', ' ', $s));
            if ($col_viste !== null): ?>
                <p class="small text-secondary mb-2"><i class="fa fa-user-check me-1" aria-hidden="true"></i><?php echo $col_sue ? 'Qui sotto ci sono solo le colonne assegnate a te.' : 'Nessuna colonna è assegnata a te: da qui puoi cambiare solo lo stato. Le colonne le assegna chi gestisce tutto il modulo Amministrazione («Campi e colonne» › «Chi compila cosa»).'; ?></p>
            <?php endif;
            foreach (Completamento::gruppiPerPersona($cfg_pb) as $g):
                $g_colonne = array_filter($g['colonne'], fn($c) => in_array($c['chiave'], $col_sue, true));
                if (!$g_colonne) continue; ?>
                <div class="pb-gruppo">
                    <div class="pb-gruppo-titolo">A cura di <?php echo $h($g['a_cura_di']); ?></div>
                    <div class="row g-3">
                    <?php foreach ($g_colonne as $c):
                        $k = $c['chiave']; $val = $valoreCol($k); $campoId = 'pb_' . $k;
                        $largo = in_array($k, ['d_url_pubblicazione', 'e_url_altre_pagine'], true);
                    ?>
                        <div class="<?php echo $largo ? 'col-12' : 'col-md-6'; ?>">
                            <label class="pb-etichetta" for="<?php echo $h($campoId); ?>" style="background: <?php echo $h($c['colore']); ?>; color: <?php echo $h(colore_testo_su($c['colore'])); ?>;"><?php echo $h(($c['lettera'] !== '' ? $c['lettera'] . ' · ' : '') . $cfg_pb->etichettaColonna($k)); // le colonne nuove non hanno la lettera del file Excel originale ?></label>
                            <?php if ($c['tipo'] === 'si_no'): ?>
                                <select class="form-select" id="<?php echo $h($campoId); ?>" name="<?php echo $h($k); ?>">
                                    <option value="">—</option>
                                    <?php foreach (['SI', 'NO'] as $o_v): ?><option value="<?php echo $o_v; ?>"<?php echo mb_strtoupper(trim($val)) === $o_v ? ' selected' : ''; ?>><?php echo $o_v; ?></option><?php endforeach; ?>
                                    <?php if (trim($val) !== '' && !in_array(mb_strtoupper(trim($val)), ['SI', 'NO'], true)): ?><option value="<?php echo $h($val); ?>" selected><?php echo $h($val); ?></option><?php endif; ?>
                                </select>
                            <?php elseif ($c['tipo'] === 'numero'): ?>
                                <input type="number" min="0" step="1" class="form-control" id="<?php echo $h($campoId); ?>" name="<?php echo $h($k); ?>" value="<?php echo $h($val); ?>">
                            <?php elseif ($largo): ?>
                                <textarea class="form-control" rows="3" maxlength="<?php echo (int)Costanti::MAX_COLONNA; ?>" id="<?php echo $h($campoId); ?>" name="<?php echo $h($k); ?>"><?php echo $h($val); ?></textarea>
                            <?php else: ?>
                                <input type="text" class="form-control" maxlength="<?php echo (int)Costanti::MAX_COLONNA; ?>" id="<?php echo $h($campoId); ?>" name="<?php echo $h($k); ?>" value="<?php echo $h($val); ?>">
                            <?php endif; ?>
                            <?php if (isset($errori_col[$k])): ?><div class="pb-errore" role="alert"><?php echo $h($errori_col[$k]); ?></div><?php endif; ?>
                            <?php if (isset($docenteScrive[$k]) && $norm($docenteScrive[$k]) !== '' && $norm((string)($p[$k] ?? '')) !== $norm($docenteScrive[$k])): ?>
                                <div class="small text-secondary mt-1">scritto dal docente: <?php echo nl2br($h(trim($docenteScrive[$k]))); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
            <button type="submit" class="btn btn-primary fw-bold" id="pbSalva"><i class="fa fa-floppy-disk me-1" aria-hidden="true"></i>Salva</button>
            <span class="small text-secondary">
                <?php if (!empty($p['modificata_il'])): ?>Ultima modifica: <strong><?php echo $h($nomeUtente($p['modificata_da']) ?: 'un operatore'); ?></strong>, il <?php echo $h($dataOra($p['modificata_il'])); ?>.
                <?php else: ?>Nessuna modifica fatta dagli operatori.<?php endif; ?>
            </span>
        </div>
    </div></form>

    <!-- Registro delle modifiche -->
    <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <h2 class="h6 fw-bold">Registro delle modifiche</h2>
        <?php if (!$registro): ?>
            <p class="small text-secondary mb-0">Ancora nessuna modifica da parte degli operatori.</p>
        <?php else: ?>
            <ul class="list-unstyled small mb-0">
            <?php foreach ($registro as $voce): ?>
                <li class="mb-2"><strong><?php echo $h($dataOra($voce['quando'])); ?></strong> · <?php echo $h($voce['chi']); ?> · <?php echo $h($voce['titolo']); ?>
                    <?php if ($voce['righe']): ?><ul class="mb-0 ps-3"><?php foreach ($voce['righe'] as $r_v): ?><li><?php echo $h($r_v); ?></li><?php endforeach; ?></ul><?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div></div>

    <!-- Elimina (non una richiesta in attesa: prima decide chi approva) -->
    <?php if ($p['stato'] !== 'da_approvare'): ?>
    <div class="card border-danger border-opacity-25 shadow-sm"><div class="card-body">
        <h2 class="h6 fw-bold text-danger">Elimina</h2>
        <p class="small text-secondary">Cancella la richiesta<?php echo $dentro ? ' (e la riga del file Excel)' : ''; ?>, con gli allegati e le immagini caricati. Non si può annullare.</p>
        <form method="POST" action="pubblicazioni.php" class="m-0"><?php csrf_field(); ?>
            <input type="hidden" name="azione" value="elimina"><input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm fw-bold" data-confirm="Eliminare «<?php echo $h($p['titolo']); ?>» con tutti i suoi file? Non si può annullare."><i class="fa fa-trash me-1" aria-hidden="true"></i>Elimina la richiesta</button>
        </form>
    </div></div>
    <?php endif; ?>

    <script nonce="<?php echo csp_nonce(); ?>">
    // «Copia il testo»: titolo, sottotitolo e descrizione negli appunti
    (function () {
        var b = document.getElementById('pbCopia');
        if (!b) return;
        b.addEventListener('click', function () {
            var t = b.dataset.testo, etichetta = b.querySelector('span');
            function fatto() { etichetta.textContent = 'Copiato'; setTimeout(function () { etichetta.textContent = 'Copia il testo'; }, 2000); }
            function ripiego() { var a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select(); try { document.execCommand('copy'); fatto(); } catch (e) {} a.remove(); }
            if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(t).then(fatto, ripiego); else ripiego();
        });
        // Passare a «Respinta» una riga nel file Excel: conferma (la riga esce dal file)
        var sel = document.getElementById('pbStato'), salva = document.getElementById('pbSalva');
        function conferma() {
            if (sel.value === 'respinta' && sel.dataset.statoAttuale !== 'respinta' && sel.dataset.nelExcel === '1') salva.setAttribute('data-confirm', 'Segnare la richiesta come respinta? La riga uscirà dal file Excel (le colonne compilate restano salvate).');
            else salva.removeAttribute('data-confirm');
        }
        sel.addEventListener('change', conferma); conferma();
    })();
    </script>

<?php else: ?>
    <?php
    // ═════ Elenco: richieste arrivate / tabella di monitoraggio ═════
    $tutte = $repo->elenco();
    // «Richieste arrivate»: solo le approvate (o arrivate senza approvazione); quelle in attesa stanno sopra, in sola lettura, dalla più vecchia
    $arrivate = array_values(array_filter($tutte, fn($r) => (int)$r['nel_monitoraggio'] === 0 && PubblicazioniRepository::approvata($r)));
    $in_attesa = $app_pb->get(ApprovazionePubblicazioni::class)->daApprovare();
    $nel_file = array_values(array_filter($tutte, fn($r) => (int)$r['nel_monitoraggio'] === 1));
    $anno = $adesso_pb->format('Y');
    $conta_stato = fn(string $s) => count(array_filter($arrivate, fn($r) => $r['stato'] === $s));
    $da_completare = count(array_filter($nel_file, fn($r) => Completamento::numeroMancanti($r, $cfg_pb, $col_conta) > 0)); // anche il numero sulla scheda «Da compilare»
    $pubblicate_anno = count(array_filter($nel_file, fn($r) => $r['stato'] === 'pubblicata' && substr((string)$r['data_evento'], 0, 4) === $anno));

    $azioni = '<a class="btn btn-outline-secondary btn-sm" href="' . ($puo_amministrazione ? 'amministrazione.php?tab=impostazioni' : 'pubblicazioni.php?vista=impostazioni') . '"><i class="fa fa-gear me-1" aria-hidden="true"></i>Impostazioni</a>';
    // «Impostazioni» e «Campi e colonne» non hanno una voce nel menu a sinistra: ci si arriva da qui (Campi e colonne solo chi ha tutto il modulo)
    if ($puo_amministrazione) $azioni .= ' <a class="btn btn-outline-secondary btn-sm" href="amministrazione.php?tab=campi"><i class="fa fa-table-list me-1" aria-hidden="true"></i>Campi e colonne</a>';
    $frase = $vista !== 'da_compilare'
        ? 'Le richieste di docenti e personale di pubblicare su sito e social. Una richiesta entra nella tabella di monitoraggio (il file Excel) quando premi «Invia al file Excel».'
        : ($col_viste !== null ? 'Compila le colonne assegnate a te nelle richieste che sono nel file Excel.' : 'Compila le colonne degli operatori nelle richieste che sono nel file Excel.');
    echo pn_testata('Richieste di pubblicazione', 'fa-bullhorn', $frase, $azioni);
    echo pn_schede([
        'arrivate' => ['href' => $url_lista('arrivate'), 'testo' => 'Richieste arrivate', 'icona' => 'fa-inbox', 'numero' => count($arrivate), 'titolo' => 'Le richieste non ancora nel file Excel'],
        'monitoraggio' => ['href' => $url_lista('monitoraggio'), 'testo' => 'Tabella di monitoraggio (file Excel)', 'icona' => 'fa-table-list', 'numero' => count($nel_file)],
        'da_compilare' => ['href' => $url_lista('da_compilare'), 'testo' => 'Da compilare', 'icona' => 'fa-pen-to-square', 'numero' => $da_completare, 'titolo' => 'Le richieste del file Excel con colonne ancora da compilare'],
    ], $vista);
    ?>

    <?php if ($vista === 'monitoraggio'): ?>
        <?php
        $righe = $repo->elenco($filtriRepo);
        $url_mon = 'pubblicazioni.php?' . http_build_query(['vista' => 'monitoraggio'] + array_filter($filtri, fn($v) => $v !== ''));
        ?>
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-4"><div class="pb-box"><div class="pb-num"><?php echo count($nel_file); ?></div><div class="small text-secondary">righe nel file</div></div></div>
            <div class="col-6 col-md-4"><div class="pb-box<?php echo $da_completare > 0 ? ' avviso' : ''; ?>"><div class="pb-num"><?php echo $da_completare; ?></div><div class="small text-secondary"><?php echo $col_conta === null ? 'con colonne da completare' : 'con le tue colonne da completare'; ?></div></div></div>
            <div class="col-12 col-md-4"><div class="pb-box"><div class="pb-num"><?php echo $pubblicate_anno; ?></div><div class="small text-secondary">pubblicate nel <?php echo $h($anno); ?> (per data dell'evento)</div></div></div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
            <form method="GET" class="d-flex flex-wrap gap-2 align-items-end flex-grow-1 m-0">
                <input type="hidden" name="vista" value="monitoraggio">
                <div><label class="form-label small mb-0" for="pbQ">Cerca nel testo</label><input type="text" class="form-control form-control-sm" id="pbQ" name="q" value="<?php echo $h($filtri['q']); ?>" maxlength="100" placeholder="titolo, richiedente, tag"></div>
                <div><label class="form-label small mb-0" for="pbS">Stato</label>
                    <select class="form-select form-select-sm" id="pbS" name="stato"><option value="">Tutti</option>
                        <?php foreach (Costanti::STATI as $k_s => $l_s): ?><option value="<?php echo $h($k_s); ?>"<?php echo $filtri['stato'] === $k_s ? ' selected' : ''; ?>><?php echo $h($l_s); ?></option><?php endforeach; ?>
                    </select></div>
                <div><label class="form-label small mb-0" for="pbDal">Evento dal</label><input type="date" class="form-control form-control-sm" id="pbDal" name="dal" value="<?php echo $h($filtri['dal']); ?>"></div>
                <div><label class="form-label small mb-0" for="pbAl">al</label><input type="date" class="form-control form-control-sm" id="pbAl" name="al" value="<?php echo $h($filtri['al']); ?>"></div>
                <button class="btn btn-outline-secondary btn-sm" type="submit">Filtra</button>
                <?php if ($filtri_attivi): ?><a class="btn btn-link btn-sm" href="<?php echo $h($url_lista('monitoraggio')); ?>">Azzera</a><?php endif; ?>
            </form>
            <a class="btn btn-primary btn-sm fw-bold" href="pubblicazioni.php?nuova=1"><i class="fa fa-plus me-1" aria-hidden="true"></i>Nuova riga a mano</a>
            <?php echo $righe ? pn_scarica($url_mon, 'Scarica') : ''; ?>
        </div>
        <?php if ($filtri_attivi && $righe): ?><p class="small text-secondary">Lo scaricamento rispetta i filtri scelti (<?php echo count($righe); ?> righe).</p><?php endif; ?>

        <?php if (!$nel_file): ?>
            <div class="card border-0 shadow-sm"><?php echo pn_vuoto('Nessuna riga nel file Excel. Invia una richiesta dalla scheda «Richieste arrivate» oppure aggiungi una riga a mano.', 'fa-table-list'); ?></div>
        <?php elseif (!$righe): ?>
            <div class="card border-0 shadow-sm"><?php echo pn_vuoto('Nessuna riga con questi filtri.', 'fa-filter'); ?></div>
        <?php else: ?>
            <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0 small">
                <thead class="table-light"><tr><th><?php echo $h($cfg_pb->titoloElenco('data_evento', 'Data evento')); ?></th><th><?php echo $h($cfg_pb->titoloElenco('titolo', 'Titolo')); ?></th><th><?php echo $h($cfg_pb->titoloElenco('richiedente', 'Richiedente')); ?></th><?php if ($cfg_pb->colonnaVisibile('modalita')): ?><th><?php echo $h($cfg_pb->titoloElenco('modalita', 'Modalità')); ?></th><?php endif; ?><th><?php echo $h($cfg_pb->titoloElenco('stato', 'Stato')); ?></th><th>Colonne</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($righe as $r): $n_m = Completamento::numeroMancanti($r, $cfg_pb, $col_conta); ?>
                    <tr>
                        <td class="text-nowrap"><?php echo $h(EsportazioneMonitoraggio::data((string)$r['data_evento'])); ?></td>
                        <td class="pb-titolo-cella"><a class="fw-bold" href="<?php echo $h($url_scheda((int)$r['id'], 'monitoraggio')); ?>"><?php echo $h($r['titolo']); ?></a><?php echo $r['origine'] === 'manuale' ? ' <span class="badge text-bg-light border">a mano</span>' : ''; ?></td>
                        <td><?php echo $h($r['richiedente']); ?></td>
                        <?php if ($cfg_pb->colonnaVisibile('modalita')): ?><td><?php echo $h(EsportazioneMonitoraggio::modalita((string)$r['modalita'])); ?></td><?php endif; ?>
                        <td><?php echo $etichettaStato((string)$r['stato']); ?></td>
                        <td class="text-nowrap"><?php echo $col_conta === [] ? '<span class="text-secondary">—</span>' : ($n_m > 0 ? '<span class="text-warning-emphasis fw-bold">' . $h(Completamento::testo($n_m)) . '</span>' : '<span class="text-success">' . $h(Completamento::testo($n_m)) . '</span>'); ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary py-0" href="<?php echo $h($url_scheda((int)$r['id'], 'monitoraggio')); ?>">Apri</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div></div>
        <?php endif; ?>

    <?php elseif ($vista === 'da_compilare'): ?>
        <?php
        // ═════ Da compilare: a schede (una per richiesta) o, per chi vede tutto, nella tabella completa ═════
        $op_visibili = array_values(array_filter($cfg_pb->colonne(), fn($c) => $c['operatori'])); // solo le visibili, nell'ordine del file Excel
        $op_tutte = array_values(array_filter($op_visibili, fn($c) => in_array($c['chiave'], $col_sue, true))); // quelle che questa persona compila (ColonneDiChi)
        $limitato = $col_viste !== null;
        if ($limitato) $ct_mie = false; // vede già solo le sue: il filtro non serve
        $a_tabella = $ct_tabella && !$limitato;
        $mie_k = $ct_mie ? $di_chi->mie($utente_admin) : [];
        $mie_nessuna = $ct_mie && $mie_k === [];
        $solo_mie = $limitato || ($ct_mie && !$mie_nessuna);
        $col_tab = $ct_mie && !$mie_nessuna ? array_values(array_filter($op_tutte, fn($c) => in_array($c['chiave'], $mie_k, true))) : $op_tutte;
        $chiavi_tab = array_column($col_tab, 'chiave');
        $norm_cella = fn(string $v, string $tipo) => $tipo === 'si_no' ? mb_strtoupper(trim(str_replace("\r\n", "\n", $v))) : trim(str_replace("\r\n", "\n", $v));
        ?>
        <div<?php echo $a_tabella ? '' : ' class="ui-comp"'; ?>>
        <?php if (!$limitato): ?>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <div class="btn-group" role="group" aria-label="Come compilare">
                    <a class="btn btn-outline-secondary btn-sm<?php echo $a_tabella ? '' : ' active'; ?>" href="<?php echo $h($url_schede($ct_mie, false)); ?>"<?php echo $a_tabella ? '' : ' aria-current="true"'; ?>><i class="fa fa-clone me-1" aria-hidden="true"></i>A schede</a>
                    <a class="btn btn-outline-secondary btn-sm<?php echo $a_tabella ? ' active' : ''; ?>" href="<?php echo $h($url_compila($ct_mie, true)); ?>"<?php echo $a_tabella ? ' aria-current="true"' : ''; ?>><i class="fa fa-table-cells me-1" aria-hidden="true"></i>Tabella completa</a>
                </div>
                <?php if (!$a_tabella): ?>
                    <a class="btn btn-outline-secondary btn-sm<?php echo $ct_mie ? ' active' : ''; ?>" href="<?php echo $h($url_schede(!$ct_mie, $ct_fatte)); ?>"<?php echo $ct_mie ? ' aria-current="true"' : ''; ?>><i class="fa <?php echo $ct_mie ? 'fa-square-check' : 'fa-square'; ?> me-1" aria-hidden="true"></i>Solo le mie colonne</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($mie_nessuna && $di_chi->assegnazioni()): ?>
            <div class="alert alert-info py-2 small" role="status">Nessuna colonna è assegnata a te: ti mostro tutte le colonne. Chi compila cosa si sceglie da «Campi e colonne» (chi ha tutto il modulo).</div>
        <?php elseif ($mie_nessuna): ?>
            <div class="alert alert-info py-2 small" role="status">Non ho trovato il tuo nome (<?php echo $h(trim(trim((string)($utente_admin['nome'] ?? '')) . ' ' . trim((string)($utente_admin['cognome'] ?? '')))); ?>) tra gli «a cura di» delle colonne: ti mostro tutte le colonne. Chi compila una colonna si può cambiare da «Campi e colonne» (chi ha tutto il modulo).</div>
        <?php elseif ($ct_mie): ?>
            <p class="small text-secondary">Le tue colonne: <?php echo count($col_tab); ?> su <?php echo count($op_tutte); ?>.</p>
        <?php endif; ?>

        <?php if (!$nel_file): ?>
            <div class="card border-0 shadow-sm"><?php echo pn_vuoto('Nessuna riga nel file Excel. Invia una richiesta dalla scheda «Richieste arrivate» oppure aggiungi una riga a mano.', 'fa-table-list'); ?></div>
        <?php elseif (!$op_visibili): ?>
            <div class="card border-0 shadow-sm"><?php echo pn_vuoto('Nessuna colonna degli operatori è visibile: si accendono da «Campi e colonne» (chi ha tutto il modulo).', 'fa-eye-slash'); ?></div>
        <?php elseif (!$op_tutte): ?>
            <div class="ui-vuoto">
                <div class="ui-vuoto-figura" aria-hidden="true"><i class="fa fa-user-lock"></i></div>
                <h2>Nessuna colonna è assegnata a te</h2>
                <p>Per questo qui non c'è niente da compilare. Le colonne le assegna chi gestisce tutto il modulo Amministrazione, da «Campi e colonne» › «Chi compila cosa».</p>
                <a class="btn btn-outline-secondary fw-bold mt-3" href="<?php echo $h($url_lista('monitoraggio')); ?>"><i class="fa fa-table-list me-1" aria-hidden="true"></i>Vai alla tabella di monitoraggio</a>
            </div>
        <?php elseif (!$a_tabella): ?>
            <?php require __DIR__ . '/parziali/da_compilare_schede.php'; ?>
        <?php else: ?>
        <?php
        // ── Tabella completa: tutte le colonne in una tabella, per più richieste insieme ──
        $righe_tab = $ct_vuote ? CompilazioneInTabella::conCelleVuote($nel_file, $chiavi_tab) : $nel_file;
        $pg = CompilazioneInTabella::pagina($righe_tab, $ct_pag);
        $url_pag = fn(int $n) => $url_compila($ct_mie, $ct_vuote, $n);
        ?>
        <style>
        .pb-tab-wrap { max-height: 72vh; overflow: auto; }
        .pb-tab-wrap thead th { position: sticky; top: 0; z-index: 3; vertical-align: bottom; min-width: 11rem; font-weight: 700; }
        .pb-tab-wrap thead th.pb-fissa, .pb-tab-wrap tbody td.pb-fissa { position: sticky; left: 0; z-index: 2; min-width: 15rem; max-width: 18rem; background: var(--bs-body-bg, #fff); }
        .pb-tab-wrap thead th.pb-fissa { z-index: 4; background: var(--bs-tertiary-bg, #f1f5f9); color: inherit; }
        .pb-tab-wrap td.pb-cella { background: transparent; }
        .pb-tab-wrap td.pb-cella.vuota { background: #fef3c7; }
        .pb-tab-wrap td.pb-cella.errore { background: #fee2e2; }
        [data-bs-theme="dark"] .pb-tab-wrap td.pb-cella.vuota { background: #4a3a12; }
        [data-bs-theme="dark"] .pb-tab-wrap td.pb-cella.errore { background: #4c1d1d; }
        .pb-tab-wrap .form-control, .pb-tab-wrap .form-select { min-width: 10rem; }
        .pb-tab-wrap textarea.form-control { min-height: 2.1rem; resize: vertical; }
        .pb-tab-wrap .modificata { box-shadow: 0 0 0 2px #2563eb; }
        .pb-barra { position: sticky; bottom: 0; z-index: 5; background: var(--bs-body-bg, #fff); border-top: 1px solid var(--bs-border-color, var(--dibest-bordo-scheda)); padding: .6rem 0; }
        </style>
        <p class="text-secondary small mb-2">Le righe del file Excel e le colonne degli operatori in una tabella sola: scrivi nelle celle (quelle vuote sono evidenziate) e premi <strong>«Salva»</strong> una volta sola per salvarle tutte. Se una cella è sbagliata non si salva niente e vedi dov'è l'errore. Niente si salva da solo.</p>

        <form method="GET" action="pubblicazioni.php" class="d-flex flex-wrap gap-3 align-items-center mb-3 m-0">
            <input type="hidden" name="vista" value="da_compilare"><input type="hidden" name="forma" value="tabella">
            <div class="form-check"><input type="hidden" name="mie" value="0"><input class="form-check-input" type="checkbox" id="pbMie" name="mie" value="1"<?php echo $ct_mie ? ' checked' : ''; ?>><label class="form-check-label" for="pbMie">Solo le mie colonne</label></div>
            <div class="form-check"><input type="hidden" name="vuote" value="0"><input class="form-check-input" type="checkbox" id="pbVuote" name="vuote" value="1"<?php echo $ct_vuote ? ' checked' : ''; ?>><label class="form-check-label" for="pbVuote">Solo le righe con celle vuote</label></div>
            <button class="btn btn-outline-secondary btn-sm" type="submit">Applica</button>
        </form>
        <?php if ($errori_tab): ?>
            <div class="alert alert-danger py-2" role="alert"><strong>Non è stato salvato niente.</strong> Correggi le celle segnate in rosso: quello che avevi scritto è ancora qui.
                <ul class="mb-0 small">
                <?php foreach ($errori_tab as $rid_e => $ee): $r_e = $repo->perId((int)$rid_e); foreach ($ee as $msg_e): ?>
                    <li><strong><?php echo $h($r_e ? (string)$r_e['titolo'] : 'Richiesta n. ' . (int)$rid_e); ?></strong>: <?php echo $h($msg_e); ?></li>
                <?php endforeach; endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!$righe_tab): ?>
            <div class="card border-0 shadow-sm"><?php echo pn_vuoto('Nessuna riga con celle vuote' . ($solo_mie ? ' nelle tue colonne' : '') . ': è tutto compilato. Per rivedere o correggere le righe già complete togli la spunta a «Solo le righe con celle vuote».', 'fa-circle-check'); ?></div>
        <?php else: ?>
            <form method="POST" action="<?php echo $h($url_compila($ct_mie, $ct_vuote, $pg['pagina'])); ?>" id="pbTabForm" class="m-0"><?php csrf_field(); ?>
                <input type="hidden" name="azione" value="salva_tabella"><input type="hidden" name="forma" value="tabella">
                <input type="hidden" name="mie" value="<?php echo $ct_mie ? 1 : 0; ?>"><input type="hidden" name="vuote" value="<?php echo $ct_vuote ? 1 : 0; ?>"><input type="hidden" name="pag" value="<?php echo (int)$pg['pagina']; ?>">
                <div class="card border-0 shadow-sm"><div class="table-responsive pb-tab-wrap"><table class="table table-sm align-middle mb-0 small" id="pbTab">
                    <thead><tr>
                        <th class="pb-fissa">Richiesta</th>
                        <?php foreach ($col_tab as $c): ?>
                            <th style="background: <?php echo $h($c['colore']); ?>; color: <?php echo $h(colore_testo_su($c['colore'])); ?>;"><?php echo $h(($c['lettera'] !== '' ? $c['lettera'] . ' · ' : '') . $cfg_pb->etichettaColonna($c['chiave'])); ?><div class="fw-normal" style="font-size:.72rem;">a cura di <?php echo $h($c['a_cura_di']); ?></div></th>
                        <?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($pg['righe'] as $r): $rid = (int)$r['id']; ?>
                        <tr>
                            <td class="pb-fissa">
                                <a class="fw-bold" href="<?php echo $h($url_scheda($rid, 'monitoraggio')); ?>"><?php echo $h($r['titolo']); ?></a><br>
                                <span class="text-secondary"><?php echo $h(EsportazioneMonitoraggio::data((string)$r['data_evento'])); ?></span>
                                <?php if (isset($errori_tab[$rid]['_'])): ?><div class="pb-errore" role="alert"><?php echo $h($errori_tab[$rid]['_']); ?></div><?php endif; ?>
                            </td>
                            <?php foreach ($col_tab as $c):
                                $k = $c['chiave'];
                                $salvato = (string)($r[$k] ?? '');
                                $val = ($e_post && isset($_POST['c'][$rid][$k]) && is_scalar($_POST['c'][$rid][$k])) ? (string)$_POST['c'][$rid][$k] : $salvato;
                                $orig = $norm_cella($salvato, $c['tipo']);
                                $nome_c = 'c[' . $rid . '][' . $k . ']';
                                $id_c = 'pbc_' . $rid . '_' . $k;
                                $et_c = $cfg_pb->etichettaColonna($k) . ' — ' . $r['titolo'];
                                $err_c = $errori_tab[$rid][$k] ?? '';
                            ?>
                                <td class="pb-cella<?php echo trim($val) === '' ? ' vuota' : ''; ?><?php echo $err_c !== '' ? ' errore' : ''; ?>">
                                    <?php if ($c['tipo'] === 'si_no'): $vu = mb_strtoupper(trim($val)); ?>
                                        <select class="form-select form-select-sm" id="<?php echo $h($id_c); ?>" name="<?php echo $h($nome_c); ?>" data-orig="<?php echo $h($orig); ?>" aria-label="<?php echo $h($et_c); ?>">
                                            <option value="">—</option>
                                            <?php foreach (['SI', 'NO'] as $o_v): ?><option value="<?php echo $o_v; ?>"<?php echo $vu === $o_v ? ' selected' : ''; ?>><?php echo $o_v; ?></option><?php endforeach; ?>
                                            <?php if (trim($val) !== '' && !in_array($vu, ['SI', 'NO'], true)): ?><option value="<?php echo $h($val); ?>" selected><?php echo $h($val); ?></option><?php endif; ?>
                                        </select>
                                    <?php elseif ($c['tipo'] === 'numero'): $num_ok = $salvato === '' || ctype_digit(trim($salvato)); ?>
                                        <input type="<?php echo $num_ok ? 'number' : 'text'; ?>"<?php echo $num_ok ? ' min="0" step="1"' : ''; ?> class="form-control form-control-sm" id="<?php echo $h($id_c); ?>" name="<?php echo $h($nome_c); ?>" value="<?php echo $h($val); ?>" data-orig="<?php echo $h($orig); ?>" aria-label="<?php echo $h($et_c); ?>">
                                    <?php else: ?>
                                        <textarea rows="1" maxlength="<?php echo (int)Costanti::MAX_COLONNA; ?>" class="form-control form-control-sm" id="<?php echo $h($id_c); ?>" name="<?php echo $h($nome_c); ?>" data-orig="<?php echo $h($orig); ?>" aria-label="<?php echo $h($et_c); ?>"><?php echo $h($val); ?></textarea>
                                    <?php endif; ?>
                                    <?php if ($err_c !== ''): ?><div class="pb-errore" role="alert"><?php echo $h($err_c); ?></div><?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div></div>
                <div class="pb-barra d-flex flex-wrap align-items-center gap-3">
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fa fa-floppy-disk me-1" aria-hidden="true"></i>Salva</button>
                    <span class="small text-secondary" id="pbTabConta" aria-live="polite">Nessuna cella cambiata.</span>
                    <span class="small text-secondary ms-auto"><?php echo (int)$pg['totale']; ?> righe<?php echo $pg['pagine'] > 1 ? ' · pagina ' . (int)$pg['pagina'] . ' di ' . (int)$pg['pagine'] : ''; ?>
                        <?php if ($pg['pagine'] > 1): ?>
                            <?php if ($pg['pagina'] > 1): ?> · <a href="<?php echo $h($url_pag($pg['pagina'] - 1)); ?>">← Precedente</a><?php endif; ?>
                            <?php if ($pg['pagina'] < $pg['pagine']): ?> · <a href="<?php echo $h($url_pag($pg['pagina'] + 1)); ?>">Successiva →</a><?php endif; ?>
                        <?php endif; ?>
                    </span>
                </div>
            </form>
            <script nonce="<?php echo csp_nonce(); ?>">
            // Si inviano solo le celle cambiate; chi sta per lasciare la pagina con celle cambiate non ancora salvate viene avvisato.
            (function () {
                var form = document.getElementById('pbTabForm'), conta = document.getElementById('pbTabConta');
                if (!form) return;
                var campi = form.querySelectorAll('[data-orig]');
                function cambiata(c) { var v = c.value.replace(/\r\n/g, '\n').trim(); if (c.tagName === 'SELECT') v = v.toUpperCase(); return v !== c.dataset.orig; }
                function aggiorna(c) {
                    var td = c.closest('td'); c.classList.toggle('modificata', cambiata(c));
                    if (td) td.classList.toggle('vuota', c.value.trim() === '');
                    var n = 0; campi.forEach(function (x) { if (cambiata(x)) n++; });
                    conta.textContent = n === 0 ? 'Nessuna cella cambiata.' : (n === 1 ? '1 cella cambiata, non ancora salvata.' : n + ' celle cambiate, non ancora salvate.');
                    return n;
                }
                campi.forEach(function (c) { c.addEventListener('input', function () { aggiorna(c); }); c.addEventListener('change', function () { aggiorna(c); }); aggiorna(c); });
                var invio = false;
                form.addEventListener('submit', function () {
                    invio = true;
                    var spenti = [];
                    campi.forEach(function (c) { if (!cambiata(c)) { c.disabled = true; spenti.push(c); } });
                    setTimeout(function () { spenti.forEach(function (c) { c.disabled = false; }); }, 0);
                });
                window.addEventListener('beforeunload', function (e) {
                    if (invio) return;
                    var n = 0; campi.forEach(function (x) { if (cambiata(x)) n++; });
                    if (n > 0) { e.preventDefault(); e.returnValue = ''; }
                });
            })();
            </script>
        <?php endif; ?>
        <?php endif; // schede o tabella ?>
        </div>

    <?php else: ?>
        <?php if ($in_attesa): ?>
            <details class="pb-box pb-attesa mb-3">
                <summary class="fw-bold"><i class="fa fa-hourglass-half me-1" aria-hidden="true"></i>In attesa di approvazione (<?php echo count($in_attesa); ?>)</summary>
                <p class="small text-secondary mt-2 mb-2">Richieste appena arrivate, in sola lettura: le approva o le respinge chi è nella lista «Chi approva». Quelle approvate compaiono qui sotto.</p>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0 small">
                    <thead><tr><th>Inviata il</th><th><?php echo $h($cfg_pb->titoloElenco('data_evento', 'Data evento')); ?></th><th><?php echo $h($cfg_pb->titoloElenco('titolo', 'Titolo')); ?></th><th><?php echo $h($cfg_pb->titoloElenco('richiedente', 'Richiedente')); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($in_attesa as $r): ?>
                        <tr>
                            <td class="text-nowrap"><?php echo $h($dataOra($r['inviata_il'])); ?></td>
                            <td class="text-nowrap"><?php echo $h(EsportazioneMonitoraggio::data((string)$r['data_evento'])); ?></td>
                            <td class="pb-titolo-cella"><?php echo $h($r['titolo']); ?></td>
                            <td><?php echo $h($r['richiedente']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </details>
        <?php endif; ?>
        <div class="row g-2 mb-3">
            <div class="col-4"><div class="pb-box<?php echo $conta_stato('ricevuta') > 0 ? ' avviso' : ''; ?>"><div class="pb-num"><?php echo $conta_stato('ricevuta'); ?></div><div class="small text-secondary">approvate (da guardare)</div></div></div>
            <div class="col-4"><div class="pb-box"><div class="pb-num"><?php echo $conta_stato('lavorazione'); ?></div><div class="small text-secondary">in lavorazione</div></div></div>
            <div class="col-4"><div class="pb-box"><div class="pb-num"><?php echo $conta_stato('respinta'); ?></div><div class="small text-secondary">respinte</div></div></div>
        </div>
        <?php if (!$arrivate): ?>
            <div class="card border-0 shadow-sm"><?php echo pn_vuoto('Nessuna richiesta da guardare: tutte quelle arrivate sono già nel file Excel.', 'fa-inbox'); ?></div>
        <?php else: ?>
            <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0 small">
                <thead class="table-light"><tr><th>Inviata il</th><th><?php echo $h($cfg_pb->titoloElenco('data_evento', 'Data evento')); ?></th><th><?php echo $h($cfg_pb->titoloElenco('titolo', 'Titolo')); ?></th><th><?php echo $h($cfg_pb->titoloElenco('richiedente', 'Richiedente')); ?></th><th><?php echo $h($cfg_pb->titoloElenco('stato', 'Stato')); ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($arrivate as $r): $respinta = $r['stato'] === 'respinta'; ?>
                    <tr>
                        <td class="text-nowrap"><?php echo $h($dataOra($r['inviata_il'])); ?></td>
                        <td class="text-nowrap"><?php echo $h(EsportazioneMonitoraggio::data((string)$r['data_evento'])); ?></td>
                        <td class="pb-titolo-cella"><a class="fw-bold" href="<?php echo $h($url_scheda((int)$r['id'], 'arrivate')); ?>"><?php echo $h($r['titolo']); ?></a></td>
                        <td><?php echo $h($r['richiedente']); ?></td>
                        <td><?php echo $etichettaStato((string)$r['stato']); ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary py-0" href="<?php echo $h($url_scheda((int)$r['id'], 'arrivate')); ?>">Apri</a>
                            <form method="POST" action="pubblicazioni.php" class="d-inline m-0"><?php csrf_field(); ?>
                                <input type="hidden" name="azione" value="invia_excel"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-primary py-0"<?php echo $respinta ? ' disabled title="Una richiesta respinta non si può inviare al file Excel."' : ' data-confirm="Inviare «' . $h($r['titolo']) . '» al file Excel? Le colonne dell\'indirizzo (D, E) e dei destinatari si precompilano con quello che ha scritto il docente."'; ?>>Invia al file Excel</button>
                            </form>
                            <?php if ($respinta): ?><div class="text-secondary" style="font-size:.75rem;">Respinta: non si può inviare.</div><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div></div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
<?php require_once 'admin_footer.php'; ?>
