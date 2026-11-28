<?php
// =========================================================================
// strumenti/mappa_accessi.php - Chi apre ogni pagina del pannello (admin/) e in quale modulo del menu sta.
//   php strumenti/mappa_accessi.php             riscrive documentazione/inventario_accessi.md
//   php strumenti/mappa_accessi.php --controlla esce con codice 1 se il documento non è aggiornato (non scrive niente)
//   php strumenti/mappa_accessi.php --stampa    scrive il documento sullo schermo
// Il documento è generato da App\Portale\Pannello\PermessiPagine (chi apre una pagina) e MenuModuli (quale voce di quale modulo),
// con i nomi dei permessi di App\Auth\Permessi: non si scrive più a mano, si cambia la mappa e si rilancia questo script.
// Non serve il database né il router: legge solo le classi e il sorgente di admin/ (per sapere chi include le schede).
// =========================================================================
if (PHP_SAPI !== 'cli') exit;

$SITO = realpath(__DIR__ . '/..');
define('RADICE_SITO', $SITO);
require $SITO . '/src/autoload.php';
require $SITO . '/inc/sezioni.php'; // MODULI_PORTALE e PAGINE_MODULO

use App\Auth\Permessi;
use App\Portale\Instradamento\AccessoPortale;
use App\Portale\Instradamento\Rotte;
use App\Portale\Pannello\MenuModuli;
use App\Portale\Pannello\PermessiPagine;

$DOC = $SITO . '/documentazione/inventario_accessi.md';

