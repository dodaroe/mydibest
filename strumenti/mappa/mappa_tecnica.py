# -*- coding: utf-8 -*-
"""Contenuto della mappa del portale per sviluppatori: stack, architettura, oggetti, funzioni, flussi, database, test e rilascio."""
from mappa_comune import fig, gal, card, griglia, passi, sezione, esc


import os, re


def versione_schema():
    """Versione dello schema letta da src/Core/Schema/Migrazioni.php (non si aggiorna a mano)."""
    p = os.path.join(os.path.dirname(__file__), '..', '..', 'src', 'Core', 'Schema', 'Migrazioni.php')
    m = re.search(r'VERSIONE\s*=\s*(\d+)', open(p, encoding='utf8').read())
    return m.group(1) if m else '?'


def cod(t):
    return '<pre><code>%s</code></pre>' % esc(t.strip('\n'))


def tab(intest, righe):
    return '<table><thead><tr>%s</tr></thead><tbody>%s</tbody></table>' % (
        ''.join('<th>%s</th>' % i for i in intest), ''.join('<tr>%s</tr>' % ''.join('<td>%s</td>' % c for c in r) for r in righe))


def box(x, y, w, h, testo, colore='#fdf2f4', bordo='#8e0f1f', t2=''):
    return ('<rect x="%d" y="%d" width="%d" height="%d" rx="8" fill="%s" stroke="%s" stroke-width="1.4"/>'
            '<text x="%d" y="%d" text-anchor="middle" font-size="14" font-weight="600" fill="#1e293b">%s</text>%s') % (
        x, y, w, h, colore, bordo, x + w // 2, y + (h // 2 - (6 if t2 else -5)), testo,
        ('<text x="%d" y="%d" text-anchor="middle" font-size="11.5" fill="#475569">%s</text>' % (x + w // 2, y + h // 2 + 12, t2)) if t2 else '')


def freccia(x1, y1, x2, y2, etichetta=''):
    s = '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#64748b" stroke-width="1.6" marker-end="url(#fr)"/>' % (x1, y1, x2, y2)
    if etichetta:
        s += '<text x="%d" y="%d" font-size="11" fill="#64748b" text-anchor="middle">%s</text>' % ((x1 + x2) // 2, (y1 + y2) // 2 - 4, etichetta)
    return s


def svg(w, h, corpo, titolo):
    return ('<figure><svg viewBox="0 0 %d %d" width="100%%" role="img" aria-label="%s"><defs><marker id="fr" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto">'
            '<path d="M0 0L10 5L0 10z" fill="#64748b"/></marker></defs>%s</svg><figcaption>%s</figcaption></figure>') % (w, h, esc(titolo), corpo, titolo)


def costruisci():
    S = []

    # 1 ------------------------------------------------------------------------------------------------
    S.append(('panoramica', 'Panoramica', sezione('panoramica', '1', 'Panoramica e stack',
        'Applicazione PHP monolitica «senza framework», in migrazione graduale da codice procedurale a classi (pattern <i>strangler fig</i>). Nessun build step, nessuna dipendenza Composer in produzione.',
        '<div class="kpi"><div><b>PHP 8.2</b><span>strict_types, senza framework</span></div><div><b>MySQL/MariaDB</b><span>mysqli, prepared statements</span></div>'
        '<div><b>605</b><span>classi in <code>src/</code> (82.000 righe)</span></div><div><b>201</b><span>pagine (radice + pannello)</span></div>'
        '<div><b>121</b><span>tabelle (23 di base, il resto create da sole)</span></div><div><b>v' + versione_schema() + '</b><span>versione dello schema</span></div>'
        '<div><b>1.430 + 3.153</b><span>casi PHPUnit + prove automatiche</span></div><div><b>10</b><span>classi del nucleo (<code>src/Http</code>)</span></div></div>'
        + tab(['Strato', 'Tecnologia', 'Note'], [
            ['Linguaggio', 'PHP 8.2+ (<code>declare(strict_types=1)</code> nelle classi)', 'Nessun <code>enum</code>/<code>readonly</code> obbligatorio: compatibile con 8.0 finché serve'],
            ['Web server', 'Apache + <code>mod_rewrite</code> (<code>.htaccess</code>); Nginx con <code>try_files</code>', 'Il codice non dipende dal nome della cartella (<code>/didattica</code>, <code>/eventi</code>…)'],
            ['Database', 'MySQL 5.7+ / MariaDB 10.3+ via <code>mysqli</code>', 'Un wrapper <code>App\\Core\\Database</code> sulla stessa connessione; <code>mysqli_report(OFF)</code>: errori silenziosi come da progetto'],
            ['Autenticazione', 'SimpleSAMLphp (SSO Unical, SPID/CIE) + sessione PHP', 'Classe <code>App\\Auth\\Saml\\AccessoSso</code>'],
            ['Front-end', 'Bootstrap 5.3 / Bootstrap Italia, Font Awesome 6, jQuery, DataTables, Select2, Chart.js, SortableJS, FullCalendar, TinyMCE, PDF.js', 'Tutto salvato in <code>assets/vendor</code>: nessun CDN a runtime'],
            ['PDF / Office', 'Generatore PDF proprio (<code>Documento</code>), Word/Excel via <code>ZipArchive</code> + XML', 'Nessuna libreria esterna: PDF/A, PAdES, DOCX, XLSX scritti in casa'],
            ['Email', 'Client SMTP interno (<code>SmtpMailer</code>), fallback <code>mail()</code>', 'STARTTLS/SSL, autenticazione, registro in <code>log_email</code>'],
            ['Test', 'PHPUnit 9.6 + suite «prove» (<code>strumenti/prove/esegui.php</code>)', '1.430 casi PHPUnit (711 di unità + 719 di integrazione) e 3.153 prove della suite, su un database usa-e-getta e, in locale, anche via HTTP'],
            ['Qualità', 'PHP-CS-Fixer (PSR-12), PHPStan (<code>phpstan.neon.dist</code>)', '<code>composer test</code>, <code>composer cs</code>'],
        ])
        + '<div class="info"><b>Licenza:</b> AGPL-3.0-or-later. Le modifiche eseguite su un server vanno rese disponibili agli utenti del servizio. '
        'Dal 23/11/2026 il file <code>LICENSE</code> è il testo <i>verbatim</i> della Free Software Foundation: quello di prima era una parafrasi '
        'con 244 righe diverse, che perdeva il rimando alla sezione 13 (dare il link al codice a chi usa il programma via rete), cioè il motivo '
        'per cui si sceglie l\'AGPL invece della GPL.</div>'
    )))

    # 2 ------------------------------------------------------------------------------------------------
    corpo = (box(30, 20, 840, 56, 'Pagine PHP (radice e admin/)', '#fff7ed', '#c2410c', 'azioni POST in alto (redirect + exit), HTML sotto · 201 file, 3 servite dal nucleo')
             + box(30, 100, 400, 56, 'Funzioni di facciata inc/', '#eff6ff', '#1d4ed8', 'db_*, csrf_field, flash_set, html_*, facciate delle classi')
             + box(470, 100, 400, 56, 'Viste (src/*/Vista)', '#eff6ff', '#1d4ed8', 'HTML di blocchi riusati, già estratto dalle pagine')
             + box(30, 180, 840, 56, 'Servizi (regole di dominio)', '#ecfdf5', '#15803d', 'ServizioPratiche, ServizioConvenzioneOnline, ServizioPrenotazioni, Direzione…')
             + box(30, 260, 400, 56, 'Repository (accesso ai dati)', '#ecfdf5', '#15803d', 'una classe per aggregato: PraticaRepository, UfficiRepository…')
             + box(470, 260, 400, 56, 'Infrastructure', '#f5f3ff', '#6d28d9', 'Mailer, Documento (PDF), Word, Excel, Upload, Cifratura, AuditLog')
             + box(30, 340, 840, 56, 'Core', '#f1f5f9', '#475569', 'Container (DI + autowiring) · App (ponte) · Database · Config · Sito · Orologio')
             + box(30, 420, 840, 50, 'MySQL / MariaDB  ·  file riservati in uploads/ (bloccati dal web)', '#fef2f2', '#b91c1c')
             + freccia(450, 76, 450, 100) + freccia(450, 156, 450, 180) + freccia(450, 236, 450, 260) + freccia(450, 316, 450, 340) + freccia(450, 396, 450, 420))
    S.append(('architettura', 'Architettura', sezione('architettura', '2', 'Architettura a strati',
        'Le pagine restano «a scheda» e parlano con le classi di <code>src/</code> tramite il container. Le classi non conoscono né il container né la sessione: ricevono tutto nel costruttore.',
        svg(900, 490, corpo, 'Strati: pagine → facciate/viste → servizi → repository/infrastruttura → core → database.')
        + tab(['Cartella', 'Contenuto', 'Regola'], [
            ['<code>/</code> (radice)', '74 pagine pubbliche, <code>config.php</code>, <code>functions.php</code>, <code>middleware.php</code>, cron', '<code>config.php</code> e <code>functions.php</code> sono negati dal web server'],
            ['<code>admin/</code>', '56 pagine del pannello; <code>admin_header.php</code> fa autenticazione, RBAC e menu', 'Ogni pagina «a scheda» ha le azioni POST in cima'],
            ['<code>src/Http/</code>', 'il <b>nucleo</b>: 10 classi (richiesta, risposta, vista, rotta, accesso, token, avvisi)', 'Non nomina il portale: lo verifica <code>NucleoSenzaPortaleTest</code>'],
            ['<code>viste/</code>', '5 modelli di pagina in <code>pubblico/</code>, <code>pannello/</code>, <code>stampe/</code>', 'PHP, non un motore di template; chiusa al web dal suo <code>.htaccess</code>'],
            ['<code>inc/</code>', '90 file di funzioni per argomento (7.275 righe), caricati da <code>functions.php</code> in ordine fisso', 'Solo facciate e funzioni non ancora migrate'],
            ['<code>src/</code>', '29 namespace <code>App\\…</code> (PSR-4, autoload senza Composer)', 'Classi con costruttore tipizzato: niente globali'],
            ['<code>database/</code>', '<code>schema.sql</code> (23 tabelle di base)', 'Il resto lo crea <code>assicura_schema()</code>'],
            ['<code>strumenti/</code>', 'prove, ambiente locale, pacchetti, script', 'Bloccata al web; non entra nel pacchetto'],
            ['<code>modelli_documenti/</code>, <code>uploads/</code>, <code>cache/</code>', 'Modelli Word, file caricati, cache e marcatori di schema', '<code>uploads/</code> mai in git; <code>cache/</code> scrivibile da PHP'],
        ])
    )))


    # 2a -----------------------------------------------------------------------------------------------
    corpo = (box(10, 20, 180, 52, 'nucleo.php', '#f1f5f9', '#475569', 'solo per gli indirizzi nuovi')
             + box(10, 100, 180, 52, 'pagina.php di sempre', '#fff7ed', '#c2410c', 'guscio di tre righe')
             + box(250, 60, 170, 52, 'Rotte', '#eff6ff', '#1d4ed8', 'tabella: percorso -> Pagina')
             + box(460, 60, 170, 52, 'Nucleo', '#fdf2f4', '#8e0f1f', 'accesso, token, pagina')
             + box(690, 20, 200, 52, 'Pagina (src/Portale/Pagine)', '#ecfdf5', '#15803d', '__invoke(Richiesta): Risposta')
             + box(690, 100, 200, 52, 'Vista + DatiVista', '#ecfdf5', '#15803d', 'modelli in viste/, escape automatico')
             + freccia(190, 46, 250, 70) + freccia(190, 126, 250, 96)
             + freccia(420, 86, 460, 86) + freccia(630, 76, 690, 50) + freccia(630, 96, 690, 120)
             + '<text x="450" y="170" text-anchor="middle" font-size="12" fill="#64748b">una sola uscita: Risposta::emetti(). Niente echo, niente exit, niente $_GET nelle pagine del nucleo.</text>')
    S.append(('nucleo', 'Il nucleo (src/Http)', sezione('nucleo', '2a', 'Il nucleo: lo strato HTTP (novembre 2026)',
        'Il portale aveva già quasi tutto il dominio in <code>src/</code>: mancava lo strato HTTP, e ogni pagina ripeteva a mano il controllo dei permessi, il token e l\'escape. '
        'Il nucleo è quello strato, in 10 classi che <b>non nominano il portale</b>, e si aggiunge una pagina alla volta: gli indirizzi di sempre restano dove sono.',
        svg(900, 185, corpo, 'Il nucleo: due vie d\'ingresso (nucleo.php o il guscio del file di sempre), una sola uscita.')
        + tab(['Classe', 'Che cosa fa'], [
            ['<code>Richiesta</code>', 'metodo, percorso, parametri e corpo della richiesta, letti una volta sola; <code>Richiesta::finta()</code> per le prove'],
            ['<code>Risposta</code>', 'l\'unico punto che stampa: <code>html()</code>, <code>contenuto()</code>, <code>json()</code>, <code>rimando()</code>, <code>negata()</code>, <code>vuota()</code>, <code>erroreServer()</code>'],
            ['<code>Vista</code> e <code>DatiVista</code>', 'i modelli in <code>viste/</code>: <code>$d->campo</code> esce già escapato, <code>$d->grezzo()</code> solo se lo chiedi, nome mancante = eccezione'],
            ['<code>Rotta</code> e <code>Rotte</code>', 'la tabella degli indirizzi: percorso, classe della pagina, titolo, file guscio. Rinunciare al token CSRF richiede un motivo scritto di almeno 30 caratteri'],
            ['<code>Nucleo</code>', 'l\'ordine fisso: accesso, token su ogni POST, poi la pagina. Un\'eccezione diventa 500 con il messaggio generico e il dettaglio solo nei log'],
            ['<code>Pagina</code>', 'l\'interfaccia di una pagina: <code>__invoke(Richiesta $r): Risposta</code>, dipendenze nel costruttore'],
            ['<code>ControlloAccesso</code>, <code>ProtezioneCsrf</code>, <code>Avvisi</code>', 'le tre interfacce con cui il nucleo chiede al portale chi può entrare, se il token è valido e che messaggi mostrare'],
        ])
        + '<h3>Una pagina nuova: cinque mosse, una quarantina di righe</h3>'
        + passi('Una riga in <code>src/Portale/Instradamento/Rotte.php</code>: <code>new Rotta(\'percorso\', PaginaX::class, \'Titolo\', \'file.php\')</code>. Il percorso è il file di oggi senza <code>.php</code>, perché i due layout usano link relativi.',
                'Il guscio <code>file.php</code>, tre righe: <code>config.php</code>, <code>functions.php</code>, <code>Nucleo::serviFile(__FILE__)</code>.',
                'La classe in <code>src/Portale/Pagine/</code>: <code>final class PaginaX implements Pagina</code>, dipendenze nel costruttore, <code>__invoke</code>. Niente <code>echo</code>, <code>exit</code>, <code>$_GET</code>, globali.',
                'Il modello in <code>viste/</code>: comincia con la guardia e scrive <code>&lt;?php echo $d->campo; ?&gt;</code>, già escapato.',
                'La prova con <code>Richiesta::finta()</code> (modello: <code>tests/Integration/Portale/PaginaContattiTest.php</code>).')
        + '<div class="nota"><b>Perché nessun indirizzo muore.</b> I QR dei badge e delle aule, i link nelle email e nei PDF già firmati puntano a file <code>.php</code>: '
          'quei file restano, ridotti a gusci di tre righe. Cancellando <code>nucleo.php</code> e la regola 3b del <code>.htaccess</code> il portale risponde come prima: '
          'lo prova <code>strumenti/prove/apache_nucleo.sh</code> con Apache vero, confrontando ogni indirizzo con e senza nucleo.</div>'
        + '<div class="info"><b>Limiti noti</b> (scritti in <code>documentazione/nucleo.md</code> per non riscoprirli): un indirizzo pulito sotto <code>admin/</code> non arriva al nucleo, '
          'perché la regola 0 del <code>.htaccess</code> chiude lo scudo del pannello con <code>[L]</code>; ogni rotta ha oggi anche il suo file guscio; '
          '<code>Vista</code> ha tre proprietà statiche (layout, avvisi, radice) che andrebbero riviste su un server a processo persistente; '
          'gli avvisi si consumano solo quando un layout li mostra, così un rimando non li perde.</div>'
        + '<div class="kpi"><div><b>3 / 201</b><span>pagine servite dal nucleo</span></div><div><b>0</b><span>rotte senza token CSRF</span></div>'
          '<div><b>163</b><span>metodi di prova su <code>src/Http</code></span></div><div><b>sì</b><span>provato con Apache vero</span></div></div>'
        + '<div class="nota">Il conto si rilegge quando serve: <code>php strumenti/avanzamento_nucleo.php</code> (con <code>--controlla</code> verifica che rotte e gusci coincidano, '
          'con <code>--casi</code> chiede a PHPUnit i casi veri).</div>'
    )))

    # 3 ------------------------------------------------------------------------------------------------
    corpo = (box(10, 40, 130, 56, 'Richiesta', '#f1f5f9', '#475569', 'Apache + .htaccess')
             + box(170, 40, 150, 56, 'config.php', '#fff7ed', '#c2410c', 'sessione, header, .env, $conn')
             + box(350, 40, 170, 56, 'functions.php', '#eff6ff', '#1d4ed8', 'inc/*, App::avvia, schema')
             + box(550, 40, 170, 56, 'middleware.php', '#ecfdf5', '#15803d', 'SSO, $user_info, ruoli')
             + box(750, 40, 140, 56, 'Pagina', '#fdf2f4', '#8e0f1f', 'azioni + HTML')
             + freccia(140, 68, 170, 68) + freccia(320, 68, 350, 68) + freccia(520, 68, 550, 68) + freccia(720, 68, 750, 68)
             + '<text x="450" y="140" text-anchor="middle" font-size="12" fill="#64748b">le pagine pubbliche senza login (verbali, convenzioni con link) definiscono PAGINA_SENZA_SSO prima di config.php</text>')
    S.append(('richiesta', 'Ciclo di una richiesta', sezione('richiesta', '3', 'Il ciclo di una richiesta',
        'Quattro file di bootstrap, sempre nello stesso ordine. Cron e prove caricano solo <code>functions.php</code>.',
        svg(900, 160, corpo, 'Bootstrap: config.php → functions.php → middleware.php → pagina.')
        + '<ol><li><b><code>config.php</code></b>: apre la sessione con cookie <code>Secure</code>, <code>HttpOnly</code>, <code>SameSite=Lax</code>; imposta gli header di sicurezza (HSTS, CSP, X-Frame-Options…); legge <code>.env</code>; apre <code>$conn</code> (mysqli).</li>'
          '<li><b><code>functions.php</code></b>: include <code>inc/*.php</code> in ordine fisso, carica l\'autoload PSR-4 (<code>src/autoload.php</code>), avvia il container (<code>App::avvia($conn)</code>) e chiama <code>assicura_schema()</code>.</li>'
          '<li><b><code>middleware.php</code></b> (solo pagine protette): <code>sync_sso_user($conn)</code> ricollega l\'utente dalla sessione SSO; se manca, reindirizza a <code>saml_login.php?redirect=…</code>; poi espone <code>$u_id</code>, <code>$u_ruolo</code>, <code>$is_full_admin</code>, <code>$user_info</code>.</li>'
          '<li><b><code>admin/admin_header.php</code></b> (pannello): autentica, calcola i permessi (<code>$puo_fsl</code>, <code>$puo_didattica_tutto</code>, <code>$is_full_admin</code>…), disegna menu e barra superiore.</li></ol>'
        + '<h3>Una pagina tipica (schema)</h3>'
        + cod('''<?php
require_once 'middleware.php';                          // login + $user_info
$serv = \\App\\Core\\App::get(\\App\\Didattica\\ServizioPratiche::class);   // dal container

if ($_SERVER['REQUEST_METHOD'] === 'POST') {            // azioni in cima
    csrf_verify($_POST['csrf_token'] ?? '');
    $err = $serv->invia($_POST, $user_info);
    flash_set($err ?? 'Pratica inviata.', $err ? 'danger' : 'success');
    header('Location: pratiche.php'); exit;             // PRG: redirect + exit
}
$elenco = $serv->dello($user_info);                     // dati
require_once 'header.php'; ?>                           <!-- HTML sotto -->''')
        + '<div class="nota"><b>Errori silenziosi.</b> Le query che falliscono restituiscono <code>false</code>/<code>[]</code>/<code>null</code> (nessuna eccezione), come nel codice storico. Le classi nuove validano gli input e restituiscono messaggi d\'errore come stringa (<code>?string</code>) o <code>Esito</code>.</div>'
    )))

    # 4 ------------------------------------------------------------------------------------------------
    S.append(('routing', 'Routing e aree', sezione('routing', '4', 'Routing, aree e layout',
        'Non c\'è un router PHP: contano il file system e un <code>.htaccess</code>. Le aree create da pannello non generano file.',
        '<ul><li><b>URL puliti</b>: <code>/area_personale</code> → <code>area_personale.php</code> se il file esiste; nel pannello <code>admin/x</code> → <code>admin/x.php</code>.</li>'
        '<li><b>Regola «3b», il nucleo</b> (novembre 2026): un indirizzo con una «/» che non è né un file né una cartella va a <code>nucleo.php</code>, '
        'che risponde con la pagina della rotta o con un 404 pulito senza aprire database e sessione. Restano fuori gli alberi di file '
        '(<code>assets</code>, <code>uploads</code>, <code>cache</code>, <code>vendor</code>, <code>src</code>, <code>inc</code>, <code>tests</code>, '
        '<code>strumenti</code>, <code>database</code>, <code>backups</code>, <code>modelli_documenti</code>): un\'immagine che manca non deve avviare PHP. '
        'Gli indirizzi a un solo pezzo restano di <code>area.php</code>, e un indirizzo sotto <code>admin/</code> non ci arriva, perché la regola 0 chiude prima con <code>[L]</code>.</li>'
        '<li><b>Aree dinamiche</b>: una riga di <code>pagine_eventi</code> (<code>slug</code>, <code>tipo_area</code>: <code>fsl</code>, <code>eventi</code>, <code>gruppi</code>, <code>calendario</code>) è servita da <code>area.php</code> tramite regola di rewrite; gli slug che coincidono con file reali sono rifiutati.</li>'
        '<li><b>Motore dei layout</b>: <code>master_template.php</code> (≈ 250 righe, più i parziali in <code>inc/area_pubblica/</code>: un file per layout, scheda, modulo di iscrizione…) disegna la pagina di un\'area con uno di otto layout:<code>grid</code>, <code>list</code>, <code>advanced_list</code>, <code>calendar</code>, <code>timeline</code>, <code>agenda</code>, <code>gruppi</code>, <code>progetti</code>. Le aree di tipo <code>calendario</code> includono <code>calendario_area.php</code>.</li>'
        '<li><b>Moduli del pannello</b>: <code>MODULI_PORTALE</code> (<code>inc/sezioni.php</code>) elenca cinque moduli (<code>orientamento</code>, <code>fsl</code>, <code>calendari</code>, <code>didattica</code>, <code>portale</code>); <code>PAGINE_MODULO</code> assegna ogni pagina al suo modulo.</li>'
        '<li><b>Pagine a schede</b>: <code>admin/didattica.php</code> prepara i dati comuni e include <code>didattica_&lt;scheda&gt;.php</code> due volte, con <code>$fase = \'azioni\'</code> e poi <code>\'vista\'</code>.</li></ul>'
        + cod('''# .htaccess (estratto)
Options -Indexes -MultiViews
<Files "config.php">      Require all denied </Files>
<FilesMatch "^\\.env">      Require all denied </FilesMatch>
RewriteEngine On
RewriteRule (^|/)vendor/ - [F,L]
RewriteRule ^(admin/.*)$ $1.php [NC,QSA,L]      # admin senza estensione
# file o cartella esistenti: si servono così; poi /nome -> nome.php; infine area.php?slug=…''')
        + '<h3>La tabella delle rotte</h3>'
        + '<p>Non è un router per attributi né un file di configurazione: è un array in <code>src/Portale/Instradamento/Rotte.php</code>, '
          'e <code>php strumenti/avanzamento_nucleo.php --controlla</code> verifica che per ogni rotta esista il suo file guscio.</p>'
        + tab(['Percorso', 'Pagina', 'File di sempre (guscio)'], [
            ['<code>contatti</code>', '<code>PaginaContatti</code>', '<code>contatti.php</code>'],
            ['<code>admin/contatti</code>', '<code>PaginaContattiPannello</code>', '<code>admin/contatti.php</code>'],
            ['<code>stampa_ricevuta</code>', '<code>PaginaStampaRicevuta</code>', '<code>stampa_ricevuta.php</code>'],
        ])
        + '<div class="nota">Il percorso di una rotta è <b>il file di oggi senza <code>.php</code></b>, non un indirizzo inventato: i due layout '
          '(sito e pannello) usano link relativi, e cambiare la profondità dell\'indirizzo li romperebbe.</div>'.replace("QQ", "")
        + gal(fig('map_adm_aree', '<b>Aree</b> (righe di <code>pagine_eventi</code>) gestite da <code>admin/aree.php</code>.'),
              fig('map_adm_impostazioni_area', '<b>Impostazioni area</b>: <code>layout_template</code>, colori e regole.'))
    )))

    # 5 ------------------------------------------------------------------------------------------------
    S.append(('core', 'Core', sezione('core', '5', 'Core: container, database, configurazione',
        'Poche classi, tutte in <code>App\\Core</code>. Le dipendenze si passano nel costruttore; il container le risolve da solo (<i>autowiring</i>).',
        tab(['Classe', 'Ruolo', 'API principale'], [
            ['<code>Container</code>', 'Dependency injection minimale, senza librerie. Servizi creati una volta e condivisi; rileva le dipendenze circolari.', '<code>set($id, fn)</code>, <code>istanza($id, $obj)</code>, <code>get(Classe::class)</code>, <code>has()</code>'],
            ['<code>App</code>', 'Ponte per il codice procedurale: un container per connessione (<code>WeakMap&lt;mysqli, Container&gt;</code>).', '<code>avvia($conn)</code>, <code>get(X::class)</code>, <code>per($conn)->get(X::class)</code>'],
            ['<code>Database</code>', 'Wrapper sulla connessione <code>mysqli</code> del portale, solo prepared statement. Tipi inferiti: int/bool → <code>i</code>, float → <code>d</code>, resto → <code>s</code>.', '<code>righe()</code>, <code>riga()</code>, <code>valore()</code>, <code>esegui()</code>, <code>inserisci()</code>, <code>comando()</code>, <code>transazione()</code>, <code>grezza()</code>'],
            ['<code>Config</code>', 'Valori dal file <code>.env</code> (righe che iniziano con <code>;</code> o <code>#</code> ignorate).', '<code>daFile()</code>, <code>testo()</code>, <code>intero()</code>, <code>booleano()</code>, <code>ha()</code>'],
            ['<code>Sito</code>', 'Radice su disco e indirizzo pubblico (calcolato o da <code>URL_SITO</code> per i cron).', '<code>radice()</code>, <code>urlBase()</code>'],
            ['<code>Orologio</code>', 'Interfaccia dell\'ora corrente: nei test si usa un orologio fisso.', '<code>adesso(): DateTimeImmutable</code>'],
            ['<code>Esito</code>', 'Risultato di un\'operazione (ok / errore con messaggio).', '<code>ok()</code>, <code>errore()</code>, <code>comeCoppia()</code>'],
        ])
        + '<h3>Esempi</h3>'
        + cod('''// Una classe riceve le dipendenze: il container le trova da sola
final class ServizioMessaggiConvenzione
{
    public function __construct(
        private ConvenzioneCompilataRepository $compilate,
        private MessaggiConvenzioneRepository $messaggi,
        private NotificheConvenzioni $notifiche
    ) {}
}

// Dalle pagine / facciate procedurali
$srv = \\App\\Core\\App::get(ServizioMessaggiConvenzione::class);
$srv = \\App\\Core\\App::per($conn)->get(ServizioMessaggiConvenzione::class);   // per un'altra connessione (cron, prove)

// Database: prepared statement, tipi inferiti
$righe = $db->righe('SELECT * FROM uffici_persone WHERE ufficio_id = ? AND email = ?', [$id, $email]);
$nuovoId = $db->inserisci('INSERT INTO uffici (nome, ambito) VALUES (?, ?)', [$nome, 'dipartimento']);
$db->transazione(fn () => $this->spostaTutto());''')
        + '<p>Quando un servizio ha bisogno di un\'implementazione diversa si registra nella <code>Registrazione</code> del namespace (scoperta da <code>App</code>):</p>'
        + cod('''final class Registrazione
{
    public static function registra(Container $c): void
    {
        $c->set(AuleTurni::class, static fn (Container $c): AuleTurni => $c->get(ServizioAuleTurni::class));
    }
}''')
    )))

    # 6 ------------------------------------------------------------------------------------------------
    S.append(('convenzioni', 'Convenzioni di codice', sezione('convenzioni', '6', 'Convenzioni di codice',
        'Ogni modulo segue lo stesso schema, e i nomi sono in italiano (dominio) per restare leggibili a chi lavora con gli uffici.',
        tab(['Suffisso / pattern', 'Cosa fa', 'Esempio'], [
            ['<code>*Repository</code>', 'Accesso ai dati di un aggregato. Nessuna regola di dominio.', '<code>PraticaRepository</code>, <code>UfficiRepository</code>, <code>ConvenzioneCompilataRepository</code>'],
            ['<code>Servizio*</code>', 'Regole di dominio e orchestrazione. Restituisce <code>?string</code> (errore) o dati; non produce HTML.', '<code>ServizioConvenzioneFirmata</code>, <code>ServizioUffici</code>, <code>ServizioIter</code>'],
            ['<code>Vista/*</code>', 'HTML di blocchi riusati (estratto dalle pagine).', '<code>PaginaAttestati</code>, <code>FlashVista</code>'],
            ['<code>Notifiche*</code>', 'Preparano i dati delle email di un modulo e le spediscono con i modelli del catalogo.', '<code>NotificheConvenzioni</code>, <code>NotifichePratiche</code>'],
            ['<code>Costanti</code>', 'Costanti di dominio del modulo (stati, tipi, cartelle).', '<code>Fsl\\Costanti::ITER_PROTOCOLLATA</code>'],
            ['<code>Registrazione</code>', 'Registra nel container i servizi che non si risolvono da soli.', '<code>Risorse\\Registrazione</code>'],
            ['Facciata procedurale', 'Funzione di <code>inc/</code> che inoltra a una classe. Resta finché esistono chiamanti legacy.', '<code>db_righe()</code> → <code>Database::righe()</code>'],
        ])
        + '<ul><li>Strategia <b>strangler fig</b>: si sposta un modulo alla volta (query → repository, regole → servizi, viste per ultime) lasciando le pagine funzionanti. Il piano è in <code>MIGRATION_PLAN.md</code>.</li>'
          '<li>Le classi di <code>src/</code> <b>non usano mai</b> <code>App</code>: è un ponte per le pagine, destinato a sparire con l\'ultimo codice procedurale.</li>'
          '<li><code>declare(strict_types=1)</code> ovunque, <code>final</code> per default, costruttori con <i>promoted properties</i>.</li>'
          '<li>Soglia di sicurezza: ogni cambiamento lascia verdi tutte le prove (<code>strumenti/prove/esegui.php</code>).</li></ul>'
    )))

    # 7 ------------------------------------------------------------------------------------------------
    S.append(('auth', 'Autenticazione e permessi', sezione('auth', '7', 'Autenticazione, ruoli e permessi',
        'Tre livelli di identità (SSO, SPID/CIE, link/password) e un RBAC a ruoli + perimetri.',
        '<h3>Identità</h3>'
        + tab(['Modo', 'Chi', 'Come'], [
            ['SSO di Ateneo', 'Studenti, docenti, PTA', '<code>saml_login.php</code> → <code>AccessoSso::accedi()</code> (SimpleSAMLphp); <code>sync_sso_user()</code> crea/aggiorna l\'utente, riconosce il tipo da <code>matricola_studente</code> / <code>matricola_dipendente</code>; dopo «Esci» nessun accesso automatico (<code>CookieUscito</code>)'],
            ['SPID / CIE', 'Esterni; conferma lettere di tutorato', 'Metodo e livello letti da attributi e contesto SAML (<code>metadati_accesso_saml()</code>); <code>INCARICHI_SOLO_SPID_CIE=1</code>'],
            ['Link personale con token', 'Scuole, aziende, relatori, Direttore', 'Token casuale (<code>random_bytes</code>) in tabella; pagine come <code>convenzione_online.php?t=</code>, <code>firma_pratica.php</code>; il Direttore apre il suo link <i>e</i> accede'],
            ['Password dedicata', 'Esterni sui verbali', '<code>verbali_accessi</code> (hash <code>password_hash</code>), sessione separata, rate limit; pagina con <code>PAGINA_SENZA_SSO</code>'],
        ])
        + '<h3>Ruoli e perimetri</h3>'
        + tab(['Elemento', 'Dettaglio'], [
            ['Ruoli (<code>ruoli</code>)', '1 Super Amministratore · 2 Gestore Area/Evento · 3 Studente · 4 Docente/Dipendente · 5 Ospite/Esterno; più <code>utenti.ruoli_secondari</code> (CSV)'],
            ['Moduli (<code>MODULI_PORTALE</code>)', 'Si abilita un utente a uno o più moduli (<code>ServizioAbilitazioni::haModulo()</code>)'],
            ['Perimetri (<code>abilitazioni_ambito</code>)', 'tutta l\'area · tutti i progetti · tutti gli eventi · attività scelte · FSL (tutto / solo convenzioni / solo scuole)'],
            ['Abilitazioni in attesa (<code>abilitazioni_attesa</code>)', 'Date a chi non ha ancora fatto accesso: si attivano al primo login con quell\'email'],
            ['Uffici (<code>uffici</code>, <code>uffici_persone</code>)', 'Appartenere a un ufficio dà accesso alle pratiche dell\'ufficio; i <i>compiti</i> (pratiche, sedute, ricevimento, bandi) aprono il pannello Didattica'],
            ['Flag calcolati', '<code>$is_full_admin</code>, <code>$puo_fsl</code>, <code>$puo_fsl_convenzioni</code>, <code>$puo_didattica_tutto</code>, <code>utente_operatore_ufficio()</code>… in <code>admin_header.php</code>'],
        ])
        + cod('''// Direzione: chi firma ora (vice se «Direttore assente»)
$dir = App::get(\\App\\Uffici\\Direzione::class);
$dir->chiFirma();                    // ['nome'=>…, 'email'=>…, 'cf'=>…]  (cf dall'utente collegato)
$dir->puoFirmare($utente);           // email o codice fiscale della persona in carica
$dir->puoImpostareAssenza($utente);  // Direttore, vice, segreteria, amministratori''')
        + '<h3>Difese di base</h3><ul><li><code>csrf_field()</code> / <code>csrf_verify()</code> (classe <code>Auth\\Csrf</code> sulla sessione) su ogni POST e AJAX.</li>'
          '<li><code>check_rate_limit($conn, $endpoint, $max, $finestra)</code> (<code>LimiteRichieste</code> + <code>rate_limit_attempts</code>) per IP sugli endpoint pubblici.</li>'
          '<li>Prenotazioni anonime: CAPTCHA interno (<code>CaptchaPrenotazione</code>), campo trappola, limite per IP.</li>'
          '<li><code>nega_accesso()</code> (<code>RispostaHttp::negaAccesso</code>) per ogni risorsa che non appartiene all\'area/ufficio dell\'utente.</li></ul>'
        + gal(fig('map_adm_utenti', '<b>Utenti e abilitazioni</b> (<code>admin/utenti.php</code>).'),
              fig('map_adm_uffici_direzione', '<b>Direzione e «Direttore assente»</b> (<code>admin/uffici.php</code>, <code>Direzione</code>).'))
    )))

    # 8 ------------------------------------------------------------------------------------------------
    S.append(('db', 'Database e migrazioni', sezione('db', '8', 'Database e migrazioni automatiche',
        'Non esistono script SQL da lanciare dopo un aggiornamento: lo schema si adegua da solo alla prima richiesta.',
        '<ul><li><b><code>Core\\Schema\\Definizioni</code></b>: <code>tabelle()</code> (<code>CREATE TABLE IF NOT EXISTS</code>), <code>colonne()</code> (<code>ALTER … ADD COLUMN</code> se manca), <code>tipi()</code> (correzioni di tipo per database vecchi).</li>'
        '<li><b><code>Core\\Schema\\Migrazioni::aggiorna()</code></b>: se esiste <code>cache/schema_v&lt;VERSIONE&gt;.ok</code> non fa nulla; altrimenti crea tabelle e colonne, poi <code>datiDiPartenza()</code> (seed, migrazioni di dati) e scrive il marcatore. Oggi <code>VERSIONE = ' + versione_schema() + '</code>.</li>'
        '<li>Si alza <code>VERSIONE</code> ogni volta che si tocca <code>Definizioni</code> o i dati di partenza.</li>'
        '<li>Le migrazioni di dati sono idempotenti con una guardia. Esempio: <code>migraUffici()</code> copia le 4 tabelle storiche negli <code>uffici</code>/<code>uffici_persone</code> unificati <i>una sola volta</i> (finché non esiste l\'ufficio <code>direzione</code>), preservando gli id e introducendo <code>op_id</code>, il numero stabile con cui <code>pratiche.assegnata_a</code> identifica l\'operatore.</li>'
        '<li>Il marcatore è condiviso tra database diversi sullo stesso codice: dopo un bump, su un secondo database va cancellato per rilanciare la migrazione.</li></ul>'
        + cod('''// Definizioni::colonne() — aggiunge la colonna solo se manca
'verbali_accessi_log' => [
    'utente' => "ADD COLUMN utente VARCHAR(150) DEFAULT ''",
],
// Migrazioni::datiDiPartenza() — guardia per eseguirla una volta
$giaFatto = (int) $this->db->valore("SELECT COUNT(*) FROM uffici WHERE chiave = 'direzione'") > 0;
if (!$giaFatto) { /* copia uffici, persone, uffici di Ateneo e remap dei bandi */ }''')
        + '<h3>Le tabelle per dominio</h3>'
        + tab(['Dominio', 'Tabelle principali'], [
            ['Utenti e accessi', '<code>ruoli</code>, <code>utenti</code>, <code>abilitazioni_ambito</code>, <code>abilitazioni_attesa</code>, <code>log_accessi</code>, <code>log_attivita</code> (audit), <code>log_email</code>, <code>rate_limit_attempts</code>'],
            ['Aree, eventi, prenotazioni', '<code>pagine_eventi</code>, <code>sottocategorie</code>, <code>eventi</code>, <code>progetti_dettagli</code>, <code>turni</code>, <code>prenotazioni</code>, <code>campi_form</code>, <code>messaggi_prenotazioni</code>, <code>partecipanti_prenotazione</code>, <code>sondaggi*</code>, <code>avvisi_*</code>'],
            ['Portale', '<code>configurazione_portale</code>, <code>slide_home</code>, <code>menu_voci</code>, <code>impostazioni_sistema</code>, <code>template_email</code>, <code>copie_configurazione</code>, <code>riepilogo_persone</code>'],
            ['Anagrafi', '<code>scuole</code>, <code>personale_ateneo</code>, <code>personale_modifiche</code>, <code>anagrafe_strutture</code>, <code>corsi_studio</code>, <code>insegnamenti</code>, <code>ateneo_cds</code>, <code>ateneo_insegnamenti*</code>'],
            ['Uffici', '<code>uffici</code>, <code>uffici_persone</code> (+ le 4 tabelle storiche, non più usate), <code>operatori_sostituzioni</code>'],
            ['Didattica', '<code>didattica_moduli</code>, <code>pratiche</code>, <code>pratiche_eventi</code>, <code>pratiche_firme</code>, <code>pratiche_conferme</code>, <code>pratiche_operatori</code>, <code>didattica_consigli*</code>, <code>didattica_sedute*</code>, <code>didattica_convocazioni</code>, <code>archivio_organi</code>, <code>verbali_accessi*</code>'],
            ['FSL e convenzioni', '<code>convenzioni_scuole</code>, <code>convenzioni_compilate</code>, <code>convenzioni_messaggi</code>, <code>convenzioni_tesi</code>, <code>valutazioni_fsl</code>'],
            ['Tutorato', '<code>tutorato_bandi</code>, <code>tutorato_incarichi</code>, <code>tutorato_eventi</code>, <code>tutorato_registro</code>, <code>tutorato_dichiarazioni</code>, <code>tutorato_accessi_dati</code>'],
            ['Email', '<code>email_modelli</code> (solo le modifiche ai testi: senza riga vale il testo originale del catalogo)'],
            ['Risorse', '<code>risorse</code>, <code>risorse_orari</code>, <code>risorse_chiusure</code>, <code>prenotazioni_risorse</code>, <code>prenotazioni_risorse_attesa</code>, <code>risorse_responsabili</code>, <code>risorse_uffici</code>, <code>risorse_contatori*</code>, <code>risorse_diario</code>, <code>risorse_scadenze</code>'],
        ])
        + '<h3>Le ultime versioni dello schema</h3>'
        + tab(['Versione', 'Che cosa aggiunge'], [
            ['<b>v135</b>', '<code>eventi.rilascia_attestato</code> (1 di partenza): spento, la presenza si registra solo per le statistiche e non esce nessun attestato'],
            ['<b>v134</b>', '<code>impostazioni_sistema.checkin_anticipo_minuti</code> (60 di partenza, da 0 a 720): quanti minuti prima dell\'inizio si apre il check-in con il QR d\'aula; prima era fisso a 30 nel codice'],
            ['v133–v131', 'campi e colonne personalizzabili della Richiesta di pubblicazione, impostazioni e tessera dell\'area personale'],
            ['v126–v124', 'MyForms: <code>forms</code>, <code>forms_gestori</code>, <code>forms_risposte</code> e i compiti del cron'],
        ])
        + '<div class="nota">I campi variabili (moduli di pratica, iter, risposte) sono salvati come JSON in colonne <code>*_json</code>: <code>didattica_moduli.campi_json</code>, <code>iter_json</code>, <code>domanda_json</code>, <code>verbale_json</code>; <code>pratiche.risposte_json</code>; <code>convenzioni_compilate.dati_json</code>.</div>'
    )))

    # 9 ------------------------------------------------------------------------------------------------
    S.append(('moduli', 'Moduli (namespace)', sezione('moduli', '9', 'I moduli e le classi principali',
        'Ogni namespace di <code>src/</code> è un modulo del portale, con le sue pagine e le sue tabelle.',
        tab(['Namespace', 'Responsabilità', 'Classi chiave', 'Pagine'], [
            ['<code>Core</code>', 'Container, DB, config', '<code>Container</code>, <code>App</code>, <code>Database</code>, <code>Config</code>, <code>Sito</code>, <code>Schema\\Definizioni</code>, <code>Schema\\Migrazioni</code>', '—'],
            ['<code>Auth</code>', 'Utenti, sessione, SSO, abilitazioni', '<code>Saml\\AccessoSso</code>, <code>Sessione</code>, <code>Csrf</code>, <code>LimiteRichieste</code>, <code>ServizioUtenti</code>, <code>Abilitazioni\\ServizioAbilitazioni</code>', '<code>saml_login.php</code>, <code>admin/utenti.php</code>'],
            ['<code>Anagrafi</code>', 'Persone, scuole, corsi, insegnamenti di Ateneo', '<code>ClientApiAteneo</code>, <code>SincronizzazioneAnagrafe</code>, <code>ServizioPersone</code>, <code>ServizioScuole</code>, <code>ServizioCatalogo</code>, <code>PermessiGestori</code>', '<code>admin/anagrafe_*.php</code>, <code>persona.php</code>'],
            ['<code>Eventi</code>', 'Eventi, progetti, turni, agenda, report', '<code>ServizioEventi</code>, <code>ServizioProgetti</code>, <code>ServizioTurni</code>, <code>ServizioAgenda</code>, <code>ServizioReport</code>, <code>ServizioSeminari</code>', '<code>admin/eventi.php</code>, <code>progetti.php</code>, <code>agenda.php</code>'],
            ['<code>Iscrizioni</code>', 'Prenotazioni dei turni, area personale, lista d\'attesa', '<code>ServizioPrenotazioni</code>, <code>ServizioDisponibilita</code>, <code>ServizioListaAttesa</code>, <code>ServizioCheckin</code>, <code>ServizioAreaPersonale</code>, <code>CaptchaPrenotazione</code>', '<code>area.php</code>, <code>area_personale.php</code>'],
            ['<code>Iscritti</code>', 'Gestione iscritti lato pannello, cron, badge, scanner', '<code>ServizioIscritti</code>, <code>ServizioCron</code>, <code>ServizioScanner</code>, <code>ServizioBadge</code>, <code>ServizioEmailMassiva</code>, <code>ServizioFormBuilder</code>, <code>ServizioMessaggi</code>, <code>CasellaMessaggi</code>', '<code>admin/iscritti.php</code>, <code>admin/messaggi.php</code>, <code>admin/api_unread.php</code>, <code>scanner.php</code>'],
            ['<code>Attestati</code>', 'Attestati e elenchi degli studenti', '<code>ServizioAttestati</code>, <code>ElencoStudenti</code>, <code>DatiAttestato</code>, <code>RegoleClasse</code>', '<code>elenco_studenti.php</code>, <code>stampa_attestato.php</code>, <code>verifica_attestato.php</code>'],
            ['<code>Fsl</code>', 'Formazione Scuola Lavoro e convenzioni con le scuole', '<code>ServizioConvenzioneOnline</code>, <code>ServizioConvenzioneFirmata</code>, <code>ServizioFirmaDirettore</code>, <code>ServizioMessaggiConvenzione</code>, <code>AllegatoAPdf/Word</code>, <code>ProgrammaFsl</code>, <code>ServizioDocumentiClasse</code>', '<code>convenzione_online.php</code>, <code>firma_convenzione.php</code>, <code>admin/fsl.php</code>'],
            ['<code>ConvenzioniTesi</code>', 'Convenzioni di tesi con enti e aziende', '<code>ServizioConvenzioniTesi</code>, <code>ConvenzioneTesiRepository</code>, <code>ModelloConvenzione</code>, <code>NotificheConvenzioniTesi</code>', '<code>convenzione_tesi.php</code>, <code>richiedi_convenzione_tesi.php</code>'],
            ['<code>Tirocini</code>', 'Tirocini curriculari: aziende (report TSP) e laboratori interni', '<code>ServizioAziende</code>, <code>ServizioLaboratori</code>, <code>LettoreFoglio</code> (xlsx/csv), <code>Province</code>', '<code>tirocini_aziende</code>, <code>tirocini_laboratori</code>; pagine <code>tirocini.php</code> (dopo l\'accesso), <code>admin/tirocini.php</code>'],
            ['<code>Didattica</code>', 'Moduli, pratiche, iter, firme, consigli, sedute, verbali, archivio', '<code>ServizioPratiche</code>, <code>ServizioIter</code>, <code>FirmaPratica</code>, <code>ServizioFlussoConvalide</code>, <code>ServizioMarcheBollo</code>, <code>ServizioSedute</code>, <code>ServizioConvocazioni</code>, <code>ArchivioVerbali</code>, <code>ServizioVerbaliPubblici</code>, <code>ModelliPratiche</code>, <code>DomandaPdfa</code>', '<code>modulo.php</code>, <code>pratiche.php</code>, <code>verbali.php</code>, <code>admin/didattica*.php</code>'],
            ['<code>Tutorato</code>', 'Bandi, lettere, firme, registro, pagamenti', '<code>ServizioIncarichi</code>, <code>ServizioRegistroTutorato</code>, <code>ServizioPagamenti</code>, <code>ServizioImportBando</code>, <code>FirmaRemotaAruba</code>, <code>VerificaPdfFirmato</code>, <code>Compenso</code>', '<code>incarico.php</code>, <code>firma_incarico.php</code>, <code>registro_tutorato.php</code>, <code>pagamenti_tutorato.php</code>'],
            ['<code>Email</code>', 'Testi delle email modificabili dal pannello, con segnaposto', '<code>ModelliEmail</code>, <code>InvioEmail</code>, <code>CatalogoEmail</code> e i cataloghi dei moduli (<code>CatalogoDidattica</code>, <code>CatalogoConvenzioni</code>, <code>CatalogoRisorse</code>, <code>CatalogoEventi</code>, <code>CatalogoSistema</code>)', '<code>admin/email.php</code>'],
            ['<code>Risorse</code>', 'Aule, laboratori, auto: prenotazioni a slot', '<code>ServizioRisorse</code>, <code>ListaAttesaRisorse</code>, <code>ServizioResponsabiliRisorse</code>, <code>ServizioDiarioBordo</code>, <code>ServizioContatoriRisorse</code>, <code>ServizioScadenzeRisorsa</code>', '<code>calendario_area.php</code>, <code>risorse_responsabile.php</code>, <code>diario_bordo.php</code>'],
            ['<code>Uffici</code>', 'Uffici di Dipartimento e Ateneo, Direzione', '<code>UfficiRepository</code>, <code>ServizioUffici</code>, <code>Direzione</code>', '<code>admin/uffici.php</code>, <code>direttore_assente.php</code>'],
            ['<code>Portale</code>', 'Home, menu, cruscotti, guide, riepiloghi', '<code>CruscottoDirettore</code>, <code>DaFarePersona</code>, <code>RiepilogoPersone</code>, <code>SospensionePromemoria</code>, <code>GuidePerRuolo</code>, <code>MenuRepository</code>, <code>OrganizzazioneProposta</code>', '<code>index.php</code>, <code>cruscotto_direttore.php</code>, <code>guida.php</code>'],
            ['<code>Sistema</code>', 'Controlli, backup, impostazioni', '<code>ControlloSito</code>, <code>FileEnv</code>, <code>ImpostazioniSistemaRepository</code>, <code>Backup\\VerificaBackup</code>', '<code>admin/sistema.php</code>, <code>admin/cron_backup.php</code>'],
            ['<code>Infrastructure</code>', 'Servizi tecnici condivisi', '<code>Mail\\SmtpMailer</code>, <code>Pdf\\Documento</code>, <code>Pdf\\FirmePades</code>, <code>Pdf\\TestoPdf</code>, <code>Documenti\\Word</code>/<code>Excel</code>, <code>Storage\\Upload</code>, <code>Sicurezza\\Cifratura</code>, <code>Audit\\AuditLog</code>', '—'],
            ['<code>Ateneo</code>, <code>Avvisi</code>, <code>Sondaggi</code>', 'Uffici di Ateneo (wrapper), avvisi per email, questionari', '<code>ServizioUfficiAteneo</code>, <code>ServizioAvvisi</code>, <code>ServizioSondaggi</code>', '<code>avvisi.php</code>, <code>sondaggio.php</code>'],
        ])
        + '<h3>Dalla schermata alle classi</h3><p>Le stesse pagine viste dall\'utente, con le classi che le servono.</p>'
        + gal(fig('map_pub_agenda', '<b>Agenda</b> · <code>agenda.php</code> → <code>ServizioAgenda</code>, <code>AgendaRepository</code>, <code>FonteEventiAgenda</code>; <code>agenda_ics.php</code> per il calendario.'),
              fig('map_pub_evento', '<b>Scheda evento</b> · <code>master_template.php</code> + <code>inc/area_pubblica/scheda_evento.php</code> →<code>ServizioEventi</code>, <code>ServizioTurni</code>, <code>ServizioDisponibilita</code>, <code>ServizioPrenotazioni</code>.'),
              fig('map_area_studente', '<b>Area personale</b> · <code>area_personale.php</code> → <code>ServizioAreaPersonale</code>, <code>AreaPersonaleRepository</code>, <code>DaFarePersona</code>.'),
              fig('map_pub_verifica', '<b>Verifica attestato</b> · <code>verifica_attestato.php</code> → <code>ServizioAttestati</code>, <code>AttestatoRepository</code>, <code>DatiAttestato</code>.'),
              fig('stud_prenota', '<b>Prenotazione di una risorsa</b> · <code>calendario_area.php</code> → <code>CalendarioRisorse</code>, <code>ServizioRisorse</code>, <code>ListaAttesaRisorse</code>.'),
              fig('fsl_modulo', '<b>Convenzione online</b> · <code>convenzione_online.php</code> → <code>ServizioConvenzioneOnline</code>, <code>PrecompilazioneConvenzione</code>, <code>LogoScuola</code>.'),
              fig('fsl_documenti_pronti', '<b>Documenti generati</b> · <code>ModelliConvenzione</code>, <code>AllegatoAModello</code> → <code>AllegatoAPdf</code> e <code>AllegatoAWord</code>.'),
              fig('stud_modulo_tesi', '<b>Modulo a pagine</b> · <code>modulo.php</code> → <code>CampiModulo</code>, <code>HtmlCampi</code>, <code>LetturaRisposte</code>, <code>ServizioPratiche::crea()</code>.'),
              fig('proto_scheda', '<b>Protocollo e iter</b> · <code>admin/didattica_pratiche.php</code> → <code>ServizioFlussoConvalide</code>, <code>ServizioMarcheBollo</code>, <code>FlussoPratica</code>.'),
              fig('ref_seduta', '<b>Seduta guidata</b> · <code>admin/didattica_sedute.php</code> → <code>ServizioSedute</code>, <code>ServizioPuntiSeduta</code>, <code>DecisioniSeduta</code>, <code>TestoVerbale</code>.'),
              fig('map_adm_did_archivio', '<b>Archivio verbali</b> · <code>ArchivioVerbali</code>, <code>ScansioneArchivio</code>, <code>OrganiArchivio</code>, <code>TestoPdf</code>.'),
              fig('map_adm_tirocini', '<b>Tirocini</b> · <code>admin/tirocini.php</code> → <code>ServizioAziende::importa()</code> (report TSP, solo la «x»), <code>LettoreFoglio</code>, <code>Province</code>.'),
              fig('map_pub_tirocini_accesso', '<b>Pagina dopo l\'accesso</b> · <code>accesso_richiesto.php</code> → <code>richiedi_accesso()</code> (401 + pulsante verso <code>saml_login.php?redirect=</code>).'),
              fig('tesi_1_pannello_da_seguire', '<b>Convenzioni di tesi</b> · <code>admin/convenzioni_tesi.php</code> → <code>ServizioConvenzioniTesi</code>, <code>ModelloConvenzione</code>.'),
              fig('tutorato_1_dati_pagamento', '<b>Dichiarazione del tutor</b> · <code>dichiarazione_tutorato.php</code> → <code>ServizioDichiarazione</code>, <code>Cifratura</code> (IBAN).'),
              fig('resp_diario', '<b>Diario di bordo</b> · <code>diario_bordo.php</code> → <code>ServizioDiarioBordo</code>, <code>DiarioBordoRepository</code>.'),
              fig('map_dir_cruscotto', '<b>Cruscotto</b> · <code>cruscotto_direttore.php</code> → <code>CruscottoDirettore</code> (undici gruppi, ognuno isolato in un <code>try</code>).'),
              fig('map_adm_iscritti', '<b>Iscritti</b> · <code>admin/iscritti.php</code> → <code>ServizioIscritti</code>, <code>FiltriIscritti</code>, <code>RisultatoMassivo</code>.'),
              fig('map_adm_iscritti_orientamento', '<b>Solo orientamento</b> · <code>ServizioIscritti::soloOrientamento()</code> → <code>prenotazioni.solo_orientamento</code>; nelle query il campo <code>fsl</code> diventa <code>IF(pr.solo_orientamento = 1, 0, pd.convenzione)</code>.'),
              fig('map_adm_messaggi', '<b>Casella messaggi</b> · <code>admin/messaggi.php</code> → <code>CasellaMessaggi</code> (permessi per area, <code>MessaggiRepository</code>, <code>MessaggiConvenzioneRepository</code>), contatore in <code>admin_header.php</code> e <code>api_unread.php</code>.'),
              fig('map_adm_fsl_stipulare', '<b>Da stipulare</b> · <code>admin/fsl.php?tab=stipulare</code> → <code>ServizioConvenzioniScuole::destinatari()</code>; «Chiedi la convenzione» (<code>ServizioIscritti::richiediConvenzione</code>) e «Scrivi alla scuola» (<code>ServizioMessaggi::inviaDaIscritti</code>).'),
              fig('map_adm_form', '<b>Form Builder</b> · <code>ServizioFormBuilder</code>, <code>FormBuilderRepository</code>, tabella <code>campi_form</code>.'),
              fig('map_adm_statistiche', '<b>Statistiche</b> · <code>StatisticheRepository</code>, Chart.js, esportazione con <code>Excel</code>.'),
              fig('map_adm_menu', '<b>Menu</b> · <code>MenuRepository</code>, <code>ServizioMenu</code>, <code>CatalogoPagineMenu</code>, ordinamento con SortableJS.'),
              fig('map_adm_anagrafe_docenti', '<b>Anagrafe</b> · <code>SincronizzazioneAnagrafe</code>, <code>ClientApiAteneoHttp</code>, <code>PersonaRepository</code>.'),
              fig('map_adm_audit', '<b>Registro operazioni</b> · <code>AuditLog</code>, <code>RegistroAuditRepository</code>, <code>registra_log_audit()</code>.'),
              fig('map_adm_uffici', '<b>Uffici</b> · <code>UfficiRepository</code>, <code>ServizioUffici</code> (regole), vista <code>admin/uffici.php</code>.'),
              fig('map_adm_risorse', '<b>Risorse</b> · <code>admin/risorse.php</code> → <code>ServizioGestioneRisorse</code>, <code>ServizioResponsabiliRisorse</code>, <code>ServizioCampiRisorsa</code>.'),
              fig('map_adm_scuole', '<b>Anagrafe scuole</b> · <code>ServizioScuole</code>, <code>ScuolaRepository</code>, tabella <code>scuole</code> (open data del Ministero).'),
              fig('map_adm_anagrafe_corsi', '<b>Corsi di studio</b> · <code>ServizioCatalogo</code>, tabelle <code>corsi_studio</code> e <code>ateneo_cds</code>.'),
              fig('map_adm_anagrafe_insegnamenti', '<b>Insegnamenti</b> · archivio di tutto l\'Ateneo, con dipartimento e integrati (<code>ateneo_insegnamenti</code>).'))
    )))

    # 10 -----------------------------------------------------------------------------------------------
    corpo = ''.join([
        box(10, 30, 120, 50, 'compilata', '#f1f5f9', '#475569', "stato_iter ''"),
        box(160, 30, 150, 50, 'caricata_scuola', '#fff7ed', '#c2410c', 'PDF PAdES ricevuto'),
        box(340, 30, 130, 50, 'in_carico', '#eff6ff', '#1d4ed8', 'protocollo entrata'),
        box(500, 30, 130, 50, 'da_firmare', '#f5f3ff', '#6d28d9', 'link al Direttore'),
        box(660, 30, 130, 50, 'firmata_direttore', '#f5f3ff', '#6d28d9', 'PAdES + 1'),
        box(760, 130, 130, 50, 'protocollata', '#ecfdf5', '#15803d', 'attiva'),
        box(340, 130, 130, 50, 'rifiutata', '#fef2f2', '#b91c1c', 'con motivo'),
        freccia(130, 55, 160, 55), freccia(310, 55, 340, 55), freccia(470, 55, 500, 55), freccia(630, 55, 660, 55), freccia(725, 80, 800, 130),
        freccia(405, 80, 405, 130), '<path d="M340 155 Q 230 150 235 82" fill="none" stroke="#64748b" stroke-width="1.6" marker-end="url(#fr)"/>',
        '<text x="250" y="170" font-size="11" fill="#64748b">la scuola ricarica</text>'])
    S.append(('flussi', 'Flussi chiave', sezione('flussi', '10', 'Flussi chiave',
        'Le macchine a stati dei processi principali. Gli stati sono costanti nel modulo; le transizioni stanno nei servizi, mai nelle pagine.',
        '<h3>Convenzione FSL (<code>convenzioni_compilate.stato_iter</code>)</h3>'
        + svg(900, 200, corpo, 'Stati della convenzione FSL: compilata → caricata_scuola → in_carico → da_firmare → firmata_direttore → protocollata; rifiutata → ricarica.')
        + '<ul><li><code>ServizioConvenzioneFirmata::carica()</code> valida con <code>VerificaFirme</code> (solo PAdES, nessuna modifica dopo la firma), salva il file in cartella bloccata, avvisa l\'ufficio e manda la ricevuta.</li>'
          '<li><code>protocolloIngresso()</code> registra il numero, manda il link al Direttore in carica (<code>Direzione::chiFirma()</code>); <code>ServizioFirmaDirettore</code> firma con Aruba o accetta il PDF firmato (stesso documento + una firma).</li>'
          '<li><code>completa()</code> registra la convenzione (<code>convenzioni_scuole</code>), conferma le prenotazioni in attesa e avvisa scuola e docenti.</li>'
          '<li><code>ServizioMessaggiConvenzione</code>: promemoria «carica il PDF» (max uno ogni 24 ore), messaggi e risposte (<code>convenzioni_messaggi</code>).</li></ul>'
        + '<h3>Casella dei messaggi (<code>CasellaMessaggi</code>)</h3>'
        + '<ul><li><code>aree()</code>/<code>filtroArea()</code> applicano le regole di <code>admin_header.php</code> a ogni area: gestore dell\'area o del modulo = nessun filtro, gestore di singole attività = <code>AND e.id IN (…)</code>, nessun diritto = area esclusa.</li>'
          '<li><code>conversazioni()</code> unisce le prenotazioni di tutte le aree (<code>messaggi_prenotazioni</code>) e le conversazioni con le scuole (<code>convenzioni_messaggi</code>, solo se la scuola ha scritto almeno una volta e per chi lavora le convenzioni FSL), ognuna col modulo (<code>Sezioni::moduloDiArea</code>).</li>'
          '<li><code>nonLetti()</code> alimenta il contatore del pulsante e <code>api_unread.php</code> (polling ogni 30 secondi).</li>'
          '<li><b>Archivia</b>: <code>prenotazioni.msg_archiviata_il</code> e <code>convenzioni_compilate.msg_archiviata_il</code> (schema v85). Una conversazione è archiviata finché la data è uguale o successiva all\'ultimo messaggio dell\'altra parte: se scrive ancora, torna in casella senza altre scritture.</li></ul>'
        + '<h3>Pratica studente (<code>didattica_moduli.iter_json</code>)</h3>'
        + '<p>L\'iter è un array di passi. Ogni passo è un ufficio (id o chiave), un marcatore (<code>"protocollo"</code>, <code>"@cdl"</code>, <code>"@segreteria"</code>) o un oggetto tipizzato:</p>'
        + cod('''["protocollo", "@cdl", "@segreteria"]                                  // iter semplice
[{"t":"persona","campo":"Relatore","gg":3},                       // firma/conferma di una persona del modulo
 {"t":"ufficio","u":"protocollo","gg":2,"pagamento":true},        // marche da bollo
 {"t":"ufficio","u":"@cdl","documento":true},                     // l'ufficio carica un documento
 {"t":"conferma","campo":"Docente"},                              // conferma docenti (senza firma digitale)
 {"t":"direttore","gg":4}]                                        // firma PAdES del Direttore''')
        + '<p><code>ServizioIter::definizione()</code> normalizza i passi; <code>ServizioFlussoConvalide::trasmettiAlPasso()</code> sposta la pratica verificando le condizioni del passo; <code>PromemoriaPratiche</code> (cron) sollecita dopo <code>gg</code> giorni; <code>FirmaPratica</code> gestisce firme e link; la domanda in PDF/A è prodotta da <code>DomandaPdfa</code> e il testo del verbale da <code>TestoVerbale</code>.</p>'
        + '<h3>Prenotazioni di eventi e risorse</h3>'
        + '<ul><li><b>Turni</b>: <code>prenotazioni.stato</code> ∈ <code>confermata</code>, <code>da_approvare</code>, <code>in_attesa</code> (lista d\'attesa), <code>richiesta_conferma</code> (posto offerto, 24 h), <code>annullata</code>, <code>rifiutata</code>; vincoli e regole in <code>ServizioRegoleIscrizioni</code>, <code>ServizioVincoli</code>, <code>RegoleFsl</code>.</li>'
          '<li><b>Risorse</b>: <code>prenotazioni_risorse.stato</code> ∈ <code>confermata</code>, <code>da_approvare</code>, <code>rifiutata</code>, <code>annullata</code>; slot calcolati da <code>CalendarioRisorse</code>; <code>ListaAttesaRisorse</code> (coda con finestra di 24 ore, il cron <code>scala()</code> passa al successivo).</li></ul>'
        + '<h3>Verbali pubblici in sola lettura</h3>'
        + cod('''puoScaricare($utente)          // admin  oppure  membro dell'ufficio fisso 'verbali_scarico'
accessoDaCredenziali($utente)  // accesso virtuale (id 0, ambiti ["*"]) per chi è abilitato
tokenVista($sedutaId)          // permesso monouso (10 min) in sessione
consumaToken($t, $sedutaId)    // vale una sola volta, per quel verbale''')
        + '<p><code>verbali.php</code> ha tre rotte: <code>?v=ID</code> (visualizzatore: PDF.js disegna le pagine su <code>&lt;canvas&gt;</code> con filigrana; niente toolbar, niente testo selezionabile), <code>?f=ID&amp;t=TOKEN</code> (legge il PDF, rifiuta <code>Sec-Fetch-Dest: document</code> e un token già usato; <code>Cache-Control: no-store</code>) e <code>?d=ID</code> (download: 403 senza <code>puoScaricare</code>). Ogni lettura e download va in <code>verbali_accessi_log</code>.</p>'
        + '<p><b>Esportazioni del pannello</b>: ogni elenco si scarica con il menu «Scarica» (<code>pn_scarica()</code>) in Excel, PDF e CSV. Classi in <code>src/Infrastructure/Documenti/</code>: <code>TabellaEsportabile</code> (una tabella), <code>FogliEsportabili</code> (report a più fogli; <code>scaricaDa()</code> rifà in PDF e CSV i fogli dell\'ultimo <code>Excel::crea()</code>). Per le tabelle già nella pagina, <code>pn_scarica_tabella()</code> + <code>assets/js/esporta_tabella.js</code> mandano le righe visibili a <code>admin/esporta_tabella.php</code>.</p>'
        + gal(fig('map_adm_fsl_home', '<b>Pannello FSL</b> (<code>admin/fsl.php</code>, <code>tab=home</code>): schede «cosa devo fare» (<code>Pannello::compiti</code>), esportazioni con <code>TabellaEsportabile</code>.'),
              fig('map_adm_fsl_iter', '<b>Pannello delle convenzioni</b> (<code>admin/fsl.php?tab=iter</code>): le fasi corrispondono agli stati.'))
        + gal(fig('map_adm_fsl_scuola', '<b>Scheda della scuola</b> (<code>tab=scuola</code>): i cinque passi dello <code>stato_iter</code>.'),
              fig('map_pub_verbali_sola_lettura', '<b><code>verbali.php</code></b> in sola lettura (viste «Per corso» e «Cerca» disegnate da JavaScript su un array JSON).'))
    )))

    # 11 -----------------------------------------------------------------------------------------------
    S.append(('documenti', 'Documenti e firme', sezione('documenti', '11', 'Documenti, PDF, Office e firme digitali',
        'Nessuna libreria esterna: PDF, DOCX e XLSX sono costruiti dal codice. Una scelta di portabilità (niente Composer in produzione) e di controllo sui formati richiesti dagli uffici.',
        tab(['Componente', 'Cosa fa'], [
            ['<code>Pdf\\Documento</code> (alias <code>PdfSemplice</code>)', 'Generatore PDF: pagine, paragrafi, tabelle, fasce, riquadri, font TrueType (<code>TrueType</code>). Usato per domande in <b>PDF/A</b> (<code>DomandaPdfa</code>), attestati, estratti dei verbali.'],
            ['<code>Pdf\\FirmePades</code> / <code>VerificaFirme</code>', 'Legge le firme PAdES di un PDF (ByteRange, <code>/Contents</code>, catena CMS) e verifica integrità e titolare; usata da convenzioni, lettere di tutorato, verbali.'],
            ['<code>Tutorato\\FirmaRemotaAruba</code>', 'Firma remota (ArubaSignService SOAP) con utente, password e OTP; <code>disponibile()</code> dipende dalle variabili <code>ARUBA_*</code> nel <code>.env</code>.'],
            ['<code>Pdf\\TestoPdf</code>', 'Estrae il testo da PDF digitali per la ricerca nell\'archivio verbali.'],
            ['<code>Documenti\\Word</code>', 'Costruisce <code>.docx</code> (ZIP di XML) partendo dai modelli in <code>modelli_documenti/</code> e <code>assets/modelli/</code> (segnaposto sostituiti); usata da Convenzione, Allegato A (<code>AllegatoAWord</code>), verbali (<code>EsportazioneVerbale</code>), lettere di incarico.'],
            ['<code>Documenti\\Excel</code>', 'Costruisce <code>.xlsx</code> da array di fogli (report, esportazioni).'],
            ['<code>Storage\\Upload</code>', 'Salvataggio di file caricati: estensione e MIME ammessi, nome casuale, <code>cartellaProtetta()</code> scrive un <code>.htaccess</code> che nega il web.'],
            ['<code>Sicurezza\\Cifratura</code>', 'Cifra i dati sensibili (IBAN) con la frase <code>CIFRATURA_CHIAVE</code>; <code>cifra()</code>/<code>decifra()</code>.'],
            ['<code>Audit\\AuditLog</code> + <code>registra_log_audit()</code>', 'Traccia delle azioni di gestione in <code>log_attivita</code>.'],
        ])
        + '<div class="info">I file riservati (convenzioni firmate, lettere, verbali, allegati) stanno in <code>uploads/</code> con <code>.htaccess</code> che nega l\'accesso: si scaricano solo passando da una pagina PHP che controlla il permesso (<code>invia_file_scaricabile()</code> → <code>Download::invia()</code>).</div>'
    )))

    # 12 -----------------------------------------------------------------------------------------------
    S.append(('email', 'Email', sezione('email', '12', 'Email',
        'Un\'interfaccia (<code>Mailer</code>) e due implementazioni: SMTP e <code>mail()</code>.',
        cod('''interface Mailer {
    public function invia(string $a, string $oggetto, string $corpoHtml, ?string $colore = null, array $allegati = []): bool;
    public function ultimoErrore(): string;
}''')
        + '<ul><li><code>SmtpMailer</code>: STARTTLS/SSL, autenticazione, controllo di ogni risposta del server, registro in <code>log_email</code> (accettata/rifiutata). Password SMTP cifrate nel database.</li>'
          '<li><code>MailerDaFunzione</code>: usa la funzione <code>inviaNotificaEmail()</code> (ambiente di prova e fallback a <code>mail()</code>).</li>'
          '<li><code>ImpaginatoreEmail</code>: layout HTML con il colore dell\'area e testo a contrasto calcolato.</li>'
          '<li><b>I testi stanno nel catalogo</b> (<code>src/Email/Catalogo*.php</code>: un modello per ogni email, con gruppo, quando parte, a chi, segnaposto e testo originale) e si modificano dalla pagina <code>admin/email.php</code> (Testi delle email); la tabella <code>email_modelli</code> tiene solo le modifiche. <code>ModelliEmail</code> compone oggetto e testo (formato semplice con <code>{{segnaposto}}</code> sempre ripuliti, oppure HTML con <code>{NOME}</code> per le email degli eventi) e <code>InvioEmail</code> le spedisce con il colore del modulo.</li>'
          '<li>Le classi <code>Notifiche*</code> e i servizi preparano i dati e chiamano <code>InvioEmail</code>; nessuna classe scrive il testo di un\'email (lo controlla il test <code>NessunaEmailScrittaNelCodiceTest</code>). Le email non bloccano la pratica se falliscono (errore registrato).</li></ul>'
    )))

    # 13 -----------------------------------------------------------------------------------------------
    S.append(('assistenza', 'Assistenza e Didacticus', sezione('assistenza', '13', 'Assistenza, notifiche push e Didacticus',
        'Chat di assistenza con operatori (<code>src/Assistenza/</code>), notifiche a finestrella (Web Push in PHP puro, <code>src/Assistenza/Push/</code>) e l\'assistente virtuale a regole (<code>src/Assistenza/Didacticus/</code>). Nessun servizio esterno, nessuna libreria da installare. Schema v86–v89.',
        '<h3>Dati</h3>'
        + tab(['Tabella', 'Contenuto'], [
            ['<code>assistenza_conversazioni</code>', 'una chat: canale (<code>utente</code> con login / <code>esterno</code> con link personale <code>token</code>), argomento, contesto (pratica/prenotazione), stato <code>bot → nuova → assegnata → chiusa</code>, operatore, tempi (<code>prima_risposta_il</code>, <code>passata_il</code>), <code>risolta_da_bot</code>, <code>bot_mancate</code>, valutazione, presenze e orari degli avvisi'],
            ['<code>assistenza_messaggi</code>', 'messaggi (<code>da</code> utente · operatore · bot · sistema; <code>tipo</code> messaggio · nota interna), <code>extra</code> JSON per link, allegati e suggerimenti di Didacticus'],
            ['<code>push_sottoscrizioni</code>', 'i recapiti dei browser (endpoint unico per impronta SHA-256, chiavi, ruolo, preferenze: silenzia, solo le mie chat)'],
            ['<code>didacticus_voci</code>', 'la base di conoscenza: argomento, titolo, domande, parole chiave, risposta, link e allegati (JSON), solo_login, proponi_operatore, attiva, contatori usi/utile/non_utile'],
            ['<code>didacticus_mancate</code>', 'domande senza risposta o la cui risposta non è bastata (da trasformare in voci)'],
            ['<code>didacticus_pagine</code>', 'v100: pagine pubbliche di unical.it (solo https) lette ogni settimana, con titolo, testo, esito e data dell\'ultima lettura: fonti del modello linguistico'],
            ['<code>didacticus_vettori</code>', 'v101: un vettore di significato per voce (numeri a 32 bit in base64) con l\'impronta del testo da cui è stato calcolato; si ricalcola solo se la voce cambia'],
            ['<code>impostazioni_sistema</code>', 'colonne <code>assistenza_attiva</code>, <code>assistenza_orari</code> (JSON giorno → fasce) e <code>didacticus_*</code> (acceso, saluto, soglia, avatar, seminato)'],
        ])
        + '<h3>Classi</h3>'
        + tab(['Classe', 'Compito'], [
            ['<code>ServizioAssistenza</code>', 'apre le chat (<code>apriDaUtente</code>, <code>apriDaEsterno</code>), scrive, prende in carico, passa, chiude, riapre, valuta; gestisce Didacticus (<code>rispondiBot</code>, <code>passaAOperatore</code>, <code>azioneBot</code>); limiti anti-abuso (per IP, email, utente, messaggi in 10 minuti)'],
            ['<code>ConversazioniRepository</code>, <code>MessaggiAssistenzaRepository</code>', 'accesso ai dati; gli orari arrivano dal servizio (<code>Orologio</code> iniettato), mai da <code>NOW()</code>'],
            ['<code>OperatoriAssistenza</code>', 'operatori = persone dell\'ufficio fisso <code>assistenza</code> + amministratori; destinatari degli avvisi (se l\'ufficio è vuoto, gli amministratori)'],
            ['<code>NotificheAssistenza</code>', 'email (modelli in <code>CatalogoAssistenza</code>) e finestrelle; un guasto delle notifiche non ferma mai la chat'],
            ['<code>ChatApi</code>', 'le risposte JSON delle pagine (casella, chat dell\'operatore, chat di chi chiede aiuto): permessi controllati dall\'endpoint, dati qui'],
            ['<code>OrariAssistenza</code>', 'servizio acceso/spento e fasce orarie («09:00-13:00, 14:00-16:00»), avviso fuori orario'],
            ['<code>ConservazioneAssistenza</code>', 'dopo 12 mesi (<code>CONSERVAZIONE_ASSISTENZA_MESI</code>) cancella messaggi, recapiti push e domande senza risposta; nome alle iniziali, email e IP svuotati, link personale invalidato'],
            ['<code>Significato</code>, <code>VettoriRepository</code>, <code>ServizioSignificato</code>', 'embedding (Ollama <code>/api/embed</code>, modello <code>bge-m3</code>) per trovare le voci per significato; spento senza <code>EMB_URL</code>; vettori calcolati dal pannello («Aggiorna ora») e dal cron, mai durante una chat; coseno tra domanda e voci'],
            ['<code>ModelloLinguistico</code>', 'facoltativo e spento di partenza (<code>LLM_URL</code>): modello che scrive la risposta con le sole fonti (voci e brani di pagine di Ateneo), un calcolo alla volta (blocco su file), timeout 45 s, <code>NON_SO</code> e fallback a regole; risposta segnalata come «preparata automaticamente»'],
            ['<code>PagineRepository</code>, <code>LettorePagine</code>, <code>ServizioPagine</code>, <code>RicercaPagine</code>', 'pagine di Ateneo come fonti: solo https del dominio unical.it (niente porte né credenziali), testo estratto senza menu e script, brani di circa 600 caratteri scelti per vicinanza alla domanda; rilettura settimanale dal cron'],
            ['<code>StatisticheAssistenza</code>, <code>Contesti</code>', 'numeri per periodo (argomento, operatore, tempi, utilità, risolte da Didacticus); pratica o prenotazione collegata, solo se della persona'],
        ])
        + '<h3>Notifiche push (Web Push)</h3>'
        + '<p>Scritte a mano con OpenSSL, senza Composer: <code>CifraturaPush</code> cifra il messaggio come vuole il browser (RFC 8291, <code>aes128gcm</code>; verificata contro il <b>vettore di prova della RFC</b>), <code>ChiaviVapid</code> firma la richiesta (ES256, RFC 8292) con le chiavi del <code>.env</code> (<code>PUSH_VAPID_PUBLIC/PRIVATE/SUBJECT</code>, create una volta con <code>strumenti/genera_chiavi_push.php</code>), <code>InvioPush</code> la spedisce con curl (<code>ClienteHttp</code> è un\'interfaccia: nelle prove un finto). Un 404/410 elimina il recapito; dopo 5 errori di fila pure. Il <code>sw.js</code> riceve la notifica e al clic apre la chat giusta. Agli operatori arriva chi scrive e le prime parole; a chi chiede aiuto solo «hai una risposta», senza testo. Senza chiavi VAPID tutto resta com\'è e valgono le email.</p>'
        + '<h3>Didacticus: come decide</h3>'
        + '<p><code>Motore</code> confronta la domanda con le voci: normalizza (minuscole, senza accenti, parole vuote tolte), riduce ogni parola a una radice (senza vocale finale, al massimo 5 lettere) e calcola un punteggio 0–100 come somiglianza pesata (peso = rarità della parola tra le voci) con ogni domanda della voce; una <b>parola chiave</b> contenuta nella domanda porta la voce in alto in proporzione a quanta parte della domanda copre; l\'argomento scelto dà un piccolo vantaggio. <code>ServizioDidacticus</code> traduce i punteggi: ≥ soglia (predefinita 60, regolabile 20–90) <b>risposta</b> con link, allegati e pulsanti; fino a 12 punti sotto la soglia <b>suggerimenti</b>; altrimenti <b>nessuna</b> (conta una domanda a vuoto: alla seconda di fila la chat passa a un operatore). Saluti, ringraziamenti e «voglio un operatore» sono riconosciuti a parte; una voce riservata a chi accede invita gli esterni ad accedere. Non genera testo e non chiama servizi esterni: dice solo quello che è scritto nelle voci.</p>'
        + cod("""stato «bot»  --  la persona scrive -->  Didacticus risponde (voce, suggerimenti o «non so»)
   |-- «Sì, grazie» ................................. chiusa, risolta_da_bot = 1
   |-- «Non mi basta» / «operatore» / 2 volte «non so» -> stato «nuova», passata_il, nota interna, email + finestrella agli operatori
   '-- un operatore risponde o «Prendo io» ............ stato «assegnata» (passata_il se veniva da Didacticus)""")
        + '<h3>Significato e modello linguistico (facoltativi)</h3>'
        + '<p>Dopo il <code>Motore</code>, se le regole non sono sicure (punteggio sotto la soglia) o lo sono poco (sotto 75), <code>ServizioSignificato</code> confronta la domanda con i vettori delle voci: con somiglianza da 0,62 sceglie la voce (da 0,55 se deve correggere una scelta debole delle regole, che resta tra i «vedi anche»), da 0,50 propone «forse intendevi…». Le soglie si regolano con <code>EMB_SOGLIA</code> e <code>EMB_SOGLIA_SUGGERISCI</code>. Il testo mostrato è sempre quello della voce. Su 20 domande parafrasate trova la voce giusta in 15 casi contro 7 delle sole regole. Se resta incerto e <code>LLM_URL</code> è impostato, <code>ModelloLinguistico</code> può scrivere una risposta con le sole fonti; in prova (Llama 3.2 3B e 1B, Qwen, Phi-4-mini) sul server a 4 core senza GPU è troppo lento e a volte inventa, quindi resta spento.</p>'
        + cod("""domanda --> Motore (regole, punteggio 0-100)
   |-- sicura (>= soglia e >= 75) ......................... risposta della voce
   |-- incerta o debole --> ServizioSignificato::cerca() --> voce per somiglianza (0,62 / 0,55) o suggerimenti (0,50)
   |-- ancora incerta e LLM_URL impostato ................. ModelloLinguistico con le sole fonti (spento di partenza)
   '-- niente di tutto questo ............................. suggerimenti a regole o «non so» (domanda senza risposta)""")
        + '<h3>Pagine e file</h3>'
        + tab(['File', 'Ruolo'], [
            ['<code>assistenza.php</code>, <code>assistenza_ajax.php</code>', 'pagina pubblica (anche come finestrella <code>?pannello=1</code>) e risposte JSON; chat di chi ha il login o, per gli esterni, del link <code>?t=</code>'],
            ['<code>admin/assistenza.php</code>, <code>admin/assistenza_ajax.php</code>, <code>admin/push_ajax.php</code>', 'casella e chat degli operatori, statistiche, orari; notifiche del browser'],
            ['<code>admin/didacticus.php</code>', 'voci (con link e allegati), domande senza risposta, prova (con la classifica per significato), pagine di Ateneo, impostazioni (stato del significato, «Aggiorna ora»); esportazione Excel/PDF/CSV e importazione CSV'],
            ['<code>didacticus_file.php</code>', 'immagine di profilo e allegati (cartella <code>uploads/didacticus/</code> chiusa al web, scaricabili solo da qui e solo per le voci attive)'],
            ['Chat che parte da Didacticus', '<code>assistenza.php</code> in modalità «avvio» (<code>data-avvio</code>): il saluto è locale finché la persona non sceglie un argomento o scrive; allora <code>assistenza_ajax.php?azione=avvia</code> crea la conversazione (<code>apriDaUtente</code>/<code>apriDaEsterno</code> con <code>$avvio</code> = <code>argomento</code> o <code>testo</code>) salvando il saluto con il nome come primo messaggio. Chi non ha l\'accesso passa da nome, email e anti-robot (<code>$_SESSION["assistenza_ospite"]</code>). <code>?modulo=1</code> riporta il modulo classico'],
            ['Operatore per corso di laurea', '<code>ServizioAssistenza::chiediCorso()</code> chiede il corso (pulsanti <code>bot_corso</code> o testo libero) se <code>OperatoriAssistenza::corsiSeguiti()</code> non è vuoto (corsi indicati nelle persone dell\'ufficio Assistenza, <code>uffici_persone.corsi</code>); <code>emailPerCorso()</code> sceglie i destinatari, con ripiego su tutti; la nota interna riporta il corso dichiarato'],
            ['<code>assets/js/assistenza-widget.js</code>', 'il pulsante di Didacticus (il gufo, immagine da <code>didacticus_file.php?avatar=1</code>) con la riga «Ti serve aiuto?»: compare dopo 1,8 s, sparisce dopo 12 s (non torna da sola nella stessa visita, <code>sessionStorage</code>) e si riapre sempre al passaggio del mouse o con la tastiera sul pulsante; la finestrella è la pagina <code>assistenza.php?pannello=1</code> in un iframe'],
            ['<code>assets/js/assistenza-*.js</code>', 'chat, casella degli operatori, finestrella, iscrizione push (testo sempre via <code>textContent</code>, mai HTML; il Service Worker mette i JS in cache: si cambia <code>?v=</code> a ogni modifica)'],
        ])
        + '<h3>Prove</h3><p><code>strumenti/prove/oop/assistenza.php</code> (chat, operatori, limiti, orari, conservazione), <code>assistenza_push.php</code> e <code>assistenza_push_db.php</code> (cifratura contro la RFC, firma, invio, iscrizioni, preferenze, integrazione con la chat), <code>assistenza_didacticus.php</code> (motore su domande vere, risposte, passaggi all\'operatore, domande senza risposta, statistiche). Scenario di dimostrazione e schermate: <code>strumenti/locale/scenario_assistenza.php</code>, <code>schermate_assistenza.php</code>.</p>'
    )))

    # 13a ----------------------------------------------------------------------------------------------
    S.append(('disponibilita', 'Disponibilità dei docenti', sezione('disponibilita', '13a', 'Disponibilità dei docenti per tesi e tirocini',
        'I docenti indicano dalla loro Area personale se accettano nuovi tesisti e tirocinanti; due pagine pubbliche mostrano i posti con un semaforo che si calcola da solo (<code>src/Disponibilita/</code>).',
        tab(['Elemento', 'Compito'], [
            ['<code>disponibilita_docenti</code>', 'una riga per docente e per tipo (<code>tesi</code>, <code>tirocinio</code>): accetta, posti, occupati, corsi (codici dall\'anagrafe di Ateneo), temi, attività, obiettivi, sede, note, <code>aggiornato_il</code> (v98)'],
            ['<code>DisponibilitaRepository</code>', 'lettura e salvataggio per utente e tipo, elenco pubblico dei docenti che accettano; valida posti e corsi'],
            ['<code>Semaforo::di()</code>', 'verde con almeno due posti liberi, arancione con uno, rosso a zero, grigio se non accetta o se l\'ultimo aggiornamento ha più di 90 giorni («da riconfermare»); funzione pura, provata con l\'orologio iniettato'],
            ['<code>la_mia_disponibilita.php</code>', 'la pagina del docente: tesi e tirocinio, «Togli dalla pagina pubblica»; tessera «La mia disponibilità» nell\'Area personale'],
            ['<code>disponibilita_tesi.php</code>, <code>disponibilita_tirocini.php</code>', 'pagine pubbliche con filtri per ricerca, corso di laurea e «solo con posti», e «Scrivi al docente»'],
        ])
        + gal(fig('map_pub_disponibilita_tesi', '<b>Disponibilità per la tesi</b> · <code>disponibilita_tesi.php</code>.'),
              fig('map_doc_disponibilita', '<b>La pagina del docente</b> · <code>la_mia_disponibilita.php</code>.'))
    )))

    # 13b ----------------------------------------------------------------------------------------------
    S.append(('area', 'Area personale per profilo', sezione('area', '13b', 'Area personale per profilo',
        'L\'area personale mostra i riquadri giusti a chi ha accesso: studente, docente, direttore, personale e uffici, gestore, amministratore, esterno (una persona può averne più d\'uno). I riquadri e il loro ordine li sceglie l\'amministratore da pannello (<em>Sito › Area personale</em>). Schema v93–v95. L\'area è un cruscotto a tessere: ogni tessera è un argomento con le novità in evidenza e porta alla pagina specifica.',
        '<h3>Dati</h3>'
        + tab(['Tabella', 'Contenuto'], [
            ['<code>area_riquadri</code>', 'un riquadro per riga: <code>chiave</code> (vuota per quelli scritti dall\'amministratore, unica per quelli di sistema), titolo, testo, icona, colore, fino a due pulsanti, <code>profili</code> (elenco separato da virgole), <code>ordine</code>, <code>attivo</code>. I riquadri di sistema mancanti si seminano alla migrazione; quelli già presenti (anche spenti o cambiati) non si toccano'],
        ])
        + '<h3>Classi (<code>src/Portale/Area/</code>)</h3>'
        + tab(['Classe', 'Compito'], [
            ['<code>Profili</code>', 'i sette profili, i nomi e le icone; <code>da()</code> e <code>inCsv()</code> per l\'elenco salvato'],
            ['<code>ProfiliPersona</code>', 'di quali profili fa parte una persona: ruolo, matricola, anagrafe di Ateneo (gruppo), uffici, Direzione, abilitazioni; chi non rientra in nessuno è «esterno»'],
            ['<code>CatalogoRiquadri</code>', 'i riquadri di sistema: tipo <em>speciale</em> (li disegna il codice con i dati della persona) o <em>scheda</em> (titolo, testo e due pulsanti modificabili), profili e ordine di partenza'],
            ['<code>RiquadriRepository</code>', 'elenco per profilo, salvataggio con controlli (titolo, testo, indirizzi, icona, colore), interruttore, su/giù, eliminazione dei soli riquadri personalizzati'],
        ])
        + '<h3>Pagine e file</h3>'
        + tab(['File', 'Ruolo'], [
            ['<code>area_personale.php</code>', 'il cruscotto: testata con i profili, filtro <code>?p=</code> per chi ha più profili, «vedi come» <code>?come=</code> per l\'amministratore, poi la griglia delle tessere nell\'ordine scelto. I vecchi indirizzi <code>?conferma_posto=</code> (link nelle email) e <code>?cancella_prenotazione=</code> rimandano a <code>miei_eventi.php</code>'],
            ['<code>inc/area/&lt;chiave&gt;.php</code>', 'una tessera per riquadro di sistema (da_fare a tutta larghezza, cruscotto_direttore, direttore_assente, pratiche, assistenza, tutorato_mio, tutorato_registro, tutorato_pagamenti, ricevimento, risorse_responsabile, risorse_prenotate, eventi, profilo, guida); <code>_tessera.php</code> è la funzione <code>area_tessera()</code> (titolo, riga di stato, novità in rosso, pulsanti); <code>_generico.php</code> disegna le schede e i riquadri scritti dall\'amministratore; <code>_tutorato_tappe.php</code> le tappe del tutor'],
            ['<code>miei_eventi.php</code>', 'prenotazioni in programma (modifica, annulla, chat, posto liberato), storico e attestati, programma FSL, sondaggi, aule e appuntamenti prenotati, scanner QR'],
            ['<code>profilo.php</code>', 'dati personali, scheda di Ateneo ed email modificabili'],
            ['<code>il_mio_tutorato.php</code>', 'a che punto è ogni incarico del tutor e i suoi documenti'],
            ['<code>admin/area_personale.php</code>', 'pannello: profili con il numero di riquadri, elenco in ordine, Spegni/Accendi, su/giù, modifica, nuovo riquadro, elimina (solo i personalizzati)'],
        ])
        + '<h3>Regole</h3><p>I tirocini non si gestiscono nel portale ma sulla piattaforma TSP 2.0: nell\'area personale c\'è solo la scheda con la guida (<code>percorso_tirocini.php</code>) e il collegamento. I pagamenti del tutorato sono spenti (riquadro «Ufficio pagamenti del tutorato» spento, e le tappe dei pagamenti spariscono da «Il mio tutorato»): si riaccendono dal pannello.</p>'
        + '<h3>Prove</h3><p><code>strumenti/prove/oop/area_personale.php</code> (profili, catalogo, archivio, pannello, file dei riquadri).</p>'
    )))

    # 13c ----------------------------------------------------------------------------------------------
    S.append(('area', 'Calendario, ricerca, stile comune', sezione('area', '13c', 'Calendario personale, ricerca unica, stile comune',
        'Quattro pezzi comuni a tutto il portale: il <b>calendario personale</b> (eventi, aule, ricevimenti, scadenze con il file .ics e l\'abbonamento del telefono), il <b>riepilogo settimanale con l\'agenda</b> (lo accende l\'amministratore), la <b>ricerca unica</b> raggruppata e la <b>libreria di stile</b> (stati, stati vuoti, tema scuro). Schema v95.',
        '<h3>Dati</h3>'
        + tab(['Tabella / colonna', 'Contenuto'], [
            ['<code>calendario_token</code>', 'il codice segreto di ogni persona (40 caratteri) con cui il telefono legge <code>calendario_ics.php?t=…</code>; si rigenera dalla pagina e il vecchio link smette di funzionare'],
            ['<code>impostazioni_sistema.riepilogo_agenda*</code>', 'interruttore del riepilogo con l\'agenda (spento di partenza), chi e quando lo ha acceso'],
        ])
        + '<h3>Classi (<code>src/Portale/</code>)</h3>'
        + tab(['Classe', 'Compito'], [
            ['<code>CalendarioPersonale</code>', '<code>voci()</code>: eventi prenotati (e lista d\'attesa), aule e laboratori, ricevimento di chi ha uno sportello, scadenze (posto da confermare), in ordine di tempo; <code>ics()</code>; il token di abbonamento; <code>emailConAgenda()</code> per il riepilogo'],
            ['<code>RiepilogoAgenda</code>', 'l\'interruttore (Gestione del portale → Email e promemoria): da acceso, <code>RiepilogoPersone</code> aggiunge l\'agenda dei sette giorni all\'email «Da fare per te» e la manda anche a chi ha solo qualcosa in agenda (testo «La tua settimana», modello <code>riepilogo_agenda</code>)'],
            ['<code>RicercaGlobale</code>', 'eventi e progetti, notizie e pagine (<code>ComunicazioniRepository::cerca</code>), guide «Come si fa», docenti, risposte di Didacticus (le riservate solo dopo l\'accesso); ogni parola deve comparire; estratto senza HTML'],
        ])
        + '<h3>Pagine e file</h3>'
        + tab(['File', 'Ruolo'], [
            ['<code>calendario.php</code>', 'il calendario dei prossimi 90 giorni a giorni, con filtri per tipo, il file .ics, il link di abbonamento (webcal, Google Calendar) e «Nuovo link»'],
            ['<code>calendario_ics.php</code>', 'il file .ics: scaricato da chi ha fatto l\'accesso oppure letto dal telefono con <code>?t=</code> (404 se il codice è sbagliato)'],
            ['<code>ricerca.php</code>', 'la ricerca unica: gruppi con collegamenti rapidi, stato vuoto con il pulsante per Didacticus'],
            ['<code>assets/css/portale-ui.css</code> · <code>inc/ui.php</code>', 'stile comune: i cinque colori degli stati (anche per i badge di Bootstrap), tessere, testata, numeri, stato vuoto, tema scuro (<code>html[data-tema="scuro"]</code> sulle pagine con <code>body.ui-scuro-ok</code>, scelta in <code>localStorage</code> <code>tema_dibest</code>); funzioni <code>ui_stato</code>, <code>ui_vuoto</code>, <code>ui_intestazione</code>, <code>ui_tema_script</code>, <code>ui_tema_pulsante</code>'],
            ['<code>manifest.json</code> · <code>sw.js</code> · <code>footer.php</code>', 'app installabile: scorciatoie (area personale, calendario, eventi, cerca), icone «any» e «maskable», pulsante «Installa l\'app» (<code>beforeinstallprompt</code>; su iPhone/iPad spiega «Aggiungi alla schermata Home»)'],
        ])
        + '<h3>Regole</h3><p>La testata del sito (banner e menu) non cambia. Il tema scuro vale solo per l\'area personale e le sue pagine (cruscotto, eventi, profilo, tutorato, calendario). Il riepilogo con l\'agenda è spento finché l\'amministratore non lo accende; chi riceve l\'email può disiscriversi dal link (lo stesso del riepilogo «Da fare per te»).</p>'
        + gal(fig('map_calendario', '<b>Calendario personale</b> · <code>calendario.php</code> → <code>CalendarioPersonale</code>, <code>calendario_ics.php</code>.'),
              fig('map_pub_ricerca_gruppi', '<b>Ricerca unica</b> · <code>ricerca.php</code> → <code>RicercaGlobale</code>.'),
              fig('map_area_studente_scuro', '<b>Tema scuro</b> · <code>assets/css/portale-ui.css</code>, <code>html[data-tema="scuro"]</code>.'))
        + '<h3>Prove</h3><p><code>strumenti/prove/oop/calendario.php</code> e <code>pagine_calendario.php</code> (voci, .ics, abbonamento, riepilogo, ricerca, libreria di stile, pagine, manifest).</p>'
    )))

    # 14 -----------------------------------------------------------------------------------------------
    # 13d ----------------------------------------------------------------------------------------------
    # Recuperata il 23/11/2026 dall'HTML committato del 20/11: il generatore l'aveva persa.
    S.append(('ateneo', 'Insegnamenti e corsi di st', sezione('ateneo', '13d', 'Insegnamenti e corsi di studio di Ateneo',
        "L'archivio di tutti gli insegnamenti dell'Ateneo (dall'anno accademico 2026/27 fino a cinque coorti avanti, quanto le API hanno già pubblicato) e dei corsi di studio di ogni dipartimento, consultabile da <code>admin/anagrafe_personale.php</code> (viste <code>insegnamenti</code> e <code>corsi</code>). Schema v118–v120.",
        '<h3>Dati</h3><table><thead><tr><th>Tabella</th><th>Contenuto</th></tr></thead><tbody><tr><td><code>insegnamenti</code></td><td>gli insegnamenti usati dal portale (scelta nelle attività), con il livello integrato / modulo</td></tr><tr><td><code>ateneo_insegnamenti</code></td><td>il catalogo di Ateneo: codice, descrizione, settore, CFU, semestre, docente, corso, anno di erogazione, codice dell\'integrato (<code>codice_integrato</code>, v120)</td></tr><tr><td><code>ateneo_cds</code></td><td>i corsi di studio di tutti i dipartimenti, con tipo, classe, dipartimento e anni</td></tr></tbody></table><h3>Classi</h3><table><thead><tr><th>Classe</th><th>Ruolo</th></tr></thead><tbody><tr><td><code>AttivitaAteneo</code></td><td>legge le attività didattiche dalle API; <code>codiceRadice</code> estrae il codice dell\'integrato da <code>StudyActivityRoot.StudyActivityCod</code></td></tr><tr><td><code>ClientApiAteneoMultiplo</code></td><td>richieste alle API per più dipartimenti e coorti; un 404 vale «nessun dato» (<code>[]</code>)</td></tr><tr><td><code>InsegnamentoRepository</code></td><td>salvataggio e ricerca (<code>filtroLivello</code>), docente degli integrati, <code>salvaCodiceIntegrato</code></td></tr><tr><td><code>CorsoRepository::ateneo</code></td><td>corsi di Ateneo con i filtri; <code>dipartimentiAteneo()</code> e <code>tipiAteneo()</code> riempiono i menu a tendina</td></tr><tr><td><code>ServizioCatalogo::completaDettagli</code></td><td>scarica un po\' alla volta le schede (CFU, codice dell\'integrato), anche dal cron giornaliero</td></tr><tr><td><code>SincronizzazioneAnagrafe</code></td><td>aggiornamento completo di strutture, docenti, corsi e insegnamenti</td></tr></tbody></table><h3>Regole</h3><ul><li>Anno di erogazione = coorte + anno − 1; il primo anno dell\'archivio è <code>PRIMO_ANNO_ARCHIVIO = 2026</code>.</li><li>Il codice dell\'integrato non è nell\'elenco delle API ma nel dettaglio di ogni modulo: arriva col tempo dal cron; se manca si può inserire a mano.</li><li>Nella tabella il «modulo di…» sta sotto il nome e compare il docente; la tabella è più stretta per non uscire dalla pagina.</li><li>Nei corsi, i filtri sono dipartimento, tipo e codice/nome; la query unisce il catalogo di Ateneo ai corsi già gestiti dal portale, uno per codice.</li></ul><div class="gal"><figure><img src="img/map_adm_anagrafe_insegnamenti.jpg" loading="lazy" alt="Insegnamenti di Ateneo · anagrafe_personale.php?vista=insegnamenti."><figcaption><b>Insegnamenti</b> · <code>anagrafe_personale.php?vista=insegnamenti</code>.</figcaption></figure><figure><img src="img/map_adm_anagrafe_corsi.jpg" loading="lazy" alt="Corsi di studio di Ateneo · anagrafe_personale.php?vista=corsi."><figcaption><b>Corsi di studio</b> · <code>anagrafe_personale.php?vista=corsi</code>.</figcaption></figure></div><h3>Prove</h3><p>I file in <code>strumenti/prove/oop/</code> sull\'archivio, sull\'integrato con i suoi moduli e sui filtri dei corsi.</p>'
    )))

    # 13e ----------------------------------------------------------------------------------------------
    # Recuperata il 23/11/2026 dall'HTML committato del 20/11: il generatore l'aveva persa.
    S.append(('promemoria', "Promemoria dell'amministra", sezione('promemoria', '13e', "Promemoria dell'amministratore",
        "Per ogni passo di un percorso l'amministratore ha un pulsante che scrive a una sola persona, con il link diretto al passo da fare. Ogni invio è scritto in <code>registra_log_audit</code>.",
        "<table><thead><tr><th>Dove</th><th>Classe e metodo</th><th>A chi scrive</th></tr></thead><tbody><tr><td>FSL · elenco studenti</td><td><code>ServizioDocumentiClasse::ricordaElenco</code>, <code>ricordaElencoATutti</code></td><td>al docente referente della prenotazione (<code>elenco_studenti.php?code=…</code>); mai prima di <code>ORE_TRA_RICORDI</code> = 24 ore dall'ultimo (<code>doc_promemoria_il</code>)</td></tr><tr><td>FSL · valutazione</td><td><code>ServizioValutazioniFsl::ricorda</code></td><td>al docente che non ha compilato la scheda</td></tr><tr><td>Didattica · pratica</td><td><code>RicordaPratica::ricorda</code></td><td>allo studente (integrazioni), al firmatario o al Direttore (<code>FirmaPratica::ricorda</code>), ai docenti da confermare (<code>ServizioConfermeDocenti::ricorda</code>)</td></tr></tbody></table><h3>Regole</h3><ul><li>Lo stato «documenti completi» di <code>DocumentiClasse</code> dipende solo dall'elenco: l'autorizzazione della scuola è facoltativa.</li><li>I pulsanti sono POST con token CSRF; l'esito torna come messaggio nella pagina (ad esempio «Promemoria mandato a…»).</li><li>Una pratica che non aspetta nessuno risponde con un errore chiaro e non manda nulla.</li></ul><h3>Prove</h3><p>I file in <code>strumenti/prove/oop/</code> sui promemoria dei documenti di classe, della valutazione e della pratica.</p>"
    )))

    # 13f ----------------------------------------------------------------------------------------------
    # Recuperata il 23/11/2026 dall'HTML committato del 20/11: il generatore l'aveva persa.
    S.append(('titulus', 'Protocollo Titulus (SOAP)', sezione('titulus', '13f', 'Protocollo Titulus (SOAP)',
        'Il portale protocolla in <b>Titulus</b> (servizio SOAP <code>Titulus4</code>, Apache Axis 1.4, WSDL rpc/encoded, autenticazione HTTP Basic) attraverso un pacchetto PHP autonomo, <code>Unical\\Titulus</code>, e uno strato del portale, <code>App\\Protocollo</code>, che mette le regole, la password e il registro.',
        '<h3>Due strati, un solo confine</h3>\n<table><thead><tr><th>Strato</th><th>Dove</th><th>Cosa sa</th></tr></thead><tbody>\n<tr><td><b>Pacchetto</b> <code>Unical\\Titulus</code></td><td><code>protocollo/php-titulus-utility/</code> (<code>src/</code>, <code>tests/</code>, <code>composer.json</code>, <code>autoload.php</code>)</td><td>XML, SOAP, controlli sul documento, esiti, catalogo di titolario e uffici. <b>Non dipende</b> da framework né da database: pari del connettore Python <code>titulus_utility</code> della cartella <code>protocollo/</code>.</td></tr>\n<tr><td><b>Portale</b> <code>App\\Protocollo</code></td><td><code>src/Protocollo/</code>, <code>admin/protocollo.php</code></td><td>Regole per richiesta, password cifrata, registro nel database, pagine di configurazione.</td></tr>\n<tr><td><b>Chi protocolla</b></td><td><code>Protocolla*InTitulus</code> nei moduli: <code>Didattica</code>, <code>Tutorato</code>, <code>Fsl</code>, <code>ConvenzioniTesi</code></td><td>Come ricavare oggetto, PDF, allegati e mittente da una pratica, una lettera o una convenzione, e cosa registrare quando arriva il numero.</td></tr>\n</tbody></table>\n<h3>Il pacchetto <code>Unical\\Titulus</code></h3>\n<table><thead><tr><th>Classe</th><th>A cosa serve</th></tr></thead><tbody>\n<tr><td><code>ServizioTitulus</code></td><td>Punto d\'ingresso: <code>protocolla()</code> (sceglie il verso dalla regola), <code>protocollaArrivo/Partenza/Interno</code>, <code>avviaIter</code>, <code>numero()</code> (mai due volte), <code>leggiNumero</code>, <code>provaConnessione</code>. <b>Non lancia eccezioni</b>: restituisce un <code>EsitoProtocollo</code> (<code>ok</code>, <code>messaggio</code> in italiano, <code>nrecord</code>, <code>numProt</code>, <code>url</code>).</td></tr>\n<tr><td><code>ConfigTitulus</code>, <code>ParametriProtocollo</code>, <code>DocumentoDaProtocollare</code>, <code>AllegatoTitulus</code></td><td>Dati immutabili: dove collegarsi (la password si passa a parte, mai in un log), come registrare (UO, RPA e <code>rpaMatricola</code>, titolario, fascicolo, OP, CC, repertorio) e cosa inviare.</td></tr>\n<tr><td><code>GeneratoreXml</code></td><td>XML del documento e della fascicolazione con DOM. Il responsabile è <code>rif_interno diritto="RPA"</code> con <code>nome_persona</code>, <code>nome_uff</code>, <code>cod_uff</code> e, se c\'è la matricola, <code>cod_persona</code>. Con un repertorio aggiunge <code>&lt;repertorio cod="…"&gt;</code> (massimo 15 caratteri).</td></tr>\n<tr><td><code>ClienteSoapTitulus</code> (<code>ClienteTitulus</code>, <code>FabbricaClienteTitulus</code>)</td><td>Chiamate <code>saveDocument</code>, <code>addInFolder</code>, <code>setWSUser</code>, <code>getRecordInfos</code>, <code>getDocumentURL</code>. Se il WSDL non si legge, rilegge l\'indirizzo e spiega la causa: spazi o apici nell\'indirizzo, 401/403 (utente o password), 404, risposta che non è un WSDL (manca <code>?wsdl</code>).</td></tr>\n<tr><td><code>CatalogoTitulus</code>, <code>Costanti</code></td><td>Titolario e uffici dell\'Ateneo (dal <code>conf.py</code> del connettore Python), tipi di documento (<code>arrivo</code>, <code>partenza</code>, <code>interno</code>) e azioni (<code>protocolla</code>, <code>iter</code>).</td></tr>\n<tr><td><code>RegistroInvii</code> (<code>RegistroInMemoria</code>, <code>RegistroSuFile</code>)</td><td>Dove ricordare ogni invio. Con un registro, <code>numero()</code> restituisce il numero già assegnato a quel riferimento (<code>rifTipo</code>, <code>rifId</code>) invece di protocollare di nuovo. Il portale usa <code>RegistroInviiPortale</code> (tabella <code>titulus_invii</code>).</td></tr>\n</tbody></table>\n<div class="nota"><b>Due trappole già pagate.</b> (1) <code>SoapClient</code> con <code>WSDL_CACHE_MEMORY</code> corrompe la richiesta e manda in segmentation fault PHP-FPM con il WSDL di Titulus4 (verificato su PHP 8.2 e 8.4): il client usa <code>WSDL_CACHE_NONE</code> e una prova del pacchetto controlla che nel sorgente non compaia mai l\'altra. (2) PHP associa gli argomenti di <code>__soapCall</code> ai nomi delle parti del WSDL <i>per posizione</i>, non per chiave.</div>\n<h3>Lo strato del portale: <code>App\\Protocollo</code></h3>\n<table><thead><tr><th>Classe</th><th>Ruolo</th></tr></thead><tbody>\n<tr><td><code>ServizioProtocollo</code></td><td><code>protocolla(RichiestaProtocollo)</code>: sceglie la regola (<code>regolaPer()</code> o <code>regolaId</code> esplicito), controlla (<code>controlla()</code>: regola presente e attiva, oggetto, UO e titolario, matricola se c\'è un fascicolo, voce d\'indice per l\'iter, dati di chi scrive secondo il verso), decifra la password e chiama <code>ServizioTitulus</code>. Ogni strada, anche l\'errore, finisce in <code>titulus_invii</code>. Ha anche <code>provaConnessione()</code> e <code>aggiornaNumero($invioId)</code> (rilegge il numero di un\'iter).</td></tr>\n<tr><td><code>InvioProtocollo</code></td><td>Facciata per i moduli: <code>disponibile($applicaA)</code> (collegamento acceso e regola attiva) e <code>numero($richiesta)</code> che restituisce <code>[\'numero\' =&gt; …, \'errore\' =&gt; …]</code>.</td></tr>\n<tr><td><code>RichiestaProtocollo</code></td><td>Oggetto valore: <code>applicaA</code> (contesto), <code>oggetto</code>, PDF principale, allegati, <code>esterno</code> (chi scrive o il destinatario), mittente interno, riferimento (<code>rifTipo</code>/<code>rifId</code>) per il «mai due volte».</td></tr>\n<tr><td><code>ConfiguratoreProtocollo</code></td><td>Salva collegamento (password con <code>Cifratura</code>, chiave <code>CIFRATURA_CHIAVE</code> del <code>.env</code>), regole (con CC), voci d\'indice e repertori, e valida i contesti.</td></tr>\n<tr><td><code>ProtocolloRepository</code></td><td>Accesso alle sei tabelle; <code>regolaPer($contesto)</code>.</td></tr>\n<tr><td><code>Costanti</code></td><td>Contesti, tipi e azioni.</td></tr>\n</tbody></table>\n<h3>Tabelle</h3>\n<table><thead><tr><th>Tabella</th><th>Contenuto</th></tr></thead><tbody>\n<tr><td><code>titulus_config</code></td><td>Una riga: <code>attivo</code>, <code>url_ws</code> (con <code>?wsdl</code>), <code>username</code>, <code>password_cifrata</code>, <code>aoo</code>, <code>agd</code>, autore, email di riserva, <code>fascicolazione_separata</code>, esito dell\'ultima prova.</td></tr>\n<tr><td><code>titulus_regole</code></td><td>Una regola per <code>applica_a</code>: verso, azione, UO, RPA (<code>rpa_nome</code>, <code>rpa_username</code>, <code>rpa_matricola</code>), titolario, fascicolo, OP, <code>voce_indice_id</code>, <code>repertorio_id</code>, <code>invia_email_rpa</code>.</td></tr>\n<tr><td><code>titulus_cc</code></td><td>Copie per conoscenza di una regola (<code>regola_id</code>, UO, nome, matricola).</td></tr>\n<tr><td><code>titulus_voci_indice</code>, <code>titulus_repertori</code></td><td>Voci per gli iter di approvazione; repertori con <code>codice</code> (max 15 caratteri) e nome.</td></tr>\n<tr><td><code>titulus_invii</code></td><td>Registro: regola, contesto, riferimento (<code>rif_tipo</code>, <code>rif_id</code>), oggetto, esito (<code>ok</code>/<code>errore</code>), <code>nrecord</code>, <code>num_prot</code>, <code>url</code>, <code>errore</code>, utente.</td></tr>\n</tbody></table>\n<h3>Quale regola vale: i contesti</h3>\n<p><code>regolaPer($contesto)</code> prova nell\'ordine: regola <b>dedicata</b> → regola del contesto «padre» → regola <b>generale</b>. Le regole disattivate si saltano (<code>attiva = 1</code>); se non ne resta nessuna l\'invio fallisce con «Nessuna regola di Titulus per questa richiesta».</p>\n<table><thead><tr><th>Contesto (<code>applica_a</code>)</th><th>Usato da</th></tr></thead><tbody>\n<tr><td><code>pratica:&lt;id modulo&gt;</code>, <code>pratica_uscita:&lt;id modulo&gt;</code></td><td><code>ProtocollaPraticaInTitulus</code>, <code>ProtocollaAttestatoInTitulus</code> (in uscita)</td></tr>\n<tr><td><code>convenzione_fsl</code>, <code>convenzione_fsl_definitivo</code></td><td><code>Fsl\\ProtocollaConvenzioneInTitulus</code>, <code>Fsl\\ProtocollaDefinitivaFslInTitulus</code></td></tr>\n<tr><td><code>convenzione_tesi</code>, <code>convenzione_tesi_definitivo</code></td><td><code>ConvenzioniTesi\\ProtocollaConvenzioneTesiInTitulus</code>, <code>…DefinitivaTesiInTitulus</code></td></tr>\n<tr><td><code>tutorato_lettera</code> (padre: <code>tutorato</code>)</td><td><code>Tutorato\\ProtocollaLetteraInTitulus</code></td></tr>\n<tr><td><code>generale</code></td><td>Riserva per tutto ciò che non ha una regola propria</td></tr>\n</tbody></table>\n<h3>Quando si protocolla: automatico o a pulsante</h3>\n<h4>Pratiche degli studenti (automatico)</h4>\n<pre><code>ServizioPratiche::crea() / inviaBozza()\n   └─ FlussoPratica::avvia()                     // FlussoPraticaConProtocollo (decoratore)\n        ├─ ServizioFlussoConvalide::avvia()      // PDF/A e primo ufficio dell\'iter, come prima\n        └─ ProtocolloAutomaticoPratica::dopoInvio($id)\n              ├─ se il 1° passo è ITER_PERSONA / ITER_DIRETTORE / ITER_CONFERMA → aspetta (null)\n              └─ altrimenti prendi(): protocollo vuoto + regola attiva → ProtocollaPraticaInTitulus::protocolla()\n                    └─ registraProtocollo(): scrive numero e data; se il passo è un ufficio protocollo, passa avanti\n\nServizioFlussoConvalide::assegnaAUfficio()        // unico punto in cui una pratica arriva a un ufficio\n   └─ se l\'ufficio è di tipo «protocollo» → AlPassoProtocollo::raggiunto($id)\n         └─ ProtocolloAlPasso → ProtocolloAutomaticoPratica::alPassoProtocollo($id)   // es. tesi dopo il relatore</code></pre>\n<ul><li><b>Perché un\'interfaccia</b> (<code>AlPassoProtocollo</code>, implementata da <code>ProtocolloAlPasso</code>): il flusso non deve conoscere chi protocolla, e il protocollo automatico a sua volta registra il numero <i>attraverso</i> il flusso. <code>ProtocolloAlPasso</code> prende <code>ProtocolloAutomaticoPratica</code> da una <code>Closure</code> al momento dell\'uso, altrimenti il container segnalerebbe una dipendenza circolare.</li><li><b>Tesi e moduli «approvazione prima»</b>: <code>ITER_TESI = [conferma(Relatore/trice), \'protocollo\', \'@cdl\']</code>. All\'invio non si protocolla; quando il relatore conferma, <code>assegnaAUfficio()</code> porta la pratica al passo «protocollo» e scatta <code>alPassoProtocollo()</code>. Un iter che comincia con una conferma e <b>non ha</b> un passo protocollo (per esempio il Diploma Supplement: <code>@cdl → direttore</code>) non protocolla mai in automatico.</li><li>Dopo il protocollo <code>modificabile()</code> è falso: lo studente non può più cambiare la domanda.</li><li><b>Segnatura sul PDF</b>: <code>ServizioFlussoConvalide::registraProtocollo()</code>, quando il numero è nuovo (automatico o scritto a mano), chiama <code>DomandaPdfa::conSegnatura()</code>. Applica alla domanda già spedita la <b>stampigliatura</b> rossa di Titulus (<code>Stampigliatura::sulPdf()</code>; se il PDF non lo permette ripiega su <code>pdf($p, $m, true)</code>, con riquadro in testa e «Prot. n. … del …» nel piede) e la aggiunge allo storico come <code>Domanda_&lt;codice&gt;_protocollata.pdf</code>, visibile allo studente. La domanda originale (<code>domanda_pdf</code>, quella spedita a Titulus, con la sua impronta) non cambia. Nel pannello (<code>admin/didattica_pratiche.php</code>) «Domanda» punta a <code>DomandaPdfa::eventoDomandaSegnata()</code> e «Originale» a <code>eventoDomanda()</code> (con l\'impronta SHA-256).</li><li><b>Stampigliatura (v123)</b>: <code>Protocollo\\Stampigliatura::testo()</code> ricava dal numero (<code>AAAA-AOO-NNNNNNN</code>) «AOO Prot. n. NNNNNNN del gg/mm/aaaa»; <code>Infrastructure\\Pdf\\SegnaturaPdf::applica()</code> aggiunge in testa alla prima pagina, a tutta larghezza, un\'annotazione <code>/Stamp</code> (bordo e testo rossi) con un <b>aggiornamento incrementale</b> (nuova tabella xref o xref stream con <code>/Prev</code>): i byte originali non cambiano, quindi le firme PAdES restano valide (Acrobat può segnalare «modificato dopo la firma»). Restituisce <code>null</code> con PDF cifrati, ruotati o illeggibili: chi chiama ripiega sull\'originale. La usano la lettera del tutorato, le convenzioni FSL e di tesi, gli attestati e le domande. Il prefisso AOO viene dal numero di Titulus (in pre-produzione <code>UCALPRG</code>): da verificare al primo protocollo di produzione.</li><li><b>Non blocca mai</b>: senza collegamento, senza regola o con Titulus in errore la pratica resta com\'è (all\'ufficio, con «Protocolla in Titulus»); <code>segnala()</code> scrive un evento interno <code>messaggio</code> con il motivo. Autore degli eventi: «Portale MyDiBEST».</li></ul>\n<h4>Lettera di incarico del tutorato (automatico, dopo il Direttore)</h4>\n<ul><li>L\'unico punto di passaggio delle firme è <code>registra_firma_incarico()</code> in <code>inc/tutorato.php</code> → <code>ServizioIncarichi::registraFirma</code>. Con il ruolo <code>direttore</code> la lettera diventa <code>firmata</code> e parte <code>ProtocolloAutomaticoLettera::dopoFirma($id)</code>, che chiama <code>ProtocollaLetteraInTitulus</code> con il contesto <code>tutorato_lettera</code> (e il repertorio «contratti di lavoro»).</li><li><b>Dopo il protocollo</b> (<code>ServizioIncarichi::protocolla()</code>): crea la copia con la stampigliatura (<code>ArchivioIncarichi::salvaCopiaSegnata()</code>, colonna <code>file_segnato</code>, evento <code>segnatura</code>), allega la copia a <code>tutorato_copia_protocollata</code> e, nell\'iter <code>uniticket</code>, scrive al gestore (<code>tutorato_gestore_protocollata</code>, con lettera e dichiarazione individuale). Se il protocollo non è disponibile o fallisce, <code>ProtocolloAutomaticoLettera</code> chiama <code>avvisaDaProtocollare()</code> e l\'operatore usa il pulsante.</li><li><b>Solleciti</b>: <code>ServizioRegistroTutorato::sollecitaFirma()</code> (pulsante «Sollecita il docente/il direttore» in <code>admin/tutorato.php</code>, al massimo una volta ogni 24 ore, non conta nei 3 solleciti automatici). A fine attività il docente titolare scarica da <code>firma_incarico.php?registro=1</code> il registro delle presenze (<code>ServizioPagamenti::pdfRegistro()</code>).</li><li>Un errore diventa un evento <code>errore</code> nello storico dell\'incarico (<code>IncaricoRepository::aggiungiEvento</code>): la firma è già registrata e non si perde.</li></ul>\n<h4>Convenzioni FSL e di tesi (a pulsante, dopo il controllo)</h4>\n<ul><li>Il protocollo in entrata e quello definitivo (dopo la firma del Direttore) li lancia l\'ufficio con «Protocolla in Titulus»: <code>admin/fsl.php</code> (<code>cv_titulus</code>, <code>cv_titulus_def</code>) e <code>admin/convenzioni_tesi.php</code> (<code>prendi_titulus</code>, <code>completa_titulus</code>). I pulsanti compaiono solo se <code>disponibile()</code>.</li><li>Il numero ricevuto registra la convenzione (date comprese), avvisa la scuola o l\'azienda e finisce nel registro di controllo (<code>registra_log_audit</code>). Senza collegamento restano i campi per scrivere numero e data a mano.</li></ul>\n<h3>Pannello e passaggio in produzione</h3>\n<ul><li><code>admin/protocollo.php</code>, schede <code>collegamento</code>, <code>regole</code> (elenco dei contesti con «dedicata», «riserva generale» o «del Tutorato» e il pulsante «Configura»), <code>voci</code> (voci d\'indice e repertori) e <code>registro</code>. Le pagine di Didattica si aprono dentro <code>didattica.php?tab=…</code>: aprire direttamente <code>didattica_moduli.php</code> dà 403.</li><li><b>Pre-produzione e produzione</b> sono due indirizzi diversi (<code>…pp.cineca.it/titulus_ws/services/Titulus4?wsdl</code> e quello vero): si cambia solo <code>url_ws</code> nella scheda «Collegamento». I numeri della pre-produzione non sono validi.</li><li>Errore tipico della pre-produzione: «Il LookUp nelle strutture interne è fallito [nome_persona=… nome_uff=…]»: il nome del responsabile o l\'ufficio non coincidono con quelli di Titulus (si controlla nella struttura interna di Titulus; la matricola viaggia come <code>cod_persona</code>). Il responsabile deve appartenere all\'ufficio (UOR della sua scheda): per il DiBEST funziona «DODARO EMANUELE» con UO <code>2014</code> (il Dipartimento), non con le UO figlie 2014.1 o 2014.2; con questa regola la prova del 14/11/2026 ha preso il protocollo 2026-UCALPRG-0001052. Un <code>nome_persona</code> vuoto dà invece un errore di validazione XSD.</li></ul>\n<h3>Prove</h3>\n<ul><li>Pacchetto: <code>php protocollo/php-titulus-utility/tests/esegui.php</code> (55 prove, senza rete: client SOAP finto, XML, controlli, «mai due volte», guardia sulla cache del WSDL).</li><li>Portale: <code>strumenti/prove/oop/protocollo.php</code> (servizio, regole, registro), <code>protocolla_pratica.php</code> (invio, errore, decoratore, passo protocollo dopo la conferma, nessuna regola), <code>protocolla_tutorato.php</code>, <code>protocolla_convenzione.php</code>, <code>protocolla_definitivo_fsl.php</code>, <code>protocolla_tesi.php</code>, <code>protocolla_definitivo_tesi.php</code>, <code>protocolla_attestato.php</code>. Un finto Titulus4 locale (router PHP su 127.0.0.1) serve per vedere la catena completa fino alla risposta.</li></ul>\n<h3>Pubblicarlo come pacchetto</h3>\n<p>Il pacchetto ha il suo <code>composer.json</code> e si può spostare in un repository a sé (<code>git subtree split --prefix=protocollo/php-titulus-utility</code>). Il portale lo carica con <code>src/autoload.php</code>, senza Composer in produzione.</p>'
    )))

    # 13h ----------------------------------------------------------------------------------------------
    # Recuperata il 23/11/2026 dall'HTML committato del 20/11: il generatore l'aveva persa.
    S.append(('accessi', 'Accessi per profilo: il pu', sezione('accessi', '13h', 'Accessi per profilo: il pubblico unico',
        "Un solo modo per dire «a chi si rivolge una cosa»: <code>App\\Auth\\Pubblico</code>. Oggi lo usano risorse, moduli della Didattica, MyForms e le richieste di pubblicazione (chi può inviare: i profili scelti nelle impostazioni, di partenza docenti e PTA); il resto del sito passa un'area alla volta (piano X2, inventario in <code>documentazione/inventario_accessi.md</code>).",
        "<table><thead><tr><th>Elemento</th><th>Dettaglio</th></tr></thead><tbody><tr><td><code>Pubblico::PROFILI</code></td><td>anonimi (chiunque), registrati, studenti, docenti, pta, personale, esterni, scuole, aziende; <code>ammesso($regola, $utente, $gruppi)</code>; gli amministratori (ruolo 1) passano sempre; <code>daVecchio()</code> traduce i formati vecchi (<code>tutti</code>, <code>studenti</code>…, <code>ruolo_accesso_id</code>)</td></tr><tr><td>Profili da dati affidabili</td><td>ruolo, ruoli secondari, gruppi dell'anagrafe, matricola da SSO. Eccezione: «scuole» lo sceglie la persona (<code>sono_una_scuola.php</code>), quindi non per cose riservate (<code>Pubblico::AVVISO_SCUOLE</code>, mostrato nel pannello); «aziende» dipende dall'email</td></tr><tr><td><code>GruppiUtente</code></td><td>l'unica lettura dei nomi dei gruppi dell'utente (tabella <code>ruoli</code>), usata da risorse, MyForms e area personale</td></tr><tr><td><code>Eventi\\AccessoEvento</code></td><td>l'unica regola su <code>eventi.ruolo_accesso_id</code> (0 tutti, −1 accesso, n ruolo; il ruolo 1 passa, il 2 no): la usano il pulsante di <code>master_template.php</code>, <code>ServizioPrenotazioni</code> e <code>ServizioProgrammaFsl</code></td></tr><tr><td><code>html_scelta_pubblico()</code>, <code>pubblico_da_post()</code></td><td>la scelta dei profili nei pannelli (<code>inc/auth.php</code>, <code>PubblicoVista</code>)</td></tr><tr><td><code>richiesta_pubblicazione.php</code>, <code>allegato_pubblicazione.php</code>, <code>admin/pubblicazioni.php</code></td><td>invio: <code>Pubblico::ammesso</code> con i profili di <code>ImpostazioniPubblicazioni::chiPuoInviare()</code> (<code>ServizioPubblicazioni::puoInviare</code>, dalla riga di <code>utenti</code>; di partenza docenti e PTA); pannello e scaricamento: <code>puoGestire()</code> = <code>PermessiAmministrazione::puoPubblicazioni</code> (amministratore, modulo <code>modulo_amministrazione</code> o <code>modulo_pubblicazioni</code>); un file lo scarica anche chi ha inviato la richiesta (<code>puoVedere()</code>). Dettaglio nelle sezioni 13i e 13j</td></tr></tbody></table><ul><li>Decisioni del 17/11/2026: nel solo menu il gestore (ruolo 2) continua a vedere le voci riservate a un ruolo; «scuole» resta autodichiarato ma lo si dice in guida e pannello.</li><li>Prossimi passi: menu (X2b), eventi con <code>pubblico_json</code> (X2c). Home, widget, area personale e Didacticus non cambiano.</li></ul>"
    )))

    # 13i ----------------------------------------------------------------------------------------------
    # Recuperata il 23/11/2026 dall'HTML committato del 20/11: il generatore l'aveva persa.
    S.append(('pubblicazioni', 'Richieste di pubblicazione', sezione('pubblicazioni', '13i', 'Richieste di pubblicazione di docenti e personale',
        "Chi lavora al Dipartimento (docenti e PTA, di partenza) chiede la pubblicazione di un evento sul sito e sui social (<code>src/Pubblicazioni/</code>, schema v127–v133 e v146; l'approvazione, la locandina e «Chi compila cosa» sono della v146). Una tabella a colonne uguali al file Excel di monitoraggio, non il JSON di MyForms: gli operatori completano le colonne riga per riga e l'export esce con le intestazioni originali, salvo quelle cambiate da «Campi e colonne». Fa parte del modulo del pannello <code>amministrazione</code> (sezione 13j, colore <code>#0e7490</code>): non c'è più un modulo <code>pubblicazioni</code> a sé; la sola abilitazione <code>modulo_pubblicazioni</code> resta come permesso parziale.",
        "<h3>Dati</h3><table><thead><tr><th>Tabella</th><th>Contenuto</th></tr></thead><tbody><tr><td><code>pubblicazioni</code></td><td>campi del docente (<code>data_evento</code>, <code>titolo</code>, <code>sottotitolo</code> (max 75 di partenza), <code>descrizione</code> HTML semplice (max 170 caratteri di testo di partenza; i limiti sono nelle impostazioni), <code>modalita</code>, <code>per_*</code> = caselle spuntate, <code>url_docente</code>, <code>altri_link_docente</code>, <code>note</code>, <code>tag</code>, <code>data_inizio_pubblicazione</code>); <code>stato</code> <code>da_approvare|ricevuta|lavorazione|pubblicata|respinta</code> (<code>ricevuta</code> si legge «Approvata»; le richieste di prima valgono come approvate); <code>decisa_da</code>, <code>decisa_il</code>, <code>respinta_motivo</code> (v146); <code>nel_monitoraggio</code> con <code>monitoraggio_dal/da</code> (la riga entra nel file Excel solo quando un operatore la invia); una colonna di testo libero per ogni colonna degli operatori (<code>c_altri_referenti</code> … <code>v_n_partecipanti</code>); <code>campi_extra_json</code> (valori dei campi nuovi, chiavi <code>x_&lt;n&gt;</code>) e <code>colonne_extra_json</code> (valori delle colonne nuove degli operatori, <code>y_&lt;n&gt;</code>), NULL per le richieste di prima (v133); <code>origine</code> <code>form|manuale</code>, <code>modificata_il/da</code></td></tr><tr><td><code>pubblicazioni_allegati</code></td><td>un file per riga (<code>locandina</code> una sola, obbligatoria, PDF/JPG/PNG; <code>allegato</code> PDF/JPG/PNG; <code>immagine</code> JPG/PNG), percorso in <code>uploads/pubblicazioni/</code> (bloccata al web), cancellazione a cascata con la richiesta</td></tr><tr><td><code>impostazioni_sistema.pubblicazioni_*</code></td><td>(v127 e v131) indirizzi che ricevono le richieste (iniziale <code>comunicazione.dibest@unical.it</code>), chi può inviare (profili di <code>Pubblico</code>, iniziale docenti e PTA), aperta e messaggio quando è chiusa, testo in cima e dopo l'invio, limiti (sottotitolo, descrizione, allegati, immagini, allegati obbligatori, giorni di anticipo), ricevuta a chi invia; più i contatori <code>pubblicazioni_ultimo_x</code> e <code>_y</code> (v133) per non riusare mai le chiavi dei campi e delle colonne nuovi; dalla v146 <code>pubblicazioni_approvazione_attiva</code> (spenta di partenza), <code>pubblicazioni_note_operative</code> e <code>pubblicazioni_pagine</code>. Una riga sola, come le impostazioni di Didacticus e dell'assistenza</td></tr><tr><td><code>pubblicazioni_campi</code></td><td>(v132–v133) una riga per ogni campo del form <b>cambiato</b> (nome, aiuto, obbligatorio, visibile, ordine) e per ogni campo <b>nuovo</b> (chiave <code>x_&lt;n&gt;</code>, tipo di MyForms, opzioni, passo 1-7, <code>in_excel</code> e intestazione della colonna). Nasce vuota: i valori di partenza restano nel codice e «Torna all'originale» cancella la riga</td></tr><tr><td><code>pubblicazioni_colonne</code></td><td>(v132–v133) una riga per ogni colonna del file Excel <b>cambiata</b> (intestazione, «a cura di», colore, tipo, visibile, ordine) e per ogni colonna <b>nuova</b> degli operatori (chiave <code>y_&lt;n&gt;</code>, <code>dopo</code> = dopo quale colonna sta)</td></tr><tr><td><code>pubblicazioni_persone</code></td><td>(v146) le tre liste dall'anagrafe di Ateneo: <code>approvatore</code>, <code>ufficio</code> (chi lavora), <code>conoscenza</code> (chi è informato). Si salva l'id della persona, non l'email: l'indirizzo si legge ogni volta da <code>personale_ateneo</code></td></tr><tr><td><code>pubblicazioni_colonne_utenti</code></td><td>(v146) «Chi compila cosa»: una riga per colonna degli operatori e utente. Vuota = tutti vedono e compilano tutto</td></tr></tbody></table><h3>Classi</h3><table><thead><tr><th>Classe</th><th>Compito</th></tr></thead><tbody><tr><td><code>ColonneMonitoraggio</code></td><td>l'elenco unico delle 24 colonne dell'export: chiave, lettera, intestazione identica all'Excel (tranne G, «Titolo iniziativa»), «a cura di», colore, tipo (testo, SI/NO, numero); più «Sottotitolo iniziativa» dopo il titolo e «Stato» in fondo. È la fonte dei valori ORIGINALI: pagine, export e guida leggono da <code>ConfigurazioneCampi</code></td></tr><tr><td><code>ImpostazioniPubblicazioni</code></td><td>l'unica classe che legge e salva le impostazioni (valori di partenza, validazione, «salva tutto o niente», cambiamenti in parole per il registro delle attività); <code>chiPuoInviare</code>, <code>aperta</code>, <code>limiti</code>, <code>ricevuta</code></td></tr><tr><td><code>ConfigurazioneCampi</code></td><td>l'unica classe che unisce i valori del codice (<code>ColonneMonitoraggio</code>, i campi di <code>PassiRichiesta</code>) con quelli salvati e con i campi e le colonne nuovi: <code>campiDelForm</code>, <code>passiVisibili</code>, <code>regole</code>, <code>colonne</code>, <code>colonneOperatori</code>. Regole fisse: Titolo e Data sempre visibili e obbligatori, Richiedente e Stato sempre presenti, destinatari come gruppo. Pagina, JavaScript, controlli, riepilogo, email, scheda, export, <code>Completamento</code> e il conteggio nel database leggono tutti da qui</td></tr><tr><td><code>ApprovazionePubblicazioni</code></td><td><code>daApprovare</code>, <code>decise</code>, <code>approva</code>, <code>respingi</code> (motivo obbligatorio, al massimo 1000 caratteri). Decide solo chi ha un account collegato a una persona della lista; basta uno: un solo UPDATE condizionato allo stato <code>da_approvare</code>, il secondo riceve «già approvata da … il …»; ogni decisione in <code>log_attivita</code></td></tr><tr><td><code>PersonePubblicazioni</code></td><td>le tre liste: <code>eApprovatore</code>, <code>approvatoriConAccount</code>, <code>elenco</code> con gli avvisi (uscita dall'anagrafe, senza email, senza account, senza abilitazione), <code>destinatari</code>, <code>aggiungi</code>, <code>togli</code>. Una lista non dà permessi nel pannello</td></tr><tr><td><code>ColonneDiChi</code></td><td>la regola unica di «Chi compila cosa» per pannello, «Da compilare», scaricamento e salvataggio: <code>senzaLimiti</code> (tutto il modulo, o nessuna assegnazione), <code>visibili</code>, <code>scrivibili</code>, <code>vietate</code>; <code>salva</code> la griglia di <code>admin/parziali/chi_compila_cosa.php</code>. <code>ServizioPubblicazioni</code> rifiuta una colonna non assegnata</td></tr><tr><td><code>CampiAggiunti</code></td><td>legge e controlla i valori dei campi nuovi (con <code>LetturaRisposte</code> di MyForms, che non cambia), salva i campi e le colonne nuovi, elimina una colonna; un campo o una colonna con valori si può solo nascondere, il tipo di un campo con risposte non cambia. Massimo 30 campi e 30 colonne</td></tr><tr><td><code>RegolePubblicazione</code>, <code>PassiRichiesta</code>, <code>Costanti</code></td><td>pulizia e controlli (form del docente e riga a mano), a che passo appartiene ogni campo, valori fissi</td></tr><tr><td><code>ServizioPubblicazioni</code></td><td><code>invia</code>, <code>inviaAlMonitoraggio</code> (precompila solo le colonne vuote: D, E e «SI» per i destinatari), <code>togliDalMonitoraggio</code>, <code>creaManuale</code>, <code>aggiornaOperatori</code>, <code>elimina</code>, <code>puoInviare</code>, <code>puoGestire</code>, <code>puoVedere</code> (chi ha inviato, chi gestisce, chi approva), <code>fileDaScaricare</code> (la locandina si scarica come <code>Locandina_titolo_breve</code>); ogni azione degli operatori in <code>log_attivita</code></td></tr><tr><td><code>PubblicazioniRepository</code>, <code>AllegatiPubblicazioni</code></td><td>accesso ai dati; salvataggio dei file con controllo del tipo sul contenuto (<code>finfo</code>) e nome casuale</td></tr><tr><td><code>LimitiCaricamento</code></td><td>legge <code>post_max_size</code>, <code>upload_max_filesize</code>, <code>max_file_uploads</code>: scrive a schermo i limiti veri (anche nel messaggio «massimo N MB» di un allegato), riconosce un invio più grande di quanto PHP accetta (che svuota <code>$_POST</code> e <code>$_FILES</code>) e avvisa gli amministratori se il server è troppo stretto</td></tr><tr><td><code>EsportazioneMonitoraggio</code>, <code>Completamento</code>, <code>RegistroModifiche</code>, <code>VistaRichiesta</code></td><td>export (<code>TabellaEsportabile</code>: Excel, PDF, CSV con apostrofo anti-formula solo nel CSV), colonne da completare e gruppi per persona, registro delle modifiche in parole, righe del docente con i file</td></tr><tr><td><code>NotifichePubblicazioni</code>, <code>RiepilogoRichiesta</code>, <code>src/Email/CatalogoPubblicazioni.php</code></td><td>email all'ufficio (tutti i campi, elenco dei file, link alla scheda) e ricevuta al docente; con l'approvazione anche <code>pubblicazioni_docente_attesa</code>, <code>_da_approvare</code>, <code>_decisa_altro</code>, <code>_approvata</code>, <code>_respinta</code>, <code>_conoscenza</code> (segnaposto <code>{{approvazione_testo}}</code> nell'avviso all'ufficio); una persona in più liste riceve un'email sola; i testi si cambiano dalla pagina Email; un invio che fallisce finisce nel registro e non annulla la richiesta</td></tr></tbody></table><h3>Pagine e chi può entrare</h3><table><thead><tr><th>Pagina</th><th>Chi entra</th></tr></thead><tbody><tr><td><code>richiesta_pubblicazione.php</code> (+ <code>assets/js/richiesta-pubblicazione.js</code>)</td><td>pubblica con la testata del sito; senza accesso «Accedi per compilare» (ritorno alla pagina), chi non è tra i profili ammessi vede a chi è riservata, con la procedura chiusa si vede il messaggio. Invia solo chi ha uno dei profili delle impostazioni (<code>Pubblico::ammesso</code>); i contatori e le regole del browser arrivano dalla pagina (<code>data-campi</code>), non sono scritti nel JavaScript</td></tr><tr><td><code>allegato_pubblicazione.php?p=&amp;a=</code></td><td>il permesso si ricontrolla a ogni richiesta: chi ha inviato la richiesta, chi approva, gli amministratori e chi ha il modulo. Chiunque altro riceve «File non trovato» (404); il file si trova dalla riga salvata, mai da un percorso passato nell'indirizzo</td></tr><tr><td><code>approva_pubblicazioni.php</code> (<code>PaginaApprovaPubblicazioni</code>, <code>viste/pubblico/approva_pubblicazioni.php</code>, passa dal nucleo)</td><td>solo chi è nella lista «Chi approva» con l'account collegato; senza accesso porta al login e torna alla richiesta. Elenco «Da approvare» e «Decise di recente»; con <code>?id=</code> la richiesta in sola lettura con i file, «Approva» e «Respingi». La tessera dell'area personale e la postazione di «Il mio lavoro» portano qui</td></tr><tr><td><code>admin/pubblicazioni.php</code></td><td>amministratori e persone con «Modulo Amministrazione» o «Amministrazione · Richieste di pubblicazione» (Utenti › «Moduli interi»); gli altri ricevono «accesso negato». Controllato anche su GET e scaricamenti. La vecchia vista <code>?vista=impostazioni</code> porta alla scheda dell'Amministrazione per chi ha tutto il modulo e mostra la sola parte Email agli altri</td></tr><tr><td>tessera «Le mie richieste di pubblicazione» (<code>inc/area/richieste_pubblicazione.php</code>)</td><td>area personale, a chi può inviare e a chi ne ha già inviata una; titolo, data, stato e «Nuova richiesta». Una richiesta inviata non si modifica da chi l'ha fatta; se respinta si legge il motivo</td></tr></tbody></table><h3>Flusso</h3><ul><li>Chi invia: riga con <code>nel_monitoraggio = 0</code> e colonne degli operatori vuote; riga e file in una transazione. Lo stato è <code>da_approvare</code> se l'approvazione è accesa e almeno un approvatore ha un account, altrimenti <code>ricevuta</code> (così non resta in attesa di nessuno). Le righe a mano nascono approvate.</li><li>In attesa: la richiesta non si invia al file, non si elimina e lo stato non si cambia dalla scheda; decide solo chi approva. Approvata passa a <code>ricevuta</code> e parte l'email all'ufficio e per conoscenza; respinta passa a <code>respinta</code> con il motivo.</li><li>Un operatore preme «Invia al file Excel»: <code>nel_monitoraggio = 1</code>; una richiesta respinta non si può inviare. Solo da qui la riga compare nella scheda «Tabella di monitoraggio» e nell'export.</li><li>«Togli dal file Excel» azzera il flag e lascia le colonne salvate; portare in «Respinta» una riga che è nel file chiede conferma e la toglie.</li><li>Le colonne si salvano solo se la riga è nel file e solo se assegnate a chi salva (<code>ColonneDiChi</code>); lo stato di una richiesta approvata si cambia sempre. «Da compilare» (<code>admin/parziali/da_compilare_schede.php</code>, o la tabella) e i numeri «da completare» guardano le sole colonne di chi è limitato.</li></ul><h3>Server e prove</h3><p>I file arrivano da un modulo con più allegati: <code>upload_max_filesize</code>, <code>post_max_size</code> e <code>max_file_uploads</code> di PHP devono essere abbastanza larghi (istruzioni in <code>documentazione/richieste_pubblicazione_sonnet.md</code>). Sul server PHP gira in PHP-FPM, non dentro Apache. Prove: <code>tests/Unit/Pubblicazioni</code> e <code>tests/Integration/Pubblicazioni</code> (regole, export, servizio, email, scarico, pannello, limiti, impostazioni, campi e colonne, campi aggiunti); <code>FileDiProva</code> fornisce PDF, PNG e JPG veri per i controlli sul contenuto. Guide: «Docenti e personale: chiedere la pubblicazione di un'iniziativa», «Richieste di pubblicazione: per chi le gestisce», «Richieste di pubblicazione: per chi le approva» e «Amministrazione: modulistica e procedure online» in <code>GuidePerRuolo</code> (chi può inviare, limiti, campi e colonne si leggono da <code>ImpostazioniPubblicazioni</code> e <code>ConfigurazioneCampi</code>). Copia di prova: <code>crea_ambiente_prova.sh</code> rende anonimo il richiedente e non copia <code>uploads/pubblicazioni/</code>. Aggiornamento del server alla v146: <code>documentazione/istruzioni_aggiornamento_approvazione.txt</code>.</p>"
    )))

    # 13j ----------------------------------------------------------------------------------------------
    # Recuperata il 23/11/2026 dall'HTML committato del 20/11: il generatore l'aveva persa.
    S.append(('amministrazione', 'Modulo Amministrazione: mo', sezione('amministrazione', '13j', 'Modulo Amministrazione: modulistica amministrativa e procedure online',
        'Modulo del pannello <code>amministrazione</code> (icona <code>fa-building-columns</code>, colore <code>#0e7490</code>, <code>MODULI_PORTALE</code> e <code>PAGINE_MODULO</code> in <code>inc/sezioni.php</code>), a schede come la Didattica (<code>admin/amministrazione.php</code> con <code>SCHEDE_AMMINISTRAZIONE</code> e un file <code>admin/amministrazione_&lt;scheda&gt;.php</code> per scheda). Contiene la Modulistica amministrativa, la Richiesta di pubblicazione (13i) e le sue impostazioni. Schema v129–v133.',
        "<h3>Classi e pagine</h3><table><thead><tr><th>Elemento</th><th>Compito</th></tr></thead><tbody><tr><td><code>src/Amministrazione/PermessiAmministrazione</code></td><td>una regola sola: <code>puoTutto</code> (amministratore o <code>modulo_amministrazione</code>), <code>puoPubblicazioni</code> (puoTutto o <code>modulo_pubblicazioni</code>), <code>puoEntrare</code>, <code>puoScheda</code> (chi ha solo le richieste apre la sola scheda <code>home</code>). La usano il pannello, il menu, l'area personale e <code>ServizioPubblicazioni::puoGestire</code>; controllata a ogni richiesta, anche GET, POST e scaricamenti. v129 allarga <code>abilitazioni_ambito.tipo</code> a 40 caratteri</td></tr><tr><td><code>CoseDaFareAmministrazione</code></td><td>un calcolo solo per le «Cose da fare»: richieste da guardare (con i giorni della più vecchia), in lavorazione, righe del file Excel da completare (per chi è limitato da «Chi compila cosa», sulle sole sue colonne), contate nel database con <code>PubblicazioniRepository::contaDaFare</code>; lo usano la Scrivania, il menu a sinistra, la home del modulo e la postazione «Richieste di pubblicazione» di «Il mio lavoro»</td></tr><tr><td><code>ProcedureOnline</code></td><td>l'elenco delle procedure online (oggi la richiesta di pubblicazione: aperta o chiusa, chi può inviarla, collegamenti) e chi le può inviare (stessa regola di <code>puoInviare</code>)</td></tr><tr><td><code>ModuloRepository</code> (<code>src/Didattica</code>)</td><td>v130: <code>didattica_moduli.ambito</code> (<code>didattica</code> o <code>amministrazione</code>, di partenza tutto <code>didattica</code>); ogni lettura, <code>salva</code>, modifica ed elimina valgono per un ambito, di partenza la Didattica: nessun cambiamento visibile. Un documento amministrativo non è mai «online»: <code>ServizioModuli</code> lo rifiuta, <code>modulo.php</code> risponde «Questo non è un modulo online» e <code>ServizioPratiche</code> non apre pratiche</td></tr><tr><td><code>admin/amministrazione.php</code> e schede</td><td><code>home</code> (Cosa devo fare), <code>modulistica</code> (procedure online + documenti e link, solo puoTutto), <code>impostazioni</code> (un riquadro «Richiesta di pubblicazione» con un solo «Salva», solo puoTutto), <code>campi</code> (campi del form, colonne del file Excel, campi e colonne aggiunti, «Torna all'originale», solo puoTutto); le richieste restano in <code>admin/pubblicazioni.php</code> (i link delle email di prima funzionano). Il vecchio <code>inizio.php?sezione=pubblicazioni</code> porta qui</td></tr><tr><td><code>modulistica_amministrativa.php</code></td><td>pagina pubblica: procedure online in cima (con «Compila online», «Accedi e compila» o «Riservata a…»), poi i documenti per categoria e la ricerca; stessa grafica di <code>modulistica.php</code> (pezzi comuni in <code>inc/parziali/</code>). Nel catalogo delle pagine del menu c'è un gruppo «Amministrazione» (la mette nel menu l'amministratore)</td></tr></tbody></table><h3>Test e rilascio</h3><p>Prove: <code>tests/Unit/Amministrazione</code>, <code>tests/Integration/Amministrazione</code> e le cartelle delle Pubblicazioni; prove di pagina <code>strumenti/prove/oop/amministrazione.php</code> e <code>pagine_amministrazione.php</code> (<code>PROVE_SOLO=amministrazione,pagine_amministrazione</code>). Aggiornamento del server: <code>documentazione/istruzioni_aggiornamento_amministrazione.txt</code> (migrazione automatica dalla v128 alla v133 al primo accesso).</p>"
    )))

    # 13k SOS aule ------------------------------------------------------------------------------------
    c = lambda t: '<code>%s</code>' % esc(t)
    S.append(('sos', 'SOS aule', sezione('sos', '13k', 'SOS aule (src/SosAule)',
        'Segnalazioni dei problemi in aula: il docente invia dal QR, il referente dell\'aula prende in carico o affida a uno studente part-time. Le quattro pagine passano dal nucleo. Schema v147, modulo spento di partenza.',
        tab(['Pagina', 'Classe', 'Chi entra'], [
            [c('sos.php?aula=<codice>'), c('PaginaSos'), 'chi ha un account di Ateneo e un profilo ammesso a inviare; la singola segnalazione solo chi l\'ha inviata e chi la gestisce'],
            [c('sos_interventi.php'), c('PaginaSosInterventi'), 'referenti d\'aula, responsabili di riserva, studenti in servizio (vedono solo le loro)'],
            [c('admin/sos_aule.php'), c('PaginaSosPannello, AzioniSosPannello, RegistroSosPannello'), 'tutto il modulo Amministrazione; anche foto e scarichi del registro'],
            [c('admin/sos_cartello.php'), c('PaginaSosCartello'), 'tutto il modulo Amministrazione'],
        ])
        + '<h3>Dominio</h3>' + tab(['Classe', 'Compito'], [
            [c('ServizioSos'), 'invio e passaggi di stato: un solo UPDATE condizionato allo stato, riga in sos_cronologia e in log_attivita'],
            [c('Costanti'), 'stati, urgenze e tabella delle transizioni (chi può passare da dove a dove)'],
            [c('PersoneSos'), 'referenti per persona_id (mai per email), riserva, studenti per email verificata e periodo, destinatari'],
            [c('NotificheSos, CronSos'), 'email (CatalogoSos) e push dopo la transazione; promemoria una volta sola e cancellazione delle foto vecchie'],
            [c('NumeriSos, CodaInterventi'), 'numeri del registro; chi è ammesso e che cosa c\'è nelle schede'],
            [c('AuleRepository, TipiRepository, SegnalazioniRepository, ImpostazioniSos, CampiSos, RegoleSos, FotoSos'), 'dati, impostazioni, campi del form, controlli, foto in uploads/sos/ (bloccata al web)'],
        ])
        + '<h3>Dati</h3><p>Tabelle ' + c('sos_aule, sos_referenti, sos_studenti, sos_tipi, sos_esiti, sos_segnalazioni, sos_cronologia') + ' e colonne ' + c('impostazioni_sistema.sos_*') + '. '
        'Il codice del QR non cambia da solo; quelli rigenerati restano riconoscibili.</p>'
        '<h3>Prove</h3><p>' + c('tests/Unit/SosAule, tests/Integration/SosAule, tests/Integration/Portale/PaginaSos*Test.php') + ' e il giro SOS di ' + c('strumenti/prove/esegui.php') + '.</p>')))

    # 13g ----------------------------------------------------------------------------------------------
    # Recuperata il 23/11/2026 dall'HTML committato: il generatore l'aveva persa.
    S.append(('myforms', 'MyForms', sezione('myforms', '13g', 'MyForms (moduli online con risposte)',
        'Form tipo Google Moduli dentro il portale (<code>src/Forms/</code>, schema v124–v126). Non è un motore nuovo: riusa il motore dei campi della Didattica (<code>CampiModulo</code>, <code>LetturaRisposte</code> con le condizioni rifatte lato server, <code>html_campo_pratica()</code> + <code>campi-pratica.js</code>) e il pubblico unico <code>App\\Auth\\Pubblico</code>. Modulo del pannello <code>forms</code> (colore <code>#be123c</code>), senza aree.',
        '<h3>Dati</h3><table><thead><tr><th>Tabella</th><th>Contenuto</th></tr></thead><tbody><tr><td><code>forms</code></td><td>codice (token pubblico casuale, mai l\'id), titolo, descrizione (HTML ripulito con <code>pulisci_html_semplice()</code> al salvataggio e in uscita), <code>campi_json</code>, <code>pubblico_json</code>, stato <code>bozza|aperta|chiusa</code>, periodo, una per persona, modificabile, anonima, massimo, messaggio di fine, email di notifica, ricevuta, <code>conserva_mesi</code>, proprietario (v124); <code>chiusura_avvisata_il</code>, <code>conserva_avvisata_il</code>, <code>conserva_fino_al</code> per il cron (v126)</td></tr><tr><td><code>forms_gestori</code></td><td>co-gestori (form_id, utente_id)</td></tr><tr><td><code>forms_risposte</code></td><td><code>risposte_json</code>, profilo, codice di ricevuta, <code>ip_impronta</code> (hash, mai l\'IP), <code>utente_impronta</code> = sha256(form_id|utente_id|<code>CIFRATURA_CHIAVE</code>) per «una per persona» e per ritrovare la propria risposta; nei form anonimi <code>utente_id</code> ed <code>email</code> restano NULL. Allegati in <code>uploads/forms/&lt;id&gt;/</code></td></tr></tbody></table><h3>Classi</h3><table><thead><tr><th>Classe</th><th>Compito</th></tr></thead><tbody><tr><td><code>ServizioForms</code></td><td>crea/duplica/apri/chiudi/elimina; <code>puoGestire()</code> (amministratore, proprietario, co-gestore); <code>statoPerPersona()</code> con il motivo (chiuso, pieno, profilo, già risposto); <code>salvaRisposta()</code> sotto <code>GET_LOCK</code> per form; <code>modificaRisposta()</code> che conserva gli allegati non ricaricati; <code>apertiPer()</code>, <code>risposteDi()</code> (per impronta)</td></tr><tr><td><code>FormRepository</code>, <code>RispostaRepository</code></td><td>accesso ai dati</td></tr><tr><td><code>CostruttoreCampi</code></td><td>tipi ammessi e controllo lato server del costruttore (<code>admin/parziali/costruttore_campi.php</code>, scritto a parte: la Didattica non cambia); esclusi i tipi «Dalle anagrafi»</td></tr><tr><td><code>ControlliAnonimi</code></td><td>campo trappola, <code>LimiteRichieste</code> (10 invii l\'ora per form e IP) e domanda anti-robot (<code>CaptchaPrenotazione</code>, la stessa delle prenotazioni)</td></tr><tr><td><code>NotificheForms</code></td><td>ricevuta a chi ha risposto (mai nei form anonimi: <code>log_email</code> registra destinatario e oggetto), avviso a <code>email_notifica</code> senza nome né email nei form anonimi, email di chiusura e di conservazione al proprietario; un errore di invio non fa mai fallire il salvataggio</td></tr><tr><td><code>ServizioRisposte</code>, <code>FiltriRisposte</code>, <code>RiepilogoRisposte</code></td><td>elenco filtrato (testo, periodo, profilo), dettaglio, allegati solo dalla cartella del form (<code>realpath</code>), eliminazione con gli allegati, ZIP, conteggi per le domande a scelta; <code>FiltriRisposte::data()</code> controlla che una data esista davvero</td></tr><tr><td><code>EsportazioneRisposte</code></td><td>righe per <code>TabellaEsportabile</code> (Excel, CSV, PDF): una colonna per domanda, abbinata per etichetta; nei form anonimi nessuna colonna nome/email; apostrofo contro le formule solo nel CSV (l\'Excel scrive le celle come testo)</td></tr><tr><td><code>CronForms</code></td><td>in <code>cron_background.php</code>: chiusura a fine periodo o al massimo (una volta per form), conservazione con avviso al proprietario 7 giorni prima; cancella solo le risposte annunciate</td></tr></tbody></table><h3>Pagine e file</h3><table><thead><tr><th>File</th><th>Compito</th></tr></thead><tbody><tr><td><code>form.php?c=</code></td><td>compilazione pubblica: 404 per bozza o codice sbagliato, «Accedi per compilare» con ritorno (<code>RitornoInterno</code>), «riservato a…», errori accanto ai campi, rimando dopo l\'invio (F5 non duplica), modifica della propria risposta</td></tr><tr><td><code>forms.php</code></td><td>«MyForms · moduli da compilare»: i form aperti a chi guarda</td></tr><tr><td><code>inc/area/miei_form.php</code></td><td>tessera «I miei form» dell\'area personale (riquadro <code>miei_form</code>, v125)</td></tr><tr><td><code>admin/forms.php</code>, <code>admin/forms_risposte.php</code>, <code>admin/forms_allegato.php</code></td><td>pannello: elenco, scheda, anteprima, co-gestori, link e QR; risposte, riepilogo, esportazione; scaricamento protetto degli allegati. <code>puoGestire()</code> a ogni richiesta, anche GET. La testata del modulo (<code>inizio.php?sezione=forms</code>) rimanda a <code>forms.php</code></td></tr><tr><td><code>RicercaGlobale::moduli()</code></td><td>i form compaiono nella ricerca unica solo a chi può compilarli</td></tr></tbody></table><div class="gal"><figure><img src="img/map_adm_myforms_campi.jpg" loading="lazy" alt="Costruttore · &lt;code&gt;admin/forms.php&lt;/code&gt; + &lt;code&gt;admin/parziali/costruttore_campi.php&lt;/code&gt;."><figcaption><b>Costruttore</b> · <code>admin/forms.php</code> + <code>admin/parziali/costruttore_campi.php</code>.</figcaption></figure><figure><img src="img/map_pub_myforms_form.jpg" loading="lazy" alt="Compilazione · &lt;code&gt;form.php?c=&lt;/code&gt;."><figcaption><b>Compilazione</b> · <code>form.php?c=</code>.</figcaption></figure><figure><img src="img/map_adm_myforms_riepilogo.jpg" loading="lazy" alt="Riepilogo · &lt;code&gt;admin/forms_risposte.php?vista=riepilogo&lt;/code&gt; (&lt;code&gt;RiepilogoRisposte&lt;/code&gt;)."><figcaption><b>Riepilogo</b> · <code>admin/forms_risposte.php?vista=riepilogo</code> (<code>RiepilogoRisposte</code>).</figcaption></figure></div><h3>Prove</h3><p><code>tests/Unit/Forms</code>, <code>tests/Integration/Forms</code> (compilazione, risposte, cron con orologio finto, ricerca), <code>RitornoInternoTest</code>. Guida: <code>documentazione/guide/guida_myforms.md</code> e voce MyForms in <code>GuidePerRuolo</code>.</p>'
    )))

    S.append(('cron', 'Job pianificati', sezione('cron', '14', 'Job pianificati (cron)',
        'Quattro script, avviabili da riga di comando o via URL con <code>CRON_KEY</code> (≥ 16 caratteri).',
        tab(['Script', 'Cosa fa'], [
            ['<code>cron_background.php</code>', 'Passo principale: auto-archiviazione, email post evento, promemoria elenco studenti, attestati delle classi, verifica/promemoria/avvisi/scadenze delle convenzioni, documenti FSL, valutazioni FSL, conservazione dei dati, <b>riepilogo «Da fare per te»</b> (<code>RiepilogoPersone::cron()</code>), <b>convenzioni di tesi</b>, controllo notturno del sito, aggiornamento anagrafe di Ateneo.'],
            ['<code>cron_attestati.php</code>', 'Invio degli attestati.'],
            ['<code>admin/cron_reminders.php</code>', 'Promemoria agli iscritti (prossime 72 ore) e, tramite <code>ServizioPromemoria</code>, quelli dei moduli registrati con le interfacce <code>PromemoriaRisorse</code> e <code>Pianificati</code>: prenotazioni di risorse, lista d\'attesa (<code>scala()</code>), scadenze delle risorse, pratiche ferme e solleciti di firma.'],
            ['<code>admin/cron_backup.php</code>', 'Esporta il database (<code>.sql.gz</code>) e i file (<code>.zip</code>), copia sul NAS, invia la copia cifrata del DB; poi <code>VerificaBackup</code> rilegge tutto.'],
        ])
        + '<ul><li>La sospensione dei promemoria (<code>SospensionePromemoria</code>) non segna nulla come inviato: alla riattivazione il cron rimanda ciò che è ancora dovuto.</li>'
          '<li>Conservazione (variabili <code>CONSERVAZIONE_*_MESI</code>): log, audit, prenotazioni anonimizzate, account inattivi, lettere di incarico, convocazioni.</li>'
          '<li>Ripristino: <code>php strumenti/ripristina_backup.php verifica|prova|file|database</code>.</li></ul>'
        + fig('map_adm_sistema', '<b>Email e controlli</b> (<code>admin/sistema.php</code>): stato del controllo notturno, backup, prova email.')
    )))

    # 14 -----------------------------------------------------------------------------------------------
    S.append(('sicurezza', 'Sicurezza', sezione('sicurezza', '15', 'Sicurezza',
        'Difesa in profondità, con le scelte che contano per un portale universitario.',
        '<div class="grid">'
        + card('Input e output', '<p>Prepared statement ovunque; output HTML sempre codificato; dati JS in <code>json_encode</code>; colori validati prima degli stili; QR letti mai aperti come link.</p>')
        + card('Sessione e CSRF', '<p>Cookie <code>Secure</code>/<code>HttpOnly</code>/<code>SameSite=Lax</code>; token CSRF su ogni POST; <code>rigenera()</code> all\'accesso; dopo «Esci» nessun accesso SSO automatico.</p>')
        + card('Header', '<p>CSP in <code>config.php</code>, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy; librerie con SRI SHA-384.</p>')
        + card('Permessi', '<p>Ogni azione verifica che evento, turno o pratica appartengano all\'area o all\'ufficio dell\'utente; cron solo da CLI o con <code>CRON_KEY</code>.</p>')
        + card('Il no del nucleo', '<p>Nelle pagine servite dal nucleo accesso e token si controllano in un punto solo, <b>prima</b> di stampare: un token sbagliato d\u00e0 '
               '403 con 133 byte di testo e nient\'altro, non mezza pagina. Il rifiuto per permesso mancante e quello per token sbagliato dicono la stessa frase, '
               'cos\u00ec non si distingue \u00abnon puoi\u00bb da \u00absessione scaduta\u00bb. \u00abSei amministratore\u00bb si legge dal database, non dalla sessione: chi perde il ruolo lo perde subito.</p>')
        + card('\u00abEntra come\u2026\u00bb', '<p>La simulazione \u00e8 di <b>sola visione</b> e scade da sola: la guardia (<code>inc/simulazione_guardia.php</code>) '
               'ferma i POST, gli indirizzi con una parola d\'azione e gli script che scrivono anche su una GET semplice (<code>self_checkin.php</code>, '
               'che registrerebbe la presenza della persona che stai guardando).</p>')
        + card('File', '<p><code>uploads/</code>, <code>.env</code> e cartelle interne negati dal web server; download solo da pagine che controllano il permesso.</p>')
        + card('Anti-abuso', '<p>Rate limit per IP, CAPTCHA interno, campo trappola; limite ai tentativi di password sui verbali.</p>')
        + card('Privacy', '<p>Nessuna connessione a terzi (font e librerie locali); IP anonimizzati nei registri dei verbali; conservazione e anonimizzazione automatiche.</p>')
        + card('Segreti', '<p>Solo nel <code>.env</code> (mai in git): DB, chiavi, Aruba, backup. Password SMTP cifrate nel database.</p>')
        + '</div>'
    )))

    # 15 -----------------------------------------------------------------------------------------------
    S.append(('frontend', 'Front-end', sezione('frontend', '16', 'Front-end e asset',
        'Rendering lato server con Bootstrap; JavaScript solo dove serve.',
        tab(['File', 'Ruolo'], [
            ['<code>assets/css/pannello.css</code>, <code>assets/js/pannello.js</code>', 'Stile e comportamenti del pannello (menu a gruppi richiudibili, «?» con le guide)'],
            ['<code>campi-pratica.js</code>, <code>campo-scuola.js</code>, <code>ricerca-personale.js</code>, <code>decisioni-pratica.js</code>', 'Campi guidati dei moduli (catalogo insegnamenti, scuola del Ministero, ricerca nell\'anagrafe, decisioni di seduta)'],
            ['<code>qrcode-generator</code>, <code>html5-qrcode</code>', 'QR generati nella pagina e scanner del check-in, locali con SRI'],
            ['<code>assets/vendor/…/pdf.js</code>', 'Visualizzatore dei verbali (PDF.js 3.11.174, Apache-2.0)'],
            ['<code>sw.js</code>, <code>manifest.json</code>', 'PWA: in cache solo le librerie statiche; le pagine arrivano sempre dal server'],
        ])
        + '<ul><li>Eventi personalizzati: il selettore delle persone emette <code>persona-scelta</code> sul form che lo contiene (<code>detail.id</code>), usato per uffici, responsabili, abilitazioni.</li>'
          '<li><code>data-confirm</code> su pulsanti e form apre una conferma prima dell\'invio.</li>'
          '<li>Pagine pubbliche che non devono usare la sessione SimpleSAML (verbali) definiscono <code>PAGINA_SENZA_SSO</code>.</li></ul>'
        + gal(fig('map_adm_testata', '<b>Testata e home a widget</b> (<code>admin/testata.php</code>, <code>WidgetHome</code>).'),
              fig('map_adm_did_moduli', '<b>Costruttore dei moduli</b>: campi, condizioni e iter generano <code>campi_json</code> / <code>iter_json</code>.'))
    )))

    # 16 -----------------------------------------------------------------------------------------------
    S.append(('test', 'Test e strumenti', sezione('test', '17', 'Test, ambiente locale e strumenti',
        'La rete di sicurezza sono le «prove»: girano su un database usa-e-getta e, in locale, anche contro le pagine reali.',
        tab(['Cosa', 'Come', 'Note'], [
            ['Prove del portale', '<code>php -d extension=zip -d extension=gd -d extension=intl strumenti/prove/esegui.php</code>', 'Crea il database <code>eventi_prova</code> da <code>database/schema.sql</code> + migrazioni; un file per modulo in <code>strumenti/prove/oop/</code>; <b>3.153 prove</b>; esce 1 se una fallisce. Con l\'ambiente locale acceso prova anche le pagine via HTTP (25 file <code>pagine_*.php</code>). <code>PROVE_SOLO=modulo</code> ne lancia uno solo; <code>--sposta-ancora=N</code> sposta di N giorni la settimana delle prenotazioni, per provare quello che dipende dal giorno'],
            ['PHPUnit', '<code>composer test</code> (in locale: il phar con <code>--bootstrap</code>, una cartella alla volta)', '<b>1.430 casi</b> (711 <code>tests/Unit</code> + 719 <code>tests/Integration</code>) in 151 file; con MariaDB spenta centinaia di prove risultano saltate invece di rosse, quindi le saltate devono essere 2 e non più; <code>DatabaseDiProva</code> crea solo le colonne usate dal test; doppi in <code>tests/Doppi</code> (<code>MailerFinto</code>, <code>OrologioFisso</code>); trait <code>ConDirezione</code> per i test che servono il Direttore'],
            ['Ambiente locale', '<code>bash strumenti/locale/avvia.sh</code>', 'Avvia MariaDB e il server PHP su <code>127.0.0.1:8080</code> con il database <code>eventi_locale</code>; <code>router.php</code> offre <code>/__accesso?u=ID</code> (accesso di prova), <code>/__email</code> (email intercettate), <code>/__cron</code>'],
            ['Dati di dimostrazione', '<code>strumenti/locale/scenario_*.php</code>', 'Persone, uffici, pratiche in ogni punto dell\'iter, convenzioni, verbali; <code>collaudo_*.php</code> porta ogni iter fino in fondo'],
            ['Schermate', '<code>strumenti/locale/schermate*.py|php</code>', 'Chrome headless (CDP) con accesso di prova; sono le immagini di guide, presentazioni e di queste mappe'],
            ['Presentazioni e PDF', '<code>strumenti/locale/presentazioni.py</code>, <code>genera_pdf.sh</code>', 'PDF delle guide per ruolo e degli iter con Chrome <code>--print-to-pdf</code>'],
            ['Controlli sul server', '<code>strumenti/prove_server.sh</code>, <code>verifica_sito.sh</code>', 'Versione PHP/estensioni, <code>php -l</code> su tutti i file, cartelle scrivibili, prove su un DB dedicato. <code>verifica_sito.sh</code> riconosce la copia di prova dalla fascia arancione: l\u00e0 i processi automatici rispondono 200 perch\u00e9 l\'ambiente li ferma da s\u00e9, nel sito vero devono dare 403'],
            ['Il nucleo', '<code>php strumenti/avanzamento_nucleo.php</code>, <code>bash strumenti/prove/apache_nucleo.sh</code>', 'Il primo conta quante pagine passano dal nucleo e quante ripetono ancora <code>nega_accesso</code>, <code>csrf_verify</code>, <code>$h = fn</code> (con <code>--controlla</code> verifica rotte e gusci, con <code>--casi</code> chiede a PHPUnit i casi veri); il secondo, con Apache vero, controlla che ogni indirizzo risponda uguale con e senza <code>nucleo.php</code>'],
        ])
        + cod('''// Un test d'integrazione tipico (PHPUnit)
final class FirmaDirettoreTest extends FslBase
{
    use ConDirezione;                      // tabelle uffici + impostaDirettore()
    public function testIlDirettoreVieneDallaDirezione(): void
    {
        $this->impostaDirettore('Mario Bianchi', 'M@Y.IT', 'bnchmr60a01h501x');
        $this->assertTrue($this->firma()->puoFirmare(['id' => 5, 'email' => 'M@Y.it']));
    }
}''')
    )))

    # 17 -----------------------------------------------------------------------------------------------
    S.append(('rilascio', 'Rilascio', sezione('rilascio', '18', 'Rilascio e operazioni',
        'Il deploy è manuale e verificabile: si costruisce un pacchetto dall\'ultimo commit e lo si estrae sul server.',
        cod('''# 1. sul PC: pacchetto con tutti i file committati (senza .env, uploads, cache, tests, strumenti, documentazione)
bash strumenti/crea_pacchetto.sh            # -> aggiornamento_completo.tgz  (+ SHA256SUMS_pacchetto.txt dentro)

# 2. sul server
cd /opt/lampp/htdocs/mydibest
tar -xzf aggiornamento_completo.tgz
sha256sum -c SHA256SUMS_pacchetto.txt | grep -v ": OK"     # non deve stampare nulla
find . -name '*.php' -not -path './uploads/*' -not -path './cache/*' -print0 | xargs -0 -n1 php -l | grep -v "^No syntax"
rm -f cache/schema_v*.ok                      # se serve: forza il ricalcolo dello schema alla prossima richiesta''')
        + '<ul><li>Dopo il caricamento basta aprire una pagina: <code>assicura_schema()</code> aggiorna database e dati di partenza.</li>'
          '<li><code>install.php</code> è l\'installer guidato (da eliminare dopo l\'uso). Il passaggio da <code>/didattica</code> a <code>/mydibest</code> è automatizzato da <code>strumenti/migra_a_mydibest.sh</code>.</li>'
          '<li>Il <code>.env</code> contiene i segreti e non si sovrascrive mai; <code>.env.example</code> elenca le variabili.</li></ul>'
    )))

    # 18 -----------------------------------------------------------------------------------------------
    S.append(('estendere', 'Come estendere', sezione('estendere', '19', 'Come aggiungere una funzione',
        'La ricetta per un nuovo modulo o una nuova tabella, seguendo i pattern esistenti.',
        '<div class="info"><b>Due vie, non una.</b> Per una pagina nuova conviene la via del nucleo (sezione 2a): una riga nella tabella delle rotte, '
        'un guscio di tre righe, una classe <code>Pagina</code>, un modello in <code>viste/</code> e una prova con <code>Richiesta::finta()</code>. '
        'Quella che segue è la via di sempre, che resta valida per le 198 pagine non ancora convertite e per tutto quello che non è una pagina.</div>'
        + passi('<b>Tabella</b>: aggiungi il <code>CREATE TABLE IF NOT EXISTS</code> in <code>Definizioni::tabelle()</code> (o la colonna in <code>colonne()</code>) e alza <code>Migrazioni::VERSIONE</code>. I dati di partenza vanno in <code>datiDiPartenza()</code>, con una guardia di idempotenza.',
              '<b>Repository</b> in <code>src/&lt;Modulo&gt;/</code>: costruttore con <code>Database</code>, metodi con query preparate che restituiscono array.',
              '<b>Servizio</b>: regole e validazioni; restituisce <code>?string</code> d\'errore o i dati; riceve repository, notifiche e <code>Orologio</code> (mai <code>new DateTime</code> diretto, per testare).',
              '<b>Notifiche</b>: una classe che usa <code>Mailer</code> e <code>Sito::urlBase()</code> per i link.',
              '<b>Pagina</b>: <code>require_once \'middleware.php\'</code>, <code>App::get(Servizio::class)</code>, azioni POST con <code>csrf_verify</code> + redirect, poi HTML. Se serve il pannello, usa <code>admin_header.php</code> e registra la pagina in <code>PAGINE_MODULO</code> e nel menu.',
              '<b>Prove</b>: un file in <code>strumenti/prove/oop/&lt;modulo&gt;.php</code> con <code>prova(condizione, "descrizione")</code> e, se serve, un test PHPUnit con <code>DatabaseDiProva</code>.',
              '<b>Documenti</b>: aggiorna <code>README.md</code>, il piano e la mappa dei file da caricare; costruisci il pacchetto e verifica con <code>sha256sum -c</code>.')
        + '<div class="nota"><b>Errori da evitare.</b> Non scrivere HTML nei servizi; non leggere la sessione nelle classi (passa i dati); non fare <code>ALTER</code> fuori da <code>Definizioni</code>; non usare <code>App::get()</code> dentro <code>src/</code>; non dimenticare <code>csrf_field()</code> nei form.</div>'
    )))

    # 19 -----------------------------------------------------------------------------------------------
    S.append(('appendice', 'Appendice', sezione('appendice', '20', 'Appendice',
        '',
        '<h3>Variabili del file <code>.env</code> (nomi, mai i valori)</h3>'
        + tab(['Gruppo', 'Variabili'], [
            ['Database', '<code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_NAME</code>'],
            ['SSO', '<code>SIMPLESAML_PATH</code>'],
            ['Indirizzi e cron', '<code>URL_SITO</code>, <code>CRON_KEY</code>'],
            ['Backup', '<code>BACKUP_NAS_PATH</code>, <code>BACKUP_NAS_GIORNI</code>, <code>BACKUP_EMAIL</code>, <code>BACKUP_EMAIL_FREQUENZA</code>, <code>BACKUP_PASSWORD</code>'],
            ['Conservazione', '<code>CONSERVAZIONE_LOG_MESI</code>, <code>_AUDIT_MESI</code>, <code>_PRENOTAZIONI_MESI</code>, <code>_UTENTI_MESI</code>, <code>_INCARICHI_MESI</code>, <code>_CONVOCAZIONI_MESI</code>'],
            ['Tutorato e firme', '<code>INCARICHI_SOLO_SPID_CIE</code>, <code>ARUBA_ARSS_URL</code>, <code>ARUBA_ARSS_DOMINIO</code>, <code>ARUBA_ARSS_CERTID</code>, <code>CIFRATURA_CHIAVE</code>, <code>FIRME_GIORNI_SOLLECITO</code>'],
            ['Riepiloghi', '<code>RIEPILOGO_PERSONE</code>'],
        ])
        + '<h3>Glossario</h3>'
        + tab(['Termine', 'Significato'], [
            ['Area', 'Una pagina del portale (riga di <code>pagine_eventi</code>) con eventi o risorse e il suo aspetto'],
            ['Modulo (del pannello)', 'Una delle cinque sezioni del pannello (<code>MODULI_PORTALE</code>)'],
            ['Modulo (di pratica)', 'Una richiesta online costruita da pannello (<code>didattica_moduli</code>)'],
            ['Iter', 'La sequenza di passi (uffici, firme, conferme) di un modulo o di una convenzione'],
            ['PAdES', 'Firma digitale incorporata nel PDF (il PDF firmato), accettata al posto del .p7m'],
            ['Facciata', 'Funzione procedurale che inoltra a una classe'],
            ['Strangler fig', 'Strategia di sostituzione graduale del codice vecchio con quello nuovo'],
            ['op_id', 'Numero stabile di una persona come operatore della Didattica, quello salvato in <code>pratiche.assegnata_a</code>'],
            ['Direttore in carica', 'Chi firma ora: il Direttore o, con «Direttore assente», il vice'],
        ])
    )))
    return S
