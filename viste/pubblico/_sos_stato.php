<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // La parte che si aggiorna da sola. Dati: stato, tono, icona, titolo, riga, passiHtml, cronologia (ora, testo), foto, aperta ?>
<div data-sos-stato="<?php echo $d->stato; ?>">
    <div class="ui-sos-box <?php echo $d->tono; ?>" role="status"><i class="fa <?php echo $d->icona; ?>" aria-hidden="true"></i><div>
        <p><strong><?php echo $d->titolo; ?></strong></p>
        <p><?php echo $d->riga; ?></p>
    </div></div>
    <?php echo $d->grezzo('passiHtml'); ?>
    <section class="ui-sos-riquadro">
        <h2>Cosa è successo</h2>
        <ul class="ui-sos-cronologia">
            <?php foreach ($d->elenco('cronologia') as $c): ?>
                <li><time><?php echo $c->ora; ?></time><span><?php echo $c->testo; ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?php if ($d->foto !== ''): ?><a class="btn btn-sm btn-outline-secondary mt-2" href="<?php echo $d->foto; ?>" target="_blank" rel="noopener"><i class="fa fa-camera me-1" aria-hidden="true"></i>Guarda la foto</a><?php endif; ?>
        <?php if ((int) $d->grezzo('aperta') === 1): ?><div class="ui-sos-facoltativo mt-2">La pagina si aggiorna da sola.</div><?php endif; ?>
    </section>
</div>