// Nome breve (per la colonna «Chi la apre») e spiegazione di ogni permesso. Devono esserci tutti: se Permessi ne aggiunge uno, lo script si ferma.
$ETICHETTE = [
    'is_full_admin' => ['amministratori', 'Amministratore globale: ruolo 1, anche come ruolo secondario (dalla riga del database)'],
    'is_area_manager' => ['gestori di tutta l\'area', 'Gestisce tutta l\'area aperta: gestore dell\'area, abilitato al modulo a cui l\'area appartiene, amministratore'],
    'can_manage_eventi' => ['chi gestisce attività dell\'area', 'Gestisce almeno un\'attività dell\'area aperta (assegnata una per una o per perimetro «progetti»/«eventi»), oppure tutta l\'area'],
    'can_manage_iscritti' => ['chi gestisce attività dell\'area', 'Come «can_manage_eventi»: iscritti, presenze, badge e statistiche dell\'area aperta'],
    'can_manage_sondaggi' => ['chi gestisce attività dell\'area', 'Come «can_manage_eventi»: sondaggi delle attività'],
    'can_manage_form' => ['chi gestisce attività dell\'area', 'Come «can_manage_eventi»: moduli di iscrizione delle attività'],
    'can_manage_settings' => ['gestori di tutta l\'area', 'Gestisce tutta l\'area aperta (le impostazioni dell\'area non si aprono a chi ha solo alcune attività)'],
    'puo_creare_eventi' => ['chi crea eventi', 'Perimetro «eventi» dell\'area aperta (o gestore di tutta l\'area): può creare eventi nuovi'],
    'puo_creare_progetti' => ['chi crea progetti', 'Perimetro «progetti» dell\'area aperta (o gestore di tutta l\'area): può creare progetti nuovi'],
    'puo_fsl' => ['modulo FSL', 'Abilitato al modulo Formazione Scuola Lavoro (anche chi ha il modulo Eventi e seminari o il perimetro «fsl»)'],
    'puo_fsl_convenzioni' => ['convenzioni FSL', 'Modulo FSL, perimetro «fsl_convenzioni» o persona dell\'ufficio «Ufficio convenzioni FSL»'],
    'puo_fsl_scuole' => ['scuole FSL', 'Modulo FSL o perimetro «fsl_scuole»'],
    'puo_didattica' => ['Didattica', 'Chi lavora in tutta la Didattica, più i referenti dei consigli dei corsi di studio (solo le sedute dei loro consigli)'],
    'puo_didattica_tutto' => ['tutta la Didattica', 'Amministratore, abilitato al modulo Didattica o operatore dell\'Ufficio didattico'],
    'puo_conv_tesi' => ['convenzioni di tesi', 'Chi lavora in tutta la Didattica e le persone dell\'ufficio «Ufficio convenzioni di tesi»'],
    'puo_tir_motorie' => ['tirocini Scienze motorie', 'Chi lavora in tutta la Didattica e le persone dell\'ufficio «Ufficio tirocini (Scienze motorie)»'],
    'puo_tutorato' => ['tutorato', 'Amministratore, abilitato al modulo Didattica o operatore dell\'Ufficio didattico con il compito «bandi»'],
    'puo_forms' => ['MyForms per intero', 'Amministratore o abilitato al modulo MyForms: può creare form nuovi'],
    'puo_forms_cogestore' => ['co-gestori di form', 'Co-gestore di almeno un form, senza l\'abilitazione al modulo'],
    'puo_forms_vedere' => ['MyForms', 'Entra in MyForms: abilitato al modulo, oppure co-gestore di almeno un form (vede solo i suoi)'],
    'puo_amministrazione' => ['Amministrazione', 'Tutto il modulo Amministrazione: amministratori e «Modulo Amministrazione»'],
    'puo_pubblicazioni' => ['richieste di pubblicazione', 'Chi può tutto in Amministrazione e chi ha solo «Amministrazione · Richieste di pubblicazione»'],
    'puo_amm_entrare' => ['Amministrazione (entra)', 'Una delle due abilitazioni dell\'Amministrazione: entra nel modulo'],
    'puo_assistenza' => ['Assistenza', 'Amministratori e persone dell\'ufficio fisso «Assistenza»: rispondono alle chat'],
    'puo_orientamento' => ['modulo Eventi e seminari', 'Amministratore o abilitato al modulo Eventi e seminari (Orientamento). Costa una query: si calcola solo se la pagina lo chiede'],
    'ha_caselle' => ['chi ha una casella di messaggi', 'Gestisce almeno un\'area con messaggi degli iscritti. Costa una query: si calcola solo se la pagina lo chiede'],
    'ha_sezioni' => ['chi ha almeno un modulo o un\'area', 'Ha almeno un modulo o un\'area da gestire (o è amministratore). Senza, il pannello rimanda all\'area personale invece del 403'],
];
foreach (Permessi::chiavi() as $k) {
    if (!isset($ETICHETTE[$k])) { fwrite(STDERR, "Manca l'etichetta del permesso «$k» in strumenti/mappa_accessi.php\n"); exit(2); }
}
foreach (array_keys($ETICHETTE) as $k) {
    if (!in_array($k, Permessi::chiavi(), true)) { fwrite(STDERR, "L'etichetta «$k» non è un permesso di App\\Auth\\Permessi\n"); exit(2); }
}

/** @return list<string> percorsi dei .php sotto admin/, relativi ad admin/ */
function file_di_admin(string $sito): array {
    $base = realpath($sito . '/admin');
    $out = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'php') $out[] = ltrim(str_replace('\\', '/', substr($f->getPathname(), strlen($base))), '/');
    }
    sort($out);
    return $out;
}

function chi(array $permessi, array $ETICHETTE): string {
    if ($permessi === ['*']) return 'qualunque utente con l\'accesso (controlla il singolo dato)';
    $nomi = [];
    foreach ($permessi as $p) $nomi[$ETICHETTE[$p][0]] = true;
    return implode(' · ', array_keys($nomi));
}

function cella(string $s): string { return str_replace(['|', "\n"], ['\\|', ' '], $s); }

$file = file_di_admin($SITO);
$sorgenti = [];
foreach ($file as $f) $sorgenti[$f] = (string)file_get_contents($SITO . '/admin/' . $f);

// Chi include ciascun file: una riga require/include che nomina il file, oppure che costruisce il nome da un prefisso («'/didattica_' . $tab . '.php'»)
$inclusa_da = [];
foreach ($file as $f) {
    $base = basename($f);
    foreach ($sorgenti as $altro => $src) {
        if ($altro === $f) continue;
        foreach (preg_split('/\R/', $src) as $riga) {
            if (!preg_match('/\b(require|include)(_once)?\b/', $riga)) continue;
            $letterale = preg_match('/[\'"\/]' . preg_quote($base, '/') . '[\'"]/', $riga) === 1;
            $da_prefisso = preg_match('/[\'"]\/?([a-z_]+_)[\'"]\s*\.\s*\$/', $riga, $m) === 1 && str_starts_with($base, $m[1]);
            if ($letterale || $da_prefisso) { $inclusa_da[$f][$altro] = true; break; }
        }
    }
}

