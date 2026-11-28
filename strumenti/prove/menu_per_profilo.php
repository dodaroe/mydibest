<?php
// Cosa vede nel menu del pannello ciascun profilo, PRIMA e DOPO la riorganizzazione dei moduli (passo S9). Nessun database: i permessi
// dei profili sono scritti qui sotto, come admin_header.php li calcola per quella persona.
//   php strumenti/prove/menu_per_profilo.php
// «Prima» è la regola del vecchio admin_header.php (moduli e voci), ricopiata; «dopo» legge App\Portale\Pannello\MenuModuli.
if (PHP_SAPI !== 'cli') exit;

$SITO = realpath(__DIR__ . '/../..');
define('RADICE_SITO', $SITO);
require $SITO . '/src/autoload.php';
require $SITO . '/inc/sezioni.php';

use App\Portale\Pannello\MenuModuli;

$tutti = ['is_full_admin' => false, 'puo_fsl' => false, 'puo_fsl_convenzioni' => false, 'puo_fsl_scuole' => false, 'puo_didattica' => false, 'puo_didattica_tutto' => false,
    'puo_conv_tesi' => false, 'puo_tir_motorie' => false, 'puo_tutorato' => false, 'puo_assistenza' => false, 'puo_forms_vedere' => false, 'puo_amm_entrare' => false,
    'puo_amministrazione' => false, 'puo_pubblicazioni' => false, 'puo_orientamento' => false];

// profilo => [descrizione, permessi accesi, moduli delle aree che gestisce]
$profili = [
    'amministratore' => ['Amministratore del portale (tutti i permessi, tutte le aree)',
        array_fill_keys(array_keys($tutti), true), ['orientamento', 'fsl', 'calendari']],
    'gestore di un\'area eventi' => ['Gestore di un\'area di tipo «eventi» (nessun modulo intero)',
        [], ['orientamento']],
    'abilitato Didattica' => ['Abilitato al modulo Didattica (anche tutorato, convenzioni di tesi, tirocini)',
        ['puo_didattica' => true, 'puo_didattica_tutto' => true, 'puo_conv_tesi' => true, 'puo_tir_motorie' => true, 'puo_tutorato' => true], []],
    'operatore Assistenza' => ['Persona dell\'ufficio Assistenza (risponde alle chat)',
        ['puo_assistenza' => true], []],
    'abilitato solo Scuole FSL' => ['Abilitato solo all\'anagrafe delle scuole (perimetro fsl_scuole)',
        ['puo_fsl_scuole' => true], []],
];

/** Il menu di prima: modulo => elenco di voci (come le disegnava il vecchio admin_header.php). */
function menu_prima(array $p, array $moduli_area): array
{
    $m = array_fill_keys($moduli_area, true);
    $out = [];
    if ($p['puo_fsl_convenzioni']) { $m['fsl'] = true; }
    if ($p['puo_didattica'] || $p['puo_conv_tesi'] || $p['puo_assistenza'] || $p['puo_tir_motorie']) { $m['didattica'] = true; }
    if ($p['puo_forms_vedere']) { $m['forms'] = true; }
    if ($p['puo_amm_entrare']) { $m['amministrazione'] = true; }
    if ($p['is_full_admin'] || $p['puo_fsl_scuole']) { $m['portale'] = true; }

    if (isset($m['orientamento']) && $p['puo_orientamento']) { $out['orientamento'] = ['Report per ambito']; }
    if (isset($m['fsl']) && $p['puo_fsl_convenzioni']) { $out['fsl'] = [$p['puo_fsl'] ? 'Formazione Scuola Lavoro' : 'Convenzioni FSL']; }
    if (isset($m['didattica'])) {
        $v = [];
        if ($p['puo_didattica_tutto']) { $v[] = 'Cosa devo fare'; $v[] = 'Pratiche degli studenti'; }
        if ($p['puo_didattica']) { $v[] = $p['puo_didattica_tutto'] ? 'Sedute e verbali' : 'Sedute del consiglio'; }
        if ($p['puo_didattica_tutto']) {
            array_push($v, 'Moduli e documenti', 'Ricevimento');
            if (!$p['is_full_admin']) { $v[] = 'Uffici e persone'; }
            array_push($v, 'Tirocini', 'Sostituzioni', 'Statistiche');
        }
        if ($p['puo_tir_motorie']) { $v[] = 'Tirocini Scienze motorie'; }
        if ($p['puo_assistenza']) { array_push($v, 'Assistenza', 'Domande e risposte'); }
        if ($p['puo_conv_tesi']) { $v[] = 'Convenzioni di tesi'; }
        if ($p['puo_tutorato']) { $v[] = 'Tutorato · lettere di incarico'; }
        $out['didattica'] = $v;
    }
    if (isset($m['forms']) && $p['puo_forms_vedere']) { $out['forms'] = ['MyForms']; }
    if (isset($m['amministrazione'])) {
        $v = ['Cosa devo fare'];
        if ($p['puo_amministrazione']) { $v[] = 'Modulistica'; }
        if ($p['puo_pubblicazioni']) { $v[] = 'Richieste di pubblicazione'; } // «Da compilare», «Impostazioni» e «Campi e colonne» stanno dentro le richieste
        $out['amministrazione'] = $v;
    }
    if (isset($m['portale'])) {
        $v = [];
        if ($p['is_full_admin']) { array_push($v, 'Anagrafi › Docenti', 'Anagrafi › Personale TA', 'Anagrafi › Insegnamenti', 'Anagrafi › Corsi di studio'); }
        if ($p['puo_fsl_scuole']) { $v[] = 'Anagrafi › Scuole'; }
        if ($p['is_full_admin']) {
            array_push($v, 'Anagrafi › Strutture e aggiornamento', 'Aree di tutti i moduli',
                'Sito pubblico › Testata e home', 'Sito pubblico › Menu', 'Sito pubblico › Contatti per gli studenti', 'Sito pubblico › Notizie e pagine',
                'Sito pubblico › Area personale', 'Sito pubblico › Entra come…', 'Utenti e abilitazioni', 'Uffici e persone', 'Protocollo (Titulus)',
                'Testi delle email', 'Sistema e registri');
        }
        $out['portale'] = $v;
    }
    foreach (array_keys($m) as $k) {
        if (!isset($out[$k])) { $out[$k] = []; } // modulo di sole aree: le voci seguono l'area
    }

    return $out;
}

