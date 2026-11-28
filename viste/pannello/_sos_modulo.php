<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // Dati: campoCsrf (HTML), fissi, righe e vuota (campi aggiunti), massimo, erroreAggiunti, elenchi (tipi ed esiti), anteprima (intro, campi, aule, tipiProblema, urgenze) ?>
<?php $csrf = $d->grezzo('campoCsrf'); ?>
<h2 class="h5 fw-bold">Campi fissi del modulo</h2>
<div class="d-flex flex-column gap-2 mb-4">
<?php foreach ($d->elenco('fissi') as $c): ?>
    <details class="card border-0 shadow-sm" id="campo-<?php echo $c->chiave; ?>">
        <summary class="card-body py-2"><strong><?php echo $c->etichetta; ?></strong>
            <span class="small text-secondary">
                <?php echo $c->visibile ? ($c->obbligatorio ? 'obbligatorio' : 'facoltativo') : 'nascosto'; ?><?php echo $c->cambiato ? ' · personalizzato (in origine «' . $c->originale . '»)' : ''; ?>
            </span></summary>
        <form method="POST" class="card-body pt-0">
            <?php echo $csrf; ?><input type="hidden" name="chiave" value="<?php echo $c->chiave; ?>">
            <div class="row g-2">
                <div class="col-md-5"><label class="form-label small fw-bold w-100">Nome del campo<input type="text" class="form-control form-control-sm" name="etichetta" maxlength="200" required value="<?php echo $c->etichetta; ?>"></label></div>
                <div class="col-md-7"><label class="form-label small fw-bold w-100">Aiuto (facoltativo)<input type="text" class="form-control form-control-sm" name="aiuto" maxlength="300" value="<?php echo $c->aiuto; ?>"></label></div>
            </div>
            <div class="d-flex flex-wrap gap-3 mb-2">
                <label class="form-check"><input class="form-check-input" type="checkbox" name="obbligatorio" value="1"<?php echo $c->obbligatorio ? ' checked' : ''; ?><?php echo $c->fisso_obb ? ' disabled' : ''; ?>> <span class="form-check-label">Obbligatorio</span></label>
                <label class="form-check"><input class="form-check-input" type="checkbox" name="visibile" value="1"<?php echo $c->visibile ? ' checked' : ''; ?><?php echo $c->fisso_vis ? ' disabled' : ''; ?>> <span class="form-check-label">Visibile</span></label>
            </div>
            <div class="ui-sos-azioni">
                <button type="submit" name="azione" value="campo_salva" class="btn btn-sm btn-azione fw-bold">Salva</button>
                <button type="submit" name="azione" value="campo_originale" class="btn btn-sm btn-outline-secondary" formnovalidate<?php echo $c->cambiato ? '' : ' disabled'; ?>><i class="fa fa-rotate-left me-1" aria-hidden="true"></i>Torna all'originale</button>
            </div>
        </form>
    </details>
<?php endforeach; ?>
</div>

<section class="card border-0 shadow-sm mb-4" id="aggiunti"><div class="card-body">
    <h2 class="h5 fw-bold">Campi aggiunti</h2>
    <p class="pn-aiuto mb-2">Altre domande in fondo al modulo (al massimo <?php echo (int) $d->massimo; ?>). Un campo con risposte non si elimina e non cambia tipo: si può solo nascondere.</p>
    <?php if ($d->erroreAggiunti !== ''): ?><div class="alert alert-danger py-2" role="alert"><?php echo $d->erroreAggiunti; ?></div><?php endif; ?>
    <form method="POST">
        <?php echo $csrf; ?><input type="hidden" name="azione" value="aggiunti_salva">
        <div id="sos-righe" data-massimo="<?php echo (int) $d->massimo; ?>">
            <?php foreach ($d->elenco('righe') as $riga): ?><?php echo $d->parziale('pannello/_sos_campo_aggiunto', ['c' => $riga]); ?><?php endforeach; ?>
        </div>
        <template id="sos-riga-vuota"><?php echo $d->parziale('pannello/_sos_campo_aggiunto', ['c' => $d->vuota]); ?></template>
        <div class="ui-sos-azioni">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="sos-aggiungi-campo"><i class="fa fa-plus me-1" aria-hidden="true"></i>Aggiungi campo</button>
            <button type="submit" class="btn btn-sm btn-azione fw-bold">Salva i campi aggiunti</button>
        </div>
    </form>
