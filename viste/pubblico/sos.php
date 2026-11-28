<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // SOS aule, pagina di chi chiede aiuto. Dati: quale (form | messaggio | segnalazione), avvisoHtml, temaHtml, mie; per ogni «quale»: vedi la classe PaginaSos ?>
<?php echo $d->grezzo('temaHtml'); ?>
<div class="ui-sos-pagina">
    <?php echo $d->grezzo('avvisoHtml'); ?>
<?php if ((string) $d->grezzo('quale') === 'form'): ?>
    <?php echo $d->parziale('pubblico/_sos_form', $d); ?>

<?php elseif ((string) $d->grezzo('quale') === 'messaggio'): ?>
    <h1>SOS assistenza didattica</h1>
    <div class="ui-sos-box <?php echo $d->tono; ?>" role="alert"><i class="fa fa-circle-info" aria-hidden="true"></i><div>
        <p><strong><?php echo $d->testo; ?></strong></p>
        <?php if ($d->telefonoRiserva !== ''): ?><p>Se è urgente chiama <a href="<?php echo $d->telefonoRiservaHref; ?>"><?php echo $d->telefonoRiserva; ?></a>.</p><?php endif; ?>
    </div></div>

<?php else: ?>
    <h1 class="visually-hidden">La tua segnalazione SOS</h1>
    <div id="sos-stato" data-frammento="<?php echo $d->urlFrammento; ?>" data-aperta="<?php echo $d->aperta; ?>"><?php echo $d->grezzo('statoHtml'); ?></div>

    <?php if ((int) $d->grezzo('puoScrivere') === 1): ?>
        <form method="post" action="<?php echo $d->azione; ?>" class="ui-sos-riquadro">
            <?php echo $d->grezzo('campoCsrf'); ?>
            <input type="hidden" name="azione" value="nota">
            <label class="ui-sos-titolo" for="sos-nota">Aggiungi una nota</label>
            <textarea class="form-control mb-2" id="sos-nota" name="nota" rows="2" maxlength="<?php echo $d->maxNota; ?>"></textarea>
            <button type="submit" class="btn btn-outline-primary fw-bold">Aggiungi la nota</button>
        </form>
        <form method="post" action="<?php echo $d->azione; ?>" class="mb-3">
            <?php echo $d->grezzo('campoCsrf'); ?>
            <input type="hidden" name="azione" value="annulla">
            <button type="submit" class="btn btn-outline-secondary fw-bold ap-grande ui-sos-invia" data-conferma="Annulli la richiesta di aiuto? Chi segue l'aula non interverrà." data-conferma-pulsante="Sì, ho risolto">Annulla, ho risolto da solo</button>
        </form>
    <?php endif; ?>

    <?php if ((int) $d->grezzo('riapribile') === 1): ?>
        <form method="post" action="<?php echo $d->azione; ?>" class="ui-sos-riquadro">
            <?php echo $d->grezzo('campoCsrf'); ?>
            <input type="hidden" name="azione" value="riapri">
            <h2>Il problema c'è ancora?</h2>
            <button type="submit" class="btn btn-outline-danger fw-bold ap-grande ui-sos-invia"><i class="fa fa-rotate-left me-2" aria-hidden="true"></i>Non è risolto</button>
            <div class="ui-sos-facoltativo mt-2">Puoi dirlo entro <?php echo $d->riapriEntro; ?>: la segnalazione torna al referente.</div>
        </form>
    <?php endif; ?>

    <?php if ((int) $d->grezzo('aperta') === 1): ?>
        <script nonce="<?php echo csp_nonce(); ?>">
        (function () {
            var box = document.getElementById('sos-stato');
            if (!box || !window.fetch) return;
            setInterval(function () {
                if (document.hidden) return;
                fetch(box.dataset.frammento, { credentials: 'same-origin', cache: 'no-store' })
                    .then(function (r) { return r.ok && !r.redirected ? r.text() : null; })
                    .then(function (t) {
                        if (t === null) return;
                        var nuovo = document.createElement('div');
                        nuovo.innerHTML = t;
                        var dopo = nuovo.querySelector('[data-sos-stato]'), prima = box.querySelector('[data-sos-stato]'), nota = document.getElementById('sos-nota');
                        if (!dopo || !prima) return;
                        // se lo stato è cambiato si ricarica tutto (cambiano anche i pulsanti), a meno che si stia scrivendo una nota
                        if (dopo.dataset.sosStato !== prima.dataset.sosStato && (!nota || nota.value === '')) { location.reload(); return; }
                        box.innerHTML = t;
                    })
                    .catch(function () {});
            }, 20000);
        })();
        </script>
    <?php endif; ?>
<?php endif; ?>

    <?php echo $d->parziale('pubblico/_sos_mie', ['mie' => $d->grezzo('mie')]); ?>
</div>
