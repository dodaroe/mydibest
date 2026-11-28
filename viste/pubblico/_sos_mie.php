<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // «Le mie segnalazioni». Dati: mie (href, titolo, sotto, stato, colore) ?>
<?php $mie = $d->elenco('mie'); ?>
<?php if ($mie): ?>
<section class="ui-sos-riquadro mt-4">
    <h2>Le mie segnalazioni</h2>
    <?php foreach ($mie as $s): ?>
        <a class="ui-sos-mia" href="<?php echo $s->href; ?>"><span><strong><?php echo $s->titolo; ?></strong><small><?php echo $s->sotto; ?></small></span><span class="ui-stato <?php echo $s->colore; ?>"><?php echo $s->stato; ?></span></a>
    <?php endforeach; ?>
</section>
<?php endif; ?>
