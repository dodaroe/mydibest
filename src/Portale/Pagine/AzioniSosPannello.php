<?php

declare(strict_types=1);

namespace App\Portale\Pagine;

use App\Auth\Flash;
use App\Auth\Vista\PubblicoVista;
use App\Core\Esito;
use App\Core\IndirizzoClient;
use App\Http\Richiesta;
use App\Infrastructure\Audit\AuditLog;
use App\SosAule\AuleRepository;
use App\SosAule\CampiSos;
use App\SosAule\ImpostazioniSos;
use App\SosAule\PersoneSos;
use App\SosAule\TipiRepository;

/**
 * I POST di admin/sos_aule.php (PaginaSosPannello). Il token CSRF e l'accesso li ha già controllati il Nucleo, e la pagina ha già controllato che chi
 * agisce possa tutto il modulo Amministrazione. Un'azione per invio. Il risultato dice se rimandare alla scheda (PRG, con il messaggio) oppure
 * ridisegnarla con quello che era stato scritto (un errore o una conferma che manca non devono far perdere il modulo).
 */
final class AzioniSosPannello
{
    /** Il nome dell'azione comincia con il nome di quello che tocca: da lì si sa la scheda a cui tornare. */
    private const SCHEDA_DI = [
        'aula' => 'aule', 'referente' => 'persone', 'studente' => 'persone', 'campo' => 'modulo', 'aggiunti' => 'modulo', 'voce' => 'modulo', 'impostazioni' => 'impostazioni',
    ];

    public function __construct(
        private readonly AuleRepository $aule,
        private readonly PersoneSos $persone,
        private readonly TipiRepository $tipi,
        private readonly CampiSos $campi,
        private readonly ImpostazioniSos $impostazioni,
        private readonly Flash $flash,
        private readonly AuditLog $audit,
        private readonly IndirizzoClient $indirizzo,
    ) {
    }

    /**
     * @return array{scheda: string, ancora: string, rimasto: array<string, mixed>|null} «rimasto» = null: fatto (o rifiutato) e si rimanda alla scheda;
     *                                                                                  altrimenti quello che serve per ridisegnarla
     */
    public function esegui(Richiesta $r, int $uid): array
    {
        $azione = (string) $r->post('azione');
        $scheda = self::SCHEDA_DI[strstr($azione . '_', '_', true)] ?? 'aule';
        $esito = ['scheda' => $scheda, 'ancora' => '', 'rimasto' => null];

        return match ($scheda) {
            'aule' => $this->aule($r, $uid, $azione, $esito),
            'persone' => $this->persone($r, $uid, $azione, $esito),
            'modulo' => $this->modulo($r, $uid, $azione, $esito),
            default => $this->impostazioni($r, $uid, $esito),
        };
    }

    // ─────────────────────────── Aule ───────────────────────────

    private function aule(Richiesta $r, int $uid, string $azione, array $esito): array
    {
        $id = (int) $r->post('id');
        $aula = $this->aule->perId($id);
        if ($azione !== 'aula_aggiungi' && $aula === null) {
            $this->dillo(Esito::errore('L\'aula non c\'è più.'));

            return $esito;
        }
        $nome = trim((string) $r->post('nome'));
        $dettagli = ['aula' => $id, 'nome' => (string) ($aula['nome'] ?? $nome)];
        $esito['ancora'] = $id > 0 ? 'aula-' . $id : '';

        switch ($azione) {
            case 'aula_aggiungi':
                if ($nome === '') {
                    $this->dillo(Esito::errore('Scrivi il nome dell\'aula.'));
                    break;
                }
                $nuova = $this->aule->aggiungi($this->datiAula($r, $nome) + ['ordine' => $this->aule->prossimoOrdine()], $uid);
                $this->dillo($nuova > 0 ? Esito::ok('Aula «' . $nome . '» aggiunta.') : Esito::errore('Non è stato possibile salvare l\'aula: riprova tra qualche minuto.'));
                if ($nuova > 0) {
                    $esito['ancora'] = 'aula-' . $nuova;
                    $this->registra($uid, 'aula aggiunta', ['aula' => $nuova, 'nome' => $nome]);
                }
                break;
            case 'aula_salva':
                if ($nome === '') {
                    $this->dillo(Esito::errore('Scrivi il nome dell\'aula.'));
                    break;
                }
                $this->cambiaAula($uid, $aula, $this->datiAula($r, $nome) + ['attiva' => $r->post('attiva') !== null], 'Aula salvata.', 'aula modificata');
                break;
            case 'aula_stato':
                $attiva = $r->post('valore') === '1';
                $this->cambiaAula($uid, $aula, ['attiva' => $attiva], $attiva ? 'Aula accesa.' : 'Aula spenta: non si può più scegliere nel form.', $attiva ? 'aula accesa' : 'aula spenta');
                break;
            case 'aula_sposta':
                $this->aule->sposta($id, $r->post('verso') === 'su' ? -1 : 1);
                break;
            case 'aula_elimina':
                $x = $this->aule->elimina($id);
                $this->dillo($x);
                if ($x->riuscito) {
                    $esito['ancora'] = '';
                    $this->registra($uid, 'aula cancellata', $dettagli);
                }
                break;
            case 'aula_rigenera':
                $codice = $this->aule->rigeneraCodice($id);
                $this->dillo($codice !== null
                    ? Esito::ok('QR rigenerato: ristampa il cartello di «' . $aula['nome'] . '», quello di prima non vale più.')
                    : Esito::errore('Non è stato possibile rigenerare il QR: riprova tra qualche minuto.'));
                if ($codice !== null) {
                    $this->registra($uid, 'QR rigenerato', $dettagli);
                }
                break;
        }

        return $esito;
    }

