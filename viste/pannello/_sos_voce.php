<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // I campi di un tipo di problema o di un esito. Dati: v (nome, aiuto, chiedeTesto, icone) ?>
<?php $v = $d->v; ?>
<div class="row g-2">
    <div class="col-md-4"><label class="form-label small fw-bold w-100">Nome<input type="text" class="form-control form-control-sm" name="nome" maxlength="120" required value="<?php echo $v->nome; ?>"></label></div>
    <div class="col-md-5"><label class="form-label small fw-bold w-100">Aiuto (facoltativo)<input type="text" class="form-control form-control-sm" name="aiuto" maxlength="255" value="<?php echo $v->aiuto; ?>"></label></div>
    <div class="col-md-3"><label class="form-label small fw-bold w-100">Icona<select class="form-select form-select-sm" name="icona">
        <?php foreach ($v->elenco('icone') as $i): ?><option value="<?php echo $i->valore; ?>"<?php echo $i->scelta ? ' selected' : ''; ?>><?php echo $i->nome; ?></option><?php endforeach; ?>
    </select></label></div>
</div>
<label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="chiede_testo" value="1"<?php echo $v->chiedeTesto ? ' checked' : ''; ?>> <span class="form-check-label">Chiede testo (chi la sceglie deve scrivere qualcosa)</span></label>
