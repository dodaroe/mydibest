// conferma.js - Finestra di conferma unica del portale (pannello di gestione e sito pubblico).
// Sostituisce i confirm() del browser. Si attiva con un attributo su un link, un pulsante o un modulo:
//   data-conferma="Testo della domanda"                      conferma semplice (Annulla / Sì, procedi)
//   data-conferma-scrivi="parola"                            eliminazione definitiva: il pulsante si accende solo se si scrive la parola
//                                                            (di solito il nome dell'elemento; maiuscole e spazi in più non contano);
//                                                            il testo della domanda resta in data-conferma
//   data-conferma-livello="pericolo"                         colore rosso anche senza «elimina» nel testo
//   data-conferma-pulsante="Testo"                          il testo del pulsante del «sì», quando non si elimina niente (es. «Sì, rigenera»)
// Il vecchio nome data-confirm (e data-confirm-level) funziona ancora, così le pagine non ancora aggiornate non cambiano.
// Da altri script: window.pnConferma(testo, function () { ... }, { scrivi: 'parola', pericolo: true }).
// Un modulo inviato dopo la conferma porta anche name e value del pulsante premuto (si rifà il click, che il browser tratta come sempre).
// Se Bootstrap JS non c'è (pagine che non lo caricano) la conferma ripiega su confirm() / prompt() del browser.
(function () {
    'use strict';
    if (window.pnConferma) return;

    var SEL = '[data-conferma],[data-conferma-scrivi],[data-confirm]';
    var PERICOLO = /elimin|cancell|definitiv|irrev|rimuov|svuot/i;
    var el = null;        // la finestra, creata alla prima richiesta
    var azione = null;    // cosa fare se si conferma
    var confermato = false;
    var parola = '';

    function norma(s) { return String(s == null ? '' : s).replace(/\s+/g, ' ').trim().toLowerCase(); }

    function haModale() { return !!(window.bootstrap && window.bootstrap.Modal); }

    function costruisci() {
        if (el) return el;
        el = document.createElement('div');
        el.className = 'modal fade';
        el.id = 'pnConferma';
        el.tabIndex = -1;
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-labelledby', 'pnConfermaTitolo');
        el.setAttribute('aria-describedby', 'pnConfermaTesto');
        el.innerHTML =
            '<div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
            + '<div class="modal-header py-2" id="pnConfermaTesta">'
            + '<h2 class="modal-title h6 fw-bold m-0" id="pnConfermaTitolo"></h2>'
            + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button></div>'
            + '<div class="modal-body py-3"><p class="mb-0" id="pnConfermaTesto"></p>'
            + '<div class="mt-3" id="pnConfermaScrivi" hidden>'
            + '<label for="pnConfermaCampo" class="form-label small fw-bold">Per confermare scrivi: <span class="font-monospace pn-conf-parola" id="pnConfermaParola"></span></label>'
            + '<input type="text" class="form-control" id="pnConfermaCampo" autocomplete="off" autocapitalize="off" spellcheck="false"></div></div>'
            + '<div class="modal-footer py-2">'
            + '<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><i class="fa fa-times me-1" aria-hidden="true"></i>Annulla</button>'
            + '<button type="button" class="btn btn-sm fw-bold px-4" id="pnConfermaSi"></button></div>'
            + '</div></div>';
        document.body.appendChild(el);

        var si = el.querySelector('#pnConfermaSi'), campo = el.querySelector('#pnConfermaCampo');
        function combacia() { return parola === '' || norma(campo.value) === norma(parola); }
        campo.addEventListener('input', function () { si.disabled = !combacia(); });
        campo.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); if (combacia()) si.click(); } });
        si.addEventListener('click', function () {
            if (!combacia()) return;
            confermato = true;
            window.bootstrap.Modal.getOrCreateInstance(el).hide();
        });
        el.addEventListener('shown.bs.modal', function () {
            (parola !== '' ? campo : el.querySelector('[data-bs-dismiss="modal"].btn-outline-secondary')).focus();
        });
        el.addEventListener('hidden.bs.modal', function () {
            var fa = confermato ? azione : null;
            azione = null; confermato = false;
            if (fa) fa();
        });
        return el;
    }

    // testo = la domanda; azione = cosa fare dopo il «sì»; opz = { scrivi: 'parola', pericolo: true|false }
    function chiedi(testo, fa, opz) {
        opz = opz || {};
        var scrivi = opz.scrivi == null ? '' : String(opz.scrivi);
        var pericolo = !!opz.pericolo || scrivi !== '' || PERICOLO.test(testo || '');
        if (!testo) testo = scrivi !== '' ? 'Eliminare definitivamente? Non si può annullare.' : 'Confermi?';

        if (!haModale()) { // ripiego: la finestra del browser
            if (scrivi !== '') {
                var r = window.prompt(testo + '\n\nPer confermare scrivi: ' + scrivi);
                if (r === null) return;
                if (norma(r) !== norma(scrivi)) { window.alert('Non coincide: non è stato fatto nulla.'); return; }
                fa();
            } else if (window.confirm(testo)) {
                fa();
            }
            return;
        }

        var m = costruisci();
        parola = scrivi;
        azione = fa; confermato = false;
        m.querySelector('#pnConfermaTesto').textContent = testo;
        var testa = m.querySelector('#pnConfermaTesta'), si = m.querySelector('#pnConfermaSi'), btnX = m.querySelector('.btn-close');
        m.querySelector('#pnConfermaTitolo').innerHTML = pericolo && !opz.pulsante ? '<i class="fa fa-trash me-1" aria-hidden="true"></i> Sei sicuro?' : '<i class="fa fa-exclamation-triangle me-1" aria-hidden="true"></i> ' + (pericolo ? 'Sei sicuro?' : 'Conferma');
        // colori in assets/css/portale-ui.css (variabili di dibest-colori.css: giusti anche nel tema scuro, del sito e del pannello)
        testa.className = 'modal-header py-2 pn-conf-testa ' + (pericolo ? 'pn-conf-pericolo' : 'pn-conf-attenzione');
        btnX.classList.remove('btn-close-white');
        si.className = 'btn btn-sm fw-bold px-4 ' + (pericolo ? 'btn-danger' : 'btn-warning text-dark');
        si.innerHTML = scrivi !== '' ? '<i class="fa fa-trash me-1" aria-hidden="true"></i>Elimina definitivamente'
            : (pericolo ? '<i class="fa fa-trash me-1" aria-hidden="true"></i>Sì, elimina' : '<i class="fa fa-check me-1" aria-hidden="true"></i>Sì, procedi');
        if (opz.pulsante) si.textContent = opz.pulsante;
        si.disabled = scrivi !== '';
        var blocco = m.querySelector('#pnConfermaScrivi'), campo = m.querySelector('#pnConfermaCampo');
        blocco.hidden = scrivi === '';
        m.querySelector('#pnConfermaParola').textContent = scrivi;
        campo.value = '';
        window.bootstrap.Modal.getOrCreateInstance(m).show();
    }

    // Dall'elemento con l'attributo: domanda, parola da scrivere, livello
    function dallElemento(x) {
        var testo = x.getAttribute('data-conferma');
        if (testo === null) testo = x.getAttribute('data-confirm');
        var liv = x.getAttribute('data-conferma-livello') || x.getAttribute('data-confirm-level') || '';
        return { testo: testo || '', opz: { scrivi: x.getAttribute('data-conferma-scrivi'), pericolo: liv === 'pericolo' || liv === 'danger', pulsante: x.getAttribute('data-conferma-pulsante') } };
    }

    // Link e pulsanti: si ferma il click, si chiede, e dopo il «sì» si rifà lo stesso click (un modulo parte col suo pulsante, name e value compresi)
    document.addEventListener('click', function (e) {
        var x = e.target && e.target.closest ? e.target.closest(SEL) : null;
        if (!x || x.tagName === 'FORM') return;
        if (x.__pnOk) { x.__pnOk = false; return; }
        if (x.disabled || e.button > 0) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        var d = dallElemento(x);
        chiedi(d.testo, function () { x.__pnOk = true; x.click(); }, d.opz);
    }, true);

    // Modulo con la conferma sul <form> stesso (al posto di onsubmit="return confirm(...)")
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (!f || !f.matches || !f.matches(SEL)) return;
        if (f.__pnOk) { f.__pnOk = false; return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        var inviato = e.submitter || null, d = dallElemento(f);
        chiedi(d.testo, function () {
            f.__pnOk = true;
            if (f.requestSubmit) { if (inviato) f.requestSubmit(inviato); else f.requestSubmit(); }
            else {
                if (inviato && inviato.name) { var h = document.createElement('input'); h.type = 'hidden'; h.name = inviato.name; h.value = inviato.value || '1'; f.appendChild(h); }
                f.submit();
            }
        }, d.opz);
    }, true);

    window.pnConferma = chiedi;
})();
