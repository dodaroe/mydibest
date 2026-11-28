<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // Dati: campoCsrf (HTML), aule (record + qr HTML, urlCartello, urlProva), attive (quante), urlTutti ?>
<?php $aule = $d->elenco('aule'); $csrf = $d->grezzo('campoCsrf'); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="pn-aiuto m-0">Ogni aula ha il suo QR: stampa il cartello e appendilo in aula.</p>
    <?php if ((int) $d->attive > 0): ?>
        <a class="btn btn-azione fw-bold" href="<?php echo $d->urlTutti; ?>"><i class="fa fa-print me-1" aria-hidden="true"></i>Stampa i cartelli di tutte le aule</a>
    <?php endif; ?>
</div>

<details class="card border-0 shadow-sm mb-3"<?php echo $aule ? '' : ' open'; ?>>
    <summary class="card-body fw-bold"><i class="fa fa-plus me-1" aria-hidden="true"></i>Aggiungi un'aula</summary>
    <form method="POST" class="card-body pt-0">
        <?php echo $csrf; ?><input type="hidden" name="azione" value="aula_aggiungi">
        <div class="row g-2">
            <div class="col-md-3"><label class="form-label small fw-bold w-100">Nome<input type="text" class="form-control" name="nome" maxlength="80" required placeholder="es. 5C2"></label></div>
            <div class="col-md-4"><label class="form-label small fw-bold w-100">Edificio<input type="text" class="form-control" name="edificio" maxlength="120" placeholder="es. Cubo 5C, piano terra"></label></div>
            <div class="col-md-5"><label class="form-label small fw-bold w-100">Note (le vede solo chi gestisce)<input type="text" class="form-control" name="note" maxlength="500"></label></div>
        </div>
        <button type="submit" class="btn btn-azione fw-bold">Aggiungi</button>
    </form>
</details>

<?php if (!$aule): ?>
    <p class="text-secondary">Non c'è ancora nessun'aula.</p>
<?php endif; ?>

<div class="d-flex flex-column gap-3">
<?php foreach ($aule as $a): $id = (int) $a->id; ?>
    <section class="card border-0 shadow-sm" id="aula-<?php echo $id; ?>">
        <div class="card-body ui-sos-aula">
            <div class="ui-sos-riquadro-qr"><?php echo $a->grezzo('qr'); ?></div>
            <div class="ui-sos-testo">
                <h2 class="h5 fw-bold mb-1"><?php echo $a->nome; ?>
                    <?php echo $a->attiva ? \App\Portale\Vista\Pannello::stato('Attiva', 'ok') : \App\Portale\Vista\Pannello::stato('Spenta', 'spento'); ?></h2>
                <div class="text-secondary"><?php echo $a->edificio === '' ? '' : $a->edificio . ' · '; ?><?php echo $a->referenti; ?> · Segnalazioni aperte: <?php echo (int) $a->aperte; ?></div>
                <?php if ($a->note !== ''): ?><div class="small mt-1"><?php echo $a->note; ?></div><?php endif; ?>

                <div class="ui-sos-azioni">
                    <a class="btn btn-sm btn-azione fw-bold" href="<?php echo $a->urlCartello; ?>"><i class="fa fa-print me-1" aria-hidden="true"></i>Stampa il cartello</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $a->urlProva; ?>" target="_blank" rel="noopener"><i class="fa fa-external-link-alt me-1" aria-hidden="true"></i>Prova il QR</a>
                    <form method="POST" class="m-0"><?php echo $csrf; ?><input type="hidden" name="azione" value="aula_stato"><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="valore" value="<?php echo $a->attiva ? 0 : 1; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo $a->attiva ? 'Spegni' : 'Accendi'; ?></button></form>
                    <form method="POST" class="m-0"><?php echo $csrf; ?><input type="hidden" name="azione" value="aula_rigenera"><input type="hidden" name="id" value="<?php echo $id; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary" data-conferma="Rigenerare il QR dell'aula «<?php echo $a->nome; ?>»? Il cartello già stampato smette di valere: va ristampato e sostituito." data-conferma-scrivi="RIGENERA" data-conferma-pulsante="Sì, rigenera"><i class="fa fa-rotate me-1" aria-hidden="true"></i>Rigenera il QR</button></form>
                    <form method="POST" class="m-0"><?php echo $csrf; ?><input type="hidden" name="azione" value="aula_sposta"><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="verso" value="su">
                        <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Sposta su «<?php echo $a->nome; ?>»" title="Sposta su"<?php echo $a->prima ? ' disabled' : ''; ?>><i class="fa fa-arrow-up" aria-hidden="true"></i></button></form>
                    <form method="POST" class="m-0"><?php echo $csrf; ?><input type="hidden" name="azione" value="aula_sposta"><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="verso" value="giu">
                        <button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="Sposta giù «<?php echo $a->nome; ?>»" title="Sposta giù"<?php echo $a->ultima ? ' disabled' : ''; ?>><i class="fa fa-arrow-down" aria-hidden="true"></i></button></form>
                    <?php if ($a->cancellabile): ?>
                        <form method="POST" class="m-0"><?php echo $csrf; ?><input type="hidden" name="azione" value="aula_elimina"><input type="hidden" name="id" value="<?php echo $id; ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-conferma="Cancellare l'aula «<?php echo $a->nome; ?>» con i suoi referenti? Il cartello stampato smette di valere." aria-label="Cancella «<?php echo $a->nome; ?>»" title="Cancella"><i class="fa fa-trash" aria-hidden="true"></i></button></form>
                    <?php endif; ?>
                </div>

                <details class="ui-sos-dettagli">
                    <summary>Modifica</summary>
                    <form method="POST" class="mt-2">
                        <?php echo $csrf; ?><input type="hidden" name="azione" value="aula_salva"><input type="hidden" name="id" value="<?php echo $id; ?>">
                        <div class="row g-2">
                            <div class="col-md-3"><label class="form-label small fw-bold w-100">Nome<input type="text" class="form-control" name="nome" maxlength="80" required value="<?php echo $a->nome; ?>"></label></div>
                            <div class="col-md-4"><label class="form-label small fw-bold w-100">Edificio<input type="text" class="form-control" name="edificio" maxlength="120" value="<?php echo $a->edificio; ?>"></label></div>
                            <div class="col-md-5"><label class="form-label small fw-bold w-100">Note<input type="text" class="form-control" name="note" maxlength="500" value="<?php echo $a->note; ?>"></label></div>
                        </div>
                        <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="attiva" value="1"<?php echo $a->attiva ? ' checked' : ''; ?>> <span class="form-check-label">Attiva (si può scegliere nel form)</span></label>
                        <button type="submit" class="btn btn-sm btn-azione fw-bold">Salva l'aula</button>
                    </form>
                </details>
            </div>
        </div>
    </section>
<?php endforeach; ?>
</div>