</div></section>

<?php foreach ($d->elenco('elenchi') as $el): ?>
<section class="card border-0 shadow-sm mb-4" id="<?php echo $el->quale; ?>"><div class="card-body">
    <h2 class="h5 fw-bold"><?php echo $el->titolo; ?></h2>
    <p class="pn-aiuto mb-2"><?php echo $el->spiegazione; ?> Una voce già usata da una segnalazione non si cancella: si spegne.</p>
    <ul class="ui-sos-lista">
    <?php foreach ($el->elenco('voci') as $v): $id = (int) $v->id; ?>
        <li>
            <div class="ui-sos-testo">
                <i class="fa <?php echo $v->icona; ?> ui-w-20 text-center me-1" aria-hidden="true"></i><strong><?php echo $v->nome; ?></strong>
                <?php echo $v->attivo ? '' : \App\Portale\Vista\Pannello::stato('Spenta', 'spento'); ?>
                <?php echo $v->chiedeTesto ? '<span class="small text-secondary">· chiede testo</span>' : ''; ?>
                <?php if ($v->aiuto !== ''): ?><div class="small text-secondary"><?php echo $v->aiuto; ?></div><?php endif; ?>
                <details class="ui-sos-dettagli">
                    <summary>Modifica</summary>
                    <form method="POST" class="mt-2">
                        <?php echo $csrf; ?><input type="hidden" name="azione" value="voce_salva"><input type="hidden" name="elenco" value="<?php echo $el->quale; ?>"><input type="hidden" name="id" value="<?php echo $id; ?>">
                        <?php echo $d->parziale('pannello/_sos_voce', ['v' => $v]); ?>
                        <button type="submit" class="btn btn-sm btn-azione fw-bold">Salva la voce</button>
                    </form>
                </details>
            </div>
            <div class="ui-sos-azioni mt-0">
                <form method="POST" class="m-0"><?php echo $csrf; ?><input type="hidden" name="azione" value="voce_sposta"><input type="hidden" name="elenco" value="<?php echo $el->quale; ?>"><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="verso" value="su">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Sposta su «<?php echo $v->nome; ?>»" title="Sposta su"<?php echo $v->prima ? ' disabled' : ''; ?>><i class="fa fa-arrow-up" aria-hidden="true"></i></button></form>
                <form method="POST" class="m-0"><?php echo $csrf; ?><input type="hidden" name="azione" value="voce_sposta"><input type="hidden" name="elenco" value="<?php echo $el->quale; ?>"><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="verso" value="giu">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Sposta giù «<?php echo $v->nome; ?>»" title="Sposta giù"<?php echo $v->ultima ? ' disabled' : ''; ?>><i class="fa fa-arrow-down" aria-hidden="true"></i></button></form>
                <form method="POST" class="m-0"><?php echo $csrf; ?><input type="hidden" name="azione" value="voce_stato"><input type="hidden" name="elenco" value="<?php echo $el->quale; ?>"><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="valore" value="<?php echo $v->attivo ? 0 : 1; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo $v->attivo ? 'Spegni' : 'Accendi'; ?></button></form>
                <form method="POST" class="m-0"><?php echo $csrf; ?><input type="hidden" name="azione" value="voce_elimina"><input type="hidden" name="elenco" value="<?php echo $el->quale; ?>"><input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" data-conferma="Cancellare la voce «<?php echo $v->nome; ?>»?" aria-label="Cancella «<?php echo $v->nome; ?>»" title="Cancella"><i class="fa fa-trash" aria-hidden="true"></i></button></form>
            </div>
        </li>
    <?php endforeach; ?>
    </ul>
    <details class="ui-sos-dettagli">
        <summary>Aggiungi una voce</summary>
        <form method="POST" class="mt-2">
            <?php echo $csrf; ?><input type="hidden" name="azione" value="voce_aggiungi"><input type="hidden" name="elenco" value="<?php echo $el->quale; ?>">
            <?php echo $d->parziale('pannello/_sos_voce', ['v' => $el->nuova]); ?>
            <button type="submit" class="btn btn-sm btn-azione fw-bold">Aggiungi</button>
        </form>
    </details>
