<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // I cartelli da appendere in aula, un foglio A4 ciascuno. Dati: cartelli (nome, edificio, qr HTML, indirizzo), telefono, indietro ?>
<?php $cartelli = $d->elenco('cartelli'); ?>
<div class="ui-cartelli">
    <div class="ui-cartelli-barra">
        <h1 class="pn-titolo">Cartelli SOS aule</h1>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a class="btn btn-outline-secondary" href="<?php echo $d->indietro; ?>"><i class="fa fa-arrow-left me-1" aria-hidden="true"></i>Torna alle aule</a>
            <?php if ($cartelli): ?><button type="button" class="btn btn-azione fw-bold" data-stampa><i class="fa fa-print me-1" aria-hidden="true"></i>Stampa</button><?php endif; ?>
        </div>
    </div>
    <?php if (!$cartelli): ?><p class="text-secondary">Non ci sono aule attive.</p><?php endif; ?>

    <?php foreach ($cartelli as $c): ?>
        <section class="ui-cartello">
            <p class="ui-cartello-titolo">SOS aula</p>
            <h2 class="ui-cartello-nome"><?php echo $c->nome; ?></h2>
            <?php if ($c->edificio !== ''): ?><p class="ui-cartello-edificio"><?php echo $c->edificio; ?></p><?php endif; ?>
            <div class="ui-cartello-riquadro"><?php echo $c->grezzo('qr'); ?></div>
            <ol class="ui-cartello-passi">
                <li>Inquadra il QR</li>
                <li>Entra con le credenziali di Ateneo</li>
                <li>Scegli il problema e invia</li>
            </ol>
            <?php if ($d->telefono !== ''): ?><p class="ui-cartello-telefono">Il telefono non prende? Chiama <strong><?php echo $d->telefono; ?></strong></p><?php endif; ?>
            <p class="ui-cartello-indirizzo">Non riesci a inquadrare? Scrivi nel browser:<br><?php echo $c->indirizzo; ?></p>
        </section>
    <?php endforeach; ?>
</div>
