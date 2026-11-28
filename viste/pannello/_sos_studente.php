<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // Il modulo di uno studente (nuovo o da modificare). Dati: m (azione, id, nome, cognome, email, dal, al, note, attivo, errore, conferma), campoCsrf (HTML) ?>
<?php $m = $d->m; ?>
<form method="POST" class="mt-2">
    <?php echo $d->grezzo('campoCsrf'); ?><input type="hidden" name="azione" value="<?php echo $m->azione; ?>"><input type="hidden" name="id" value="<?php echo (int) $m->id; ?>">
    <?php if ($m->errore !== ''): ?><div class="alert alert-warning py-2" role="alert"><?php echo $m->errore; ?></div><?php endif; ?>
    <div class="row g-2">
        <div class="col-md-3"><label class="form-label small fw-bold w-100">Nome<input type="text" class="form-control" name="nome" maxlength="100" required value="<?php echo $m->nome; ?>"></label></div>
        <div class="col-md-3"><label class="form-label small fw-bold w-100">Cognome<input type="text" class="form-control" name="cognome" maxlength="100" required value="<?php echo $m->cognome; ?>"></label></div>
        <div class="col-md-6"><label class="form-label small fw-bold w-100">Email istituzionale<input type="email" class="form-control" name="email" maxlength="150" required placeholder="nome.cognome@studenti.unical.it" value="<?php echo $m->email; ?>"></label></div>
        <div class="col-6 col-md-3"><label class="form-label small fw-bold w-100">Dal<input type="date" class="form-control" name="dal" value="<?php echo $m->dal; ?>"></label></div>
        <div class="col-6 col-md-3"><label class="form-label small fw-bold w-100">Al<input type="date" class="form-control" name="al" value="<?php echo $m->al; ?>"></label></div>
        <div class="col-md-6"><label class="form-label small fw-bold w-100">Note<input type="text" class="form-control" name="note" maxlength="500" value="<?php echo $m->note; ?>"></label></div>
    </div>
    <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="attivo" value="1"<?php echo (int) $m->attivo === 1 ? ' checked' : ''; ?>> <span class="form-check-label">Attivo</span></label>
    <?php if ($m->conferma): ?>
        <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="conferma_email" value="1"> <span class="form-check-label fw-bold">Confermo: l'indirizzo è giusto anche se non è @studenti.unical.it</span></label>
    <?php endif; ?>
    <button type="submit" class="btn btn-sm btn-azione fw-bold"><?php echo $m->azione === 'studente_aggiungi' ? 'Aggiungi lo studente' : 'Salva lo studente'; ?></button>
</form>
