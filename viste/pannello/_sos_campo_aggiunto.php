<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // Una riga dei campi aggiunti: i nomi che legge CampiSos::salvaAggiunti (App\Forms\CostruttoreCampi). Dati: c (chiave, etichetta, tipi, opzioni, obbligatorio, aiuto, visibile) ?>
<?php $c = $d->c; ?>
<fieldset class="ui-sos-campo">
    <legend class="visually-hidden">Campo aggiunto</legend>
    <input type="hidden" name="sx_chiave[]" value="<?php echo $c->chiave; ?>">
    <div class="row g-2">
        <div class="col-md-5"><label class="form-label small fw-bold w-100">Domanda<input type="text" class="form-control form-control-sm" name="sx_etichetta[]" maxlength="200" value="<?php echo $c->etichetta; ?>"></label></div>
        <div class="col-md-3"><label class="form-label small fw-bold w-100">Tipo<select class="form-select form-select-sm" name="sx_tipo[]">
            <?php foreach ($c->elenco('tipi') as $t): ?><option value="<?php echo $t->valore; ?>"<?php echo $t->scelto ? ' selected' : ''; ?>><?php echo $t->nome; ?></option><?php endforeach; ?>
        </select></label></div>
        <div class="col-md-4"><label class="form-label small fw-bold w-100">Scelte (solo per le scelte, separate da virgola)<input type="text" class="form-control form-control-sm" name="sx_opzioni[]" value="<?php echo $c->opzioni; ?>"></label></div>
        <div class="col-md-5"><label class="form-label small fw-bold w-100">Aiuto (facoltativo)<input type="text" class="form-control form-control-sm" name="sx_aiuto[]" maxlength="300" value="<?php echo $c->aiuto; ?>"></label></div>
        <div class="col-6 col-md-2"><label class="form-label small fw-bold w-100">Obbligatorio<select class="form-select form-select-sm" name="sx_obbl[]"><option value="0">No</option><option value="1"<?php echo $c->obbligatorio ? ' selected' : ''; ?>>Sì</option></select></label></div>
        <div class="col-6 col-md-2"><label class="form-label small fw-bold w-100">Visibile<select class="form-select form-select-sm" name="sx_vis[]"><option value="1">Sì</option><option value="0"<?php echo $c->visibile ? '' : ' selected'; ?>>No</option></select></label></div>
        <div class="col-md-3 d-flex align-items-end mb-2"><button type="button" class="btn btn-sm btn-outline-danger" data-sos-togli><i class="fa fa-times me-1" aria-hidden="true"></i>Togli il campo</button></div>
    </div>
</fieldset>