/** Il menu di adesso, da MenuModuli. */
function menu_dopo(array $p, array $moduli_area): array
{
    $out = [];
    foreach (MenuModuli::moduliVisibili($p, $moduli_area) as $k) {
        $out[$k] = array_map(
            static fn(array $v): string => (isset($v['gruppo']) ? (MenuModuli::gruppi($k)[$v['gruppo']][1] . ' › ') : '') . MenuModuli::testo($v, $p),
            MenuModuli::vociVisibili($k, $p)
        );
    }

    return $out;
}

$nome_modulo = static fn(string $k): string => $k === 'portale' ? 'Gestione del portale (vecchio)' : (MODULI_PORTALE[$k]['nome'] ?? $k);
$ordine = array_merge(array_keys(MODULI_PORTALE), ['portale']);
$stampa = static function (array $menu) use ($nome_modulo, $ordine): void {
    if (!$menu) { echo "    (nessun modulo: va all'area personale)\n"; return; }
    uksort($menu, static fn($a, $b) => array_search($a, $ordine, true) <=> array_search($b, $ordine, true));
    foreach ($menu as $k => $voci) {
        echo '    ■ ' . $nome_modulo($k) . ($voci ? '' : '  [le voci seguono l\'area aperta]') . "\n";
        foreach ($voci as $v) { echo '        - ' . $v . "\n"; }
    }
};

foreach ($profili as $chiave => [$descr, $accesi, $moduli_area]) {
    $p = array_merge($tutti, $accesi);
    echo "\n=== " . strtoupper($chiave) . ' — ' . $descr . "\n";
    echo "  PRIMA:\n";
    $prima = menu_prima($p, $moduli_area);
    $stampa($prima);
    echo "  DOPO:\n";
    $dopo = menu_dopo($p, $moduli_area);
    $stampa($dopo);

    // Confronto per voce (ignora in quale gruppo o modulo stava): cosa si perde, cosa si guadagna
    $norm = static fn(string $s): string => trim(substr($s, ($pos = strrpos($s, '›')) === false ? 0 : $pos + strlen('›')));
    $v_prima = array_map($norm, array_merge(...array_values($prima) ?: [[]]));
    $v_dopo = array_map($norm, array_merge(...array_values($dopo) ?: [[]]));
    $v_prima = array_values(array_unique($v_prima));
    $v_dopo = array_values(array_unique($v_dopo));
    $perse = array_diff($v_prima, $v_dopo);
    $nuove = array_diff($v_dopo, $v_prima);
    echo '  → voci perse: ' . ($perse ? implode(', ', $perse) : 'nessuna') . "\n";
    echo '  → voci nuove: ' . ($nuove ? implode(', ', $nuove) : 'nessuna') . "\n";
    if ($prima !== [] && $dopo === []) { echo "  !!! ATTENZIONE: prima entrava nel pannello, ora no\n"; }
}
echo "\n";
