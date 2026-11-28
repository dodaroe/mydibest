<?php
// Prove via HTTP (ambiente locale acceso) di un giro di SOS aule: il docente invia dal QR, il referente affida, lo studente dice «Non risolto», il referente risolve
// e al docente parte l'email di chiusura. Le transizioni vogliono «Sto andando» prima di «Non risolto» e «La riprendo io» prima di «Risolta».
// Chiamato da esegui.php: usa $http, $jar (amministratore), $loc. Quello che crea lo toglie; le impostazioni di prima tornano com'erano.
sezione("SOS aule: il giro completo (QR → affidata → non risolta → risolta)");

$so_token = fn(array $r): string => preg_match('/name="csrf_token" value="([^"]+)"/', $r['corpo'], $m) ? $m[1] : '';
$so_accedi = function (int $u) use ($http): string { $j = tempnam(sys_get_temp_dir(), 'jar'); $http('/__accesso?u=' . $u, null, $j); return $j; };
$so_uno = fn(string $q) => $loc->query($q)->fetch_row()[0] ?? null;

$http('/mydibest/admin/sos_aule.php', null, $jar); // lo schema si aggiorna da solo (v147)
$so_imp_prima = $loc->query('SELECT sos_aperto, sos_chi FROM impostazioni_sistema WHERE id = 1')->fetch_assoc() ?: [];
$so_ids = '99971, 99972, 99973';
$so_pulisci = function () use ($loc, $so_ids): void {
    $loc->query("DELETE FROM sos_segnalazioni WHERE utente_id IN ($so_ids)"); // la cronologia va via a cascata
    $loc->query("DELETE FROM sos_referenti WHERE persona_id = 'PROVA-SOS-P'");
    $loc->query("DELETE FROM sos_studenti WHERE email = 'studente.sos@studenti.unical.it'");
    $loc->query("DELETE FROM sos_aule WHERE codice = 'GZRPRVSS'");
    $loc->query("DELETE FROM utenti WHERE id IN ($so_ids)");
    $loc->query("DELETE FROM personale_ateneo WHERE id = 'PROVA-SOS-P'");
    $loc->query("DELETE FROM log_email WHERE destinatario LIKE '%.sos@example.org' OR destinatario = 'studente.sos@studenti.unical.it'");
};
$so_pulisci();
$loc->query("UPDATE impostazioni_sistema SET sos_aperto = 1, sos_chi = 'docenti,pta' WHERE id = 1");

