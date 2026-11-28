<?php
// sos_interventi.php - guscio: la pagina di chi interviene in aula (App\Portale\Pagine\PaginaSosInterventi). Le email degli avvisi portano qui con ?id=<segnalazione>.
require_once 'config.php';
require_once 'functions.php';
App\Http\Nucleo::serviFile(__FILE__);
