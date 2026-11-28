<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // Una persona di una lista. Dati: p (persona_id, nominativo, email, avvisi), ambito (aula|riserva), aula (id o 0), campoCsrf (HTML) ?>
<?php $p = $d->p; ?>
<li>
    <div class="ui-sos-testo">
        <strong><?php echo $p->nominativo; ?></strong>
        <?php if ($p->email !== ''): ?><span class="text-secondary small"><?php echo $p->email; ?></span><?php endif; ?>
        <?php foreach ($p->elenco('avvisi') as $avviso): ?>
            <div class="ui-sos-avviso"><i class="fa fa-triangle-exclamation me-1" aria-hidden="true"></i><?php echo $avviso; ?></div>
        <?php endforeach; ?>
    </div>
    <form method="POST" class="m-0">
        <?php echo $d->grezzo('campoCsrf'); ?><input type="hidden" name="azione" value="referente_togli"><input type="hidden" name="ambito" value="<?php echo $d->ambito; ?>">
        <input type="hidden" name="aula_id" value="<?php echo (int) $d->aula; ?>"><input type="hidden" name="persona" value="<?php echo $p->persona_id; ?>">
        <button type="submit" class="btn btn-sm btn-outline-danger" data-conferma="Togliere <?php echo $p->nominativo; ?> da questa lista?" data-conferma-pulsante="Sì, togli">Togli</button>
    </form>
</li>
