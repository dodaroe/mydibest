<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Assistenza\Push\ServizioPush;
use App\Auth\UtenteRepository;
use App\Core\Sito;
use App\Email\InvioEmail;
use Throwable;

/**
 * Gli avvisi di SOS aule: email (testi in CatalogoSos, modificabili dalla pagina «Email» del pannello) e notifica sul telefono. Le chiama la pagina DOPO l'esito di
 * ServizioSos, quindi a transazione chiusa; non lanciano mai eccezioni (un avviso che non parte non cambia l'esito: resta nel log del server).
 *
 * - nuova(): a chi riceve le segnalazioni dell'aula (PersoneSos::destinatari), email e notifica;
 * - nota(): a chi ha in mano la segnalazione (chi l'ha presa e lo studente affidato) o, se non è di nessuno, a chi riceve quelle dell'aula;
 * - affidata(): allo studente a cui è appena stata affidata;
 * - tolta(): allo studente che la perde (ripresa, passata a un altro, risolta dal referente); al referente che perde la presa non va niente;
 * - promemoria(): la manda il cron (CronSos) a referenti dell'aula e riserva se nessuno l'ha presa;
 * - nonRisolta(): dopo il «Non risolto» dello studente a chi riceve le segnalazioni dell'aula; dopo il «Non è risolto» di chi l'ha inviata a chi l'ha in mano (o, se
 *   nessuno, a chi riceve quelle dell'aula);
 * - annullata(): a chi l'aveva in mano (o, se nessuno l'aveva presa, a chi riceve quelle dell'aula), perché non vada in aula per niente;
 * - staArrivando(): solo la notifica sul telefono a chi ha segnalato, se l'ha attivata;
 * - chiusa(): al docente, per «Risolta», «Chiusa senza soluzione» e «Inoltrata», solo all'email verificata copiata nella segnalazione.
 * Una persona riceve una sola email per evento (stesso indirizzo) e una sola notifica (stesso account), anche se compare due volte; chi ha agito non riceve niente.
 */
final class NotificheSos
{
    public function __construct(
        private InvioEmail $invio,
        private ServizioPush $push,
        private SegnalazioniRepository $segnalazioni,
        private PersoneSos $persone,
        private TipiRepository $tipi,
        private UtenteRepository $utenti,
        private Sito $sito,
    ) {
    }

    /** «aula 5C2»; un nome che comincia già con «Aula» («Aula Magna») resta com'è. */
    public static function conAula(string $nome): string
    {
        return preg_match('/^aula\b/iu', $nome) === 1 ? $nome : 'aula ' . $nome;
    }

