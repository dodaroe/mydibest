<?php
// amministrazione_home.php - «Cosa devo fare nell'Amministrazione»: la prima schermata del modulo. Mostra i numeri di quello che aspetta chi lavora
// le richieste di pubblicazione (richieste da guardare, in lavorazione, righe del file Excel da completare) con un pulsante che apre la pagina delle
// richieste sulla vista giusta, e sotto «Tutte le sezioni» (con «Da compilare»: le colonne degli operatori in tabella, col numero di righe da completare).
// Chi ha solo «Amministrazione · Richieste di pubblicazione» vede solo queste voci.
// Voci per chi può tutto ($puo_amministrazione): Modulistica, Impostazioni, Campi e colonne e SOS aule.
// Scheda del pannello Amministrazione: si apre da amministrazione.php (o ?tab=home), mai direttamente. Non ha azioni proprie.
if (!defined('AMMINISTRAZIONE_PANNELLO')) { http_response_code(403); exit('Accesso negato.'); }

if ($fase === 'azioni') {

    return;
}

$compiti_a = [];
$sezioni_a = [];

if ($puo_amministrazione) {
    $sezioni_a[] = ['fa-file-lines', 'Modulistica', 'Documenti da scaricare e link per il personale, e le procedure online come la richiesta di pubblicazione.', 'amministrazione.php?tab=modulistica'];
    $sezioni_a[] = ['fa-sliders', 'Impostazioni', 'Richiesta di pubblicazione: chi può inviarla, aperta o chiusa, testi, limiti ed email.', 'amministrazione.php?tab=impostazioni'];
    $sezioni_a[] = ['fa-table-list', 'Campi e colonne', 'Richiesta di pubblicazione: nomi, aiuti e obbligatorietà dei campi del modulo e colonne del file Excel.', 'amministrazione.php?tab=campi'];
    $sezioni_a[] = ['fa-life-ring', 'SOS aule', 'Richieste di aiuto dal QR in aula: aule e cartelli, referenti, studenti part-time, il form, le impostazioni e il registro.', 'sos_aule.php'];
}

if ($puo_pubblicazioni) {
    // Un guasto nel contare (tabella non ancora creata dopo un caricamento a metà) non deve far sparire la pagina: numeri a zero e l'errore nel log
    $n_pb = ['ricevute' => 0, 'lavorazione' => 0, 'da_completare' => 0];
    try {
        $n_pb = \App\Core\App::per($conn)->get(\App\Amministrazione\CoseDaFareAmministrazione::class)->pubblicazioni($utente_admin);
    } catch (\Throwable $e) {
        error_log('Amministrazione, cose da fare: ' . $e->getMessage());
    }
    $compiti_a[] = ['numero' => $n_pb['ricevute'], 'titolo' => 'Richieste da guardare', 'testo' => 'Richieste di pubblicazione arrivate e non ancora nel file Excel.', 'href' => 'pubblicazioni.php?vista=arrivate', 'bottone' => 'Apri le richieste', 'tono' => 'tuo', 'vuoto' => 'Nessuna richiesta da guardare.'];
    $compiti_a[] = ['numero' => $n_pb['lavorazione'], 'titolo' => 'In lavorazione', 'testo' => 'Richieste in lavorazione e non ancora nel file Excel.', 'href' => 'pubblicazioni.php?vista=arrivate', 'bottone' => 'Vedi', 'tono' => 'altro', 'vuoto' => 'Nessuna richiesta in lavorazione.'];
    $compiti_a[] = ['numero' => $n_pb['da_completare'], 'titolo' => 'File Excel da completare', 'testo' => 'Righe della tabella di monitoraggio con colonne ancora vuote.', 'href' => 'pubblicazioni.php?vista=monitoraggio', 'bottone' => 'Apri la tabella', 'tono' => 'tuo', 'vuoto' => 'Tutte le righe sono complete.'];

    $sezioni_a[] = ['fa-bullhorn', 'Richieste di pubblicazione', 'Le richieste di pubblicare su sito e social, la tabella di monitoraggio (file Excel) e gli indirizzi che le ricevono.', 'pubblicazioni.php?vista=arrivate'];
    // Lo stesso numero del compito «File Excel da completare» e del menu a sinistra (CoseDaFareAmministrazione)
    $n_dc = (int)$n_pb['da_completare'];
    $sezioni_a[] = ['fa-pen-to-square', 'Da compilare', ($n_dc === 0 ? 'Nessuna riga del file Excel ha celle vuote.' : ($n_dc === 1 ? '1 riga del file Excel ha celle vuote' : $n_dc . ' righe del file Excel hanno celle vuote') . ': compila le tue colonne, una richiesta alla volta.'), 'pubblicazioni.php?vista=da_compilare'];
}

// SOS aule: solo a chi può tutto e solo se qualcosa è fermo da più di un giorno
if ($puo_amministrazione) {
    try {
        $n_sos = \App\Core\App::per($conn)->get(\App\Amministrazione\CoseDaFareAmministrazione::class)->sosFerme();
        if ($n_sos > 0) {
            $compiti_a[] = ['numero' => $n_sos, 'titolo' => 'SOS aule non risolte', 'testo' => 'Segnalazioni «non risolte» ferme da più di un giorno.', 'href' => 'sos_aule.php?scheda=registro&stato=non_risolta&dal=&al=', 'bottone' => 'Apri SOS aule', 'tono' => 'tuo', 'vuoto' => ''];
        }
    } catch (\Throwable $e) {
        error_log('Amministrazione, SOS aule: ' . $e->getMessage());
    }
}

echo pn_compiti($compiti_a, 'Cosa devo fare nell\'Amministrazione');
?>
<h2 class="h6 fw-bold text-uppercase text-secondary mt-4 mb-2" style="letter-spacing:.05em;">Tutte le sezioni</h2>
<div class="pn-sezioni">
    <?php foreach ($sezioni_a as [$ico_s, $tit_s, $txt_s, $href_s]): ?>
        <a class="pn-sezione" href="<?php echo $h($href_s); ?>"><i class="fa <?php echo $h($ico_s); ?>" aria-hidden="true"></i><span><strong><?php echo $h($tit_s); ?></strong><small><?php echo $h($txt_s); ?></small></span></a>
    <?php endforeach; ?>
</div>