$so_docenti = (int) $so_uno("SELECT id FROM ruoli WHERE nome = 'Docenti'");
$loc->query("INSERT INTO personale_ateneo (id, cognome, nome, email, attivo) VALUES ('PROVA-SOS-P', 'Referente', 'Paola', 'referente.sos@example.org', 1)");
$loc->query("INSERT INTO utenti (id, codice_fiscale, nome, cognome, email, ruolo_id, ruoli_secondari, persona_id) VALUES
    (99971, 'SOSGIRO99971XXXX', 'Dora', 'Docente', 'docente.sos@example.org', 4, '$so_docenti', NULL),
    (99972, 'SOSGIRO99972XXXX', 'Paola', 'Referente', 'referente.sos@example.org', 4, '', 'PROVA-SOS-P'),
    (99973, 'SOSGIRO99973XXXX', 'Sergio', 'Studente', 'studente.sos@studenti.unical.it', 4, '', NULL)");
$loc->query("INSERT INTO sos_aule (nome, edificio, codice) VALUES ('GIRO', 'Aula delle prove', 'GZRPRVSS')");
$so_aula = (int) $loc->insert_id;
$loc->query("INSERT INTO sos_referenti (ambito, aula_id, persona_id, nominativo) VALUES ('aula', $so_aula, 'PROVA-SOS-P', 'Referente Paola')");
$loc->query("INSERT INTO sos_studenti (nome, cognome, email) VALUES ('Sergio', 'Studente', 'studente.sos@studenti.unical.it')");
$so_studente = (int) $loc->insert_id;
$so_tipo = (int) $so_uno('SELECT id FROM sos_tipi WHERE attivo = 1 AND chiede_testo = 0 ORDER BY ordine, id LIMIT 1');
$so_esito = (int) $so_uno('SELECT id FROM sos_esiti WHERE attivo = 1 AND chiede_testo = 0 ORDER BY ordine, id LIMIT 1');
$so_email_da = (int) $so_uno('SELECT COALESCE(MAX(id), 0) FROM log_email');

$j_doc = $so_accedi(99971);
$j_ref = $so_accedi(99972);
$j_stu = $so_accedi(99973);

// Il docente, dal QR
$so_qr = '/mydibest/sos.php?aula=GZRPRVSS';
$r = $http($so_qr, null, $j_doc);
prova($r['codice'] === 200 && str_contains($r['corpo'], 'Aula delle prove') && !preg_match('/<b>(Fatal error|Parse error|Warning)<\/b>|Uncaught /', $r['corpo']), "dal QR il modulo si apre con l'aula già scelta", (string) $r['codice']);
$r = $http($so_qr, ['csrf_token' => $so_token($r), 'azione' => 'invia', 'avvisato' => '', 'aula_id' => $so_aula, 'tipo_id' => $so_tipo, 'urgenza' => 'ferma', 'descrizione' => 'Prova del giro'], $j_doc);
$so_riga = fn(): array => $loc->query("SELECT * FROM sos_segnalazioni WHERE utente_id = 99971 ORDER BY id DESC LIMIT 1")->fetch_assoc() ?: [];
$so_id = (int) ($so_riga()['id'] ?? 0);
prova($r['codice'] === 303 && $so_id > 0 && $so_riga()['stato'] === 'inviata', "il docente invia", $r['codice'] . ' ' . ($so_riga()['stato'] ?? 'nessuna riga'));

// Un passaggio dalla pagina di chi interviene: si prende il token della card e si preme il pulsante
$so_premi = function (string $jar_di, array $campi, string $atteso, string $nome) use ($http, $so_token, $so_riga, $so_id): void {
    $url = '/mydibest/sos_interventi.php?id=' . $so_id;
    $r = $http($url, ['csrf_token' => $so_token($http($url, null, $jar_di))] + $campi, $jar_di);
    prova($r['codice'] === 303 && ($so_riga()['stato'] ?? '') === $atteso, $nome, $r['codice'] . ' ' . ($so_riga()['stato'] ?? ''));
};
$so_premi($j_ref, ['azione' => 'affida', 'studente' => $so_studente], 'assegnata', "il referente affida allo studente");
$so_premi($j_stu, ['azione' => 'sto_andando'], 'in_corso', "lo studente: «Sto andando»");
$so_premi($j_stu, ['azione' => 'non_risolta', 'esito' => $so_esito, 'nota' => ''], 'non_risolta', "lo studente: «Non risolto»");
$so_premi($j_ref, ['azione' => 'riprendi'], 'presa', "il referente: «La riprendo io»");
$so_premi($j_ref, ['azione' => 'risolta'], 'risolta', "il referente: «Risolta»");

// sos_chiusa: al docente, con lo stato nuovo nell'oggetto
$so_chiusa = (int) $so_uno("SELECT COUNT(*) FROM log_email WHERE id > $so_email_da AND destinatario = 'docente.sos@example.org' AND oggetto LIKE '%Risolta%'");
prova($so_chiusa === 1, "al docente parte una email di chiusura (sos_chiusa), registrata in log_email", (string) $so_chiusa);

foreach ([$j_doc, $j_ref, $j_stu] as $j) is_file($j) && unlink($j);
$so_pulisci();
if ($so_imp_prima) {
    $so_q = $loc->prepare('UPDATE impostazioni_sistema SET sos_aperto = ?, sos_chi = ? WHERE id = 1');
    $so_q->bind_param('is', $so_imp_prima['sos_aperto'], $so_imp_prima['sos_chi']);
    $so_q->execute();
}
