<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // Le schede e le card: la parte che si aggiorna da sola. Dati: scheda, linguette (testo, numero, attiva, url), card (vedi _sos_card), vuoto ?>
<div data-sos-elenco="<?php echo $d->scheda; ?>">
    <nav class="ui-sos-linguette" aria-label="Segnalazioni">
        <?php foreach ($d->elenco('linguette') as $l): ?>
            <a href="<?php echo $l->url; ?>"<?php if ((int) $l->grezzo('attiva') === 1): ?> aria-current="page"<?php endif; ?>><?php echo $l->testo; ?> (<?php echo $l->numero; ?>)</a>
        <?php endforeach; ?>
    </nav>
    <?php $card = $d->elenco('card'); ?>
    <?php if (!$card): ?><p class="ui-sos-sotto"><?php echo $d->vuoto; ?></p><?php endif; ?>
    <div class="ui-sos-elenco">
        <?php foreach ($card as $c): ?>
            <?php echo $d->parziale('pubblico/_sos_card', $c); ?>
        <?php endforeach; ?>
    </div>
</div>
