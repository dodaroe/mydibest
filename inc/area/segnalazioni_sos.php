<?php
// Tessera «segnalazioni_sos» del cruscotto dell'area personale (la include area_personale.php: usa $conn e $user_info). Porta a sos.php, dove ci sono il form e
// «Le mie segnalazioni». Compare solo a chi può inviare la richiesta di aiuto (ServizioSos::puoInviare) o ne ha già inviata una; il numero è quello delle aperte.
$app_sos = \App\Core\App::per($conn);
$mie_sos = $app_sos->get(\App\SosAule\SegnalazioniRepository::class)->delDocente((int)($_SESSION['utente_id'] ?? 0), 100);
if (!$mie_sos && !$app_sos->get(\App\SosAule\ServizioSos::class)->puoInviare($user_info ?? null)) {
    return;
}
$aperte_sos = count(array_filter($mie_sos, static fn (array $s): bool => in_array($s['stato'], \App\SosAule\Costanti::STATI_APERTI, true)));
area_tessera('fa-life-ring', '#b91c1c', 'Le mie segnalazioni SOS', $aperte_sos > 0 ? 'Segui a che punto è la richiesta di aiuto.' : 'Un problema in aula? Chiedi aiuto da qui o dal QR in aula.',
    'sos.php', $aperte_sos, $aperte_sos === 1 ? 'aperta' : 'aperte');
