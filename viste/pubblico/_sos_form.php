<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // Il form di sos.php. Dati: vedi PaginaSos::form() ?>
<?php
$c = $d->c;
$err = $d->errori;
$errore = static fn (string $testo): string => $testo === '' ? '' : '<p class="ui-comp-errore" role="alert"><i class="fa fa-circle-exclamation" aria-hidden="true"></i>' . $testo . '</p>';   // il testo arriva già con l'escape
$scelta = (int) $d->grezzo('aulaScelta') === 1;
$aggiunti = $d->elenco('aggiunti');
?>
<h1>SOS assistenza didattica</h1>
<p class="ui-sos-sotto"><?php echo $d->sottotitolo; ?></p>

<?php if ($d->messaggio !== ''): ?>
    <div class="ui-sos-box azione" role="alert"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i><div><p><strong><?php echo $d->messaggio; ?></strong></p></div></div>
<?php endif; ?>
<?php if ((int) $d->grezzo('cartelloVecchio') === 1): ?>
    <div class="ui-sos-box attesa" role="status"><i class="fa fa-qrcode" aria-hidden="true"></i><div><p><strong>Questo cartello non è più valido: scegli l'aula dall'elenco.</strong></p></div></div>
<?php endif; ?>
<?php if ((int) $d->grezzo('fuoriOrario') === 1): ?>
    <div class="ui-sos-box attesa" role="status"><i class="fa fa-clock" aria-hidden="true"></i><div><p><strong>Fuori orario: potrebbe non intervenire nessuno subito.</strong><?php if ($d->telefonoRiserva !== ''): ?> Se è urgente chiama <a href="<?php echo $d->telefonoRiservaHref; ?>"><?php echo $d->telefonoRiserva; ?></a><?php endif; ?></p></div></div>
<?php endif; ?>
<?php if ((int) $d->grezzo('avvisoDoppione') === 1): ?>
    <?php $x = $d->doppione; ?>
    <div class="ui-sos-box info" role="status"><i class="fa fa-circle-info" aria-hidden="true"></i><div>
        <p><strong>C'è già una segnalazione aperta per quest'aula, inviata alle <?php echo $x->ora; ?>.</strong></p>
        <p><?php echo $x->tipo; ?> · <?php echo $x->stato; ?><?php if ($x->href !== ''): ?> · <a href="<?php echo $x->href; ?>">Apri la tua segnalazione</a><?php endif; ?></p>
        <div class="ui-sos-azioni">
            <details>
                <summary class="btn fw-bold">Aggiungi una nota a quella</summary>
                <form method="post" action="<?php echo $d->azione; ?>">
                    <?php echo $d->grezzo('campoCsrf'); ?>
                    <input type="hidden" name="azione" value="nota">
                    <input type="hidden" name="aula" value="<?php echo $d->aulaId; ?>">
                    <input type="hidden" name="doppione" value="<?php echo $x->id; ?>">
                    <label class="visually-hidden" for="sos-nota-doppione">La tua nota</label>
                    <textarea class="form-control mb-2" id="sos-nota-doppione" name="nota" rows="2" maxlength="<?php echo $d->maxNota; ?>" placeholder="Che cosa vedi tu?"></textarea>
                    <button type="submit" class="btn fw-bold">Aggiungi la nota</button>
                </form>
            </details>
            <button type="submit" class="btn" form="sos-form" name="togli_avviso" value="1" formnovalidate>Invia comunque</button>
        </div>
    </div></div>
<?php endif; ?>

