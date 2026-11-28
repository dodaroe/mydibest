<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // Dati: campoCsrf (HTML), aperto, attivoDavvero, motivo, chi (HTML), telefono, orari, ferma, altre, intro, giorni, errori ?>
<?php $errori = $d->elenco('errori'); ?>
<?php if ($errori): ?>
    <div class="alert alert-danger" role="alert"><strong>Non ho salvato niente:</strong><ul class="mb-0"><?php foreach ($errori as $e): ?><li><?php echo $e; ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="POST" class="card border-0 shadow-sm mb-3"><div class="card-body">
    <?php echo $d->grezzo('campoCsrf'); ?><input type="hidden" name="azione" value="impostazioni_salva">

    <h2 class="h5 fw-bold">Servizio</h2>
    <label class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" role="switch" name="aperto" value="1"<?php echo $d->aperto ? ' checked' : ''; ?>> <span class="form-check-label fw-bold">Aperto: si possono inviare segnalazioni</span></label>
    <p class="mb-3"><?php echo $d->attivoDavvero ? \App\Portale\Vista\Pannello::stato('Il servizio è attivo', 'ok') : \App\Portale\Vista\Pannello::stato('Il servizio non è attivo', 'attesa'); ?>
        <?php if (!$d->attivoDavvero): ?><span class="small text-secondary"><?php echo $d->motivo; ?></span><?php endif; ?></p>

    <div class="mb-3"><?php echo $d->grezzo('chi'); ?></div>

    <div class="row g-2">
        <div class="col-md-4"><label class="form-label small fw-bold w-100">Telefono di riserva<input type="tel" class="form-control" name="telefono_riserva" maxlength="30" value="<?php echo $d->telefono; ?>"></label>
            <div class="form-text">Scritto sul cartello, per quando il telefono non prende.</div></div>
        <div class="col-md-8"><label class="form-label small fw-bold w-100">Orari di presidio<textarea class="form-control" name="orari" rows="3" maxlength="600" placeholder="lun-ven 8-19" aria-describedby="sos-aiuto-orari"><?php echo $d->orari; ?></textarea></label>
            <div class="form-text" id="sos-aiuto-orari">Una riga per fascia, «giorni ore»: <code>lun-ven 8-19</code>, <code>sab 8:30-13</code>. Giorni: lun mar mer gio ven sab dom. Vuoto = sempre presidiato. Fuori orario il modulo avvisa, ma invia lo stesso.</div></div>
    </div>

    <h2 class="h5 fw-bold mt-4">Promemoria</h2>
    <div class="row g-2">
        <div class="col-md-4"><label class="form-label small fw-bold w-100">Lezione ferma: dopo quanti minuti<input type="number" class="form-control" name="promemoria_ferma" min="0" max="1440" value="<?php echo $d->ferma; ?>"></label></div>
        <div class="col-md-4"><label class="form-label small fw-bold w-100">Altre segnalazioni: dopo quanti minuti<input type="number" class="form-control" name="promemoria_altre" min="0" max="1440" value="<?php echo $d->altre; ?>"></label></div>
    </div>
    <div class="form-text mb-3">0 = nessun promemoria. Il promemoria parte al primo giro del cron dopo questi minuti: può arrivare fino a 15 minuti dopo.</div>

    <h2 class="h5 fw-bold mt-4">Testi e foto</h2>
    <label class="form-label small fw-bold w-100">Testo introduttivo del modulo (facoltativo)<textarea class="form-control" name="testo_introduttivo" rows="4" maxlength="3000"><?php echo $d->intro; ?></textarea></label>
    <div class="row g-2">
        <div class="col-md-4"><label class="form-label small fw-bold w-100">Giorni di conservazione delle foto<input type="number" class="form-control" name="conserva_foto_giorni" min="1" max="1095" value="<?php echo $d->giorni; ?>"></label></div>
    </div>

    <button type="submit" class="btn btn-azione fw-bold mt-2"><i class="fa fa-floppy-disk me-1" aria-hidden="true"></i>Salva le impostazioni</button>
</div></form>