</div></section>
<?php endforeach; ?>

<?php $ant = $d->anteprima; ?>
<section class="card border-0 shadow-sm mb-3" id="anteprima"><div class="card-body">
    <h2 class="h5 fw-bold">Anteprima: come lo vedrà il docente</h2>
    <p class="pn-aiuto mb-2">Solo da guardare: mostra quello che è già salvato.</p>
    <fieldset class="ui-sos-anteprima" disabled>
        <legend class="visually-hidden">Anteprima del modulo</legend>
        <?php if ($ant->intro !== ''): ?><p><?php echo nl2br($ant->intro); ?></p><?php endif; ?>
        <?php foreach ($ant->elenco('campi') as $c): $forma = $c->forma; ?>
            <div class="mb-3">
                <div class="fw-bold"><?php echo $c->etichetta; ?><?php echo $c->obbligatorio ? ' <span class="ui-sos-obbligatorio" title="obbligatorio">*</span>' : ''; ?></div>
                <?php if ($c->aiuto !== ''): ?><div class="small text-secondary mb-1"><?php echo $c->aiuto; ?></div><?php endif; ?>
                <?php if ($forma === 'aula'): ?>
                    <select class="form-select" aria-label="<?php echo $c->etichetta; ?>"><option>Scegli l'aula</option><?php foreach ($ant->elenco('aule') as $aula): ?><option><?php echo $aula; ?></option><?php endforeach; ?></select>
                <?php elseif ($forma === 'tipo'): ?>
                    <div class="ui-sos-tipi">
                        <?php foreach ($ant->elenco('tipiProblema') as $t): ?>
                            <div class="ui-sos-tipo"><i class="fa <?php echo $t->icona; ?>" aria-hidden="true"></i><span><?php echo $t->nome; ?></span><?php if ($t->aiuto !== ''): ?><small><?php echo $t->aiuto; ?></small><?php endif; ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($forma === 'urgenza'): ?>
                    <?php foreach ($ant->elenco('urgenze') as $u): ?><label class="form-check"><input class="form-check-input" type="radio"> <span class="form-check-label"><?php echo $u; ?></span></label><?php endforeach; ?>
                <?php elseif ($forma === 'radio' || $forma === 'caselle'): ?>
                    <?php foreach ($c->elenco('scelte') as $scelta): ?><label class="form-check"><input class="form-check-input" type="<?php echo $forma === 'radio' ? 'radio' : 'checkbox'; ?>"> <span class="form-check-label"><?php echo $scelta; ?></span></label><?php endforeach; ?>
                <?php elseif ($forma === 'casella'): ?>
                    <label class="form-check"><input class="form-check-input" type="checkbox"> <span class="form-check-label">Sì</span></label>
                <?php elseif ($forma === 'lungo'): ?>
                    <textarea class="form-control" rows="3" aria-label="<?php echo $c->etichetta; ?>"></textarea>
                <?php else: ?>
                    <input class="form-control" type="<?php echo $forma === 'file' ? 'file' : ($forma === 'data' ? 'date' : 'text'); ?>" aria-label="<?php echo $c->etichetta; ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <button type="button" class="btn btn-azione fw-bold w-100">Invia la richiesta di aiuto</button>
    </fieldset>
</div></section>

<script nonce="<?php echo csp_nonce(); ?>">
(function () {
    var righe = document.getElementById('sos-righe');
    var modello = document.getElementById('sos-riga-vuota');
    var aggiungi = document.getElementById('sos-aggiungi-campo');
    if (!righe || !modello || !aggiungi) { return; }
    function conta() { aggiungi.disabled = righe.children.length >= parseInt(righe.dataset.massimo, 10); }
    aggiungi.addEventListener('click', function () {
        righe.appendChild(modello.content.cloneNode(true));
        righe.lastElementChild.querySelector('input[type=text]').focus();
        conta();
    });
    righe.addEventListener('click', function (e) {
        var b = e.target.closest('[data-sos-togli]');
        if (b) { b.closest('.ui-sos-campo').remove(); conta(); }
    });
    conta();
})();
</script>