<form id="sos-form" method="post" action="<?php echo $d->azione; ?>" enctype="multipart/form-data" novalidate>
    <?php echo $d->grezzo('campoCsrf'); ?>
    <input type="hidden" name="azione" value="invia">
    <input type="hidden" name="avvisato" value="<?php echo $d->avvisato; ?>">

    <?php if ($scelta): ?>
        <details class="ui-sos-qui"<?php echo $err->aula_id !== '' ? ' open' : ''; ?>>
            <summary>
                <span class="ui-sos-qui-nome"><?php echo $d->aulaPrima; ?><?php echo (int) $d->grezzo('aulaStacco') === 1 ? ' ' : ''; ?><strong id="sos-aula-nome"><?php echo $d->aulaNome; ?></strong></span>
                <span><?php if ($d->aulaEdificio !== ''): ?><?php echo $d->aulaEdificio; ?> · <?php endif; ?>Non è questa? <span class="ui-sos-cambia">Cambia</span></span>
            </summary>
    <?php endif; ?>
    <div class="ui-sos-gruppo<?php echo $err->aula_id !== '' ? ' errore' : ''; ?><?php echo $scelta ? ' mt-2 mb-0' : ''; ?>">
        <label class="ui-sos-titolo" for="sos-aula"><?php echo $c->aula->etichetta; ?></label>
        <?php echo $errore($err->aula_id); ?>
        <select class="form-select" id="sos-aula" name="aula_id">
            <option value="">— scegli l'aula —</option>
            <?php foreach ($d->elenco('aule') as $a): ?>
                <option value="<?php echo $a->id; ?>" data-nome="<?php echo $a->breve; ?>"<?php echo (int) $a->grezzo('scelta') === 1 ? ' selected' : ''; ?>><?php echo $a->nome; ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($c->aula->aiuto !== ''): ?><div class="ui-sos-facoltativo"><?php echo $c->aula->aiuto; ?></div><?php endif; ?>
    </div>
    <?php if ($scelta): ?></details><?php endif; ?>

    <div class="ui-sos-docente">
        <div class="ui-sos-facoltativo"><?php echo $c->docente->etichetta; ?> (dal tuo account)</div>
        <strong><?php echo $d->docenteNome; ?></strong>
        <div><?php echo $d->docenteEmail !== '' ? $d->docenteEmail : 'Nessuna email verificata: non riceverai gli avvisi per email.'; ?></div>
        <?php if ($c->docente->aiuto !== ''): ?><div class="ui-sos-facoltativo"><?php echo $c->docente->aiuto; ?></div><?php endif; ?>
    </div>

    <fieldset class="ui-sos-gruppo<?php echo $err->tipo_id !== '' ? ' errore' : ''; ?>">
        <legend class="ui-sos-titolo"><?php echo $c->tipo->etichetta; ?></legend>
        <?php echo $errore($err->tipo_id); ?>
        <?php if ($c->tipo->aiuto !== ''): ?><div class="ui-sos-facoltativo mb-2"><?php echo $c->tipo->aiuto; ?></div><?php endif; ?>
        <div class="ui-sos-tipi">
            <?php foreach ($d->elenco('tipi') as $t): ?>
                <div class="ui-sos-scelta">
                    <input type="radio" name="tipo_id" id="sos-tipo-<?php echo $t->id; ?>" value="<?php echo $t->id; ?>"<?php echo (int) $t->grezzo('scelto') === 1 ? ' checked' : ''; ?>>
                    <label class="ui-sos-tipo" for="sos-tipo-<?php echo $t->id; ?>"><i class="fa <?php echo $t->icona; ?>" aria-hidden="true"></i><span><?php echo $t->nome; ?></span><?php if ($t->aiuto !== ''): ?><small><?php echo $t->aiuto; ?></small><?php endif; ?></label>
                </div>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <fieldset class="ui-sos-gruppo<?php echo $err->urgenza !== '' ? ' errore' : ''; ?>">
        <legend class="ui-sos-titolo"><?php echo $c->urgenza->etichetta; ?></legend>
        <?php echo $errore($err->urgenza); ?>
        <?php if ($c->urgenza->aiuto !== ''): ?><div class="ui-sos-facoltativo mb-2"><?php echo $c->urgenza->aiuto; ?></div><?php endif; ?>
        <div class="ui-sos-urgenze">
            <?php foreach ($d->elenco('urgenze') as $u): ?>
                <div class="ui-sos-scelta">
                    <input type="radio" name="urgenza" id="sos-urgenza-<?php echo $u->id; ?>" value="<?php echo $u->id; ?>"<?php echo (int) $u->grezzo('scelta') === 1 ? ' checked' : ''; ?>>
                    <label class="ui-sos-urgenza<?php echo (int) $u->grezzo('ferma') === 1 ? ' ferma' : ''; ?>" for="sos-urgenza-<?php echo $u->id; ?>"><i class="fa <?php echo $u->icona; ?>" aria-hidden="true"></i><span><?php echo $u->testo; ?></span></label>
                </div>
            <?php endforeach; ?>
        </div>
    </fieldset>

    <div class="ui-sos-gruppo<?php echo $err->descrizione !== '' ? ' errore' : ''; ?>">
        <label class="ui-sos-titolo" for="sos-descrizione"><?php echo $c->descrizione->etichetta; ?><?php if ((int) $c->descrizione->grezzo('facoltativo') === 1): ?> <span class="ui-sos-facoltativo">(facoltativo)</span><?php endif; ?></label>
        <?php echo $errore($err->descrizione); ?>
        <textarea class="form-control" id="sos-descrizione" name="descrizione" rows="3" maxlength="<?php echo $d->maxDescrizione; ?>"><?php echo $d->descrizione; ?></textarea>
        <div class="ui-sos-facoltativo">Massimo <?php echo $d->maxDescrizione; ?> caratteri.<?php if ($c->descrizione->aiuto !== ''): ?> <?php echo $c->descrizione->aiuto; ?><?php endif; ?></div>
    </div>

    <?php if ((int) $c->foto->grezzo('visibile') === 1): ?>
        <div class="ui-sos-gruppo<?php echo $err->foto !== '' ? ' errore' : ''; ?>">
            <span class="ui-sos-titolo"><?php echo $c->foto->etichetta; ?><?php if ((int) $c->foto->grezzo('facoltativo') === 1): ?> <span class="ui-sos-facoltativo">(facoltativa)</span><?php endif; ?></span>
            <?php echo $errore($err->foto); ?>
            <label class="ui-sos-foto" for="sos-foto"><i class="fa fa-camera" aria-hidden="true"></i><span>Scatta o scegli una foto</span><input type="file" id="sos-foto" name="foto" accept="image/jpeg,image/png"></label>
            <?php if ((int) $d->grezzo('fotoDaRiscegliere') === 1): ?><div class="ui-sos-facoltativo">Se avevi scelto una foto, sceglila di nuovo.</div><?php endif; ?>
            <?php if ($c->foto->aiuto !== ''): ?><div class="ui-sos-facoltativo"><?php echo $c->foto->aiuto; ?></div><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ((int) $c->telefono->grezzo('visibile') === 1): ?>
        <div class="ui-sos-gruppo<?php echo $err->telefono !== '' ? ' errore' : ''; ?>">
            <label class="ui-sos-titolo" for="sos-telefono"><?php echo $c->telefono->etichetta; ?><?php if ((int) $c->telefono->grezzo('facoltativo') === 1): ?> <span class="ui-sos-facoltativo">(facoltativo)</span><?php endif; ?></label>
            <?php echo $errore($err->telefono); ?>
            <input type="tel" class="form-control" id="sos-telefono" name="telefono" value="<?php echo $d->telefono; ?>" maxlength="<?php echo $d->maxTelefono; ?>" autocomplete="tel">
            <?php if ($c->telefono->aiuto !== ''): ?><div class="ui-sos-facoltativo"><?php echo $c->telefono->aiuto; ?></div><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ((int) $c->insegnamento->grezzo('visibile') === 1): ?>
        <div class="ui-sos-gruppo campo-pratica<?php echo $err->insegnamento_id !== '' ? ' errore' : ''; ?>">
            <span class="ui-sos-titolo"><?php echo $c->insegnamento->etichetta; ?><?php if ((int) $c->insegnamento->grezzo('facoltativo') === 1): ?> <span class="ui-sos-facoltativo">(facoltativo)</span><?php endif; ?></span>
            <?php echo $errore($err->insegnamento_id); ?>
            <div class="input-group" id="sos-catalogo" hidden>
                <input type="text" class="form-control ins-testo" id="sos-insegnamento" name="insegnamento_testo" value="<?php echo $d->insegnamentoTesto; ?>" readonly aria-label="<?php echo $c->insegnamento->etichetta; ?>" placeholder="Scegli dal catalogo">
                <button type="button" class="btn btn-outline-primary ins-scegli" data-bersaglio="sos-insegnamento"><i class="fa fa-magnifying-glass me-1" aria-hidden="true"></i>Scegli</button>
            </div>
            <input type="hidden" name="insegnamento_meta" class="ins-meta" value="<?php echo $d->insegnamentoMeta; ?>">
            <noscript>
                <select class="form-select" name="insegnamento_id" aria-label="<?php echo $c->insegnamento->etichetta; ?>">
                    <option value="">— scegli l'insegnamento —</option>
                    <?php foreach ($d->elenco('insegnamenti') as $i): ?><option value="<?php echo $i->id; ?>"<?php echo (int) $i->grezzo('scelto') === 1 ? ' selected' : ''; ?>><?php echo $i->nome; ?></option><?php endforeach; ?>
                </select>
            </noscript>
            <?php if ($c->insegnamento->aiuto !== ''): ?><div class="ui-sos-facoltativo"><?php echo $c->insegnamento->aiuto; ?></div><?php endif; ?>
        </div>
        <script src="<?php echo $d->scriptInsegnamenti; ?>" data-base="<?php echo $d->base; ?>" data-se-manca="Se non lo trovi chiudi e scrivilo nella descrizione del problema." data-anno-vuoto="Nessun insegnamento per questo anno: prova un altro anno."></script>
    <?php endif; ?>

    <?php foreach ($aggiunti as $a): ?>
        <?php $id = 'sos-' . $a->chiave; $tipo = (string) $a->grezzo('tipo'); $facoltativo = (int) $a->grezzo('facoltativo') === 1 ? ' <span class="ui-sos-facoltativo">(facoltativo)</span>' : ''; ?>
        <?php if ($tipo === 'radio' || $tipo === 'multicheck'): ?>
            <fieldset class="ui-sos-gruppo<?php echo $a->errore !== '' ? ' errore' : ''; ?>">
                <legend class="ui-sos-titolo"><?php echo $a->etichetta; ?><?php echo $facoltativo; ?></legend>
                <?php echo $errore($a->errore); ?>
                <div>
                    <?php foreach ($a->elenco('opzioni') as $o): ?>
                        <label class="form-check"><input class="form-check-input" type="<?php echo $tipo === 'radio' ? 'radio' : 'checkbox'; ?>" name="<?php echo $a->chiave; ?><?php echo $tipo === 'radio' ? '' : '[]'; ?>" value="<?php echo $o->testo; ?>"<?php echo (int) $o->grezzo('scelta') === 1 ? ' checked' : ''; ?>> <span class="form-check-label"><?php echo $o->testo; ?></span></label>
                    <?php endforeach; ?>
                </div>
                <?php if ($a->aiuto !== ''): ?><div class="ui-sos-facoltativo"><?php echo $a->aiuto; ?></div><?php endif; ?>
            </fieldset>
        <?php else: ?>
            <div class="ui-sos-gruppo<?php echo $a->errore !== '' ? ' errore' : ''; ?>">
                <?php if ($tipo === 'checkbox'): ?>
                    <?php echo $errore($a->errore); ?>
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="<?php echo $a->chiave; ?>" value="1"<?php echo $a->valore !== '' ? ' checked' : ''; ?>> <span class="form-check-label fw-bold"><?php echo $a->etichetta; ?></span><?php echo $facoltativo; ?></label>
                <?php else: ?>
                    <label class="ui-sos-titolo" for="<?php echo $id; ?>"><?php echo $a->etichetta; ?><?php echo $facoltativo; ?></label>
                    <?php echo $errore($a->errore); ?>
                    <?php if ($tipo === 'textarea'): ?>
                        <textarea class="form-control" id="<?php echo $id; ?>" name="<?php echo $a->chiave; ?>" rows="3"><?php echo $a->valore; ?></textarea>
                    <?php else: ?>
                        <input class="form-control" id="<?php echo $id; ?>" name="<?php echo $a->chiave; ?>" type="<?php echo $tipo === 'date' || $tipo === 'number' || $tipo === 'url' ? $tipo : 'text'; ?>" value="<?php echo $a->valore; ?>">
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($a->aiuto !== ''): ?><div class="ui-sos-facoltativo"><?php echo $a->aiuto; ?></div><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-danger fw-bold ap-grande ui-sos-invia"><i class="fa fa-paper-plane me-2" aria-hidden="true"></i>Invia la richiesta di aiuto</button>
</form>

<script nonce="<?php echo csp_nonce(); ?>">
(function () {
    var scelta = document.getElementById('sos-aula'), nome = document.getElementById('sos-aula-nome'), catalogo = document.getElementById('sos-catalogo');
    if (scelta && nome) scelta.addEventListener('change', function () {
        var o = scelta.options[scelta.selectedIndex];
        nome.textContent = o && o.dataset.nome ? o.dataset.nome : '…';
    });
    if (catalogo) catalogo.hidden = false;
})();
</script>