    /** @return array{nome: string, edificio: string, note: string} */
    private function datiAula(Richiesta $r, string $nome): array
    {
        return ['nome' => $nome, 'edificio' => (string) $r->post('edificio'), 'note' => (string) $r->post('note')];
    }

    /** modifica() scrive tutte le colonne: quelle che non cambiano si rimettono come sono. @param array<string, mixed> $aula @param array<string, mixed> $cambi */
    private function cambiaAula(int $uid, array $aula, array $cambi, string $fatto, string $azione): void
    {
        $dati = $cambi + [
            'nome' => (string) $aula['nome'], 'edificio' => (string) $aula['edificio'], 'note' => (string) $aula['note'],
            'risorsa_id' => $aula['risorsa_id'] === null ? null : (int) $aula['risorsa_id'], 'ordine' => (int) $aula['ordine'], 'attiva' => (int) $aula['attiva'] === 1,
        ];
        if (!$this->aule->modifica((int) $aula['id'], $dati)) {
            $this->dillo(Esito::errore('Non è stato possibile salvare l\'aula: riprova tra qualche minuto.'));

            return;
        }
        $this->dillo(Esito::ok($fatto));
        $this->registra($uid, $azione, ['aula' => (int) $aula['id'], 'nome' => (string) $dati['nome']]);
    }

    // ─────────────────────────── Persone ───────────────────────────

    private function persone(Richiesta $r, int $uid, string $azione, array $esito): array
    {
        if ($azione === 'referente_aggiungi' || $azione === 'referente_togli') {
            $ambito = $r->post('ambito') === PersoneSos::RISERVA ? PersoneSos::RISERVA : PersoneSos::AULA;
            $aulaId = $ambito === PersoneSos::AULA ? (int) $r->post('aula_id') : null;
            $persona = trim((string) $r->post('persona'));
            $x = $azione === 'referente_aggiungi'
                ? $this->persone->aggiungiReferente($ambito, $aulaId, $persona, $uid)
                : $this->persone->togliReferente($ambito, $aulaId, $persona);
            $this->dillo($x);
            if ($x->riuscito) {
                $this->registra($uid, $azione === 'referente_aggiungi' ? 'referente aggiunto' : 'referente tolto', ['lista' => $ambito, 'aula' => $aulaId, 'persona' => $persona]);
            }
            $esito['ancora'] = $ambito === PersoneSos::AULA ? 'ref-' . $aulaId : 'riserva';

            return $esito;
        }

        $id = (int) $r->post('id');
        $esito['ancora'] = 'studenti';
        if ($azione === 'studente_togli') {
            $s = $this->persone->studentePerId($id);
            $x = $this->persone->togliStudente($id);
            $this->dillo($x);
            if ($x->riuscito) {
                $this->registra($uid, 'studente tolto', ['studente' => $id, 'email' => (string) ($s['email'] ?? '')]);
            }

            return $esito;
        }

        $nuovo = $azione === 'studente_aggiungi';
        $dati = ['attivo' => $r->post('attivo') !== null ? 1 : 0];
        foreach (['nome', 'cognome', 'email', 'dal', 'al', 'note'] as $k) {
            $dati[$k] = trim((string) $r->post($k));
        }
        $prima = $nuovo ? null : $this->persone->studentePerId($id);
        // un indirizzo che non è degli studenti di Ateneo si accetta solo dopo una spunta (chi l'ha già confermato non lo conferma di nuovo)
        $daConfermare = filter_var($dati['email'], FILTER_VALIDATE_EMAIL) !== false && !PersoneSos::emailDiAteneo($dati['email'])
            && mb_strtolower($dati['email']) !== mb_strtolower((string) ($prima['email'] ?? '')) && $r->post('conferma_email') === null;
        $x = $daConfermare
            ? Esito::errore('«' . $dati['email'] . '» non è un indirizzo ' . PersoneSos::DOMINIO_STUDENTI . ': se è giusto, spunta la conferma e salva di nuovo.')
            : ($nuovo ? $this->persone->aggiungiStudente($dati, $uid) : $this->persone->modificaStudente($id, $dati));
        if (!$x->riuscito) {
            $esito['rimasto'] = ['studente' => ['id' => $nuovo ? 0 : $id, 'valori' => $dati, 'errore' => $x->messaggio, 'conferma' => $daConfermare]];

            return $esito;
        }
        $this->dillo($x);
        $this->registra($uid, $nuovo ? 'studente aggiunto' : 'studente modificato', ['email' => mb_strtolower($dati['email']), 'attivo' => $dati['attivo']]);

        return $esito;
    }

