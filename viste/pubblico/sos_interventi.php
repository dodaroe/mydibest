<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // SOS aule, pagina di chi interviene. Dati: quale (elenco | messaggio), avvisoHtml, temaHtml; per ogni «quale»: vedi la classe PaginaSosInterventi ?>
<?php echo $d->grezzo('temaHtml'); ?>
<div class="ui-sos-pagina larga">
    <h1>SOS aule</h1>
<?php if ((string) $d->grezzo('quale') === 'messaggio'): ?>
    <?php echo $d->grezzo('avvisoHtml'); ?>
    <div class="ui-sos-box <?php echo $d->tono; ?>" role="alert"><i class="fa fa-circle-info" aria-hidden="true"></i><div>
        <p><strong><?php echo $d->testo; ?></strong></p>
        <?php if ($d->indietro !== ''): ?><p><a href="<?php echo $d->indietro; ?>">Torna alle tue segnalazioni</a></p><?php endif; ?>
    </div></div>
<?php else: ?>
    <p class="ui-sos-sotto"><?php echo $d->sottotitolo; ?> La pagina si aggiorna da sola.</p>
    <?php if ((int) $d->grezzo('spento') === 1): ?>
        <div class="ui-sos-box attesa" role="status"><i class="fa fa-circle-pause" aria-hidden="true"></i><div>
            <p><strong>Il servizio SOS aule è spento: non arrivano nuove segnalazioni. Chiudi quelle che hai ancora in mano.</strong></p>
        </div></div>
    <?php endif; ?>
    <?php if ((int) $d->grezzo('push') === 1): ?>
        <button type="button" class="btn btn-outline-primary fw-bold ui-sos-notifiche" id="sos-push" data-csrf="<?php echo $d->csrfToken; ?>" hidden><i class="fa fa-bell me-2" aria-hidden="true"></i>Attiva le notifiche su questo telefono</button>
        <div class="ui-sos-facoltativo mb-2" id="sos-push-stato" aria-live="polite"></div>
    <?php endif; ?>
    <?php echo $d->grezzo('avvisoHtml'); ?>

    <div id="sos-interventi" data-frammento="<?php echo $d->urlFrammento; ?>" data-ancora="<?php echo $d->ancora; ?>"><?php echo $d->grezzo('elencoHtml'); ?></div>

    <script nonce="<?php echo csp_nonce(); ?>">
    (function () {
        var box = document.getElementById('sos-interventi');
        if (!box) return;
        var scelta = box.dataset.ancora && document.getElementById(box.dataset.ancora);
        if (scelta && !location.hash) scelta.scrollIntoView({ block: 'start' });
        if (!window.fetch) return;
        // non si aggiorna mentre si sta compilando: un riquadro aperto, un campo scritto o con il cursore, la finestra di conferma
        function occupato() {
            if (document.hidden || document.body.classList.contains('modal-open') || box.querySelector('details[open]')) return true;
            var a = document.activeElement;
            if (a && box.contains(a) && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)) return true;
            return Array.prototype.some.call(box.querySelectorAll('textarea, input[type="number"]'), function (c) { return c.value !== ''; });
        }
        setInterval(function () {
            if (occupato()) return;
            fetch(box.dataset.frammento, { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) {
                    if (r.status === 403 || r.status === 404) { location.replace('sos_interventi.php'); return null; } // non è più sua
                    return r.ok && !r.redirected ? r.text() : null;
                })
                .then(function (t) {
                    if (t === null || occupato()) return;
                    var nuovo = document.createElement('template'); // dentro un template le foto non si scaricano
                    nuovo.innerHTML = t;
                    if (!nuovo.content.querySelector('[data-sos-elenco]')) return;
                    // le miniature già scaricate si riusano
                    nuovo.content.querySelectorAll('img[data-foto]').forEach(function (img) {
                        var vecchia = box.querySelector('img[data-foto][src="' + img.getAttribute('src') + '"]');
                        if (vecchia) img.replaceWith(vecchia);
                    });
                    box.replaceChildren(nuovo.content);
                })
                .catch(function () {});
        }, 30000);
    })();
    </script>

    <?php if ((int) $d->grezzo('push') === 1): ?>
        <script src="<?php echo $d->scriptPush; ?>"></script>
        <script nonce="<?php echo csp_nonce(); ?>">
        (function () {
            var btn = document.getElementById('sos-push'), st = document.getElementById('sos-push-stato'), P = window.AssistenzaPush, chiave = '';
            if (!P || !P.supportato()) { st.textContent = 'Questo browser non supporta le notifiche (su iPhone aggiungi prima il sito alla schermata Home).'; return; }
            function fatto() { btn.hidden = true; st.textContent = 'Le notifiche sono attive su questo telefono.'; }
            P.stato().then(function (s) {
                var endpoint = s.sottoscrizione ? s.sottoscrizione.endpoint : '';
                return fetch('push_persona_ajax.php?endpoint=' + encodeURIComponent(endpoint), { credentials: 'same-origin' }).then(function (r) { return r.json(); });
            }).then(function (j) {
                chiave = j.chiave || '';
                if (j.iscritto) fatto(); else btn.hidden = false;
            }).catch(function () {});
            btn.addEventListener('click', function () {
                btn.disabled = true;
                P.iscrivi(chiave).then(function (s) {
                    var corpo = new URLSearchParams({ csrf_token: btn.dataset.csrf, azione: 'iscrivi', sottoscrizione: JSON.stringify(s.toJSON()) });
                    return fetch('push_persona_ajax.php', { method: 'POST', body: corpo, credentials: 'same-origin' }).then(function (r) { return r.json(); });
                }).then(function (j) {
                    if (!j.ok) throw new Error(j.errore);
                    fatto();
                }).catch(function (e) { st.textContent = e.message || 'Non è stato possibile.'; btn.disabled = false; });
            });
        })();
        </script>
    <?php endif; ?>
<?php endif; ?>
</div>
