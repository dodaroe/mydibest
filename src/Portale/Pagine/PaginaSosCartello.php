<?php

declare(strict_types=1);

namespace App\Portale\Pagine;

use App\Amministrazione\PermessiAmministrazione;
use App\Attestati\Vista\Qr;
use App\Core\Sito;
use App\Http\Pagina;
use App\Http\Richiesta;
use App\Http\Risposta;
use App\Http\Vista;
use App\Portale\Pannello\ContestoPannello;
use App\SosAule\AuleRepository;
use App\SosAule\ImpostazioniSos;

/**
 * I cartelli con il QR da appendere in aula (admin/sos_cartello.php): ?aula=ID per uno, ?tutte=1 per tutte le aule attive. Un foglio A4 per cartello.
 * Sta nel layout del pannello come tutte le sue pagine; in stampa le regole .ui-cartello di portale-ui.css lasciano solo i fogli.
 * Stessa abilitazione di SOS aule: chi può tutto il modulo Amministrazione.
 */
final class PaginaSosCartello implements Pagina
{
    public function __construct(
        private readonly AuleRepository $aule,
        private readonly ImpostazioniSos $impostazioni,
        private readonly PermessiAmministrazione $permessi,
        private readonly ContestoPannello $contesto,
        private readonly Sito $sito,
    ) {
    }

    public function __invoke(Richiesta $r): Risposta
    {
        if (!$this->permessi->puoTutto($this->contesto->utente($r))) {
            return Risposta::negata('I cartelli di SOS aule sono riservati agli amministratori e a chi ha l\'abilitazione al modulo Amministrazione.');
        }
        if ($r->query('tutte') !== null) {
            $aule = $this->aule->tutte(true);
        } else {
            $aula = $this->aule->perId((int) $r->query('aula'));
            if ($aula === null) {
                return Risposta::nonTrovata('L\'aula del cartello non c\'è.');
            }
            $aule = [$aula];
        }

        $base = $this->sito->urlBase();
        $qr = new Qr($base);
        $cartelli = [];
        foreach ($aule as $a) {
            $indirizzo = $base . '/sos.php?aula=' . $a['codice'];
            $cartelli[] = [
                'nome' => $a['nome'], 'edificio' => $a['edificio'],
                'qr' => $qr->html($indirizzo, '', 'QR dell\'aula ' . $a['nome'], 'ui-cartello-qr'),
                'indirizzo' => preg_replace('~^https?://~', '', $indirizzo),
            ];
        }

        return Risposta::html(Vista::pannello('Cartelli SOS aule', Vista::rendi('pannello/sos_cartello', [
            'cartelli' => $cartelli,
            'telefono' => $this->impostazioni->tutte()['telefono_riserva'],
            'indietro' => 'sos_aule.php?p_id=' . $this->contesto->area($r) . '&scheda=aule',
        ])));
    }
}
