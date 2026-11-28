<?php
// inc/ui.php - Pezzi comuni dell'interfaccia (stile in assets/css/portale-ui.css): stati con i colori fissi, stato vuoto, intestazione delle pagine interne
// e interruttore del tema scuro. Caricato da header.php e da admin/admin_header.php.

if (!function_exists('ui_stato')) {
    /** Etichetta di stato nei cinque colori fissi: ok (fatto) · attesa (in attesa) · azione (serve un'azione) · info · neutro (chiuso, annullato). */
    function ui_stato(string $tipo, string $testo, string $icona = ''): string {
        $tipo = in_array($tipo, ['ok', 'attesa', 'azione', 'info', 'neutro'], true) ? $tipo : 'neutro';
        return '<span class="ui-stato ' . $tipo . '">' . ($icona !== '' ? '<i class="fa ' . htmlspecialchars($icona, ENT_QUOTES) . '" aria-hidden="true"></i>' : '')
            . htmlspecialchars($testo, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

if (!function_exists('ui_stato_colore')) {
    /** Come ui_stato(), per gli stati che nel codice portano ancora un colore: verde = ok · ambra/viola = in attesa · rosso = serve un'azione · blu = informazione · altro = neutro. */
    function ui_stato_colore(string $colore, string $testo, string $icona = ''): string {
        $tipo = match (strtolower($colore)) {
            '#15803d', '#198754', '#16a34a' => 'ok',
            '#b45309', '#f59e0b', '#7c3aed' => 'attesa',
            '#b91c1c', '#dc2626', '#b30000' => 'azione',
            '#0056b3', '#0e7490', '#0284c7' => 'info',
            default => 'neutro',
        };
        return ui_stato($tipo, $testo, $icona);
    }
}

if (!function_exists('ui_avviso_modifiche')) {
    /**
     * Nei moduli lunghi: se si cerca di lasciare la pagina (chiusura, indietro, un link) con dei campi cambiati e non salvati, il browser chiede conferma.
     * Il controllo si spegne all'invio del modulo. Va stampato dopo il modulo; $idModulo è l'id del <form>.
     */
    function ui_avviso_modifiche(string $idModulo): string {
        return '<script nonce="' . csp_nonce() . '">(function(){var f=document.getElementById(' . json_encode($idModulo) . ');if(!f)return;var m=false;'
            . "f.addEventListener('input',function(){m=true;});f.addEventListener('change',function(){m=true;});f.addEventListener('submit',function(){m=false;});"
            . "window.addEventListener('beforeunload',function(e){if(m){e.preventDefault();e.returnValue='';}});})();</script>";
    }
}

if (!function_exists('ui_bozza_automatica')) {
    /**
     * Salvataggio automatico della bozza di un modulo lungo (src/Portale/BozzaModulo, salva_bozza.php): ogni 30 secondi, se qualcosa è cambiato, i campi di testo
     * vanno sul server; riaprendo la pagina col link personale la bozza si ripresenta (con l'ora). $bozza = risultato di BozzaModulo::leggi() o null.
     * Va stampato dopo il modulo; il modulo deve avere il campo del codice di sicurezza.
     *
     * @param array{campi: array<string, string>, quando: string}|null $bozza
     */
    function ui_bozza_automatica(string $idModulo, string $tipo, string $token, ?array $bozza): string {
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $nota = $bozza ? '<div class="alert alert-info small py-2" role="status"><i class="fa fa-floppy-disk me-1" aria-hidden="true"></i>Abbiamo ripreso la bozza salvata il '
            . $h(date('d/m/Y \a\l\l\e H:i', (int) strtotime($bozza['quando']))) . ': controlla i dati e poi premi «Salva».</div>' : '';
        return $nota . '<div class="small text-secondary mt-1" id="' . $h($idModulo) . 'BozzaStato" aria-live="polite"></div><script nonce="' . csp_nonce() . '">(function(){'
            . 'var f=document.getElementById(' . json_encode($idModulo) . '),s=document.getElementById(' . json_encode($idModulo . 'BozzaStato') . ');if(!f)return;'
            . 'var b=' . json_encode($bozza['campi'] ?? new stdClass(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ',m=false,inviando=false;'
            . "Object.keys(b).forEach(function(k){var e=f.elements[k];if(!e||e.type==='file'||e.type==='hidden')return;if(e.type==='checkbox'||e.type==='radio'){e.checked=(e.value===b[k]);}else if(e.value===''||" . ($bozza ? 'true' : 'false') . "){e.value=b[k];}});"
            . "f.addEventListener('input',function(){m=true;});f.addEventListener('change',function(){m=true;});f.addEventListener('submit',function(){m=false;});"
            . "setInterval(function(){if(!m||inviando)return;inviando=true;var d=new FormData();Array.prototype.forEach.call(f.elements,function(e){if(!e.name||e.disabled||e.type==='file'||e.type==='password'||e.type==='submit'||e.type==='button')return;"
            . "if((e.type==='checkbox'||e.type==='radio')&&!e.checked)return;d.append(e.name,e.value);});d.set('tipo'," . json_encode($tipo) . ');d.set(\'token\',' . json_encode($token) . ");"
            . "fetch('salva_bozza.php',{method:'POST',body:d,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){inviando=false;if(j&&j.ok){m=false;s.textContent='Bozza salvata alle '+j.ora+'.';}}).catch(function(){inviando=false;});},30000);"
            . '})();</script>';
    }
}

if (!function_exists('ui_vuoto')) {
    /** Stato vuoto: cerchio leggero con l'icona, titolo, testo e un pulsante utile (facoltativo). */
    function ui_vuoto(string $icona, string $titolo, string $testo = '', string $href = '', string $etichetta = '', string $colore = '#64748b'): string {
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        return '<div class="ui-vuoto" style="--tc:' . $h($colore) . ';"><div class="ui-vuoto-figura" aria-hidden="true"><i class="fa ' . $h($icona) . '"></i></div>'
            . '<h2>' . $h($titolo) . '</h2>' . ($testo !== '' ? '<p>' . $h($testo) . '</p>' : '')
            . ($href !== '' && $etichetta !== '' ? '<a href="' . $h($href) . '" class="btn btn-primary fw-bold px-4 rounded-pill">' . $h($etichetta) . '</a>' : '') . '</div>';
    }
}

if (!function_exists('ui_passi')) {
    /**
     * I passi di un percorso (es. «Dati → Turni → Modulo → Pubblica»): lista ordinata, il passo in corso ha aria-current="step".
     * Ogni passo: titolo, stato (fatto · corrente · da_fare · bloccato · fermo = chiuso senza arrivare in fondo, grigio), link facoltativo, nota facoltativa («manca: firma del Direttore»).
     * Senza stato si ricava da $corrente (prima = fatto, quello = corrente, dopo = da_fare). $corrente = posizione (da 0) del passo in corso,
     * -1 se è tutto fatto. $dimensione: «normale» o «piccolo». Sul telefono i passi vanno uno sotto l'altro. Stile: assets/css/portale-ui.css (.ui-percorso; .ui-passi è un'altra cosa, la barra della FSL delle scuole).
     * Il segno dello stato non è solo un colore: icona o numero più una scritta per chi usa uno screen reader.
     *
     * @param list<array{titolo: string, stato?: string, link?: string, nota?: string}> $passi
     */
    function ui_passi(array $passi, int $corrente, string $dimensione = 'normale', string $etichetta = 'Passi da seguire'): string {
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $stati = ['fatto' => ['fa-check', 'fatto'], 'corrente' => ['', 'passo attuale'], 'da_fare' => ['', 'da fare'], 'bloccato' => ['fa-lock', 'bloccato'], 'fermo' => ['fa-xmark', 'fermato qui']];
        $o = '<ol class="ui-percorso' . ($dimensione === 'piccolo' ? ' ui-percorso-piccolo' : '') . '" aria-label="' . $h($etichetta) . '">';
        $n = 0;
        foreach (array_values($passi) as $i => $p) {
            $n++;
            $stato = (string) ($p['stato'] ?? '');
            if (!isset($stati[$stato])) {
                $stato = $i < $corrente || $corrente < 0 ? 'fatto' : ($i === $corrente ? 'corrente' : 'da_fare');
            }
            [$icona, $parola] = $stati[$stato];
            $titolo = (string) ($p['titolo'] ?? '');
            $nota = (string) ($p['nota'] ?? '');
            $link = (string) ($p['link'] ?? '');
            $dentro = '<span class="ui-passo-segno" aria-hidden="true">' . ($icona !== '' ? '<i class="fa ' . $icona . '"></i>' : (string) $n) . '</span>'
                . '<span class="ui-passo-testo"><span class="ui-passo-titolo">' . $h($titolo) . '</span><span class="visually-hidden"> (' . $parola . ')</span>'
                . ($nota !== '' ? '<small class="ui-passo-nota">' . $h($nota) . '</small>' : '') . '</span>';
            $o .= '<li class="ui-passo" data-stato="' . $stato . '"' . ($i === $corrente ? ' aria-current="step"' : '') . '>'
                . ($link !== '' ? '<a class="ui-passo-corpo" href="' . $h($link) . '">' . $dentro . '</a>' : '<div class="ui-passo-corpo">' . $dentro . '</div>') . '</li>';
        }

        return $o . '</ol>';
    }
}

if (!function_exists('ui_link_non_valido')) {
    /**
     * Pagina di un link personale (firma, conferma, lettera) che non vale più: dice perché e porta a ciò che aspetta la persona. Se ha già fatto l'operazione
     * non serve altro; altrimenti il compito si ritrova in «Da fare per te» dell'area personale.
     */
    function ui_link_non_valido(string $titolo, string $motivo): string {
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        return '<h1 class="fw-bold h3">' . $h($titolo) . '</h1><p>' . $h($motivo) . '</p>'
            . '<p class="text-secondary">Se hai già fatto quello che ti era richiesto non serve altro. Se invece ti aspetta ancora qualcosa, la trovi in «Da fare per te» nella tua area personale.</p>'
            . '<a class="btn btn-primary fw-bold" href="area_personale.php#da-fare"><i class="fa fa-list-check me-1" aria-hidden="true"></i>Vai a «Da fare per te»</a>';
    }
}

if (!function_exists('ui_prossima_cosa')) {
    /**
     * «Prossima cosa da fare»: va messa subito dopo flash_html(). Compare solo se il messaggio appena mostrato era un successo (compito chiuso) e alla persona
     * resta qualcosa in «Da fare per te»; altrimenti non scrive niente.
     */
    function ui_prossima_cosa(): string {
        global $conn, $user_info;
        if (($GLOBALS['_flash_mostrato'] ?? '') !== 'success' || empty($user_info['id'])) {
            return '';
        }
        try {
            $p = \App\Core\App::per($conn)->get(\App\Portale\ProssimaCosa::class)->perUtente($user_info, (string) ($_SERVER['REQUEST_URI'] ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
        if ($p === null) {
            return '';
        }
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        return '<div class="alert alert-info border-0 border-start border-4 border-info d-flex flex-wrap align-items-center gap-2" role="status"><div class="flex-grow-1">'
            . '<strong>Prossima cosa da fare:</strong> ' . $h($p['testo']) . '<span class="d-block small text-secondary">' . $h($p['dettaglio']) . ($p['altre'] > 0 ? ' · e altre ' . (int) $p['altre'] : '') . '</span></div>'
            . '<a class="btn btn-sm btn-primary fw-bold" href="' . $h($p['link']) . '">Vai</a><a class="btn btn-sm btn-outline-secondary" href="area_personale.php">Area personale</a></div>';
    }
}

if (!function_exists('ui_prossimo_passo')) {
    /**
     * «Il prossimo passo»: la tessera grande in cima all'area personale con la cosa più urgente di «Da fare per te» (App\Portale\ProssimaCosa::tessera), il pulsante «Vai»
     * e «Vedi tutte (N)». Lo stile è in assets/css/portale-ui.css (.ui-prossimo).
     *
     * @param array{testo: string, dettaglio: string, link: string, gruppo: string, scadenza: array{testo: string, urgente: bool}|null, totale: int} $p
     */
    function ui_prossimo_passo(array $p): string {
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $sc = $p['scadenza'] ?? null;
        return '<section class="ui-prossimo" aria-labelledby="uiProssimoEt"><span class="ui-prossimo-icona" aria-hidden="true"><i class="fa fa-flag-checkered"></i></span>'
            . '<div class="ui-prossimo-corpo"><div class="ui-prossimo-et" id="uiProssimoEt">Il prossimo passo' . ($p['gruppo'] !== '' ? ' · ' . $h($p['gruppo']) : '') . '</div>'
            . '<div class="ui-prossimo-testo">' . $h($p['testo']) . '</div>'
            . '<div class="ui-prossimo-dett">' . $h($p['dettaglio']) . ($sc ? ' <span class="badge ' . ($sc['urgente'] ? 'text-bg-danger' : 'text-bg-light border') . '">' . $h($sc['testo']) . '</span>' : '') . '</div></div>'
            . '<div class="ui-prossimo-azioni"><a class="btn btn-danger btn-lg fw-bold" href="' . $h($p['link']) . '">Vai<i class="fa fa-arrow-right ms-2" aria-hidden="true"></i></a>'
            . '<a class="btn btn-outline-secondary" href="da_fare.php">Vedi tutte (' . (int) $p['totale'] . ')</a></div></section>';
    }
}

if (!function_exists('ui_barra_telefono')) {
    /**
     * La barra fissa in basso sul telefono (sotto i 768 px) con Home, Da fare, Calendario, Messaggi e Profilo: solo con l'accesso fatto; va stampata in fondo alla pagina,
     * prima del piè di pagina. I numeri li lascia in sessione l'area personale (App\Portale\Area\BarraTelefono): le pagine figlie non fanno letture in più.
     * Mette sul body la classe ui-barra-tel-si, che lascia lo spazio in fondo e porta sopra la barra il pulsante dell'assistenza «?».
     */
    function ui_barra_telefono(string $pagina = ''): string {
        if (empty($_SESSION['utente_id'])) {
            return '';
        }
        $pagina = $pagina !== '' ? $pagina : (string) ($_SERVER['PHP_SELF'] ?? '');
        $numeri = \App\Portale\Area\BarraTelefono::letti($_SESSION, time());
        $href = $numeri['href'] ?? 'miei_eventi.php';
        $voci = \App\Portale\Area\BarraTelefono::voci($numeri['da_fare'] ?? 0, $numeri['messaggi'] ?? 0, $href);

        return \App\Portale\Area\BarraTelefono::html($voci, \App\Portale\Area\BarraTelefono::attiva($pagina, $href))
            . '<script nonce="' . csp_nonce() . '">document.body.classList.add("ui-barra-tel-si");</script>';
    }
}

if (!function_exists('ui_intestazione')) {
    /** Titolo di una pagina interna, con il «torna a…» sopra. */
    function ui_intestazione(string $titolo, string $indietro_href = 'area_personale.php', string $indietro_et = 'Area personale', string $sottotitolo = ''): string {
        $h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        return '<header class="ui-pagina-testa">' . ($indietro_href !== '' ? '<a href="' . $h($indietro_href) . '" class="ui-indietro"><i class="fa fa-arrow-left me-1" aria-hidden="true"></i>' . $h($indietro_et) . '</a>' : '')
            . '<h1>' . $h($titolo) . '</h1>' . ($sottotitolo !== '' ? '<p>' . $h($sottotitolo) . '</p>' : '') . '</header>';
    }
}

if (!function_exists('ui_tema_script')) {
    /**
     * Tema chiaro/scuro per le pagine che lo prevedono: parte dalla scelta fatta (localStorage, chiave «tema_dibest», la stessa per sito e pannello)
     * o da quella del sistema, e se c'è il pulsante (ui_tema_pulsante) lo fa cambiare. I colori stanno in assets/css/dibest-colori.css.
     * Sito: va stampato dentro <body>, prima del contenuto (mette html[data-tema="scuro"] e la classe ui-scuro-ok sul body).
     * Pannello ($pannello = true): va stampato nell'<head> (così non c'è il lampo di chiaro) e imposta html[data-bs-theme] di Bootstrap;
     * la vecchia scelta del pannello («adminTheme») viene ripresa; senza una scelta il pannello resta chiaro (il sito segue invece il sistema).
     */
    function ui_tema_script(bool $pannello = false): string {
        $p = $pannello ? 'true' : 'false';
        return '<script nonce="' . csp_nonce() . "\">(function(){var d=document.documentElement,k='tema_dibest',s=null,pn={$p};try{s=localStorage.getItem(k);"
            . "if(!s&&pn){var v=localStorage.getItem('adminTheme');if(v)s=v==='dark'?'scuro':'chiaro';}}catch(e){}"
            . "var scuro=s?s==='scuro':(!pn&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches);"
            . "function ap(sc){if(pn){d.setAttribute('data-bs-theme',sc?'dark':'light');}else if(sc){d.setAttribute('data-tema','scuro');}else{d.removeAttribute('data-tema');}}"
            . "function ora(){return pn?d.getAttribute('data-bs-theme')==='dark':d.getAttribute('data-tema')==='scuro';}"
            . "if(!pn)document.body.classList.add('ui-scuro-ok');if(scuro)ap(true);"
            . "window.uiTemaAgg=function(){var b=document.getElementById('uiTemaBtn');if(!b)return;var sc=ora();"
            . "b.setAttribute('aria-pressed',sc?'true':'false');b.querySelector('span').textContent=sc?'Tema chiaro':'Tema scuro';b.querySelector('i').className='fa '+(sc?'fa-sun':'fa-moon');};"
            . "window.uiTemaCambia=function(){var sc=!ora();ap(sc);"
            . "try{localStorage.setItem(k,sc?'scuro':'chiaro');}catch(e){}window.uiTemaAgg();};"
            . "document.addEventListener('DOMContentLoaded',window.uiTemaAgg);})();</script>";
    }
}

if (!function_exists('ui_tema_pulsante')) {
    function ui_tema_pulsante(): string {
        return '<button type="button" class="ui-tema-btn" id="uiTemaBtn" data-on-click="uiTemaCambia" aria-pressed="false" title="Cambia tema, chiaro o scuro"><i class="fa fa-moon" aria-hidden="true"></i><span>Tema scuro</span></button>';
    }
}

// Voci per il menu «Altro ▾» del pannello (src/Portale/Vista/VociMenu.php): le pericolose finiscono in fondo, in rosso, con la conferma
if (!function_exists('pn_voce_link')) {
    function pn_voce_link(string $href, string $testo, string $icona = '', bool $pericolo = false, string $attributi = ''): string {
        return \App\Portale\Vista\VociMenu::link($href, $testo, $icona, $pericolo, $attributi);
    }
}

if (!function_exists('pn_voce_post')) {
    /** Una voce che invia un modulo con il token csrf. $campi = altri campi nascosti; $azione = indirizzo del modulo (vuoto = la pagina). */
    function pn_voce_post(string $nome, string $valore, string $testo, string $icona = '', string $conferma = '', bool $pericolo = false, array $campi = [], string $azione = '', string $scrivi = ''): string {
        ob_start();
        csrf_field();
        $csrf = (string)ob_get_clean();

        return \App\Portale\Vista\VociMenu::post($csrf, $nome, $valore, $testo, $icona, $conferma, $pericolo, $campi, $azione, $scrivi);
    }
}

if (!function_exists('pn_menu_riga')) {
    /** Il menu «Altro ▾» piccolo, per una riga di tabella o una scheda stretta. */
    function pn_menu_riga(array $voci, string $testo = 'Altro'): string {
        return \App\Portale\Vista\VociMenu::riga($voci, $testo);
    }
}

if (!function_exists('pn_menu_altro')) {
    /** Il menu «Altro ▾» (pn_altro) con le voci in ordine: prima le normali, poi un filo, poi le pericolose. */
    function pn_menu_altro(array $voci, string $testo = 'Altro'): string {
        return pn_altro(\App\Portale\Vista\VociMenu::ordina($voci), $testo);
    }
}
