<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // Dati: schede (titolo, icona, attiva, url), corpo (HTML della scheda aperta) ?>
<div class="ui-sos">
    <header class="pn-testata">
        <div class="pn-testata-riga">
            <span class="pn-testata-ico" aria-hidden="true"><i class="fa fa-life-ring"></i></span>
            <div class="pn-testata-testi">
                <h1 class="pn-titolo">SOS aule</h1>
                <p class="pn-aiuto">Il docente inquadra il QR in aula e segnala il problema; la segnalazione arriva a chi segue quell'aula. Qui si preparano le aule, le persone e il modulo.</p>
            </div>
            <div class="pn-testata-azioni"><a class="btn btn-outline-secondary btn-sm" href="../guida.php?r=sos_gestione" target="_blank" rel="noopener"><i class="fa fa-circle-question me-1" aria-hidden="true"></i>Guida</a></div>
        </div>
    </header>

    <nav class="pn-schede" aria-label="Sezioni di SOS aule">
        <?php foreach ($d->elenco('schede') as $s): ?>
            <a class="pn-scheda<?php echo $s->attiva ? ' attiva' : ''; ?>" href="<?php echo $s->url; ?>"<?php echo $s->attiva ? ' aria-current="page"' : ''; ?>><i class="fa <?php echo $s->icona; ?>" aria-hidden="true"></i><span><?php echo $s->titolo; ?></span></a>
        <?php endforeach; ?>
    </nav>

    <?php echo $d->grezzo('corpo'); ?>
</div>