    public function nuova(int $id): void
    {
        try {
            $s = $this->segnalazioni->perId($id);
            if ($s === null) {
                return;
            }
            $this->avvisa($this->persone->destinatari((int) $s['aula_id']), 'sos_nuova', $this->comuni($s) + $this->richiesta($s) + [
                'ora' => self::quando((string) $s['inviata_il']),
            ], $id, 'Apri la segnalazione ' . $s['codice'] . '.');
        } catch (Throwable $e) {
            error_log('[SOS aule] avvisi della segnalazione ' . $id . ': ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $chi la riga di utenti di chi ha scritto (non riceve il proprio avviso) */
    public function nota(int $id, array $chi, string $nota, string $ruolo): void
    {
        try {
            $s = $this->segnalazioni->perId($id);
            if ($s === null) {
                return;
            }
            $destinatari = self::senza($this->inMano($s) ?: $this->persone->destinatari((int) $s['aula_id']), $chi);
            $ultima = $this->segnalazioni->cronologia($id);
            $this->avvisa($destinatari, 'sos_nota', $this->comuni($s) + [
                'autore' => self::nome($chi) . ' (' . $ruolo . ')',
                'ora' => self::quando((string) ($ultima === [] ? '' : end($ultima)['quando'])),
                'nota' => InvioEmail::citazione($nota),
            ], $id, 'Nuova nota sulla segnalazione ' . $s['codice'] . '.');
        } catch (Throwable $e) {
            error_log('[SOS aule] avvisi della nota alla segnalazione ' . $id . ': ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $chi la riga di utenti di chi l'ha affidata */
    public function affidata(int $id, array $chi): void
    {
        try {
            $s = $this->segnalazioni->perId($id);
            if ($s === null || (string) $s['stato'] !== 'assegnata') {
                return;
            }
            $studente = $this->persone->studentePerId((int) $s['assegnata_a_studente']);
            if ($studente === null) {
                return;
            }
            $this->avvisa(self::senza([$this->recapitoStudente($studente)], $chi), 'sos_affidata', $this->comuni($s) + $this->richiesta($s) + [
                'nome' => (string) $studente['nome'],
            ], $id, 'Ti è stato affidato l\'intervento ' . $s['codice'] . '.');
        } catch (Throwable $e) {
            error_log('[SOS aule] avviso allo studente per la segnalazione ' . $id . ': ' . $e->getMessage());
        }
    }

    /**
     * Lo studente che perde l'intervento mentre era suo (il referente lo riprende, lo passa a un altro, lo segna risolto): non serve più che vada.
     * Se dopo il passaggio è ancora suo, non parte niente.
     *
     * @param array<string, mixed> $prima la riga della segnalazione com'era PRIMA del passaggio
     * @param array<string, mixed> $chi la riga di utenti di chi ha agito
     */
    public function tolta(array $prima, array $chi): void
    {
        $id = (int) ($prima['id'] ?? 0);
        try {
            if (!in_array((string) ($prima['stato'] ?? ''), ['assegnata', 'in_corso'], true)) {
                return;
            }
            $s = $this->segnalazioni->perId($id);
            $suo = (int) $prima['assegnata_a_studente'];
            if ($s === null || ((int) $s['assegnata_a_studente'] === $suo && in_array($s['stato'], ['assegnata', 'in_corso', 'non_risolta'], true))) {
                return;
            }
            $studente = $this->persone->studentePerId($suo);
            if ($studente === null) {
                return;
            }
            // la segnalazione non è più sua e non la può aprire: il pulsante porta al suo elenco
            $this->avvisa(self::senza([$this->recapitoStudente($studente)], $chi), 'sos_tolta', $this->comuni($s) + [
                'nome' => (string) $studente['nome'],
                'nome_aula' => (string) $s['aula_nome'],
            ], $id, 'La segnalazione SOS ' . $s['aula_nome'] . ' non è più affidata a te: non serve più che tu vada.', $this->sito->urlBase() . '/sos_interventi.php');
        } catch (Throwable $e) {
            error_log('[SOS aule] avviso allo studente che non deve più andare (segnalazione ' . $id . '): ' . $e->getMessage());
        }
    }

    /**
     * Nessuno ha preso la segnalazione entro i minuti scelti: a tutti i referenti dell'aula e ai responsabili di riserva. Che parta una volta sola lo decide
     * App\SosAule\CronSos.
     */
    public function promemoria(int $id, int $minuti): void
    {
        try {
            $s = $this->segnalazioni->perId($id);
            if ($s === null || (string) $s['stato'] !== 'inviata') {
                return;
            }
            $destinatari = array_values(array_filter(
                array_merge($this->persone->referentiDellAula((int) $s['aula_id']), $this->persone->riserva()),
                static fn (array $p): bool => $p['in_anagrafe']
            ));
            $this->avvisa($destinatari, 'sos_promemoria', $this->comuni($s) + $this->richiesta($s) + [
                'ora' => self::quando((string) $s['inviata_il']),
                'minuti' => (string) $minuti,
            ], $id, 'La segnalazione ' . $s['codice'] . ' aspetta ancora: nessuno l\'ha presa.');
        } catch (Throwable $e) {
            error_log('[SOS aule] promemoria della segnalazione ' . $id . ': ' . $e->getMessage());
        }
    }

    /**
     * La segnalazione è «Non risolta»: perché lo dice lo studente o perché chi l'ha inviata ha premuto «Non è risolto» (lo dice l'ultima riga della cronologia).
     *
     * @param array<string, mixed> $chi la riga di utenti di chi ha agito
     */
    public function nonRisolta(int $id, array $chi): void
    {
        try {
            $s = $this->segnalazioni->perId($id);
            $passo = $this->segnalazioni->ultimoPassaggio($id, 'non_risolta');
            if ($s === null || (string) $s['stato'] !== 'non_risolta' || $passo === null) {
                return;
            }
            $riaperta = (string) $passo['da_stato'] === 'risolta';
            $destinatari = ($riaperta ? $this->inMano($s) : []) ?: $this->persone->destinatari((int) $s['aula_id']);
            $esito = $riaperta ? null : $this->tipi->perId(TipiRepository::ESITI, (int) $s['esito_id']);
            $this->avvisa(self::senza($destinatari, $chi), 'sos_non_risolta', $this->comuni($s) + [
                'esito' => $riaperta ? 'chi ha segnalato dice che il problema c\'è ancora' : (string) ($esito['nome'] ?? ''),
                'autore' => self::nome($chi),
                'ora' => self::quando((string) $passo['quando']),
                'nota' => InvioEmail::citazione($riaperta ? '' : (string) $s['esito_nota']),
            ], $id, 'La segnalazione ' . $s['codice'] . ' non è risolta.');
        } catch (Throwable $e) {
            error_log('[SOS aule] avvisi del «non risolto» della segnalazione ' . $id . ': ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $chi la riga di utenti di chi ha annullato */
    public function annullata(int $id, array $chi): void
    {
        try {
            $s = $this->segnalazioni->perId($id);
            $passo = $this->segnalazioni->ultimoPassaggio($id, 'annullata');
            if ($s === null || $passo === null) {
                return;
            }
            // chi l'aveva in mano si legge con lo stato di PRIMA dell'annullamento: lo studente c'è solo se era affidata o in corso
            $destinatari = $this->inMano($s, (string) $passo['da_stato']) ?: $this->persone->destinatari((int) $s['aula_id']);
            $dati = $this->comuni($s) + ['nome_aula' => (string) $s['aula_nome']];
            $this->avvisa(self::senza($destinatari, $chi), 'sos_annullata', $dati, $id,
                'La segnalazione SOS ' . $s['aula_nome'] . ' è stata annullata da chi l\'ha inviata: non serve andare.');
        } catch (Throwable $e) {
            error_log('[SOS aule] avvisi dell\'annullamento della segnalazione ' . $id . ': ' . $e->getMessage());
        }
    }

    /** «Mario Rossi sta arrivando»: solo la notifica sul telefono a chi ha segnalato, niente email. @param array<string, mixed> $chi la riga di utenti di chi arriva */
    public function staArrivando(int $id, array $chi): void
    {
        try {
            $s = $this->segnalazioni->perId($id);
            $docente = (int) ($s['utente_id'] ?? 0);
            if ($s === null || $docente <= 0 || $docente === (int) ($chi['id'] ?? 0)) {
                return;
            }
            $this->push->avvisaPersona($docente, 'SOS ' . self::conAula((string) $s['aula_nome']), trim(($chi['nome'] ?? '') . ' ' . ($chi['cognome'] ?? '')) . ' sta arrivando.',
                $this->sito->urlBase() . '/sos.php?segnalazione=' . $id);
        } catch (Throwable $e) {
            error_log('[SOS aule] notifica a chi ha segnalato (segnalazione ' . $id . '): ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed>|null $chi la riga di utenti di chi ha agito: se è chi ha segnalato non riceve niente */
    public function chiusa(int $id, ?array $chi = null): void
    {
        try {
            $s = $this->segnalazioni->perId($id);
            $stato = (string) ($s['stato'] ?? '');
            if ($s === null || !in_array($stato, ['risolta', 'chiusa_non_risolta', 'inoltrata'], true) || trim((string) $s['email']) === ''
                || ($chi !== null && (int) ($chi['id'] ?? 0) === (int) $s['utente_id'])) {
                return;
            }
            $nota = '';
            if ($stato === 'inoltrata') {
                $nota = (string) $s['inoltrata_a'];
            } elseif ($stato === 'chiusa_non_risolta') {
                $nota = (string) ($this->segnalazioni->ultimoPassaggio($id, $stato)['nota'] ?? '');
            }
            $risolta = $stato === 'risolta';
            $inviata = $this->invio->invia((string) $s['email'], 'sos_chiusa', $this->comuni($s) + [
                'nome' => (string) $s['nome'],
                'stato' => Costanti::etichetta($stato),
                'nota' => InvioEmail::citazione($nota),
                'se_risolta' => $risolta ? 'Se il problema c\'è ancora, hai ' . Costanti::ORE_RIAPERTURA . ' ore per dirlo con il pulsante qui sotto.' : '',
            ], $risolta ? $this->sito->urlBase() . '/sos.php?segnalazione=' . $id : '');
            if (!$inviata) {
                error_log('[SOS aule] email di chiusura non inviata (segnalazione ' . $id . '): ' . $this->invio->ultimoErrore());
            }
        } catch (Throwable $e) {
            error_log('[SOS aule] avviso di chiusura della segnalazione ' . $id . ': ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $s @return array<string, string> */
    private function comuni(array $s): array
    {
        return ['codice' => (string) $s['codice'], 'aula' => self::conAula((string) $s['aula_nome']), 'tipo' => (string) $s['tipo_nome']];
    }

    /** Quello che serve a chi deve intervenire: dove, quanto è urgente, chi chiede e che cosa ha scritto. @param array<string, mixed> $s @return array<string, string> */
    private function richiesta(array $s): array
    {
        $edificio = trim((string) $s['aula_edificio']);
        $telefono = trim((string) $s['telefono']);

        return [
            'edificio' => $edificio === '' ? '' : ' (' . $edificio . ')',
            'urgenza' => Costanti::URGENZE[(string) $s['urgenza']] ?? (string) $s['urgenza'],
            'docente' => trim($s['cognome'] . ' ' . $s['nome']),
            'telefono_testo' => $telefono === '' ? '' : ', cellulare ' . $telefono,
            'descrizione' => InvioEmail::citazione((string) $s['descrizione']),
        ];
    }

    /**
     * Chi ha preso la segnalazione e lo studente a cui è affidata.
     *
     * @param array<string, mixed> $s
     * @param string|null $stato lo stato da guardare, se non è quello di adesso (dopo un annullamento serve quello di prima)
     * @return list<array{email: string, utenti: list<int>}>
     */
    private function inMano(array $s, ?string $stato = null): array
    {
        $out = [];
        $presa = (int) $s['presa_da'];
        if ($presa > 0) {
            // per un referente l'indirizzo è quello dell'anagrafe di Ateneo, come per la segnalazione nuova
            $email = '';
            foreach (array_merge($this->persone->referentiDellAula((int) $s['aula_id']), $this->persone->riserva()) as $p) {
                if (in_array($presa, $p['utenti'], true) && $p['email'] !== '') {
                    $email = $p['email'];
                    break;
                }
            }
            $out[] = ['email' => $email !== '' ? $email : (string) (UtenteRepository::emailVerificata($this->utenti->perId($presa)) ?? ''), 'utenti' => [$presa]];
        }
        $studente = in_array($stato ?? $s['stato'], ['assegnata', 'in_corso'], true) ? $this->persone->studentePerId((int) $s['assegnata_a_studente']) : null;
        if ($studente !== null) {
            $out[] = $this->recapitoStudente($studente);
        }

        return $out;
    }

    /** @param array<string, mixed> $studente riga di sos_studenti @return array{email: string, utenti: list<int>} */
    private function recapitoStudente(array $studente): array
    {
        $email = mb_strtolower(trim((string) $studente['email']));

        return ['email' => $email, 'utenti' => $this->persone->accountDellEmail($email)];
    }

    /**
     * Chi ha agito non riceve il proprio avviso: si riconosce dall'account o dall'indirizzo.
     *
     * @param list<array<string, mixed>> $destinatari
     * @param array<string, mixed> $chi la riga di utenti
     * @return list<array<string, mixed>>
     */
    private static function senza(array $destinatari, array $chi): array
    {
        $mia = mb_strtolower(trim((string) ($chi['email'] ?? '')));
        $mio = (int) ($chi['id'] ?? 0);

        return array_values(array_filter($destinatari, static fn (array $d): bool => !in_array($mio, $d['utenti'], true) && ($mia === '' || mb_strtolower($d['email']) !== $mia)));
    }

    /** @param iterable<array{email: string, utenti: list<int>}> $destinatari @param array<string, string> $dati */
    private function avvisa(iterable $destinatari, string $chiave, array $dati, int $id, string $testoPush, string $link = ''): void
    {
        $link = $link !== '' ? $link : $this->sito->urlBase() . '/sos_interventi.php?id=' . $id;
        $titolo = $this->invio->prepara($chiave, $dati)['oggetto'];
        $indirizzi = [];
        $account = [];
        foreach ($destinatari as $d) {
            $email = mb_strtolower(trim($d['email']));
            if ($email !== '' && !isset($indirizzi[$email])) {
                $indirizzi[$email] = true;
                try {
                    if (!$this->invio->invia($d['email'], $chiave, $dati, $link)) {
                        error_log('[SOS aule] email «' . $chiave . '» non inviata (segnalazione ' . $id . '): ' . $this->invio->ultimoErrore());
                    }
                } catch (Throwable $e) {
                    error_log('[SOS aule] email «' . $chiave . '» (segnalazione ' . $id . '): ' . $e->getMessage());
                }
            }
            foreach ($d['utenti'] as $utenteId) {
                if (isset($account[$utenteId])) {
                    continue;
                }
                $account[$utenteId] = true;
                try {
                    $this->push->avvisaPersona($utenteId, $titolo, $testoPush, $link);
                } catch (Throwable $e) {
                    error_log('[SOS aule] notifica (segnalazione ' . $id . '): ' . $e->getMessage());
                }
            }
        }
    }

    /** «Cognome Nome». @param array<string, mixed> $utente */
    private static function nome(array $utente): string
    {
        return trim(($utente['cognome'] ?? '') . ' ' . ($utente['nome'] ?? ''));
    }

    /** «10:12»: gli avvisi partono nel momento del fatto, il giorno non serve. */
    private static function quando(string $data): string
    {
        $t = strtotime($data);

        return $t ? date('H:i', $t) : '';
    }
}
