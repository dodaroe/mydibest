<?php
// Prove via HTTP (ambiente locale acceso) del modulo Amministrazione: le pagine si aprono per l'amministratore, per chi ha tutto il modulo e per chi ha solo
// «Amministrazione · Richieste di pubblicazione» (che vede la home con le sole voci delle richieste), rimandano o negano a chi non ha permesso, e i vecchi
// indirizzi (pubblicazioni.php?id=N, inizio.php?sezione=pubblicazioni) funzionano; la Modulistica amministrativa (AM2) e le impostazioni della Richiesta di
// pubblicazione (AM3). Chiamato da esegui.php: usa $http, $jar (amministratore) e $loc.
sezione("Amministrazione: pagine del modulo, abilitati parziali e vecchi indirizzi");

$errore_php = fn(array $r): bool => (bool) preg_match('/<b>(Fatal error|Parse error|Warning|Deprecated|Notice)<\/b>|Uncaught /', $r['corpo']);
$apre = fn(array $r, string ...$testi): bool => $r['codice'] === 200 && !$errore_php($r) && str_contains($r['corpo'], '</html>') && array_reduce($testi, fn($ok, $t) => $ok && str_contains($r['corpo'], $t), true);
$accedi = function (int $u) use ($http): string { $j = tempnam(sys_get_temp_dir(), 'jar'); $http('/__accesso?u=' . $u, null, $j); return $j; };
// La richiesta ha la locandina obbligatoria: l'invio vero è multipart, con un PDF di prova; $togli_file cancella i file salvati prima di cancellare le righe
$pdf_prova = tempnam(sys_get_temp_dir(), 'pdf');
file_put_contents($pdf_prova, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");
$invia_con_locandina = function (string $url, array $campi, string $jar) use ($BASE, $pdf_prova): array {
    $ch = curl_init($BASE . $url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $campi + ['locandina' => new CURLFile($pdf_prova, 'application/pdf', 'manifesto.pdf')]]);
    $corpo = (string) curl_exec($ch);
    $r = ['codice' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), 'corpo' => $corpo, 'dove' => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL)];
    curl_close($ch);
    return $r;
};
$togli_file = function (string $titolo_like) use ($loc, $SITO): void {
    $st = $loc->prepare('SELECT a.percorso FROM pubblicazioni_allegati a JOIN pubblicazioni p ON p.id = a.pubblicazione_id WHERE p.titolo LIKE ?');
    $st->bind_param('s', $titolo_like);
    $st->execute();
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $x) {
        if (str_starts_with($x['percorso'], 'uploads/pubblicazioni/') && is_file($SITO . '/' . $x['percorso'])) unlink($SITO . '/' . $x['percorso']);
    }
};

