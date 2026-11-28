<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // Dati: campoCsrf (HTML), scoperte (nomi), conAnagrafe, aule (record + persone, ricerca HTML), riserva, quantiRiserva, ricercaRiserva (HTML), studenti (record + modulo), nuovo (modulo) ?>
<?php $csrf = $d->grezzo('campoCsrf'); $scoperte = $d->elenco('scoperte'); $studenti = $d->elenco('studenti'); ?>
<?php if ($scoperte): ?>
    <div class="alert alert-danger" role="alert"><i class="fa fa-triangle-exclamation me-1" aria-hidden="true"></i><strong>Per queste aule nessuno riceverebbe le segnalazioni:</strong> <?php echo implode(', ', $scoperte); ?>. Aggiungi un referente con email, o un responsabile di riserva.</div>
<?php endif; ?>
<?php if (!$d->conAnagrafe): ?>
    <div class="alert alert-warning">L'anagrafe del personale di Ateneo è vuota: caricala da <a href="anagrafe_personale.php?vista=strutture">Anagrafe personale</a> per poter scegliere i referenti.</div>
<?php endif; ?>

<h2 class="h5 fw-bold">Referenti delle aule</h2>
<p class="pn-aiuto mb-2">Ricevono le segnalazioni della loro aula. Si scelgono dall'anagrafe di Ateneo.</p>
<?php if (!$d->elenco('aule')): ?><p class="text-secondary">Aggiungi prima le aule, nella scheda «Aule».</p><?php endif; ?>
<div class="d-flex flex-column gap-2 mb-4">
<?php foreach ($d->elenco('aule') as $a): $aula = (int) $a->id; ?>
    <section class="card border-0 shadow-sm" id="ref-<?php echo $aula; ?>"><div class="card-body">
        <h3 class="h6 fw-bold mb-1"><?php echo $a->nome; ?> <span class="text-secondary fw-normal"><?php echo $a->edificio; ?></span>
            <?php echo $a->attiva ? '' : \App\Portale\Vista\Pannello::stato('Spenta', 'spento'); ?></h3>
        <ul class="ui-sos-lista">
            <?php foreach ($a->elenco('persone') as $p): ?>
                <?php echo $d->parziale('pannello/_sos_persona', ['p' => $p, 'ambito' => 'aula', 'aula' => $aula, 'campoCsrf' => $csrf]); ?>
            <?php endforeach; ?>
        </ul>
        <?php if ((int) $a->quanti === 0): ?><p class="small text-secondary mb-1">Nessun referente: le segnalazioni vanno ai responsabili di riserva.</p><?php endif; ?>
        <?php if ($d->conAnagrafe): ?>
            <details class="ui-sos-dettagli">
                <summary>Aggiungi un referente</summary>
                <form method="POST" class="mt-2" data-sos-persona>
                    <?php echo $csrf; ?><input type="hidden" name="azione" value="referente_aggiungi"><input type="hidden" name="ambito" value="aula"><input type="hidden" name="aula_id" value="<?php echo $aula; ?>"><input type="hidden" name="persona" value="">
                    <?php echo $a->grezzo('ricerca'); ?>
                </form>
            </details>
        <?php endif; ?>
    </div></section>
<?php endforeach; ?>
</div>

<section class="card border-0 shadow-sm mb-4" id="riserva"><div class="card-body">
    <h2 class="h5 fw-bold">Responsabili di riserva</h2>
    <p class="pn-aiuto mb-2">Ricevono le segnalazioni delle aule senza un referente, e possono lavorare quelle di tutte le aule.</p>
    <ul class="ui-sos-lista">
        <?php foreach ($d->elenco('riserva') as $p): ?>
            <?php echo $d->parziale('pannello/_sos_persona', ['p' => $p, 'ambito' => 'riserva', 'aula' => 0, 'campoCsrf' => $csrf]); ?>
        <?php endforeach; ?>
    </ul>
    <?php if ((int) $d->quantiRiserva === 0): ?><p class="small text-secondary mb-1">Nessun responsabile di riserva.</p><?php endif; ?>
    <?php if ($d->conAnagrafe): ?>
        <details class="ui-sos-dettagli">
            <summary>Aggiungi un responsabile di riserva</summary>
            <form method="POST" class="mt-2" data-sos-persona>
                <?php echo $csrf; ?><input type="hidden" name="azione" value="referente_aggiungi"><input type="hidden" name="ambito" value="riserva"><input type="hidden" name="aula_id" value="0"><input type="hidden" name="persona" value="">
                <?php echo $d->grezzo('ricercaRiserva'); ?>
            </form>
        </details>
    <?php endif; ?>
</div></section>

<section class="card border-0 shadow-sm mb-3" id="studenti"><div class="card-body">
    <h2 class="h5 fw-bold">Studenti part-time</h2>
    <p class="pn-aiuto mb-2">Il referente può mandarli in aula. Entrano nel portale con la loro email istituzionale; contano solo nel periodo indicato.</p>
    <ul class="ui-sos-lista">
    <?php foreach ($studenti as $s): $sm = $s->modulo; ?>
        <li>
            <div class="ui-sos-testo">
                <strong><?php echo $s->nominativo; ?></strong>
                <?php echo $s->attivo ? '' : \App\Portale\Vista\Pannello::stato('Spento', 'spento'); ?>
                <div class="small text-secondary"><?php echo $s->email; ?> · <?php echo $s->periodo; ?></div>
                <?php if ($s->note !== ''): ?><div class="small"><?php echo $s->note; ?></div><?php endif; ?>
                <?php foreach ($s->elenco('avvisi') as $avviso): ?>
                    <div class="ui-sos-avviso"><i class="fa fa-triangle-exclamation me-1" aria-hidden="true"></i><?php echo $avviso; ?></div>
                <?php endforeach; ?>
                <details class="ui-sos-dettagli"<?php echo $sm->aperto ? ' open' : ''; ?>>
                    <summary>Modifica</summary>
                    <?php echo $d->parziale('pannello/_sos_studente', ['m' => $sm, 'campoCsrf' => $csrf]); ?>
                </details>
            </div>
            <form method="POST" class="m-0">
                <?php echo $csrf; ?><input type="hidden" name="azione" value="studente_togli"><input type="hidden" name="id" value="<?php echo (int) $s->id; ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" data-conferma="Togliere <?php echo $s->nominativo; ?> dagli studenti? Se ha già fatto interventi resta in elenco, spento." data-conferma-pulsante="Sì, togli">Togli</button>
            </form>
        </li>
    <?php endforeach; ?>
    </ul>
    <?php if (!$studenti): ?><p class="small text-secondary mb-1">Nessuno studente.</p><?php endif; ?>
    <?php $nuovo = $d->nuovo; ?>
    <details class="ui-sos-dettagli"<?php echo $nuovo->aperto ? ' open' : ''; ?>>
        <summary>Aggiungi uno studente</summary>
        <?php echo $d->parziale('pannello/_sos_studente', ['m' => $nuovo, 'campoCsrf' => $csrf]); ?>
    </details>
</div></section>

<script nonce="<?php echo csp_nonce(); ?>">
document.querySelectorAll('form[data-sos-persona]').forEach(function (f) {
    f.addEventListener('persona-scelta', function (e) { f.querySelector('[name=persona]').value = e.detail.id; f.submit(); });
    f.addEventListener('submit', function (e) { if (!f.querySelector('[name=persona]').value) { e.preventDefault(); } });
});
</script>
