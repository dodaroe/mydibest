<?php

declare(strict_types=1);

namespace App\Portale\Instradamento;

use App\Http\Rotta;
use App\Portale\Pagine\PaginaApprovaPubblicazioni;
use App\Portale\Pagine\PaginaContatti;
use App\Portale\Pagine\PaginaContattiPannello;
use App\Portale\Pagine\PaginaNumeriFsl;
use App\Portale\Pagine\PaginaScuoleFsl;
use App\Portale\Pagine\PaginaSos;
use App\Portale\Pagine\PaginaSosCartello;
use App\Portale\Pagine\PaginaSosPannello;
use App\Portale\Pagine\PaginaStampaRicevuta;

/**
 * La tabella delle rotte del portale: una riga per pagina servita dal Nucleo (App\Http\Nucleo).
 *
 * Sta qui e non in src/Http perché è un ELENCO di pagine del portale: il meccanismo (src/Http) non deve conoscerle, e la prova di pulizia di
 * src/Http non ammette elenchi. Non porta permessi: chi apre una pagina del pannello lo dice PermessiPagine (la prova RotteTest verifica che
 * ogni rotta del pannello risolva a una pagina d'ingresso della mappa).
 *
 * Il percorso di una rotta è l'indirizzo vero del file di oggi, senza «.php» («admin/contatti», non «pannello/contatti»): il file vecchio resta
 * dov'è, come guscio di due righe, e nessun indirizzo cambia. Una rotta con un percorso a PROFONDITÀ DIVERSA da quella del suo file non
 * funzionerebbe finché header.php e admin_header.php hanno i link relativi (href="index.php" aprirebbe pannello/index.php): vedi la sezione
 * «Correzioni dopo la Fase 4» del piano. Una pagina nuova, senza file di oggi e senza layout, può avere il percorso che vuole (con almeno una «/»,
 * perché la regola del .htaccess lo mandi a nucleo.php).
 */
final class Rotte
{
    /** @return list<Rotta> */
    public static function elenco(): array
    {
        return [
            new Rotta('contatti', PaginaContatti::class, 'Contatti', 'contatti.php'),
            new Rotta('approva_pubblicazioni', PaginaApprovaPubblicazioni::class, 'Richieste di pubblicazione da approvare', 'approva_pubblicazioni.php'),
            new Rotta('sos', PaginaSos::class, 'SOS assistenza didattica', 'sos.php'),
            new Rotta('sos_interventi', \App\Portale\Pagine\PaginaSosInterventi::class, 'SOS aule · Interventi', 'sos_interventi.php'),
            new Rotta('admin/contatti', PaginaContattiPannello::class, 'Sito pubblico › Contatti', 'admin/contatti.php'),
            new Rotta('admin/fsl', PaginaNumeriFsl::class, 'Formazione Scuola Lavoro › Numeri e report', 'admin/fsl.php'),
            new Rotta('admin/fsl_scuole', PaginaScuoleFsl::class, 'Formazione Scuola Lavoro › Scuole e convenzioni', 'admin/fsl_scuole.php'),
            new Rotta('admin/sos_aule', PaginaSosPannello::class, 'Amministrazione › SOS aule', 'admin/sos_aule.php'),
            new Rotta('admin/sos_cartello', PaginaSosCartello::class, 'Amministrazione › SOS aule › Cartelli', 'admin/sos_cartello.php'),
            new Rotta('stampa_ricevuta', PaginaStampaRicevuta::class, 'Ricevuta di prenotazione', 'stampa_ricevuta.php'),
        ];
    }
}