// Voci del menu che accendono ciascuna pagina
$voci_di = []; // file => list<[modulo, testo]>
foreach (MenuModuli::moduli() as $modulo) {
    foreach (MenuModuli::voci($modulo) as $v) {
        $pagine = $v['pagine'] ?? [];
        $pagine[] = basename(explode('?', (string)$v['href'])[0]);
        foreach (array_unique($pagine) as $p) $voci_di[$p][] = [$modulo, (string)$v['testo']];
    }
}

function modulo_di_pagina(string $f, array $voci_di): array {
    if (isset($voci_di[$f])) {
        $moduli = array_values(array_unique(array_column($voci_di[$f], 0)));
        $voci = array_values(array_unique(array_column($voci_di[$f], 1)));
        return [$moduli, $voci];
    }
    if (isset(PAGINE_MODULO[$f])) return [[PAGINE_MODULO[$f]], []];
    return [[], []];
}

/** @return array<string, true> */
function moduli_della_scheda(string $f, array $inclusa_da, array $voci_di, array $visti = []): array {
    $out = [];
    foreach (array_keys($inclusa_da[$f] ?? []) as $madre) {
        if (isset($visti[$madre])) continue;
        [$m] = modulo_di_pagina(basename($madre), $voci_di);
        foreach ($m as $x) $out[$x] = true;
        if (!$m) $out += moduli_della_scheda($madre, $inclusa_da, $voci_di, $visti + [$f => true]);
    }
    return $out;
}

$rotte_per_file = [];
foreach (Rotte::elenco() as $rotta) $rotte_per_file[$rotta->file] = $rotta;
$nome_modulo = static fn (string $k): string => MODULI_PORTALE[$k]['nome'] ?? $k;
$mappa = PermessiPagine::pagine();

$o = [];
$o[] = '# Inventario degli accessi al pannello';
$o[] = '';
$o[] = '> **Questo documento è generato** da `strumenti/mappa_accessi.php` (legge `App\Portale\Pannello\PermessiPagine`, `MenuModuli` e `App\Auth\Permessi`): non si modifica a mano. Per cambiare chi apre una pagina si cambia `PermessiPagine`, poi si rilancia `php strumenti/mappa_accessi.php`. `php strumenti/mappa_accessi.php --controlla` dice se il documento è rimasto indietro.';
$o[] = '';
$o[] = 'Ogni file di `admin/` (sottocartelle comprese) sta in uno solo di tre elenchi: **pagina d\'ingresso** (passa da `admin_header.php`, che applica la regola), **scheda** (inclusa da un\'altra pagina, con la guardia in testa) o **fuori** (non passa da `admin_header.php`: cron, AJAX, scaricamenti, con un controllo proprio). Dove una pagina elenca più permessi **basta uno**. I controlli dentro le singole pagine restano: sono la seconda protezione.';
$o[] = '';

// ── 1. Permessi ──
$o[] = '## 1. I permessi';
$o[] = '';
$o[] = 'Li calcola `App\Auth\Permessi` (una volta per richiesta, per l\'utente e l\'area aperta con `?p_id=`); i nomi sono quelli della mappa.';
$o[] = '';
$o[] = '| Permesso | Chi ce l\'ha | Pagine d\'ingresso che apre |';
$o[] = '|---|---|---|';
$aperte_da = [];
foreach ($mappa as $pagina => $regola) foreach ($regola as $p) $aperte_da[$p][] = $pagina;
foreach (Permessi::chiavi() as $k) {
    $pagine = $aperte_da[$k] ?? [];
    sort($pagine);
    $o[] = '| `' . $k . '` | ' . cella($ETICHETTE[$k][1]) . ' | ' . ($pagine ? cella(implode(', ', array_map(fn ($p) => "`$p`", $pagine))) : '—') . ' |';
}
$o[] = '';

