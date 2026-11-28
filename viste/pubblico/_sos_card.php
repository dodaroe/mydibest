<?php if (!($d ?? null) instanceof \App\Http\DatiVista) { exit; } // Una segnalazione per chi interviene, con i suoi pulsanti. Dati: vedi PaginaSosInterventi::card ?>
<?php
$id = (int) $d->grezzo('id');
// l'inizio di ogni modulo: l'azione, il token e, quando toglie qualcosa a qualcuno o chiude, la domanda di conferma
$modulo = static function (string $azione, string $classe = '', string $conferma = '', string $pulsante = '') use ($d): string {
    return '<form method="post" action="' . $d->url . '"' . ($classe !== '' ? ' class="' . $classe . '"' : '')
        . ($conferma !== '' ? ' data-conferma="' . \App\Http\DatiVista::escapa($conferma) . '" data-conferma-pulsante="' . \App\Http\DatiVista::escapa($pulsante) . '"' : '') . '>'
        . $d->grezzo('campoCsrf') . '<input type="hidden" name="azione" value="' . $azione . '">';
};
$studenti = $d->elenco('studenti');
$chiudibile = (int) $d->grezzo('risolta') === 1;
?>
<article class="ui-sos-card <?php echo $d->classe; ?>" id="sos-<?php echo $id; ?>">
    <div class="ui-sos-card-testa">
        <div>
            <h2 class="ui-sos-aula-nome"><?php echo $d->aula; ?></h2>
            <div class="ui-sos-edificio"><?php echo $d->edificio; ?></div>
        </div>
        <div class="ui-sos-quando">
            <?php if ((int) $d->grezzo('ferma') === 1): ?>
                <span class="ui-stato ui-sos-ferma"><i class="fa fa-circle-pause" aria-hidden="true"></i><?php echo $d->urgenza; ?></span>
            <?php else: ?>
                <span class="ui-stato <?php echo $d->classe === 'aiuto' ? 'attesa' : 'neutro'; ?>"><?php echo $d->urgenza; ?></span>
            <?php endif; ?>
            <?php if ($d->daQuanto !== ''): ?><span class="ui-sos-da"><?php echo $d->daQuanto; ?></span><?php endif; ?>
            <span class="ui-stato <?php echo $d->colore; ?>"><?php echo $d->stato; ?></span>
        </div>
    </div>
    <div class="ui-sos-problema"><i class="fa <?php echo $d->icona; ?>" aria-hidden="true"></i><span><?php echo $d->tipo; ?></span></div>
    <?php if ($d->nonRisoltaTitolo !== ''): ?>
        <div class="ui-sos-box azione" role="status"><i class="fa fa-triangle-exclamation" aria-hidden="true"></i><div>
            <p><strong><?php echo $d->nonRisoltaTitolo; ?></strong></p>
            <p><?php echo $d->nonRisoltaTesto; ?></p>
        </div></div>
    <?php endif; ?>
    <?php if ($d->descrizione !== '' || $d->foto !== ''): ?>
        <div class="ui-sos-descrizione">
            <p><?php echo $d->descrizione; ?></p>
            <?php if ($d->foto !== ''): ?><a class="ui-sos-miniatura" href="<?php echo $d->foto; ?>" target="_blank" rel="noopener" aria-label="Apri la foto"><img data-foto src="<?php echo $d->foto; ?>" alt="Foto della segnalazione" loading="lazy" width="72" height="72"></a><?php endif; ?>
        </div>
    <?php endif; ?>
    <?php $aggiunti = $d->elenco('aggiunti'); ?>
    <?php if ($aggiunti): ?>
        <ul class="ui-sos-dati">
            <?php foreach ($aggiunti as $a): ?>
                <li><span><?php echo $a->etichetta; ?>:</span> <strong><?php echo $a->valore; ?></strong></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <div class="ui-sos-chi">
        <span><i class="fa fa-user me-1" aria-hidden="true"></i><strong><?php echo $d->docente; ?></strong><span class="ui-sos-edificio"> · <?php echo $d->codice; ?> · <?php echo $d->ora; ?></span></span>
        <?php if ($d->telefono !== ''): ?><a class="btn btn-outline-primary fw-bold" href="<?php echo $d->telefonoHref; ?>" title="<?php echo $d->telefono; ?>"><i class="fa fa-phone me-1" aria-hidden="true"></i>Chiama</a><?php endif; ?>
    </div>
    <div class="ui-sos-in-mano"><?php echo $d->inMano; ?></div>

    <div class="ui-sos-comandi">
        <?php if ((int) $d->grezzo('staAndando') === 1): ?>
            <?php echo $modulo('sto_andando', 'intero'); ?>
                <button type="submit" class="btn fw-bold btn-primary"><i class="fa fa-person-walking" aria-hidden="true"></i>Sto andando</button>
            </form>
        <?php endif; ?>

        <?php if ((int) $d->grezzo('risoltoStudente') === 1): ?>
            <details class="intero">
                <summary class="btn fw-bold btn-success"><i class="fa fa-check" aria-hidden="true"></i>Risolto</summary>
                <?php echo $modulo('risolta', 'ui-sos-pannello'); ?>
                    <label class="ui-sos-titolo" for="sos-minuti-<?php echo $id; ?>">Minuti dell'intervento <span class="ui-sos-facoltativo">(facoltativo)</span></label>
                    <input type="number" class="form-control mb-2" id="sos-minuti-<?php echo $id; ?>" name="minuti" min="0" max="<?php echo $d->maxMinuti; ?>" inputmode="numeric">
                    <button type="submit" class="btn fw-bold btn-success"><i class="fa fa-check" aria-hidden="true"></i>Conferma: risolto</button>
                </form>
            </details>
        <?php endif; ?>

        <?php if ((int) $d->grezzo('nonRisolto') === 1): ?>
            <details class="intero">
                <summary class="btn fw-bold btn-outline-danger"><i class="fa fa-xmark" aria-hidden="true"></i>Non risolto</summary>
                <?php echo $modulo('non_risolta', 'ui-sos-pannello'); ?>
                    <fieldset class="ui-sos-gruppo mb-3">
                        <legend class="ui-sos-titolo">Che cosa non ha funzionato?</legend>
                        <div class="ui-sos-tipi">
                            <?php foreach ($d->elenco('esiti') as $e): ?>
                                <div class="ui-sos-scelta">
                                    <input type="radio" name="esito" id="sos-esito-<?php echo $id; ?>-<?php echo $e->id; ?>" value="<?php echo $e->id; ?>" required>
                                    <label class="ui-sos-tipo" for="sos-esito-<?php echo $id; ?>-<?php echo $e->id; ?>"><i class="fa <?php echo $e->icona; ?>" aria-hidden="true"></i><span><?php echo $e->nome; ?></span></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                    <label class="ui-sos-titolo" for="sos-nota-<?php echo $id; ?>">Nota <span class="ui-sos-facoltativo">(obbligatoria se la scelta lo richiede, come «Altro»)</span></label>
                    <textarea class="form-control mb-2" id="sos-nota-<?php echo $id; ?>" name="nota" rows="2" maxlength="<?php echo $d->maxNota; ?>"></textarea>
                    <label class="ui-sos-titolo" for="sos-minuti-no-<?php echo $id; ?>">Minuti dell'intervento <span class="ui-sos-facoltativo">(facoltativo)</span></label>
                    <input type="number" class="form-control mb-2" id="sos-minuti-no-<?php echo $id; ?>" name="minuti" min="0" max="<?php echo $d->maxMinuti; ?>" inputmode="numeric">
                    <button type="submit" class="btn fw-bold btn-danger"><i class="fa fa-paper-plane" aria-hidden="true"></i>Invia: non risolto</button>
                </form>
            </details>
        <?php endif; ?>

        <?php if ($chiudibile): ?>
            <?php echo $modulo('risolta', 'intero', 'Segni la segnalazione come risolta? Chi l\'ha inviata viene avvisato.', 'Sì, è risolta'); ?>
                <button type="submit" class="btn fw-bold btn-success"><i class="fa fa-check" aria-hidden="true"></i>Risolta</button>
            </form>
        <?php endif; ?>

        <?php if ($d->prendi !== ''): ?>
            <?php echo $modulo((string) $d->grezzo('prendiAzione'), $chiudibile ? '' : 'intero', (string) $d->grezzo('confermaPrendi'), 'Sì, la riprendo'); ?>
                <button type="submit" class="btn fw-bold <?php echo $chiudibile ? 'btn-outline-primary' : 'btn-primary'; ?>"><i class="fa fa-hand" aria-hidden="true"></i><?php echo $d->prendi; ?></button>
            </form>
        <?php endif; ?>

        <?php if ($d->affida !== ''): ?>
            <details>
                <summary class="btn fw-bold btn-outline-primary"><i class="fa fa-user-plus" aria-hidden="true"></i><?php echo $d->affida; ?></summary>
                <?php if (!$studenti): ?>
                    <div class="ui-sos-pannello"><?php echo $d->nessunoStudente; ?></div>
                <?php else: ?>
                    <?php echo $modulo('affida', 'ui-sos-pannello', (string) $d->grezzo('confermaAffida'), 'Sì, passala'); ?>
                        <fieldset class="ui-sos-gruppo mb-2">
                            <legend class="ui-sos-titolo">Quale studente?</legend>
                            <?php foreach ($studenti as $i => $s): ?>
                                <label class="form-check"><input type="radio" name="studente" value="<?php echo $s->id; ?>"<?php if ($i === 0): ?> checked<?php endif; ?>> <?php echo $s->nome; ?></label>
                            <?php endforeach; ?>
                        </fieldset>
                        <button type="submit" class="btn fw-bold btn-primary"><i class="fa fa-check" aria-hidden="true"></i>Affida</button>
                    </form>
                <?php endif; ?>
            </details>
        <?php endif; ?>

        <?php if ((int) $d->grezzo('inoltra') === 1): ?>
            <details>
                <summary class="btn fw-bold btn-outline-secondary"><i class="fa fa-share" aria-hidden="true"></i>La inoltro a…</summary>
                <?php echo $modulo('inoltra', 'ui-sos-pannello', 'Inoltri la segnalazione? Chi l\'ha inviata viene avvisato.', 'Sì, inoltra'); ?>
                    <label class="ui-sos-titolo" for="sos-inoltra-<?php echo $id; ?>">A chi la inoltri, e quando lo hai avvisato?</label>
                    <textarea class="form-control mb-2" id="sos-inoltra-<?php echo $id; ?>" name="a_chi" rows="2" maxlength="<?php echo $d->maxNota; ?>" required></textarea>
                    <button type="submit" class="btn fw-bold btn-primary"><i class="fa fa-check" aria-hidden="true"></i>Inoltra</button>
                </form>
            </details>
        <?php endif; ?>

        <?php if ((int) $d->grezzo('chiudi') === 1): ?>
            <details class="intero">
                <summary class="btn fw-bold btn-outline-secondary"><i class="fa fa-ban" aria-hidden="true"></i>Chiudo senza soluzione</summary>
                <?php echo $modulo('chiudi', 'ui-sos-pannello', 'Chiudi la segnalazione senza soluzione? Chi l\'ha inviata viene avvisato.', 'Sì, chiudila'); ?>
                    <label class="ui-sos-titolo" for="sos-chiudi-<?php echo $id; ?>">Perché la chiudi?</label>
                    <textarea class="form-control mb-2" id="sos-chiudi-<?php echo $id; ?>" name="nota" rows="2" maxlength="<?php echo $d->maxNota; ?>" required></textarea>
                    <button type="submit" class="btn fw-bold btn-primary"><i class="fa fa-check" aria-hidden="true"></i>Chiudi la segnalazione</button>
                </form>
            </details>
        <?php endif; ?>
    </div>

    <?php if ((int) $d->grezzo('aperta') === 1): ?>
        <section class="ui-sos-storia">
            <h3 class="ui-sos-titolo">Cosa è successo</h3>
            <ul class="ui-sos-cronologia">
                <?php foreach ($d->elenco('cronologia') as $c): ?>
                    <li><time><?php echo $c->ora; ?></time><span><?php echo $c->testo; ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php else: ?>
        <a class="ui-sos-storia-link" href="<?php echo $d->url; ?>#sos-<?php echo $id; ?>">Cosa è successo</a>
    <?php endif; ?>
</article>
