<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // Il registro: filtri, numeri del periodo, elenco con la cronologia. Dati: vedi RegistroSosPannello::dati ?>
<?php
$scelta = static function (string $nome, string $etichetta, string $tutte, string $chiave) use ($d): void {
    ?>
    <div class="col-6 col-md-3">
        <label class="form-label small fw-bold" for="sos-f-<?php echo $nome; ?>"><?php echo $etichetta; ?></label>
        <select class="form-select" id="sos-f-<?php echo $nome; ?>" name="<?php echo $nome; ?>">
            <option value=""><?php echo $tutte; ?></option>
            <?php foreach ($d->elenco($chiave) as $o): ?>
                <option value="<?php echo $o->valore; ?>"<?php if ((int) $o->grezzo('scelta') === 1): ?> selected<?php endif; ?>><?php echo $o->testo; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
};
?>
<form method="get" action="sos_aule.php" class="card border-0 shadow-sm mb-3"><div class="card-body">
    <input type="hidden" name="p_id" value="<?php echo $d->filtro; ?>">
    <input type="hidden" name="scheda" value="registro">
    <div class="row g-2">
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold" for="sos-f-dal">Dal</label>
            <input type="date" class="form-control" id="sos-f-dal" name="dal" value="<?php echo $d->dal; ?>">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold" for="sos-f-al">Al</label>
            <input type="date" class="form-control" id="sos-f-al" name="al" value="<?php echo $d->al; ?>">
        </div>
        <?php $scelta('aula', 'Aula', 'Tutte', 'aule'); ?>
        <?php $scelta('tipo', 'Tipo di problema', 'Tutti', 'tipi'); ?>
        <?php $scelta('stato', 'Stato', 'Tutti', 'stati'); ?>
        <?php $scelta('urgenza', 'Urgenza', 'Tutte', 'urgenze'); ?>
        <?php $scelta('studente', 'Studente', 'Tutti', 'studenti'); ?>
        <div class="col-6 col-md-3">
            <label class="form-label small fw-bold" for="sos-f-q">Testo</label>
            <input type="search" class="form-control" id="sos-f-q" name="q" value="<?php echo $d->testo; ?>" maxlength="100" placeholder="Codice, nome, parole">
        </div>
    </div>
    <div class="ui-sos-azioni">
        <button type="submit" class="btn btn-primary fw-bold"><i class="fa fa-filter me-1" aria-hidden="true"></i>Filtra</button>
        <a class="btn btn-outline-secondary" href="<?php echo $d->urlAzzera; ?>">Togli i filtri</a>
    </div>
</div></form>

<section class="mb-4" aria-labelledby="sos-numeri">
    <div class="ui-sos-intestazione">
        <h2 class="h5 mb-0" id="sos-numeri">Numeri del periodo: <?php echo $d->periodo; ?></h2>
        <?php echo $d->grezzo('scaricaNumeriHtml'); ?>
    </div>
    <p class="small text-secondary">I numeri seguono solo le date; gli altri filtri restringono l'elenco qui sotto.</p>
    <?php echo $d->grezzo('numeriHtml'); ?>

    <div class="row g-3">
        <?php foreach ([['perAula', 'Per aula', 'Aula'], ['perTipo', 'Per tipo di problema', 'Tipo di problema']] as [$chiave, $titolo, $colonna]): ?>
            <div class="col-12 col-md-6">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <h3 class="h6 fw-bold"><?php echo $titolo; ?></h3>
                    <?php if (!$d->elenco($chiave)): ?>
                        <p class="small text-secondary mb-0">Nessuna segnalazione nel periodo.</p>
                    <?php else: ?>
                        <table class="table table-sm small mb-0">
                            <thead><tr><th scope="col"><?php echo $colonna; ?></th><th scope="col" class="text-end">Quante</th></tr></thead>
                            <tbody>
                                <?php foreach ($d->elenco($chiave) as $v): ?>
                                    <tr><td class="text-break"><?php echo $v->nome; ?></td><td class="text-end"><?php echo $v->quante; ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div></div>
            </div>
        <?php endforeach; ?>
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <h3 class="h6 fw-bold">Aule malate</h3>
                <p class="small text-secondary">Stessa aula e stesso problema almeno <?php echo $d->soglia; ?> volte nel periodo (le annullate non contano): forse c'è qualcosa da cambiare.</p>
                <?php if (!$d->elenco('malate')): ?>
                    <p class="small mb-0">Nessuna.</p>
                <?php else: ?>
                    <table class="table table-sm small mb-0">
                        <thead><tr><th scope="col">Aula</th><th scope="col">Problema</th><th scope="col" class="text-end">Volte</th></tr></thead>
                        <tbody>
                            <?php foreach ($d->elenco('malate') as $m): ?>
                                <tr><td class="text-break"><?php echo $m->aula; ?></td><td class="text-break"><?php echo $m->tipo; ?></td><td class="text-end"><?php echo $m->quante; ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div></div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <h3 class="h6 fw-bold">Minuti degli studenti, per mese</h3>
                <?php if (!$d->elenco('minuti')): ?>
                    <p class="small text-secondary mb-0">Nessun minuto segnato nel periodo.</p>
                <?php else: ?>
                    <table class="table table-sm small mb-0">
                        <thead><tr><th scope="col">Studente</th><th scope="col">Mese</th><th scope="col" class="text-end">Minuti</th><th scope="col" class="text-end">Volte</th></tr></thead>
                        <tbody>
                            <?php foreach ($d->elenco('minuti') as $m): ?>
                                <tr><td class="text-break"><?php echo $m->studente; ?></td><td><?php echo $m->mese; ?></td><td class="text-end"><?php echo $m->minuti; ?></td><td class="text-end"><?php echo $m->interventi; ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div></div>
        </div>
    </div>
