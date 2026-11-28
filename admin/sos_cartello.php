<?php
// sos_cartello.php - guscio: i cartelli con il QR delle aule sono App\Portale\Pagine\PaginaSosCartello (rotta «admin/sos_cartello», src/Portale/Instradamento/Rotte.php).
// L'accesso lo decide il Nucleo PRIMA della pagina (PermessiPagine: chi può tutto il modulo Amministrazione).
require_once '../config.php';
require_once '../functions.php';
App\Http\Nucleo::serviFile(__FILE__);
