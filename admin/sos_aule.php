<?php
// sos_aule.php - guscio: Amministrazione › SOS aule è App\Portale\Pagine\PaginaSosPannello (rotta «admin/sos_aule», src/Portale/Instradamento/Rotte.php).
// L'accesso lo decide il Nucleo PRIMA della pagina (PermessiPagine: chi può tutto il modulo Amministrazione).
require_once '../config.php';
require_once '../functions.php';
App\Http\Nucleo::serviFile(__FILE__);