// Persone di prova nel database locale (tolte alla fine): solo richieste, tutto il modulo, nessuna abilitazione
$loc->query("DELETE FROM abilitazioni_ambito WHERE utente_id IN (99961, 99962, 99963, 99964)");
$loc->query("DELETE FROM utenti WHERE id IN (99961, 99962, 99963, 99964)");
$loc->query("INSERT INTO utenti (id, codice_fiscale, nome, cognome, email, ruolo_id) VALUES
    (99961, 'AMMPAG99961XXXXX', 'Rita', 'Richieste', 'rita.richieste.amm@example.org', 4),
    (99962, 'AMMPAG99962XXXXX', 'Tina', 'Tuttoilmodulo', 'tina.tutto.amm@example.org', 4),
    (99963, 'AMMPAG99963XXXXX', 'Nino', 'Nessuno', 'nino.nessuno.amm@example.org', 4),
    (99964, 'AMMPAG99964XXXXX', 'Rosa', 'Richiesteearea', 'rosa.richieste.area.amm@example.org', 4)");
$id_area_x = (int) ($loc->query("SELECT id FROM pagine_eventi ORDER BY id LIMIT 1")->fetch_row()[0] ?? 0);
$loc->query("INSERT INTO abilitazioni_ambito (utente_id, tipo, pagina_id) VALUES (99961, 'modulo_pubblicazioni', 0), (99962, 'modulo_amministrazione', 0), (99964, 'modulo_pubblicazioni', 0), (99964, 'eventi', $id_area_x)");

// Pagine del pannello: la tabella delle richieste esiste dopo la prima richiesta (lo schema si aggiorna da solo), poi una richiesta di prova
$http('/mydibest/admin/amministrazione.php', null, $jar);
$loc->query("DELETE FROM pubblicazioni WHERE titolo LIKE 'Richiesta di prova AM1%'");
$loc->query("INSERT INTO pubblicazioni (richiedente, stato, nel_monitoraggio, data_evento, titolo) VALUES ('Rossi Maria', 'ricevuta', 0, '2026-12-05', 'Richiesta di prova AM1')");
$id_pb = (int) $loc->insert_id;
$loc->query("INSERT INTO pubblicazioni (richiedente, stato, nel_monitoraggio, data_evento, titolo) VALUES ('Verdi Anna', 'pubblicata', 1, '2026-12-06', 'Richiesta di prova AM1 nel file')");

// Amministratore
$r = $http('/mydibest/admin/amministrazione.php', null, $jar);
prova($apre($r, 'Richieste da guardare', 'Tutte le sezioni', 'Cosa devo fare', 'Richieste di pubblicazione', 'amministrazione.php?tab=modulistica', 'Modulistica'), "amministrazione.php da amministratore: si apre (voci delle richieste e Modulistica)");
prova(str_contains($r['corpo'], 'class="fw-bold text-white">Amministrazione<') && str_contains($r['corpo'], 'pubblicazioni.php?vista=arrivate'), "amministrazione.php: testata del modulo e menu a sinistra con le Richieste di pubblicazione");
$r = $http('/mydibest/admin/pubblicazioni.php', null, $jar);
prova($apre($r, 'class="fw-bold text-white">Amministrazione<', 'class="pn-briciole"', '>Amministrazione</a></li><li><span aria-current="page">Richieste di pubblicazione</span>', 'Richieste di pubblicazione'), "pubblicazioni.php: modulo Amministrazione e percorso Amministrazione › Richieste di pubblicazione");
prova($apre($http('/mydibest/admin/pubblicazioni.php?vista=monitoraggio', null, $jar), 'Tabella di monitoraggio') && $apre($http('/mydibest/admin/pubblicazioni.php?vista=impostazioni', null, $j_rich_am1 = $accedi(99961)), 'Impostazioni delle richieste'), "pubblicazioni.php: le sue viste (tabella di monitoraggio, impostazioni per chi ha solo le richieste) si aprono");
is_file($j_rich_am1) && unlink($j_rich_am1);
prova($apre($http('/mydibest/admin/pubblicazioni.php?id=' . $id_pb, null, $jar), 'Richiesta di prova AM1', 'Amministrazione</a>'), "vecchio indirizzo pubblicazioni.php?id=N: la scheda della richiesta si apre");
foreach (['amministrazione', 'pubblicazioni'] as $sez) {
    $r = $http('/mydibest/admin/inizio.php?sezione=' . $sez, null, $jar);
    prova(in_array($r['codice'], [302, 303], true) && str_ends_with($r['dove'], '/admin/amministrazione.php'), "inizio.php?sezione=$sez: porta a amministrazione.php", $r['codice'] . ' ' . $r['dove']);
}
$r = $http('/mydibest/admin/inizio.php', null, $jar);
prova($apre($r) && str_contains($r['corpo'], 'inizio.php?sezione=amministrazione') && !str_contains($r['corpo'], 'inizio.php?sezione=pubblicazioni'), "pagina iniziale del pannello: una card «Amministrazione» e nessuna a sé per le richieste");
$r = $http('/mydibest/admin/utenti.php?p_id=1', null, $jar);
prova($apre($r, 'Amministrazione (tutto il modulo)', 'Amministrazione · Richieste di pubblicazione (solo questa parte)'), "Utenti: si abilita a tutto il modulo Amministrazione o alle sole richieste di pubblicazione");

// Chi ha solo «Amministrazione · Richieste di pubblicazione»
$j_r = $accedi(99961);
$r = $http('/mydibest/admin/amministrazione.php', null, $j_r);
prova($apre($r, 'Richieste da guardare', 'Richieste di pubblicazione') && !str_contains($r['corpo'], 'Modulistica') && !str_contains($r['corpo'], 'Impostazioni'), "solo richieste: amministrazione.php si apre con le sole voci delle richieste");
prova($apre($http('/mydibest/admin/pubblicazioni.php', null, $j_r), 'Richieste di pubblicazione') && $apre($http('/mydibest/admin/pubblicazioni.php?id=' . $id_pb, null, $j_r), 'Richiesta di prova AM1'), "solo richieste: pubblicazioni.php e la scheda di una richiesta (vecchio link) si aprono");
foreach (['inizio.php', 'dashboard.php', 'index.php', 'inizio.php?sezione=pubblicazioni', 'inizio.php?sezione=amministrazione'] as $pag) {
    $r = $http('/mydibest/admin/' . $pag, null, $j_r);
    prova(in_array($r['codice'], [302, 303], true) && str_ends_with($r['dove'], '/admin/amministrazione.php'), "solo richieste: $pag porta a amministrazione.php", $r['codice'] . ' ' . $r['dove']);
}
$r = $http('/mydibest/admin/amministrazione.php?tab=modulistica', null, $j_r);
prova($r['codice'] === 403 && str_contains($r['corpo'], 'Accesso negato'), "solo richieste: la scheda Modulistica (solo per chi ha tutto il modulo) è negata", (string) $r['codice']);

// Le richieste nelle «Cose da fare»: scrivania del pannello, menu a sinistra del modulo e «Il mio lavoro» dell'area personale
$re_da_guardare = '/Richiest[ae] di pubblicazione da guardare/';
$r = $http('/mydibest/admin/amministrazione.php', null, $j_r);
prova($apre($r) && preg_match('/href="pubblicazioni\.php\?vista=arrivate[^"]*"[^>]*>(?:(?!<\/a>).)*Richieste di pubblicazione(?:(?!<\/a>).)*<span class="side-num"[^>]*>\d+<\/span><\/a>/s', $r['corpo']) === 1, "menu a sinistra: «Richieste di pubblicazione» col numero di quelle da guardare");
$j_ra = $accedi(99964);
$r = $http('/mydibest/admin/inizio.php', null, $j_ra);
prova($apre($r) && preg_match($re_da_guardare, $r['corpo']) === 1 && str_contains($r['corpo'], 'pubblicazioni.php?vista=arrivate') && preg_match('/Rig(?:a|he) del file Excel da completare/', $r['corpo']) === 1 && str_contains($r['corpo'], 'pubblicazioni.php?vista=monitoraggio'),
    "scrivania: per chi ha le richieste (e un'area) ci sono le righe «da guardare» e «del file Excel da completare»");
prova(!str_contains($r['corpo'], 'in lavorazione'), "scrivania: le richieste «in lavorazione» non compaiono");
$r = $http('/mydibest/admin/inizio.php', null, $jar);
prova($apre($r) && preg_match($re_da_guardare, $r['corpo']) === 1, "scrivania: l'amministratore vede le richieste da guardare");
foreach (['rita' => $j_r, 'rosa' => $j_ra, 'tina' => $accedi(99962)] as $chi => $jj) {
    $r = $http('/mydibest/area_personale.php', null, $jj);
    prova($apre($r, 'Il mio lavoro', 'admin/pubblicazioni.php?vista=arrivate', 'admin/pubblicazioni.php?vista=monitoraggio') && str_contains($r['corpo'], 'Richieste di pubblicazione'), "area personale ($chi): «Il mio lavoro» ha la postazione Richieste di pubblicazione");
}

// Chi ha tutto il modulo
$j_t = $accedi(99962);
prova($apre($http('/mydibest/admin/amministrazione.php', null, $j_t), 'Richieste da guardare') && $apre($http('/mydibest/admin/pubblicazioni.php', null, $j_t), 'Richieste di pubblicazione'), "tutto il modulo: amministrazione.php e pubblicazioni.php si aprono");

// Chi non ha il permesso: accesso negato (anche sulla scheda di una richiesta e sul vecchio indirizzo)
$j_n = $accedi(99963);
foreach (['amministrazione.php', 'pubblicazioni.php', 'pubblicazioni.php?id=' . $id_pb, 'pubblicazioni.php?vista=monitoraggio&scarica=csv'] as $pag) {
    $r = $http('/mydibest/admin/' . $pag, null, $j_n);
    prova($r['codice'] === 403 && str_contains($r['corpo'], 'Accesso negato') && !str_contains($r['corpo'], 'Richiesta di prova AM1'), "senza permesso: $pag → accesso negato", (string) $r['codice']);
}
$r = $http('/mydibest/admin/inizio.php?sezione=pubblicazioni', null, $j_n);
prova(!in_array($r['codice'], [302, 303], true) || !str_ends_with($r['dove'], '/admin/amministrazione.php'), "senza permesso: ?sezione=pubblicazioni non porta all'Amministrazione");
// Senza accesso: si rimanda al login
foreach (['amministrazione.php', 'pubblicazioni.php?id=' . $id_pb] as $pag) {
    $r = $http('/mydibest/admin/' . $pag);
    prova(in_array($r['codice'], [302, 303], true) && str_contains($r['dove'], 'saml_login.php'), "senza accesso: $pag rimanda al login", $r['codice'] . ' ' . $r['dove']);
}

// Chi non ha il permesso: né righe in scrivania né postazione nell'area personale
$r = $http('/mydibest/area_personale.php', null, $j_n);
prova($apre($r) && !str_contains($r['corpo'], 'pubblicazioni.php?vista=arrivate') && !str_contains($r['corpo'], 'Richieste di pubblicazione'), "area personale: senza permesso la postazione Richieste di pubblicazione non c'è");
$j_nn = $accedi(99963);
$loc->query("INSERT INTO abilitazioni_ambito (utente_id, tipo, pagina_id) VALUES (99963, 'eventi', $id_area_x)");
$r = $http('/mydibest/admin/inizio.php', null, $j_nn);
prova($apre($r) && preg_match($re_da_guardare, $r['corpo']) !== 1 && !str_contains($r['corpo'], 'pubblicazioni.php?vista='), "scrivania: senza permesso (anche con un'area) le richieste non ci sono");

// ── Modulistica amministrativa (AM2): scheda del pannello, pagina pubblica, modulo.php e moduli della Didattica ──
sezione("Amministrazione: Modulistica amministrativa (scheda del pannello, pagina pubblica, moduli della Didattica)");
$loc->query("DELETE FROM didattica_moduli WHERE id IN (99911, 99912, 99921) OR titolo LIKE 'Documento prova AM2%'");
$loc->query("INSERT INTO didattica_moduli (id, titolo, categoria, descrizione, tipo, file_path, link, attivo, ordine, ambito) VALUES
    (99911, 'Documento prova AM2 amministrativo', 'Prova AM2 missioni', '<p>Come chiedere una missione</p>', 'documento', 'uploads/modulistica/prova_am2.pdf', 'https://www.unical.it/prova-am2', 1, 1, 'amministrazione'),
    (99912, 'Documento prova AM2 nascosto', 'Prova AM2 missioni', '', 'documento', NULL, NULL, 0, 2, 'amministrazione'),
    (99921, 'Documento prova AM2 della didattica', 'Prova AM2 tesi', '', 'documento', 'uploads/modulistica/prova_am2_did.pdf', NULL, 1, 1, 'didattica')");
$loc->query("DELETE FROM utenti WHERE id IN (99965, 99966)");
$loc->query("INSERT INTO utenti (id, codice_fiscale, nome, cognome, email, ruolo_id, ruoli_secondari) VALUES
    (99965, 'AMMPAG99965XXXXX', 'Dora', 'Docente', 'dora.docente.amm@example.org', 4, '6'),
    (99966, 'AMMPAG99966XXXXX', 'Sara', 'Studente', 'sara.studente.amm@example.org', 3, '')");
$conta_mod = fn(string $dove) => (int) $loc->query("SELECT COUNT(*) FROM didattica_moduli WHERE $dove")->fetch_row()[0];
$j_d = $accedi(99965);
$j_s = $accedi(99966);

// Pagina pubblica: anonimo, docente, studente
$r = $http('/mydibest/modulistica_amministrativa.php');
prova($apre($r, 'Home</a>', 'Modulistica amministrativa</span>', 'Procedure online', 'Richiesta di pubblicazione di un’iniziativa', 'Accedi e compila', 'Documento prova AM2 amministrativo', 'Prova AM2 missioni', 'uploads/modulistica/prova_am2.pdf', 'https://www.unical.it/prova-am2', 'mod-voce')
    && !str_contains($r['corpo'], 'Documento prova AM2 nascosto') && !str_contains($r['corpo'], 'Documento prova AM2 della didattica') && !str_contains($r['corpo'], 'Compila online') && !str_contains($r['corpo'], 'Prenota un appuntamento'),
    "modulistica_amministrativa.php (anonimo): procedura con «Accedi e compila», solo i documenti pubblicati dell'Amministrazione, niente moduli online né sportelli");
$r = $http('/mydibest/modulistica_amministrativa.php', null, $j_d);
prova($apre($r, 'Procedure online', 'Compila online', 'richiesta_pubblicazione.php', 'Documento prova AM2 amministrativo') && !str_contains($r['corpo'], 'Accedi e compila') && !str_contains($r['corpo'], 'Riservata a'), "modulistica_amministrativa.php (docente): «Compila online»");
$r = $http('/mydibest/modulistica_amministrativa.php', null, $j_s);
prova($apre($r, 'Procedure online', 'Riservata a: Docenti, Personale tecnico amministrativo', 'Documento prova AM2 amministrativo') && !str_contains($r['corpo'], 'Compila online') && !str_contains($r['corpo'], 'Accedi e compila'), "modulistica_amministrativa.php (studente): «Riservata a: Docenti, Personale tecnico amministrativo»");
$r = $http('/mydibest/modulistica_amministrativa.php?q=missione');
prova($apre($r, 'Documento prova AM2 amministrativo') && !str_contains($r['corpo'], 'Procedure online'), "modulistica_amministrativa.php: la ricerca filtra documenti e procedure");
$r = $http('/mydibest/modulistica_amministrativa.php?q=zzzzqqqq');
prova($apre($r, 'Modulistica amministrativa') && !str_contains($r['corpo'], 'Documento prova AM2 amministrativo') && !str_contains($r['corpo'], 'Procedure online'), "modulistica_amministrativa.php: una ricerca senza risultati non mostra niente");

// La Modulistica della Didattica non vede i documenti amministrativi (e viceversa)
foreach (['modulistica.php', 'modulistica.php?q=prova%20am2'] as $pag) {
    $r = $http('/mydibest/' . $pag);
    prova($apre($r, 'Modulistica', 'Documento prova AM2 della didattica') && !str_contains($r['corpo'], 'Documento prova AM2 amministrativo') && !str_contains($r['corpo'], 'Prova AM2 missioni') && !str_contains($r['corpo'], 'Procedure online'), "$pag: solo i moduli della Didattica");
}

// Scheda «Modulistica» del pannello: amministratore e «tutto il modulo» sì; solo richieste, nessun permesso e anonimo no
$r = $http('/mydibest/admin/amministrazione.php?tab=modulistica', null, $jar);
prova($apre($r, 'Procedure online', 'Richiesta di pubblicazione di un’iniziativa', 'Chi può inviarla: Docenti, Personale tecnico amministrativo', 'Aperta', 'Pagina pubblica', 'pubblicazioni.php?vista=arrivate', 'Documenti e link', 'Nuovo documento o link', 'Documento prova AM2 amministrativo', 'Documento prova AM2 nascosto', 'Non pubblicato', 'Prova AM2 missioni')
    && !str_contains($r['corpo'], 'Documento prova AM2 della didattica') && str_contains($r['corpo'], 'href="amministrazione.php?tab=modulistica&amp;p_id='), "scheda Modulistica da amministratore: procedure in cima, documenti dell'Amministrazione (non quelli della Didattica), voce nel menu");
$r = $http('/mydibest/admin/amministrazione.php?tab=modulistica&nuovo=1', null, $jar);
prova($apre($r, 'Nuovo documento o link', 'name="titolo"', 'name="file_modulo"', 'name="link"', 'arriveranno più avanti') && !str_contains($r['corpo'], 'id="mTipo"') && !str_contains($r['corpo'], 'Modulo online (apre una pratica)'), "scheda Modulistica: il modulo nuovo non offre il tipo «online» (riga in grigio)");
$tok = preg_match('/name="csrf_token" value="([^"]+)"/', $r['corpo'], $mc) ? $mc[1] : '';
$r = $http('/mydibest/admin/amministrazione.php?tab=modulistica&modifica=99911', null, $jar);
prova($apre($r, 'Modifica documento o link', 'Documento prova AM2 amministrativo', 'File attuale'), "scheda Modulistica: modifica di un documento dell'Amministrazione");
$r = $http('/mydibest/admin/amministrazione.php?tab=modulistica&modifica=99921', null, $jar);
prova($apre($r, 'Documento non trovato') && !str_contains($r['corpo'], 'Modifica documento o link') && !str_contains($r['corpo'], 'name="modulo_id" value="99921"'), "scheda Modulistica: l'id di un modulo della Didattica non si apre in modifica");
$r = $http('/mydibest/admin/amministrazione.php?tab=inesistente', null, $jar);
prova($apre($r, 'Richieste da guardare'), "amministrazione.php: una scheda che non esiste ripiega sulla home");
$j_t = $accedi(99962);
$r = $http('/mydibest/admin/amministrazione.php?tab=modulistica', null, $j_t);
prova($apre($r, 'Procedure online', 'Documenti e link'), "scheda Modulistica: chi ha tutto il modulo la apre");
$r = $http('/mydibest/admin/amministrazione.php', null, $j_t);
prova($apre($r, 'Tutte le sezioni', 'amministrazione.php?tab=modulistica', 'Modulistica'), "home dell'Amministrazione: «Modulistica» in «Tutte le sezioni» e nel menu per chi può tutto");
$j_r = $accedi(99961);
$r = $http('/mydibest/admin/amministrazione.php?tab=modulistica', null, $j_r);
prova($r['codice'] === 403 && str_contains($r['corpo'], 'Accesso negato') && !str_contains($r['corpo'], 'Documento prova AM2'), "scheda Modulistica: chi ha solo le richieste di pubblicazione → accesso negato", (string) $r['codice']);
$r = $http('/mydibest/admin/amministrazione.php', null, $j_r);
prova($apre($r, 'Richieste da guardare') && !str_contains($r['corpo'], 'tab=modulistica'), "home dell'Amministrazione: chi ha solo le richieste non vede «Modulistica» né nel menu né nell'elenco");
$j_n = $accedi(99963);
$r = $http('/mydibest/admin/amministrazione.php?tab=modulistica', null, $j_n);
prova($r['codice'] === 403 && str_contains($r['corpo'], 'Accesso negato'), "scheda Modulistica: senza permesso → accesso negato", (string) $r['codice']);
$r = $http('/mydibest/admin/amministrazione.php?tab=modulistica');
prova(in_array($r['codice'], [302, 303], true) && str_contains($r['dove'], 'saml_login.php'), "scheda Modulistica: senza accesso rimanda al login", $r['codice'] . ' ' . $r['dove']);

// Azioni della scheda (POST con codice di sicurezza, permesso controllato sul server)
$amm_url = '/mydibest/admin/amministrazione.php?tab=modulistica';
$prima = $conta_mod('1');
$http($amm_url, ['csrf_token' => $tok, 'salva_modulistica' => 1, 'modulo_id' => 0, 'titolo' => 'Documento prova AM2 online', 'tipo' => 'online', 'categoria' => 'Prova AM2 missioni'], $jar);
prova($conta_mod('1') === $prima && $conta_mod("titolo = 'Documento prova AM2 online'") === 0, "scheda Modulistica: un modulo «online» inviato a mano viene rifiutato dal server");
$http($amm_url, ['csrf_token' => $tok, 'salva_modulistica' => 1, 'modulo_id' => 0, 'titolo' => 'Documento prova AM2 salvato', 'categoria' => 'Prova AM2 missioni', 'attivo' => 1, 'link' => 'https://www.unical.it/prova-am2-post'], $jar);
$riga = $loc->query("SELECT id, ambito, tipo, attivo, link FROM didattica_moduli WHERE titolo = 'Documento prova AM2 salvato'")->fetch_assoc() ?: [];
prova(($riga['ambito'] ?? '') === 'amministrazione' && ($riga['tipo'] ?? '') === 'documento' && (int) ($riga['attivo'] ?? 0) === 1 && ($riga['link'] ?? '') === 'https://www.unical.it/prova-am2-post', "scheda Modulistica: salva un documento dell'Amministrazione", json_encode($riga));
$http($amm_url, ['csrf_token' => $tok, 'salva_modulistica' => 1, 'modulo_id' => 99921, 'titolo' => 'Manomesso AM2'], $jar);
prova($conta_mod("id = 99921 AND titolo = 'Documento prova AM2 della didattica'") === 1 && $conta_mod("titolo = 'Manomesso AM2'") === 0, "scheda Modulistica: cambiando l'id a mano non si modifica un modulo della Didattica");
$http($amm_url, ['csrf_token' => $tok, 'elimina_modulistica' => 99921], $jar);
prova($conta_mod('id = 99921') === 1, "scheda Modulistica: cambiando l'id a mano non si elimina un modulo della Didattica");
$http($amm_url, ['salva_modulistica' => 1, 'titolo' => 'Documento prova AM2 senza codice'], $jar);
prova($conta_mod("titolo = 'Documento prova AM2 senza codice'") === 0, "scheda Modulistica: un POST senza codice di sicurezza non salva");
$pag_r = $http('/mydibest/admin/pubblicazioni.php', null, $j_r);
$tok_r = preg_match('/name="csrf_token" value="([^"]+)"/', $pag_r['corpo'], $mr) ? $mr[1] : '';
$http($amm_url, ['csrf_token' => $tok_r, 'salva_modulistica' => 1, 'titolo' => 'Documento prova AM2 di chi non può'], $j_r);
$http($amm_url, ['csrf_token' => $tok_r, 'elimina_modulistica' => 99911], $j_r);
prova($tok_r !== '' && $conta_mod("titolo = 'Documento prova AM2 di chi non può'") === 0 && $conta_mod('id = 99911') === 1, "scheda Modulistica: chi ha solo le richieste non può salvare né eliminare (permesso ricontrollato sul server)");
if (!empty($riga['id'])) {
    $http($amm_url, ['csrf_token' => $tok, 'elimina_modulistica' => (int) $riga['id']], $jar);
    prova($conta_mod('id = ' . (int) $riga['id']) === 0, "scheda Modulistica: elimina un documento dell'Amministrazione");
}

// modulo.php: un documento amministrativo non è un modulo online e non apre una pratica (neppure con un POST)
$r = $http('/mydibest/modulo.php?id=99911', null, $j_d);
prova($r['codice'] === 404 && !$errore_php($r) && str_contains($r['corpo'], 'Questo non è un modulo online') && str_contains($r['corpo'], 'Documento prova AM2 amministrativo') && str_contains($r['corpo'], 'modulistica_amministrativa.php'), "modulo.php?id=<documento amministrativo>: rifiutato con un messaggio chiaro", (string) $r['codice']);
$r = $http('/mydibest/modulo.php?id=99911', ['invia_modulo' => 1, 'csrf_token' => 'x'], $j_d);
prova($r['codice'] === 404 && str_contains($r['corpo'], 'Questo non è un modulo online') && (int) $loc->query("SELECT COUNT(*) FROM pratiche WHERE modulo_id = 99911")->fetch_row()[0] === 0, "modulo.php: anche con un POST il documento amministrativo non apre una pratica");
$r = $http('/mydibest/modulo.php?id=99911&anteprima=1', null, $jar);
prova($r['codice'] === 404 && str_contains($r['corpo'], 'Questo non è un modulo online'), "modulo.php: nemmeno l'anteprima dell'amministratore lo apre");
$r = $http('/mydibest/modulo.php?id=99911');
prova(in_array($r['codice'], [302, 303], true), "modulo.php: senza accesso rimanda al login come sempre", (string) $r['codice']);
$r = $http('/mydibest/modulo.php?id=99921', null, $j_d);
prova($r['codice'] === 404 && str_contains($r['corpo'], 'Modulo non disponibile'), "modulo.php: un documento della Didattica (non online) resta «Modulo non disponibile»");

// La scheda «Moduli e documenti» della Didattica non cambia: stesso modulo, tutte le scelte, e senza i documenti amministrativi
$r = $http('/mydibest/admin/didattica.php?tab=moduli', null, $jar);
prova($apre($r, 'Nuovo modulo o documento', 'Documento prova AM2 della didattica') && !str_contains($r['corpo'], 'Documento prova AM2 amministrativo') && !str_contains($r['corpo'], 'Prova AM2 missioni'), "Didattica, Moduli e documenti: elenco solo dei moduli della Didattica");
$r = $http('/mydibest/admin/didattica.php?tab=moduli&nuovo=1', null, $jar);
prova($apre($r, 'Nuovo modulo', 'id="mTipo"', 'Modulo online (apre una pratica)', 'Documento da scaricare', 'name="titolo"', 'name="file_modulo"', 'Parti da un modello pronto', 'id="ddForm"'), "Didattica, Moduli e documenti: il modulo nuovo ha ancora tipo, modelli e tutti i passi");
$r = $http('/mydibest/admin/didattica.php?tab=moduli&modifica=99911', null, $jar);
prova($apre($r, 'Nuovo modulo o documento') && !str_contains($r['corpo'], 'Documento prova AM2 amministrativo') && !str_contains($r['corpo'], 'id="ddForm"'), "Didattica, Moduli e documenti: l'id di un documento amministrativo non si apre in modifica");

// Pulizia (Modulistica amministrativa)
$loc->query("DELETE FROM didattica_moduli WHERE id IN (99911, 99912, 99921) OR titolo LIKE 'Documento prova AM2%' OR titolo = 'Manomesso AM2'");
$loc->query("DELETE FROM utenti WHERE id IN (99965, 99966)");
foreach ([$j_d, $j_s, $j_t, $j_r, $j_n] as $j) is_file($j) && unlink($j);

// ── Impostazioni della Richiesta di pubblicazione (AM3): pagina della richiesta, Modulistica amministrativa, scheda «Impostazioni», vecchia vista ──
sezione("Amministrazione: impostazioni della Richiesta di pubblicazione (chi può inviare, apertura, testi, limiti, email)");
// Le impostazioni di adesso si rimettono alla fine; le persone di prova: PTA (gruppo dall'anagrafe), studente
$col_am3 = ['pubblicazioni_chi_puo_inviare', 'pubblicazioni_aperta', 'pubblicazioni_messaggio_chiusa', 'pubblicazioni_testo_introduttivo', 'pubblicazioni_testo_dopo_invio', 'pubblicazioni_max_sottotitolo',
    'pubblicazioni_max_descrizione', 'pubblicazioni_max_allegati', 'pubblicazioni_max_immagini', 'pubblicazioni_allegati_obbligatori', 'pubblicazioni_giorni_minimi', 'pubblicazioni_email_notifica', 'pubblicazioni_ricevuta',
    'pubblicazioni_note_operative', 'pubblicazioni_pagine'];
$imp_prima_am3 = $loc->query('SELECT ' . implode(', ', $col_am3) . ' FROM impostazioni_sistema WHERE id = 1')->fetch_assoc() ?: [];
$rimetti_am3 = function () use ($loc, $imp_prima_am3): void {
    if (!$imp_prima_am3) return;
    $st = $loc->prepare('UPDATE impostazioni_sistema SET ' . implode(', ', array_map(fn($c) => "$c = ?", array_keys($imp_prima_am3))) . ' WHERE id = 1');
    $v = array_values($imp_prima_am3);
    $st->bind_param(str_repeat('s', count($v)), ...$v);
    $st->execute();
};
$imposta_am3 = function (array $valori) use ($loc): void {
    $st = $loc->prepare('UPDATE impostazioni_sistema SET ' . implode(', ', array_map(fn($c) => "$c = ?", array_keys($valori))) . ' WHERE id = 1');
    $v = array_map('strval', array_values($valori));
    $st->bind_param(str_repeat('s', count($v)), ...$v);
    $st->execute();
};
prova($imp_prima_am3 !== [], "le colonne delle impostazioni ci sono (migrazione v131)");
$imposta_am3(['pubblicazioni_chi_puo_inviare' => 'docenti,pta', 'pubblicazioni_aperta' => 1, 'pubblicazioni_max_sottotitolo' => 75, 'pubblicazioni_max_descrizione' => 170, 'pubblicazioni_max_allegati' => 10,
    'pubblicazioni_max_immagini' => 10, 'pubblicazioni_allegati_obbligatori' => 1, 'pubblicazioni_giorni_minimi' => 0, 'pubblicazioni_ricevuta' => 1,
    'pubblicazioni_testo_introduttivo' => 'Richiesta di pubblicazione su pagina web e canali social istituzionali DiBEST, indirizzata al Direttore.',
    'pubblicazioni_testo_dopo_invio' => "La richiesta è arrivata all'ufficio comunicazione.", 'pubblicazioni_messaggio_chiusa' => 'Al momento le richieste di pubblicazione sono chiuse.']);
$id_pta_g = (int) ($loc->query("SELECT id FROM ruoli WHERE nome = 'Personale tecnico amministrativo'")->fetch_row()[0] ?? 0);
$loc->query("DELETE FROM pubblicazioni WHERE utente_id IN (99967, 99968)");
$loc->query("DELETE FROM utenti WHERE id IN (99967, 99968)");
$loc->query("INSERT INTO utenti (id, codice_fiscale, nome, cognome, email, ruolo_id, ruoli_secondari) VALUES
    (99967, 'AMMPAG99967XXXXX', 'Gina', 'Tecnica', 'gina.pta.amm@example.org', 4, '$id_pta_g'),
    (99968, 'AMMPAG99968XXXXX', 'Sara', 'Studente', 'sara.studente.am3@example.org', 3, '')");
prova($id_pta_g > 0, "il gruppo «Personale tecnico amministrativo» c'è nel database locale");
$j_p = $accedi(99967);
$j_s3 = $accedi(99968);
$j_t3 = $accedi(99962);
$j_r3 = $accedi(99961);
$rp = '/mydibest/richiesta_pubblicazione.php';

// richiesta_pubblicazione.php: anonimo, PTA, studente (valori di partenza)
$r = $http($rp);
prova($apre($r, 'Accedi per compilare', 'canali social istituzionali DiBEST, indirizzata al Direttore.') && !str_contains($r['corpo'], 'id="rp-form"'), "richiesta_pubblicazione.php (anonimo): testo in cima dalle impostazioni e «Accedi per compilare»");
$r = $http($rp, null, $j_p);
prova($apre($r, 'id="rp-form"', 'data-max-sottotitolo="75"', 'data-max-descrizione="170"', 'data-max-allegati="10"', 'data-allegati-obbligatori="1"', 'min="' . date('Y-m-d') . '"', 'non può essere una data passata', 'richiesta-pubblicazione.js?v=6')
    && !str_contains($r['corpo'], 'riservata a'), "richiesta_pubblicazione.php (PTA): il modulo a sette passi, con i limiti delle impostazioni");
$r = $http($rp, null, $j_s3);
prova($apre($r, 'Questa richiesta è riservata a: Docenti, Personale tecnico amministrativo.') && !str_contains($r['corpo'], 'id="rp-form"') && !str_contains($r['corpo'], 'riservata ai docenti'), "richiesta_pubblicazione.php (studente): «riservata a: Docenti, Personale tecnico amministrativo»");
$r = $http('/mydibest/area_personale.php', null, $j_p);
prova($apre($r, 'Le mie richieste di pubblicazione', 'Nuova richiesta'), "area personale (PTA): la tessera delle richieste di pubblicazione, con «Nuova richiesta»");
$r = $http('/mydibest/area_personale.php', null, $j_s3);
prova($apre($r) && !str_contains($r['corpo'], 'Le mie richieste di pubblicazione'), "area personale (studente senza richieste): niente tessera");
$loc->query("INSERT INTO pubblicazioni (utente_id, richiedente, stato, nel_monitoraggio, data_evento, titolo) VALUES (99968, 'Studente Sara', 'ricevuta', 0, '2026-12-05', 'Richiesta di prova AM3 della studentessa')");
$r = $http('/mydibest/area_personale.php', null, $j_s3);
prova($apre($r, 'Le mie richieste di pubblicazione', 'Richiesta di prova AM3 della studentessa') && !str_contains($r['corpo'], '>Nuova richiesta<'), "area personale (chi non può più inviare ma ne ha inviata una): la tessera c'è, senza «Nuova richiesta»");

// Limiti cambiati: contatori, file e giorni di anticipo presi dalle impostazioni
$imposta_am3(['pubblicazioni_max_sottotitolo' => 50, 'pubblicazioni_max_descrizione' => 120, 'pubblicazioni_max_allegati' => 3, 'pubblicazioni_allegati_obbligatori' => 0, 'pubblicazioni_giorni_minimi' => 2,
    'pubblicazioni_testo_introduttivo' => '<p>Prova AM3: <b>scrivi bene</b></p>', 'pubblicazioni_chi_puo_inviare' => 'docenti']);
$dopo2 = date('Y-m-d', strtotime('+2 days'));
$id_doc_g = (int) ($loc->query("SELECT id FROM ruoli WHERE nome = 'Docenti'")->fetch_row()[0] ?? 0);
$loc->query("DELETE FROM utenti WHERE id = 99965");
$loc->query("INSERT INTO utenti (id, codice_fiscale, nome, cognome, email, ruolo_id, ruoli_secondari) VALUES (99965, 'AMMPAG99965XXXXX', 'Dora', 'Docente', 'dora.docente.amm@example.org', 4, '$id_doc_g')");
$j_d3 = $accedi(99965);
$r = $http($rp, null, $j_d3);
prova($apre($r, 'id="rp-form"', 'data-max-sottotitolo="50"', '/ 50</div>', 'data-max-descrizione="120"', 'data-max-allegati="3"', 'data-allegati-obbligatori="0"', '(facoltativo)</span></label>',
        'min="' . $dopo2 . '"', 'almeno 2 giorni dopo oggi', '<b>scrivi bene</b>', 'data-messaggio-inizio="La data di inizio pubblicazione deve essere almeno 2 giorni dopo oggi')
    && !preg_match('/name="allegati\[\]"[^>]*required/', $r['corpo']), "richiesta_pubblicazione.php: limiti, allegati facoltativi, giorni di anticipo e testo in cima cambiati dalle impostazioni");
$r = $http($rp, null, $j_p);
prova($apre($r, 'Questa richiesta è riservata a: Docenti.') && !str_contains($r['corpo'], 'id="rp-form"'), "richiesta_pubblicazione.php: tolto il PTA da «Chi può inviare», il PTA non la compila più");

// Procedura chiusa: la pagina mostra solo il messaggio (anche a chi non è entrato), un POST non salva, la Modulistica dice «Chiusa»
$imposta_am3(['pubblicazioni_aperta' => 0, 'pubblicazioni_messaggio_chiusa' => 'Prova AM3: richieste chiuse fino a gennaio.', 'pubblicazioni_chi_puo_inviare' => 'docenti,pta']);
foreach (['anonimo' => '', 'PTA' => $j_p, 'docente' => $j_d3, 'amministratore' => $jar] as $chi => $j) {
    $r = $http($rp, null, $j);
    prova($apre($r, 'Prova AM3: richieste chiuse fino a gennaio.') && !str_contains($r['corpo'], 'id="rp-form"') && !str_contains($r['corpo'], 'Accedi per compilare') && !str_contains($r['corpo'], 'riservata a'), "richiesta_pubblicazione.php chiusa ($chi): solo il messaggio delle impostazioni");
}
$pag_d = $http('/mydibest/area_personale.php', null, $j_d3);
$tok_d = preg_match('/name="csrf_token" value="([^"]+)"/', $pag_d['corpo'], $md) ? $md[1] : '';
$prima_pb = (int) $loc->query("SELECT COUNT(*) FROM pubblicazioni")->fetch_row()[0];
$http($rp, ['csrf_token' => $tok_d, 'ho_letto' => 1, 'data_evento' => '2026-12-05', 'modalita' => 'online', 'url_docente' => 'https://www.unical.it/am3', 'titolo' => 'Richiesta di prova AM3 chiusa',
    'sottotitolo' => 'x', 'descrizione' => 'y', 'per_studenti' => 1, 'tag' => 't', 'note' => 'n', 'data_inizio_pubblicazione' => '2027-01-10'], $j_d3);
prova((int) $loc->query("SELECT COUNT(*) FROM pubblicazioni")->fetch_row()[0] === $prima_pb, "richiesta_pubblicazione.php chiusa: un invio non salva niente");
foreach (['anonimo' => '', 'docente' => $j_d3] as $chi => $j) {
    $r = $http('/mydibest/modulistica_amministrativa.php', null, $j);
    prova($apre($r, 'Procedure online', 'Richiesta di pubblicazione di un’iniziativa', 'Chiusa</span>', 'Al momento non si possono inviare richieste.') && !str_contains($r['corpo'], 'Compila online') && !str_contains($r['corpo'], 'Accedi e compila'),
        "modulistica_amministrativa.php chiusa ($chi): «Chiusa» e nessun pulsante");
}
$r = $http('/mydibest/admin/amministrazione.php?tab=modulistica', null, $jar);
prova($apre($r, 'Chiusa', 'Chi può inviarla: Docenti, Personale tecnico amministrativo', 'amministrazione.php?tab=impostazioni') && !str_contains($r['corpo'], 'arriveranno più avanti.</li>') && !str_contains($r['corpo'], "L'interruttore per aprirla"),
    "scheda Modulistica: stato e chi presi dalle impostazioni, collegamento «Impostazioni», niente più riga in grigio");
$imposta_am3(['pubblicazioni_aperta' => 1]);

// Scheda «Impostazioni»: amministratore e «tutto il modulo» sì; solo richieste e senza permesso no
$r = $http('/mydibest/admin/amministrazione.php?tab=impostazioni', null, $jar);
prova($apre($r, 'Richiesta di pubblicazione', 'Chi può inviare la richiesta', 'name="pb_chi_profili[]" value="docenti" checked', 'name="pb_chi_profili[]" value="pta" checked', 'Apertura', 'name="aperta"', 'name="messaggio_chiusa"', 'Testi',
        'name="testo_introduttivo"', 'name="testo_dopo_invio"', 'Limiti', 'name="max_sottotitolo"', 'value="50"', 'name="giorni_minimi"', 'name="allegati_obbligatori"', 'Email', 'name="indirizzi"', 'name="ricevuta_al_richiedente"', 'salva_impostazioni_pubblicazioni')
    && !str_contains($r['corpo'], 'value="anonimi"') && substr_count($r['corpo'], 'name="salva_impostazioni_pubblicazioni"') === 1, "scheda Impostazioni (amministratore): le cinque parti, un solo «Salva», niente «anonimi»");
prova(preg_match('/class="nav-link w-100 active" href="pubblicazioni\.php\?vista=arrivate[^"]*"[^>]*>(?:(?!<\/a>).)*Richieste di pubblicazione/s', $r['corpo']) === 1 && !str_contains($r['corpo'], 'fa-sliders me-2'),
    "scheda Impostazioni: niente voce propria nel menu a sinistra, si accende «Richieste di pubblicazione»");
$tok_a = preg_match('/name="csrf_token" value="([^"]+)"/', $r['corpo'], $ma) ? $ma[1] : '';
$r = $http('/mydibest/admin/amministrazione.php', null, $jar);
prova($apre($r, 'amministrazione.php?tab=impostazioni', 'Impostazioni'), "home dell'Amministrazione: «Impostazioni» in «Tutte le sezioni»");
$r = $http('/mydibest/admin/amministrazione.php?tab=impostazioni', null, $j_t3);
prova($apre($r, 'Chi può inviare la richiesta', 'name="indirizzi"'), "scheda Impostazioni: chi ha tutto il modulo la apre");
$r = $http('/mydibest/admin/amministrazione.php?tab=impostazioni', null, $j_r3);
prova($r['codice'] === 403 && str_contains($r['corpo'], 'Accesso negato'), "scheda Impostazioni: chi ha solo le richieste di pubblicazione → accesso negato", (string) $r['codice']);
$r = $http('/mydibest/admin/amministrazione.php', null, $j_r3);
prova($apre($r, 'Richieste da guardare') && !str_contains($r['corpo'], 'tab=impostazioni'), "solo richieste: nessuna voce «Impostazioni» del modulo (menu e home)");

// Salvataggio dalla scheda: con un errore non si salva niente (e si vede accanto al campo), poi un salvataggio vero con la riga nel registro
$base_post = ['csrf_token' => $tok_a, 'salva_impostazioni_pubblicazioni' => 1, 'pb_chi_profili' => ['docenti', 'pta', 'anonimi'], 'aperta' => 1, 'messaggio_chiusa' => 'Chiuse.',
    'testo_introduttivo' => '<p>Prova AM3 salvata</p>', 'testo_dopo_invio' => 'Grazie.', 'note_operative' => 'Nota di prova: scrivi bene.', 'pagine_pubblicazione' => '', 'max_sottotitolo' => '60', 'max_descrizione' => '170', 'max_allegati' => '10', 'max_immagini' => '10',
    'allegati_obbligatori' => 1, 'giorni_minimi' => '0', 'indirizzi' => 'comunicazione.dibest@unical.it', 'ricevuta_al_richiedente' => 1];
$r = $http('/mydibest/admin/amministrazione.php?tab=impostazioni', array_merge($base_post, ['max_sottotitolo' => '0', 'indirizzi' => 'non-valido']), $jar);
$v_am3 = fn(string $c) => (string) $loc->query("SELECT $c FROM impostazioni_sistema WHERE id = 1")->fetch_row()[0];
prova($apre($r, 'Le impostazioni non sono state salvate', 'Caratteri del sottotitolo: scrivi un numero intero da 1 a 100.', 'Questi indirizzi non sono validi: non-valido.', 'value="non-valido"') && $v_am3('pubblicazioni_max_sottotitolo') === '50',
    "scheda Impostazioni: errori campo per campo e nessun salvataggio");
$n_log = (int) $loc->query("SELECT COUNT(*) FROM log_attivita WHERE azione = 'Richiesta di pubblicazione: impostazioni modificate'")->fetch_row()[0];
$r = $http('/mydibest/admin/amministrazione.php?tab=impostazioni', $base_post, $jar);
$log_am3 = $loc->query("SELECT dettagli_json FROM log_attivita WHERE azione = 'Richiesta di pubblicazione: impostazioni modificate' ORDER BY id DESC LIMIT 1")->fetch_row()[0] ?? '';
prova(in_array($r['codice'], [200, 302, 303], true) && $v_am3('pubblicazioni_max_sottotitolo') === '60' && $v_am3('pubblicazioni_chi_puo_inviare') === 'docenti,pta' && $v_am3('pubblicazioni_testo_introduttivo') === '<p>Prova AM3 salvata</p>'
    && (int) $loc->query("SELECT COUNT(*) FROM log_attivita WHERE azione = 'Richiesta di pubblicazione: impostazioni modificate'")->fetch_row()[0] === $n_log + 1 && str_contains((string) $log_am3, '50 → 60'),
    "scheda Impostazioni: salva (senza «anonimi») e scrive nel registro cosa è cambiato", (string) $log_am3);
$http('/mydibest/admin/amministrazione.php?tab=impostazioni', array_merge($base_post, ['max_sottotitolo' => '99']), $j_r3);
prova($v_am3('pubblicazioni_max_sottotitolo') === '60', "scheda Impostazioni: chi ha solo le richieste non salva (permesso ricontrollato sul server)");
$http('/mydibest/admin/amministrazione.php?tab=impostazioni', array_merge($base_post, ['csrf_token' => 'sbagliato', 'max_sottotitolo' => '98']), $jar);
prova($v_am3('pubblicazioni_max_sottotitolo') === '60', "scheda Impostazioni: senza il codice di sicurezza giusto non salva");

// Vecchia vista pubblicazioni.php?vista=impostazioni: chi può tutto va alla scheda, chi ha solo le richieste vede la sola parte Email (e la salva)
$r = $http('/mydibest/admin/pubblicazioni.php?vista=impostazioni', null, $jar);
prova(in_array($r['codice'], [302, 303], true) ? str_ends_with($r['dove'], '/admin/amministrazione.php?tab=impostazioni') : str_contains($r['corpo'], 'amministrazione.php?tab=impostazioni'), "pubblicazioni.php?vista=impostazioni (amministratore): rimanda alla scheda Impostazioni", $r['codice'] . ' ' . $r['dove']);
$r = $http('/mydibest/admin/pubblicazioni.php?vista=impostazioni', null, $j_r3);
prova($apre($r, 'Impostazioni delle richieste', 'Email', 'name="indirizzi"', 'name="ricevuta_al_richiedente"', 'Manda la ricevuta a chi invia la richiesta')
    && !str_contains($r['corpo'], 'name="pb_chi_profili[]"') && !str_contains($r['corpo'], 'name="max_sottotitolo"') && !str_contains($r['corpo'], 'name="aperta"') && !str_contains($r['corpo'], 'name="testo_introduttivo"'), "pubblicazioni.php?vista=impostazioni (solo richieste): la sola parte Email");
$tok_r3 = preg_match('/name="csrf_token" value="([^"]+)"/', $r['corpo'], $mr3) ? $mr3[1] : '';
$http('/mydibest/admin/pubblicazioni.php', ['csrf_token' => $tok_r3, 'azione' => 'impostazioni', 'indirizzi' => 'comunicazione.dibest@unical.it, prova.am3@example.org', 'max_sottotitolo' => '11', 'aperta' => 0], $j_r3);
prova($v_am3('pubblicazioni_email_notifica') === 'comunicazione.dibest@unical.it, prova.am3@example.org' && $v_am3('pubblicazioni_ricevuta') === '0' && $v_am3('pubblicazioni_max_sottotitolo') === '60' && $v_am3('pubblicazioni_aperta') === '1',
    "pubblicazioni.php?vista=impostazioni (solo richieste): salva indirizzi e ricevuta, e nient'altro");
$r = $http('/mydibest/admin/pubblicazioni.php?vista=impostazioni', null, $j_r3);
prova($apre($r, 'name="ricevuta_al_richiedente" value="1" aria-describedby') && !str_contains($r['corpo'], 'name="ricevuta_al_richiedente" value="1" checked'), "pubblicazioni.php?vista=impostazioni: la ricevuta spenta resta spenta");

// Categoria della Modulistica: suggerimento dell'Amministrazione; quello della Didattica non cambia
$r = $http('/mydibest/admin/amministrazione.php?tab=modulistica&nuovo=1', null, $jar);
prova($apre($r, 'placeholder="es. Missioni, Acquisti, Personale"') && !str_contains($r['corpo'], 'es. Tirocini, Piani di studio'), "scheda Modulistica: la Categoria suggerisce «es. Missioni, Acquisti, Personale»");
$r = $http('/mydibest/admin/didattica.php?tab=moduli&nuovo=1', null, $jar);
prova($apre($r, 'placeholder="es. Tirocini, Piani di studio"') && !str_contains($r['corpo'], 'Missioni, Acquisti'), "Didattica, Moduli e documenti: la Categoria suggerisce ancora «es. Tirocini, Piani di studio»");

// ───── AM4: scheda «Campi e colonne» e richiesta con un passo senza campi visibili ─────
// Da qui si invia con la sola locandina: «almeno un altro allegato» (acceso dalle prove qui sopra) si spegne; $rimetti_am3 rimette il valore di prima
$imposta_am3(['pubblicazioni_allegati_obbligatori' => 0]);
$n_campi_am4 = (int) $loc->query("SELECT COUNT(*) FROM pubblicazioni_campi")->fetch_row()[0] + (int) $loc->query("SELECT COUNT(*) FROM pubblicazioni_colonne")->fetch_row()[0];
prova($n_campi_am4 === 0, "campi e colonne: all'inizio nessuna personalizzazione salvata (valgono i valori del codice)", (string) $n_campi_am4);
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', null, $jar);
prova($apre($r, 'Campi del form', 'Colonne del file Excel', 'name="campo[sottotitolo][etichetta]"', 'name="campo[titolo][etichetta]"', 'Sempre visibile e obbligatorio.', 'name="colonna[f_pubblicazione_social][tipo]"',
        'name="colonna[f_pubblicazione_social][intestazione]"', 'Se cambi le intestazioni, il file scaricato non sarà più uguale alla Tabella di monitoraggio originale.', 'Torna alle intestazioni originali', 'name="salva_campi"', 'name="salva_colonne"')
    && str_contains($r['corpo'], 'name="campo[titolo][visibile]" value="1" checked disabled>') && str_contains($r['corpo'], 'name="colonna[titolo][visibile]" value="1" checked disabled>')
    && !str_contains($r['corpo'], 'name="colonna[titolo][tipo]"') && !preg_match('/name="campo\[sottotitolo\]\[visibile\]"[^>]*disabled/', $r['corpo']), "scheda Campi e colonne (amministratore): le due parti, le regole fisse accanto ai campi, l'avviso e il «Torna alle intestazioni originali»");
prova(preg_match('/class="nav-link w-100 active" href="pubblicazioni\.php\?vista=arrivate[^"]*"[^>]*>(?:(?!<\/a>).)*Richieste di pubblicazione/s', $r['corpo']) === 1 && !str_contains($r['corpo'], 'fa-table-list me-2'),
    "scheda Campi e colonne: niente voce propria nel menu a sinistra, si accende «Richieste di pubblicazione»");
$tok_c = preg_match('/name="csrf_token" value="([^"]+)"/', $r['corpo'], $mc) ? $mc[1] : '';
$r = $http('/mydibest/admin/amministrazione.php', null, $jar);
prova($apre($r, 'amministrazione.php?tab=campi', 'Campi e colonne'), "home dell'Amministrazione: «Campi e colonne» in «Tutte le sezioni»");
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', null, $j_t3);
prova($apre($r, 'Campi del form', 'Colonne del file Excel'), "scheda Campi e colonne: chi ha tutto il modulo la apre");
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', null, $j_r3);
prova($r['codice'] === 403 && str_contains($r['corpo'], 'Accesso negato'), "scheda Campi e colonne: chi ha solo le richieste di pubblicazione → accesso negato", (string) $r['codice']);
$r = $http('/mydibest/admin/amministrazione.php', null, $j_r3);
prova($apre($r, 'Richieste da guardare') && !str_contains($r['corpo'], 'tab=campi'), "solo richieste: nessuna voce «Campi e colonne» (menu e home)");

// I valori di partenza dei campi (come li manda la scheda), con i cambi di questa prova
$post_campi_am4 = function (array $cambi = []) use ($tok_c): array {
    $post = ['csrf_token' => $tok_c, 'salva_campi' => 1];
    $i = 0;
    foreach (\App\Pubblicazioni\ConfigurazioneCampi::CAMPI as $k => $d) {
        $v = ['etichetta' => $d['etichetta'], 'aiuto' => $d['aiuto'], 'ordine' => ++$i];
        if ($d['obbligatorio']) $v['obbligatorio'] = 1;
        $v['visibile'] = 1;
        $v = array_merge($v, $cambi[$k] ?? []);
        foreach (['obbligatorio', 'visibile'] as $c) if (empty($v[$c])) unset($v[$c]);
        $post['campo'][$k] = $v;
    }
    return $post;
};
$n_righe_am4 = fn(string $t) => (int) $loc->query("SELECT COUNT(*) FROM $t")->fetch_row()[0];
// Un errore: nessun salvataggio e il messaggio accanto al campo
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', $post_campi_am4(['titolo' => ['etichetta' => '  ']]), $jar);
prova($apre($r, 'Le modifiche non sono state salvate', '«Titolo»: scrivi il nome del campo.') && $n_righe_am4('pubblicazioni_campi') === 0, "scheda Campi e colonne: nome vuoto → errore accanto al campo e nessun salvataggio");
// Chi ha solo le richieste, e un codice di sicurezza sbagliato, non salvano
$http('/mydibest/admin/amministrazione.php?tab=campi', $post_campi_am4(['sottotitolo' => ['etichetta' => 'Abusivo']]), $j_r3);
$http('/mydibest/admin/amministrazione.php?tab=campi', array_merge($post_campi_am4(['sottotitolo' => ['etichetta' => 'Abusivo']]), ['csrf_token' => 'sbagliato']), $jar);
prova($n_righe_am4('pubblicazioni_campi') === 0, "scheda Campi e colonne: chi ha solo le richieste e il codice sbagliato non salvano (permesso ricontrollato sul server)");
// Un salvataggio vero: sottotitolo rinominato e facoltativo, Titolo e Data non si nascondono neanche forzando il POST, Tag, data di inizio e Note nascosti = il passo 6 resta senza campi
$n_log_c = (int) $loc->query("SELECT COUNT(*) FROM log_attivita WHERE azione = 'Richiesta di pubblicazione: campi del form modificati'")->fetch_row()[0];
$http('/mydibest/admin/amministrazione.php?tab=campi', $post_campi_am4([
    'sottotitolo' => ['etichetta' => 'Frase breve', 'obbligatorio' => 0], 'tag' => ['visibile' => 0], 'data_inizio_pubblicazione' => ['visibile' => 0], 'note' => ['visibile' => 0], 'titolo' => ['visibile' => 0, 'obbligatorio' => 0], 'data_evento' => ['visibile' => 0]]), $jar);
$log_c = (string) ($loc->query("SELECT dettagli_json FROM log_attivita WHERE azione = 'Richiesta di pubblicazione: campi del form modificati' ORDER BY id DESC LIMIT 1")->fetch_row()[0] ?? '');
$righe_campi_am4 = $loc->query("SELECT chiave FROM pubblicazioni_campi ORDER BY chiave")->fetch_all(MYSQLI_NUM);
prova(array_column($righe_campi_am4, 0) === ['data_inizio_pubblicazione', 'note', 'sottotitolo', 'tag']
    && (int) $loc->query("SELECT COUNT(*) FROM log_attivita WHERE azione = 'Richiesta di pubblicazione: campi del form modificati'")->fetch_row()[0] === $n_log_c + 1 && str_contains($log_c, 'Frase breve') && str_contains($log_c, 'nascosto'),
    "scheda Campi e colonne: salva solo le differenze (Titolo e Data restano fissi) e scrive nel registro «prima → dopo»", $log_c);
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', null, $jar);
prova($apre($r, 'value="Frase breve"', 'Con queste scelte il passo non ha campi da mostrare') && !preg_match('/name="campo\[sottotitolo\]\[obbligatorio\]"[^>]*checked/', $r['corpo']), "scheda Campi e colonne: dopo il salvataggio si vedono le scelte e l'anteprima dice che il passo 6 non ha campi");

// La richiesta: il passo senza campi visibili non c'è, i passi si ricontano, il sottotitolo è facoltativo e ha il nome nuovo; il JavaScript riceve le regole
$r = $http($rp, null, $j_d3);
prova($apre($r, 'id="rp-form"', 'Passo 1 di 6', 'id="rp-passo-5"', 'id="rp-passo-7"', '>6. Riepilogo', 'name="locandina"', 'Frase breve <span class="fw-normal text-secondary">(facoltativo)</span>', 'richiesta-pubblicazione.js?v=6',
        'data-campi=', '&quot;visibile&quot;:false')
    && !str_contains($r['corpo'], 'id="rp-passo-6"') && !str_contains($r['corpo'], 'name="tag"') && !str_contains($r['corpo'], 'name="data_inizio_pubblicazione"') && !preg_match('/name="sottotitolo"[^>]*required/', $r['corpo']),
    "richiesta_pubblicazione.php: un passo senza campi visibili si salta (6 passi), campi nascosti assenti, sottotitolo facoltativo e rinominato");
// Un invio senza sottotitolo (facoltativo) e senza i campi del passo 6 (nascosti) viene accettato dal server
$pag_d2 = $http($rp, null, $j_d3);
$tok_d2 = preg_match('/name="csrf_token" value="([^"]+)"/', $pag_d2['corpo'], $md2) ? $md2[1] : '';
$invia_con_locandina($rp, ['csrf_token' => $tok_d2, 'ho_letto' => 1, 'data_evento' => '2026-12-05', 'modalita' => 'online', 'url_docente' => 'https://www.unical.it/am4', 'titolo' => 'Richiesta di prova AM4 senza sottotitolo',
    'descrizione' => 'Descrizione', 'per_studenti' => 1], $j_d3);
prova((int) $loc->query("SELECT COUNT(*) FROM pubblicazioni WHERE titolo = 'Richiesta di prova AM4 senza sottotitolo'")->fetch_row()[0] === 1, "richiesta_pubblicazione.php: sottotitolo facoltativo e campi nascosti non bloccano l'invio (con la sola locandina)");

// Colonne del file Excel: rinominata, nascosta, tornata all'originale
$post_colonne_am4 = function (array $cambi = []) use ($tok_c): array {
    $post = ['csrf_token' => $tok_c, 'salva_colonne' => 1];
    foreach (\App\Pubblicazioni\ColonneMonitoraggio::tutte() as $i => $d) {
        $v = ['intestazione' => $d['intestazione'], 'a_cura_di' => $d['a_cura_di'], 'colore' => strtolower($d['colore']), 'tipo' => $d['tipo'], 'visibile' => 1, 'ordine' => $i + 1];
        $v = array_merge($v, $cambi[$d['chiave']] ?? []);
        if (empty($v['visibile'])) unset($v['visibile']);
        $post['colonna'][$d['chiave']] = $v;
    }
    return $post;
};
$http('/mydibest/admin/amministrazione.php?tab=campi', $post_colonne_am4(['f_pubblicazione_social' => ['intestazione' => 'Social? (SI/NO)'], 'u_produzione_locandina' => ['visibile' => 0], 'titolo' => ['visibile' => 0]]), $jar);
$colonne_am4 = $loc->query("SELECT chiave, intestazione, visibile FROM pubblicazioni_colonne ORDER BY chiave")->fetch_all(MYSQLI_ASSOC);
prova(count($colonne_am4) === 2 && $colonne_am4[0]['chiave'] === 'f_pubblicazione_social' && $colonne_am4[0]['intestazione'] === 'Social? (SI/NO)' && $colonne_am4[1]['chiave'] === 'u_produzione_locandina' && (string) $colonne_am4[1]['visibile'] === '0',
    "scheda Campi e colonne: colonne rinominata e nascosta salvate (la colonna del Titolo non si nasconde)", json_encode($colonne_am4));
$r = $http('/mydibest/admin/pubblicazioni.php?vista=monitoraggio', null, $jar);
prova($apre($r, 'Tabella di monitoraggio'), "pubblicazioni.php?vista=monitoraggio: si apre con le colonne personalizzate");
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', ['csrf_token' => $tok_c, 'ripristina_colonna' => 'f_pubblicazione_social'], $jar);
prova((int) $loc->query("SELECT COUNT(*) FROM pubblicazioni_colonne WHERE chiave = 'f_pubblicazione_social'")->fetch_row()[0] === 0 && $n_righe_am4('pubblicazioni_colonne') === 1, "scheda Campi e colonne: «Torna all'originale» di una colonna cancella la sua riga");
$http('/mydibest/admin/amministrazione.php?tab=campi', $post_colonne_am4(['sottotitolo' => ['intestazione' => 'Frase breve (excel)'], 'u_produzione_locandina' => ['visibile' => 0]]), $jar);
$http('/mydibest/admin/amministrazione.php?tab=campi', ['csrf_token' => $tok_c, 'ripristina_intestazioni' => 1], $jar);
prova((int) $loc->query("SELECT COUNT(*) FROM pubblicazioni_colonne WHERE intestazione IS NOT NULL")->fetch_row()[0] === 0 && $n_righe_am4('pubblicazioni_colonne') === 1, "scheda Campi e colonne: «Torna alle intestazioni originali» toglie le intestazioni e lascia la colonna nascosta");
$http('/mydibest/admin/amministrazione.php?tab=campi', ['csrf_token' => $tok_c, 'ripristina_campo' => 'sottotitolo'], $jar);
foreach (['tag', 'data_inizio_pubblicazione', 'note'] as $campo_am4) $http('/mydibest/admin/amministrazione.php?tab=campi', ['csrf_token' => $tok_c, 'ripristina_campo' => $campo_am4], $jar);
$r = $http($rp, null, $j_d3);
prova($apre($r, 'Passo 1 di 7', 'id="rp-passo-6"', 'name="tag"') && $n_righe_am4('pubblicazioni_campi') === 0, "richiesta_pubblicazione.php: tornati all'originale i campi, la pagina ha di nuovo sette passi");
$loc->query("DELETE FROM pubblicazioni_colonne");
$loc->query("DELETE FROM pubblicazioni_campi");
$togli_file('Richiesta di prova AM4%');
$loc->query("DELETE FROM pubblicazioni WHERE titolo LIKE 'Richiesta di prova AM4%'");

// ───── AM5: campi nuovi del form e colonne nuove degli operatori ─────
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', null, $jar);
prova($apre($r, 'Campi nuovi del form', 'Colonne nuove degli operatori', 'name="cx_etichetta[]"', 'id="cx-dati"', 'name="salva_campi_nuovi"', 'name="salva_colonne_nuove"', 'id="cn-modello"'), "scheda Campi e colonne: ci sono le parti «Campi nuovi del form» e «Colonne nuove degli operatori»");
$tok_c5 = preg_match('/name="csrf_token" value="([^"]+)"/', $r['corpo'], $m5) ? $m5[1] : '';
$post_cx5 = function (array $righe) use ($tok_c5): array {
    $post = ['csrf_token' => $tok_c5, 'salva_campi_nuovi' => 1] + array_fill_keys(['cx_etichetta', 'cx_tipo', 'cx_opzioni', 'cx_obbl', 'cx_aiuto', 'cx_chiave', 'cx_passo', 'cx_vis', 'cx_excel', 'cx_intest'], []);
    foreach ($righe as $x) {
        $x += ['tipo' => 'text', 'opzioni' => '', 'obbl' => '0', 'aiuto' => '', 'chiave' => '', 'passo' => '3', 'vis' => '1', 'excel' => '0', 'intest' => ''];
        foreach (['etichetta', 'tipo', 'opzioni', 'obbl', 'aiuto', 'chiave', 'passo', 'vis', 'excel', 'intest'] as $k) $post['cx_' . $k][] = (string) $x[$k];
    }
    return $post;
};
// Errore: una scelta senza le scelte → niente salvato, messaggio accanto
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', $post_cx5([['etichetta' => 'Livello', 'tipo' => 'radio', 'passo' => '3']]), $jar);
prova($apre($r, 'Le modifiche non sono state salvate', 'scrivi le scelte') && $n_righe_am4('pubblicazioni_campi') === 0, "campi nuovi: una scelta senza le scelte → errore e nessun salvataggio");
// Chi ha solo le richieste e il codice sbagliato non salvano
$http('/mydibest/admin/amministrazione.php?tab=campi', $post_cx5([['etichetta' => 'Abusivo']]), $j_r3);
$http('/mydibest/admin/amministrazione.php?tab=campi', array_merge($post_cx5([['etichetta' => 'Abusivo']]), ['csrf_token' => 'sbagliato']), $jar);
prova($n_righe_am4('pubblicazioni_campi') === 0, "campi nuovi: chi ha solo le richieste e il codice sbagliato non salvano");
// Due campi: una scelta obbligatoria (passo 3, colonna nel file) e una data facoltativa (passo 2, colonna nel file)
$n_log5 = (int) $loc->query("SELECT COUNT(*) FROM log_attivita WHERE azione = 'Richiesta di pubblicazione: campi nuovi del form modificati'")->fetch_row()[0];
$http('/mydibest/admin/amministrazione.php?tab=campi', $post_cx5([
    ['etichetta' => 'Livello', 'tipo' => 'radio', 'opzioni' => 'Base, Avanzato', 'obbl' => '1', 'passo' => '3', 'excel' => '1', 'intest' => 'Livello evento'],
    ['etichetta' => 'Scadenza iscrizioni', 'tipo' => 'date', 'passo' => '2', 'excel' => '1']]), $jar);
$campi5 = $loc->query("SELECT chiave, tipo, passo FROM pubblicazioni_campi ORDER BY chiave")->fetch_all(MYSQLI_ASSOC);
prova(count($campi5) === 2 && $campi5[0]['chiave'] === 'x_1' && $campi5[0]['tipo'] === 'radio' && $campi5[1]['chiave'] === 'x_2' && $campi5[1]['tipo'] === 'date'
    && (int) $loc->query("SELECT COUNT(*) FROM log_attivita WHERE azione = 'Richiesta di pubblicazione: campi nuovi del form modificati'")->fetch_row()[0] === $n_log5 + 1, "campi nuovi: salvati due campi (scelta e data), chiavi x_1 e x_2, e una riga nel registro", json_encode($campi5));
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', null, $jar);
prova($apre($r, 'value="Livello"', 'value="Scadenza iscrizioni"') && str_contains($r['corpo'], '"chiave":"x_1"'), "scheda Campi e colonne: i campi nuovi si rivedono nel costruttore");

// La richiesta: i campi nuovi sono in fondo al loro passo, con la scelta e la data; il JavaScript li conosce
$r = $http($rp, null, $j_d3);
$pos = fn(string $t) => strpos($r['corpo'], $t);
prova($apre($r, 'data-extra="x_1"', 'data-extra="x_2"', 'type="radio" name="x_1"', 'value="Avanzato"', 'type="date" class="form-control" style="max-width:220px;" name="x_2"', 'richiesta-pubblicazione.js?v=6', '&quot;extra&quot;:true')
    && $pos('name="descrizione"') < $pos('data-extra="x_1"') && $pos('data-extra="x_1"') < $pos('id="rp-passo-4"')
    && $pos('name="altri_link_docente"') < $pos('data-extra="x_2"') && $pos('data-extra="x_2"') < $pos('id="rp-passo-3"'),
    "richiesta_pubblicazione.php: il campo a scelta è in fondo al passo 3, la data in fondo al passo 2");
$tok_d5 = preg_match('/name="csrf_token" value="([^"]+)"/', $r['corpo'], $md5) ? $md5[1] : '';
$base5 = ['csrf_token' => $tok_d5, 'ho_letto' => 1, 'data_evento' => '2026-12-05', 'modalita' => 'online', 'url_docente' => 'https://www.unical.it/am5', 'titolo' => 'Richiesta di prova AM5',
    'descrizione' => 'Descrizione', 'per_studenti' => 1, 'tag' => 't', 'note' => 'n', 'sottotitolo' => 's', 'data_inizio_pubblicazione' => date('Y-m-d', strtotime('+10 days'))];
// La scelta è obbligatoria: senza, la richiesta torna con l'errore
$r = $http($rp, $base5, $j_d3);
prova($apre($r, 'Livello: campo obbligatorio') && (int) $loc->query("SELECT COUNT(*) FROM pubblicazioni WHERE titolo = 'Richiesta di prova AM5'")->fetch_row()[0] === 0, "richiesta_pubblicazione.php: campo nuovo obbligatorio vuoto → rifiutata, con l'errore");
$r = $invia_con_locandina($rp, $base5 + ['x_1' => 'Avanzato', 'x_2' => '2026-12-01'], $j_d3);
$riga5 = $loc->query("SELECT id, campi_extra_json FROM pubblicazioni WHERE titolo = 'Richiesta di prova AM5'")->fetch_assoc() ?: ['id' => 0, 'campi_extra_json' => ''];
$err5 = preg_match('/id="rp-errori-server".*?<\/ul>/s', $r['corpo'], $me5) ? trim(strip_tags(str_replace('</li>', ' | ', $me5[0]))) : ($r['codice'] . ' ' . $r['dove']);
prova((int) $riga5['id'] > 0 && json_decode((string) $riga5['campi_extra_json'], true) === ['x_1' => 'Avanzato', 'x_2' => '2026-12-01'], "richiesta_pubblicazione.php: con la scelta e la data la richiesta arriva e i valori sono salvati", (string) $riga5['campi_extra_json'] . ' ' . $err5);
$id5 = (int) $riga5['id'];
// La scheda dell'operatore e il file scaricato
$r = $http('/mydibest/admin/pubblicazioni.php?id=' . $id5, null, $jar);
prova($apre($r, 'Livello', 'Avanzato', 'Scadenza iscrizioni', '01/12/2026'), "scheda della richiesta: i campi nuovi si leggono (la data come gg/mm/aaaa)");
$tok_s5 = preg_match('/name="csrf_token" value="([^"]+)"/', $r['corpo'], $ms5) ? $ms5[1] : '';
$http('/mydibest/admin/pubblicazioni.php', ['csrf_token' => $tok_s5, 'azione' => 'invia_excel', 'id' => $id5, 'da' => 'scheda'], $jar);
$r = $http('/mydibest/admin/pubblicazioni.php?vista=monitoraggio&scarica=csv', null, $jar);
$righe_csv5 = preg_split('/\r\n|\n/', trim((string) $r['corpo']));
$sep5 = substr_count((string) ($righe_csv5[0] ?? ''), ';') > substr_count((string) ($righe_csv5[0] ?? ''), ',') ? ';' : ',';
$testa5 = str_getcsv(ltrim((string) ($righe_csv5[0] ?? ''), "\xEF\xBB\xBF"), $sep5);
$i_liv = array_search('Livello evento', $testa5, true);
$i_scad = array_search('Scadenza iscrizioni', $testa5, true);
$ultima5 = '';
foreach (array_reverse($righe_csv5) as $riga_csv) { if (str_contains($riga_csv, 'Richiesta di prova AM5')) { $ultima5 = $riga_csv; break; } }
$celle5 = str_getcsv($ultima5, $sep5);
prova($r['codice'] === 200 && $i_liv !== false && $i_scad !== false && ($celle5[$i_liv] ?? '') === 'Avanzato' && ($celle5[$i_scad] ?? '') === '01/12/2026',
    "file scaricato (CSV): le colonne dei campi nuovi ci sono, con la scelta e la data", json_encode([$i_liv, $i_scad, $celle5[$i_liv] ?? null, $celle5[$i_scad] ?? null]));

// Colonna nuova degli operatori
$post_cn5 = fn(array $righe) => ['csrf_token' => $tok_c5, 'salva_colonne_nuove' => 1, 'ycol' => $righe];
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', $post_cn5(['nuova_1' => ['intestazione' => '', 'a_cura_di' => 'Ufficio', 'colore' => '#ff0000', 'tipo' => 'si_no', 'dopo' => 'titolo', 'visibile' => 1]]), $jar);
prova($apre($r, 'Le modifiche non sono state salvate', 'scrivi l&#039;intestazione') && $n_righe_am4('pubblicazioni_colonne') === 0, "colonne nuove: intestazione vuota → errore e nessun salvataggio");
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', $post_cn5(['nuova_1' => ['intestazione' => 'Altra', 'a_cura_di' => 'Ufficio', 'colore' => '#ff0000', 'tipo' => 'si_no', 'dopo' => 'colonna_che_non_c_e', 'visibile' => 1]]), $jar);
prova($apre($r, 'Le modifiche non sono state salvate', 'scegli dopo quale colonna sta') && $n_righe_am4('pubblicazioni_colonne') === 0, "colonne nuove: una posizione che non c'è → errore e nessun salvataggio");
$http('/mydibest/admin/amministrazione.php?tab=campi', $post_cn5(['nuova_1' => ['intestazione' => 'Esito social', 'a_cura_di' => 'Ufficio comunicazione', 'colore' => '#ff0000', 'tipo' => 'si_no', 'dopo' => 'titolo', 'visibile' => 1]]), $jar);
prova((string) ($loc->query("SELECT chiave FROM pubblicazioni_colonne")->fetch_row()[0] ?? '') === 'y_1', "colonne nuove: salvata la colonna y_1");
$r = $http('/mydibest/admin/pubblicazioni.php?id=' . $id5, null, $jar);
prova($apre($r, 'name="y_1"', 'Esito social', 'A cura di Ufficio comunicazione'), "scheda della richiesta (nel file Excel): la colonna nuova si compila come le altre");
$http('/mydibest/admin/pubblicazioni.php', ['csrf_token' => $tok_s5, 'azione' => 'salva', 'id' => $id5, 'da' => 'scheda', 'stato' => 'ricevuta', 'y_1' => 'forse'], $jar);
prova((string) $loc->query("SELECT colonne_extra_json FROM pubblicazioni WHERE id = $id5")->fetch_row()[0] === '', "scheda della richiesta: un valore sbagliato nella colonna SI/NO non si salva");
$http('/mydibest/admin/pubblicazioni.php', ['csrf_token' => $tok_s5, 'azione' => 'salva', 'id' => $id5, 'da' => 'scheda', 'stato' => 'ricevuta', 'y_1' => 'SI'], $jar);
prova(json_decode((string) $loc->query("SELECT colonne_extra_json FROM pubblicazioni WHERE id = $id5")->fetch_row()[0], true) === ['y_1' => 'SI'], "scheda della richiesta: il valore della colonna nuova si salva");
$r = $http('/mydibest/admin/pubblicazioni.php?id=' . $id5, null, $jar);
prova($apre($r, 'Esito social: (vuoto) → «SI»'), "scheda della richiesta: il registro dice «Esito social: (vuoto) → «SI»»");
// Eliminare: la colonna ha un valore → solo nascondere
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', null, $jar);
prova($apre($r, 'Ha valori in 1 richiesta', 'Non si può eliminare perché ha valori') && !str_contains($r['corpo'], 'name="elimina_colonna_nuova" value="y_1"'), "colonne nuove: con un valore la scheda dice in quante richieste e non offre «Elimina»");
$http('/mydibest/admin/amministrazione.php?tab=campi', ['csrf_token' => $tok_c5, 'elimina_colonna_nuova' => 'y_1'], $jar);
prova((int) $loc->query("SELECT COUNT(*) FROM pubblicazioni_colonne WHERE chiave = 'y_1'")->fetch_row()[0] === 1, "colonne nuove: forzando il POST l'eliminazione con valori è rifiutata dal server");
// Eliminare un campo con valori: rifiutato; il tipo non cambia; nascondere si può
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', $post_cx5([['etichetta' => 'Scadenza iscrizioni', 'tipo' => 'date', 'chiave' => 'x_2', 'passo' => '2', 'excel' => '1']]), $jar);
prova($apre($r, 'Le modifiche non sono state salvate', 'non si può eliminare') && (int) $loc->query("SELECT COUNT(*) FROM pubblicazioni_campi WHERE chiave LIKE 'x\_%'")->fetch_row()[0] === 2, "campi nuovi: eliminare un campo con valori è rifiutato (si può solo nascondere)");
$r = $http('/mydibest/admin/amministrazione.php?tab=campi', $post_cx5([['etichetta' => 'Livello', 'tipo' => 'text', 'chiave' => 'x_1', 'passo' => '3', 'obbl' => '1'], ['etichetta' => 'Scadenza iscrizioni', 'tipo' => 'date', 'chiave' => 'x_2', 'passo' => '2']]), $jar);
prova($apre($r, 'Le modifiche non sono state salvate', 'il tipo non si può cambiare') && (string) $loc->query("SELECT tipo FROM pubblicazioni_campi WHERE chiave = 'x_1'")->fetch_row()[0] === 'radio', "campi nuovi: il tipo di un campo con valori non si cambia");
$http('/mydibest/admin/amministrazione.php?tab=campi', $post_cx5([['etichetta' => 'Livello', 'tipo' => 'radio', 'opzioni' => 'Base, Avanzato', 'chiave' => 'x_1', 'passo' => '3', 'obbl' => '1', 'excel' => '1', 'intest' => 'Livello evento'], ['etichetta' => 'Scadenza iscrizioni', 'tipo' => 'date', 'chiave' => 'x_2', 'passo' => '2', 'vis' => '0', 'excel' => '1']]), $jar);
$r = $http($rp, null, $j_d3);
prova((int) $loc->query("SELECT visibile FROM pubblicazioni_campi WHERE chiave = 'x_2'")->fetch_row()[0] === 0 && !str_contains($r['corpo'], 'data-extra="x_2"') && str_contains($r['corpo'], 'data-extra="x_1"')
    && (string) $loc->query("SELECT campi_extra_json FROM pubblicazioni WHERE id = $id5")->fetch_row()[0] !== '', "campi nuovi: nascosto il campo con valori sparisce dalla richiesta e i valori restano");

// Pulizia delle prove AM5 (e i numeri delle chiavi tornano a zero)
$loc->query("DELETE FROM pubblicazioni_colonne");
$loc->query("DELETE FROM pubblicazioni_campi");
$togli_file('Richiesta di prova AM5%');
$loc->query("DELETE FROM pubblicazioni WHERE titolo LIKE 'Richiesta di prova AM5%'");
$loc->query("UPDATE impostazioni_sistema SET pubblicazioni_ultimo_x = 0, pubblicazioni_ultimo_y = 0 WHERE id = 1");

// ── Da compilare (AM7): la «Tabella completa», le colonne degli operatori in tabella e un solo «Salva» per più righe (di partenza ci sono le schede) ──
sezione("Amministrazione: «Da compilare» (colonne degli operatori in tabella, salvataggio di più righe insieme)");
$loc->query("DELETE FROM pubblicazioni WHERE titolo LIKE 'Richiesta di prova AM7%'");
$loc->query("DELETE FROM abilitazioni_ambito WHERE utente_id IN (99971, 99972)");
$loc->query("DELETE FROM utenti WHERE id IN (99971, 99972)");
$loc->query("INSERT INTO utenti (id, codice_fiscale, nome, cognome, email, ruolo_id) VALUES
    (99971, 'AMMPAG99971XXXXX', 'Sara', 'Studentessa', 'sara.studentessa.am7@example.org', 3),
    (99972, 'AMMPAG99972XXXXX', 'Floriana', 'Mantovano', 'floriana.mantovano.am7@example.org', 4)");
$loc->query("INSERT INTO abilitazioni_ambito (utente_id, tipo, pagina_id) VALUES (99972, 'modulo_pubblicazioni', 0)");
$loc->query("INSERT INTO pubblicazioni (richiedente, stato, nel_monitoraggio, data_evento, titolo) VALUES
    ('Rossi Maria', 'pubblicata', 1, '2026-12-05', 'Richiesta di prova AM7 prima'), ('Verdi Anna', 'pubblicata', 1, '2026-12-06', 'Richiesta di prova AM7 seconda')");
$id7a = (int) $loc->query("SELECT id FROM pubblicazioni WHERE titolo = 'Richiesta di prova AM7 prima'")->fetch_row()[0];
$id7b = (int) $loc->query("SELECT id FROM pubblicazioni WHERE titolo = 'Richiesta di prova AM7 seconda'")->fetch_row()[0];
$j_s7 = $accedi(99971);
$j_f7 = $accedi(99972);
$j_r7 = $accedi(99961); // abilitato alle sole richieste (Rita)
$j_n7 = $accedi(99963); // nessun permesso (Nino)
$u7 = '/mydibest/admin/pubblicazioni.php?vista=da_compilare&forma=tabella';
$da_compilare_db = fn() => (int) \App\Core\App::per($loc)->get(\App\Pubblicazioni\PubblicazioniRepository::class)->contaDaFare()['da_completare'];

// Chi può vedere la tabella: amministratore sì, abilitato alle sole richieste sì, studente e senza permessi no, senza accesso al login
$r = $http($u7, null, $jar);
prova($apre($r, 'Da compilare', 'Richiesta di prova AM7 prima', 'Richiesta di prova AM7 seconda', 'name="c[' . $id7a . '][c_altri_referenti]"', 'Solo le mie colonne', 'Solo le righe con celle vuote', 'Salva')
    && preg_match('/id="pbVuote"[^>]*checked/', $r['corpo']) === 1 && preg_match('/id="pbMie"[^>]*checked/', $r['corpo']) !== 1,
    "da_compilare (amministratore): la tabella si apre con le righe, le celle e il filtro «righe con celle vuote» acceso di partenza");
$tok7 = preg_match('/name="csrf_token" value="([^"]+)"/', $r['corpo'], $m7) ? $m7[1] : '';
$r = $http($u7, null, $j_r7);
prova($apre($r, 'Da compilare', 'Richiesta di prova AM7 prima'), "da_compilare (abilitato alle sole richieste): la tabella si apre");
foreach (['studente' => $j_s7, 'senza permessi' => $j_n7] as $chi => $jj) {
    $r = $http($u7, null, $jj);
    prova($r['codice'] === 403 && str_contains($r['corpo'], 'Accesso negato') && !str_contains($r['corpo'], 'Richiesta di prova AM7'), "da_compilare ($chi): accesso negato", (string) $r['codice']);
}
$r = $http($u7);
prova(in_array($r['codice'], [302, 303], true) && str_contains($r['dove'], 'saml_login.php'), "da_compilare (senza accesso): rimanda al login", $r['codice'] . ' ' . $r['dove']);

// Il numero della voce «Da compilare» nel menu a sinistra e nella home è quello di contaDaFare
$atteso7 = $da_compilare_db();
$r = $http($u7, null, $j_r7);
prova($atteso7 >= 2 && preg_match('/class="pn-scheda[^"]*" href="pubblicazioni\.php\?vista=da_compilare[^"]*"[^>]*>(?:(?!<\/a>).)*Da compilare(?:(?!<\/a>).)*<span class="pn-num[^"]*">(\d+)<\/span><\/a>/s', $r['corpo'], $mn) === 1 && (int) $mn[1] === $atteso7
    && !str_contains($r['corpo'], 'fa-pen-to-square me-2'),
    "«Da compilare»: scheda della pagina delle richieste col numero di righe da completare (uguale a contaDaFare), niente voce nel menu a sinistra", "atteso $atteso7, trovato " . ($mn[1] ?? '—'));
$r = $http('/mydibest/admin/pubblicazioni.php?vista=arrivate', null, $jar);
prova($apre($r, 'href="amministrazione.php?tab=campi"', 'Campi e colonne'), "pagina delle richieste (chi ha tutto il modulo): pulsante «Campi e colonne» nella testata");
$r = $http('/mydibest/admin/pubblicazioni.php?vista=arrivate', null, $j_r7);
prova($apre($r) && !str_contains($r['corpo'], 'tab=campi'), "pagina delle richieste (solo richieste): niente pulsante «Campi e colonne»");
$r = $http('/mydibest/admin/amministrazione.php', null, $j_r7);
prova($apre($r, 'pubblicazioni.php?vista=da_compilare', 'Da compilare') && preg_match('/(\d+) righ[ae] del file Excel (?:ha|hanno) celle vuote/', $r['corpo'], $mh) === 1 && (int) $mh[1] === $atteso7,
    "home dell'Amministrazione (anche per chi ha solo le richieste): voce «Da compilare» con lo stesso numero", "atteso $atteso7, trovato " . ($mh[1] ?? '—'));

// Salvataggio: una cella sbagliata in una riga → non si salva niente di tutta la pagina e la tabella si ridisegna con quanto scritto
$cella = fn(int $id, string $k) => $loc->query("SELECT $k FROM pubblicazioni WHERE id = $id")->fetch_row()[0];
$voci7 = fn(int $id) => (int) $loc->query("SELECT COUNT(*) FROM log_attivita WHERE azione = 'Richieste di pubblicazione: modifica' AND dettagli_json LIKE '{\"id\":$id,%'")->fetch_row()[0];
$post7 = fn(string $sbagliata) => ['csrf_token' => $tok7, 'azione' => 'salva_tabella', 'forma' => 'tabella', 'mie' => 0, 'vuote' => 1, 'pag' => 1,
    'c' => [$id7a => ['c_altri_referenti' => 'Prof. Bianchi'], $id7b => ['f_pubblicazione_social' => $sbagliata, 'c_altri_referenti' => 'Prof. Verdi']]];
$r = $http($u7 . '&mie=0&vuote=1', $post7('forse'), $jar);
prova($apre($r, 'Non è stato salvato niente', 'pb-errore', 'SI, NO', 'Prof. Bianchi', 'Prof. Verdi') && $cella($id7a, 'c_altri_referenti') === null && $cella($id7b, 'c_altri_referenti') === null && $voci7($id7a) === 0,
    "da_compilare: una cella sbagliata → niente salvato in nessuna riga, errore accanto alla cella e quanto scritto resta nella tabella");
$r = $http($u7 . '&mie=0&vuote=1', $post7('no'), $jar);
prova(in_array($r['codice'], [302, 303], true) && str_contains($r['dove'], 'vista=da_compilare') && $cella($id7a, 'c_altri_referenti') === 'Prof. Bianchi' && $cella($id7b, 'c_altri_referenti') === 'Prof. Verdi' && $cella($id7b, 'f_pubblicazione_social') === 'NO' && $voci7($id7a) === 1 && $voci7($id7b) === 1,
    "da_compilare: corretto, le due righe si salvano insieme e ognuna ha la sua voce nel registro delle modifiche", $r['codice'] . ' ' . $r['dove']);
$r = $http($r['dove'], null, $jar);
prova($apre($r, 'Salvate le modifiche a 2 richieste'), "da_compilare: dopo il salvataggio si torna alla tabella con il messaggio");
// chi non ha il permesso non salva niente, nemmeno forzando il POST
$r = $http($u7, ['csrf_token' => $tok7, 'azione' => 'salva_tabella', 'c' => [$id7a => ['c_altri_referenti' => 'Manomessa']]], $j_s7);
prova($r['codice'] === 403 && $cella($id7a, 'c_altri_referenti') === 'Prof. Bianchi', "da_compilare: lo studente non può salvare nemmeno forzando il POST", (string) $r['codice']);

// «Solo le mie colonne»: chi ha il nome tra gli «a cura di» vede solo le sue; chi non c'è, tutte (e la pagina lo dice)
$r = $http($u7 . '&mie=1&vuote=0', null, $j_f7);
prova($apre($r, 'name="c[' . $id7a . '][j_tipo_iniziativa]"', 'Le tue colonne:') && !str_contains($r['corpo'], 'name="c[' . $id7a . '][d_url_pubblicazione]"') && !str_contains($r['corpo'], 'Non ho trovato il tuo nome'),
    "da_compilare, «Solo le mie colonne»: si vedono solo le colonne a cura di chi guarda");
$r = $http($u7 . '&mie=1&vuote=0', null, $j_r7);
prova($apre($r, 'Non ho trovato il tuo nome', 'name="c[' . $id7a . '][d_url_pubblicazione]"'), "da_compilare, «Solo le mie colonne» senza nessuna corrispondenza: si mostrano tutte e la pagina lo dice");
$r = $http($u7 . '&vuote=1', null, $j_r7);
prova($apre($r, 'name="c[' . $id7b . '][j_tipo_iniziativa]"'), "da_compilare: con celle vuote da completare le righe restano elencate");

// Pulizia delle prove AM7
$loc->query("DELETE FROM pubblicazioni WHERE titolo LIKE 'Richiesta di prova AM7%'");
$loc->query("DELETE FROM log_attivita WHERE azione = 'Richieste di pubblicazione: modifica' AND (dettagli_json LIKE '{\"id\":$id7a,%' OR dettagli_json LIKE '{\"id\":$id7b,%')");
$loc->query("DELETE FROM abilitazioni_ambito WHERE utente_id IN (99971, 99972)");
$loc->query("DELETE FROM utenti WHERE id IN (99971, 99972)");
foreach ([$j_s7, $j_f7, $j_r7, $j_n7] as $j) is_file($j) && unlink($j);

// Pulizia (impostazioni di prima, persone e richieste di prova)
$rimetti_am3();
$loc->query("DELETE FROM pubblicazioni WHERE utente_id IN (99967, 99968) OR titolo LIKE 'Richiesta di prova AM3%'");
$loc->query("DELETE FROM utenti WHERE id IN (99965, 99967, 99968)");
foreach ([$j_p, $j_s3, $j_t3, $j_r3, $j_d3] as $j) is_file($j) && unlink($j);

// Pulizia
$loc->query("DELETE FROM pubblicazioni WHERE titolo LIKE 'Richiesta di prova AM1%'");
$loc->query("DELETE FROM abilitazioni_ambito WHERE utente_id IN (99961, 99962, 99963, 99964)");
$loc->query("DELETE FROM utenti WHERE id IN (99961, 99962, 99963, 99964)");
foreach ([$j_r, $j_ra, $j_t, $j_n, $j_nn, $pdf_prova] as $j) is_file($j) && unlink($j);