</section>

<section aria-labelledby="sos-elenco">
    <div class="ui-sos-intestazione">
        <h2 class="h5 mb-0" id="sos-elenco">Segnalazioni (<?php echo $d->quante; ?>)</h2>
        <?php echo $d->grezzo('scaricaElencoHtml'); ?>
    </div>
    <?php if (!$d->elenco('righe')): ?>
        <div class="card border-0 shadow-sm"><div class="card-body text-secondary">Nessuna segnalazione con questi filtri.</div></div>
    <?php endif; ?>
    <?php foreach ($d->elenco('righe') as $s): ?>
        <details class="ui-sos-riga" id="sos-<?php echo $s->id; ?>">
            <summary>
                <span class="ui-sos-riga-titolo"><strong><?php echo $s->aula; ?></strong> · <i class="fa <?php echo $s->icona; ?>" aria-hidden="true"></i> <?php echo $s->tipo; ?></span>
                <span class="ui-stato <?php echo $s->colore; ?>"><?php echo $s->stato; ?></span>
                <span class="ui-sos-riga-sotto"><?php echo $s->codice; ?> · <?php echo $s->quando; ?> · <?php echo $s->urgenza; ?> · <?php echo $s->docente; ?></span>
            </summary>
            <div class="ui-sos-riga-corpo">
                <ul class="ui-sos-dati">
                    <?php if ($s->edificio !== ''): ?><li><span>Edificio:</span> <?php echo $s->edificio; ?></li><?php endif; ?>
                    <?php if ($s->descrizione !== ''): ?><li><span>Descrizione:</span> <?php echo $s->descrizione; ?></li><?php endif; ?>
                    <?php if ($s->email !== ''): ?><li><span>Email:</span> <?php echo $s->email; ?></li><?php endif; ?>
                    <?php if ($s->telefono !== ''): ?><li><span>Cellulare:</span> <?php echo $s->telefono; ?></li><?php endif; ?>
                    <?php foreach ($s->elenco('aggiunti') as $a): ?><li><span><?php echo $a->etichetta; ?>:</span> <?php echo $a->valore; ?></li><?php endforeach; ?>
                    <?php if ($s->studente !== ''): ?><li><span>Studente:</span> <?php echo $s->studente; ?></li><?php endif; ?>
                    <?php if ($s->esito !== ''): ?><li><span>Non risolto:</span> <?php echo $s->esito; ?></li><?php endif; ?>
                    <?php if ($s->inoltrata !== ''): ?><li><span>Inoltrata a:</span> <?php echo $s->inoltrata; ?></li><?php endif; ?>
                    <?php if ($s->foto !== ''): ?><li><a href="<?php echo $s->foto; ?>" target="_blank" rel="noopener">Foto<span class="visually-hidden"> della segnalazione <?php echo $s->codice; ?></span></a></li><?php endif; ?>
                </ul>
                <h3 class="h6 fw-bold">Cosa è successo</h3>
                <ul class="ui-sos-cronologia con-giorno">
                    <?php foreach ($s->elenco('cronologia') as $c): ?>
                        <li><time><?php echo $c->ora; ?></time><span><?php echo $c->testo; ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </details>
    <?php endforeach; ?>
    <?php if ((int) $d->grezzo('altre') > 0): ?>
        <p class="small text-secondary">Ce ne sono altre <?php echo $d->altre; ?>: stringi i filtri o scarica l'elenco.</p>
    <?php endif; ?>
</section>