// ── 2. Moduli del menu ──
$o[] = '## 2. Il menu, modulo per modulo';
$o[] = '';
$o[] = 'Le voci fisse stanno in `MenuModuli`. Le voci che seguono l\'area aperta (Dashboard, Eventi, Iscritti…) non sono qui: dipendono dall\'area, non dal modulo.';
$o[] = '';
foreach (array_keys(MODULI_PORTALE) as $modulo) {
    $voci = MenuModuli::voci($modulo);
    $o[] = '### ' . $nome_modulo($modulo) . ' (`' . $modulo . '`)';
    $o[] = '';
    if (!$voci) {
        $o[] = 'Nessuna voce fissa: le sue pagine seguono le aree del modulo.';
        $o[] = '';
        continue;
    }
    $o[] = '| Voce | Indirizzo | Chi la vede | Pagine che la accendono |';
    $o[] = '|---|---|---|---|';
    foreach ($voci as $v) {
        $pagine = array_values(array_unique(array_merge($v['pagine'] ?? [], [basename(explode('?', (string)$v['href'])[0])])));
        $o[] = '| ' . cella((string)$v['testo']) . ' | `' . $v['href'] . '` | ' . cella(chi($v['permessi'], $ETICHETTE)) . ' | ' . cella(implode(', ', array_map(fn ($p) => "`$p`", $pagine))) . ' |';
    }
    $o[] = '';
}

// ── 3. Pagina per pagina ──
$o[] = '## 3. Pagina per pagina';
$o[] = '';
$o[] = 'L\'indirizzo è quello del file (si apre anche senza «.php»); la colonna «Nucleo» dice se la pagina è una rotta di `nucleo.php` (vedi la sezione 4).';
$o[] = '';
$o[] = '| File | Indirizzo | Tipo | Chi la apre | Modulo del menu | Voce | Nucleo |';
$o[] = '|---|---|---|---|---|---|---|';
foreach ($file as $f) {
    $tipo = PermessiPagine::tipo($f);
    [$moduli, $voci] = modulo_di_pagina(basename($f), $voci_di);
    $regola = $tipo === 'pagina' ? $mappa[$f] : null;
    if ($tipo === 'pagina') {
        $quando = chi($regola, $ETICHETTE);
        if (PermessiPagine::rimandaAllAreaPersonale($f)) $quando .= ' (senza: rimando all\'area personale)';
    } elseif ($tipo === 'scheda') {
        $da = array_keys($inclusa_da[$f] ?? []);
        sort($da);
        $quando = in_array($f, ['admin_header.php', 'admin_footer.php'], true) ? 'non si apre: la include ogni pagina d\'ingresso' : ($da ? 'come la pagina che la include (' . implode(', ', array_map(fn ($p) => "`$p`", $da)) . '); da sola dà 403' : 'da sola dà 403');
    } elseif ($tipo === 'fuori') {
        $quando = 'controllo proprio (sessione, chiave del cron o ruolo)';
    } else {
        $quando = '**senza regola**';
    }
    if ($moduli === [] && $tipo === 'pagina' && $regola !== null) {
        $segue_area = (bool)array_intersect($regola, ['is_area_manager', 'can_manage_eventi', 'can_manage_iscritti', 'can_manage_sondaggi', 'can_manage_form', 'can_manage_settings']);
        $mod = $segue_area ? 'segue l\'area aperta' : (PermessiPagine::rimandaAllAreaPersonale($f) ? 'ingresso del pannello' : '—');
    } elseif ($tipo === 'scheda' && in_array($f, ['admin_header.php', 'admin_footer.php'], true)) {
        $mod = '—';
    } elseif ($moduli === [] && $tipo === 'scheda') {
        // una scheda sta nel modulo delle pagine che la includono (anche attraverso altre schede)
        $mm = array_keys(moduli_della_scheda($f, $inclusa_da, $voci_di));
        $mod = $mm ? implode(', ', array_map($nome_modulo, $mm)) : '—';
    } else {
        $mod = $moduli ? implode(', ', array_map($nome_modulo, $moduli)) : '—';
    }
    $rotta_di = $rotte_per_file['admin/' . $f] ?? null;
    $indirizzo = $tipo === 'pagina' || $tipo === 'fuori' ? '`admin/' . $f . '`' : '—'; // una scheda non si apre da sola: l'indirizzo è quello della pagina che la include
    $o[] = '| `' . $f . '` | ' . $indirizzo . ' | ' . ($tipo ?? '?') . ' | ' . cella($quando) . ' | ' . cella($mod) . ' | ' . ($voci ? cella(implode('; ', $voci)) : '—') . ' | ' . ($rotta_di ? '`' . $rotta_di->percorso . '`' : '—') . ' |';
}
$o[] = '';