    // ─────────────────────────── Modulo ───────────────────────────

    private function modulo(Richiesta $r, int $uid, string $azione, array $esito): array
    {
        if ($azione === 'campo_salva' || $azione === 'campo_originale') {
            $chiave = (string) $r->post('chiave');
            $esito['ancora'] = 'campo-' . preg_replace('/[^a-z]/', '', $chiave);
            $x = $azione === 'campo_salva'
                ? $this->campi->salvaFisso($chiave, $r->datiModulo())
                : (isset(CampiSos::FISSI[$chiave]) ? $this->campi->ripristina($chiave) : Esito::errore('Quel campo non c\'è.'));
            $this->dillo($x);
            if ($x->riuscito) {
                $this->registra($uid, $azione === 'campo_salva' ? 'campo del form modificato' : 'campo del form riportato all\'originale', ['campo' => $chiave]);
            }

            return $esito;
        }
        if ($azione === 'aggiunti_salva') {
            $esito['ancora'] = 'aggiunti';
            $x = $this->campi->salvaAggiunti($r->datiModulo());
            if (!$x->riuscito) {
                $esito['rimasto'] = ['aggiunti' => ['errore' => $x->messaggio, 'post' => $r->datiModulo()]];

                return $esito;
            }
            $this->dillo($x);
            $this->registra($uid, 'campi aggiunti salvati', ['campi' => count($this->campi->aggiunti())]);

            return $esito;
        }

        $quale = $r->post('elenco') === TipiRepository::ESITI ? TipiRepository::ESITI : TipiRepository::TIPI;
        $id = (int) $r->post('id');
        $esito['ancora'] = $quale;
        $nome = (string) $r->post('nome');
        $chiede = $r->post('chiede_testo') !== null;
        switch ($azione) {
            case 'voce_aggiungi':
                $x = $this->tipi->aggiungi($quale, $nome, (string) $r->post('icona'), (string) $r->post('aiuto'), $chiede);
                break;
            case 'voce_salva':
                $x = $this->tipi->rinomina($quale, $id, $nome, (string) $r->post('icona'), (string) $r->post('aiuto'), $chiede);
                break;
            case 'voce_stato':
                $x = $this->tipi->accendi($quale, $id, $r->post('valore') === '1');
                break;
            case 'voce_elimina':
                $x = $this->tipi->elimina($quale, $id);
                break;
            case 'voce_sposta':
                $ids = array_map('intval', array_column($this->tipi->elenco($quale, false), 'id'));
                $i = array_search($id, $ids, true);
                $j = $i === false ? false : $i + ($r->post('verso') === 'su' ? -1 : 1);
                if ($j !== false && isset($ids[$j])) {
                    [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
                    $this->tipi->ordina($quale, $ids);
                }

                return $esito;
            default:
                return $esito;
        }
        $this->dillo($x);
        if ($x->riuscito) {
            $this->registra($uid, str_replace('_', ' ', $azione), ['elenco' => $quale, 'voce' => $id, 'nome' => $nome]);
        }

        return $esito;
    }

    // ─────────────────────────── Impostazioni ───────────────────────────

    private function impostazioni(Richiesta $r, int $uid, array $esito): array
    {
        $dati = ['aperto' => $r->post('aperto') !== null, 'chi' => PubblicoVista::daPost('sos_chi', $r->datiModulo())['profili']];
        foreach (['telefono_riserva', 'orari', 'promemoria_ferma', 'promemoria_altre', 'testo_introduttivo', 'conserva_foto_giorni'] as $k) {
            $dati[$k] = (string) $r->post($k);
        }
        $salvate = $this->impostazioni->aggiorna($dati);
        if ($salvate['errori']) {
            $esito['rimasto'] = ['impostazioni' => ['valori' => $dati, 'errori' => $salvate['errori']]];

            return $esito;
        }
        $this->dillo(Esito::ok($salvate['cambi'] ? 'Impostazioni salvate.' : 'Non è cambiato niente.'));
        if ($salvate['cambi']) {
            $this->registra($uid, 'impostazioni salvate', $salvate['cambi']);
        }

        return $esito;
    }

    // ─────────────────────────── Interno ───────────────────────────

    private function dillo(Esito $x): void
    {
        $this->flash->imposta($x->messaggio, $x->riuscito ? 'success' : 'danger');
    }

    /** @param array<string, mixed> $dettagli */
    private function registra(int $uid, string $azione, array $dettagli): void
    {
        if ($uid > 0) {
            $this->audit->registra($uid, 'SOS aule: ' . $azione, $dettagli, $this->indirizzo->ip() ?? 'Sconosciuto');
        }
    }
}