// ── 4. Rotte del Nucleo ──
$o[] = '## 4. Le rotte del Nucleo';
$o[] = '';
$o[] = 'Le pagine servite da `App\Http\Nucleo` (tabella `App\Portale\Instradamento\Rotte`). Il Nucleo chiede l\'accesso a `PermessiPagine` PRIMA di costruire la pagina: la rotta non porta permessi, e la colonna «Chi la apre» qui sotto è letta dalla mappa. Il file vecchio resta dov\'è, come guscio di due righe, quindi il suo indirizzo non muore.';
$o[] = '';
$o[] = '| Rotta | Indirizzi | Pagina (classe) | File di oggi | Chi la apre |';
$o[] = '|---|---|---|---|---|';
foreach (Rotte::elenco() as $rotta) {
    if (AccessoPortale::eDelPannello($rotta)) {
        $regola_rotta = PermessiPagine::regola(substr($rotta->file, strlen('admin/')));
        $chi_rotta = $regola_rotta === null ? '**senza regola**' : chi($regola_rotta, $ETICHETTE) . (PermessiPagine::rimandaAllAreaPersonale($rotta->file) ? ' (senza: rimando all\'area personale)' : '');
    } else {
        $chi_rotta = 'pubblica (le regole proprie della pagina restano dentro la pagina)';
    }
    $o[] = '| `' . $rotta->percorso . '` | ' . implode(', ', array_map(fn ($i) => "`$i`", $rotta->indirizzi())) . ' | `' . $rotta->pagina . '` | `' . $rotta->file . '` | ' . cella($chi_rotta) . ' |';
}
$o[] = '';

// ── 5. SOS aule: due pagine pubbliche con regole proprie, che la tabella qui sopra non può leggere ──
$o[] = '## 5. SOS aule: chi entra dove';
$o[] = '';
$o[] = '| Pagina | Chi entra |';
$o[] = '|---|---|';
$o[] = '| `sos.php` | Senza accesso: login, poi si torna all\'aula del QR. Invia chi ha un profilo ammesso dalle impostazioni, con il modulo acceso. Una segnalazione la vede solo chi l\'ha inviata e chi la gestisce (referente, studente a cui è affidata). |';
$o[] = '| `sos_interventi.php` | Referenti delle aule (solo le loro), responsabili di riserva (tutte), studenti part-time in servizio (solo quelle affidate a loro). |';
$o[] = '| `admin/sos_aule.php` (anche foto e scarichi del registro) | Tutto il modulo Amministrazione. |';
$o[] = '| `admin/sos_cartello.php` | Tutto il modulo Amministrazione. |';
$o[] = '';
$testo = implode("\n", $o);

if (in_array('--stampa', $argv, true)) { echo $testo; exit(0); }
$attuale = is_file($DOC) ? str_replace("\r\n", "\n", (string)file_get_contents($DOC)) : '';
if (in_array('--controlla', $argv, true)) {
    if ($attuale === $testo) { echo "documentazione/inventario_accessi.md è aggiornato.\n"; exit(0); }
    echo "documentazione/inventario_accessi.md non è aggiornato: lancia  php strumenti/mappa_accessi.php\n";
    exit(1);
}
file_put_contents($DOC, $testo);
echo "Scritto documentazione/inventario_accessi.md (" . count($file) . " file di admin/, " . count($mappa) . " pagine d'ingresso, " . count(Permessi::chiavi()) . " permessi, " . count(Rotte::elenco()) . " rotte del Nucleo).\n";
