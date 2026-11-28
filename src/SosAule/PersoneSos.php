<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Auth\UtenteRepository;
use App\Core\Database;
use App\Core\Esito;
use App\Core\Orologio;
use Throwable;

/**
 * Le persone di SOS aule: referenti delle aule e responsabili di riserva (tabella sos_referenti, scelti da personale_ateneo) e studenti part-time
 * (tabella sos_studenti, scritti a mano). Qui non c'è HTML.
 *
 * - Referente: il permesso vale per l'ACCOUNT con utenti.persona_id uguale a quello della lista, mai per l'email. L'email per gli avvisi si legge ogni
 *   volta da personale_ateneo. I responsabili di riserva possono fare tutto quello che fa un referente, su tutte le aule.
 * - Studente: il permesso vale per l'account con l'email VERIFICATA (UtenteRepository::emailVerificata) uguale a quella scritta dall'amministratore,
 *   se lo studente è attivo e oggi è dentro dal/al.
 */
final class PersoneSos
{
    public const AULA = 'aula';
    public const RISERVA = 'riserva';

    /** Le email degli studenti di Ateneo: un indirizzo diverso si accetta solo dopo conferma (lo chiede la pagina). */
    public const DOMINIO_STUDENTI = '@studenti.unical.it';

    public function __construct(private Database $db, private Orologio $orologio)
    {
    }

    // ─────────────────────────── Le regole, senza database ───────────────────────────

    /**
     * @param array<string, mixed>|null $utente la riga di utenti
     * @param list<string> $idsPersona persone dell'anagrafe di una lista
     */
    public static function collegata(?array $utente, array $idsPersona): bool
    {
        if ($utente === null || empty($utente['id'])) {
            return false;
        }
        $pid = trim((string) ($utente['persona_id'] ?? ''));

        return $pid !== '' && in_array($pid, $idsPersona, true);
    }

    /**
     * Lo studente che corrisponde a questo account, oggi. Un'email scritta nel profilo e mai confermata non dà niente.
     *
     * @param array<string, mixed>|null $utente la riga di utenti
     * @param list<array<string, mixed>> $studenti righe di sos_studenti
     * @param string $oggi Y-m-d
     * @return array<string, mixed>|null
     */
    public static function studenteValido(?array $utente, array $studenti, string $oggi): ?array
    {
        $email = UtenteRepository::emailVerificata($utente);
        if ($email === null || empty($utente['id'])) {
            return null;
        }
        foreach ($studenti as $s) {
            if (mb_strtolower(trim((string) ($s['email'] ?? ''))) === $email && self::attivoIl($s, $oggi)) {
                return $s;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $studente */
    public static function attivoIl(array $studente, string $oggi): bool
    {
        $dal = (string) ($studente['dal'] ?? '');
        $al = (string) ($studente['al'] ?? '');

        return (int) ($studente['attivo'] ?? 0) === 1 && ($dal === '' || $oggi >= $dal) && ($al === '' || $oggi <= $al);
    }

    public static function emailDiAteneo(string $email): bool
    {
        return str_ends_with(mb_strtolower(trim($email)), self::DOMINIO_STUDENTI);
    }

    // ─────────────────────────── Referenti e riserva ───────────────────────────

    /** @return list<array{persona_id: string, nominativo: string, nome: string, email: string, in_anagrafe: bool, account: bool, utenti: list<int>, avvisi: list<string>}> */
    public function referentiDellAula(int $aulaId): array
    {
        return $this->lista(self::AULA, $aulaId);
    }

    /** @return list<array{persona_id: string, nominativo: string, nome: string, email: string, in_anagrafe: bool, account: bool, utenti: list<int>, avvisi: list<string>}> */
    public function riserva(): array
    {
        return $this->lista(self::RISERVA, null);
    }

    /**
     * Chi riceve le segnalazioni di un'aula: i referenti con un'email; se non ce ne sono, i responsabili di riserva con un'email.
     * Vuoto = nessuno la riceverebbe (e allora non si invia).
     *
     * @return list<array{persona_id: string, nominativo: string, nome: string, email: string, in_anagrafe: bool, account: bool, utenti: list<int>, avvisi: list<string>}>
     */
    public function destinatari(int $aulaId): array
    {
        $conEmail = static fn (array $p): bool => $p['in_anagrafe'] && $p['email'] !== '';

        return array_values(array_filter($this->referentiDellAula($aulaId), $conEmail)) ?: array_values(array_filter($this->riserva(), $conEmail));
    }

    /** Se almeno un'aula attiva ha un referente che riceve, o c'è un responsabile di riserva che riceve. */
    public function qualcunoRiceve(): bool
    {
        $riceve = "EXISTS (SELECT 1 FROM sos_referenti r JOIN personale_ateneo p ON p.id = r.persona_id AND p.attivo = 1 AND p.email LIKE '%_@_%' WHERE ";
        try {
            return (int) $this->db->valore('SELECT ' . $riceve . "r.ambito = 'riserva') OR " . $riceve . "r.ambito = 'aula' AND r.aula_id IN (SELECT id FROM sos_aule WHERE attiva = 1))") === 1;
        } catch (Throwable $e) {
            error_log('[SOS aule] destinatari non letti: ' . $e->getMessage());

            return false;
        }
    }

    /** Le aule attive per cui nessuno riceverebbe una segnalazione. @return list<array<string, mixed>> */
    public function auleSenzaNessuno(): array
    {
        return array_values(array_filter(
            $this->db->righe('SELECT * FROM sos_aule WHERE attiva = 1 ORDER BY ordine, nome, id'),
            fn (array $a): bool => $this->destinatari((int) $a['id']) === []
        ));
    }

    /**
     * Referente di quell'aula o responsabile di riserva; senza aula: referente di almeno un'aula o responsabile di riserva.
     *
     * @param array<string, mixed>|null $utente la riga di utenti
     */
    public function eReferente(?array $utente, ?int $aulaId = null): bool
    {
        $aule = $this->auleDelReferente($utente);

        return $aule === null || ($aulaId === null ? $aule !== [] : in_array($aulaId, $aule, true));
    }

    /**
     * Le aule di cui l'utente è referente. null = tutte (è responsabile di riserva); [] = nessuna.
     *
     * @param array<string, mixed>|null $utente la riga di utenti
     * @return list<int>|null
     */
    public function auleDelReferente(?array $utente): ?array
    {
        $pid = trim((string) ($utente['persona_id'] ?? ''));
        if ($utente === null || empty($utente['id']) || $pid === '') {
            return [];
        }
        try {
            $righe = $this->db->righe('SELECT ambito, aula_id, persona_id FROM sos_referenti WHERE persona_id = ?', [$pid]);
        } catch (Throwable $e) {
            error_log('[SOS aule] referenti non letti: ' . $e->getMessage());

            return [];
        }
        $aule = [];
        foreach ($righe as $r) {
            if (!self::collegata($utente, [(string) $r['persona_id']])) {
                continue;
            }
            if ($r['ambito'] === self::RISERVA) {
                return null;
            }
            $aule[] = (int) $r['aula_id'];
        }

        return $aule;
    }

    /** Aggiunge una persona dell'anagrafe ai referenti di un'aula (ambito «aula») o alla riserva (ambito «riserva», senza aula). */
    public function aggiungiReferente(string $ambito, ?int $aulaId, string $personaId, int $da): Esito
    {
        if ($ambito !== self::AULA && $ambito !== self::RISERVA) {
            return Esito::errore('Lista non valida.');
        }
        $aulaId = $ambito === self::AULA ? (int) $aulaId : null;
        if ($ambito === self::AULA && $this->db->riga('SELECT id FROM sos_aule WHERE id = ?', [$aulaId]) === null) {
            return Esito::errore('L\'aula non c\'è più.');
        }
        $persona = $this->db->riga('SELECT id, nome, cognome FROM personale_ateneo WHERE id = ? LIMIT 1', [trim($personaId)]);
        if ($persona === null) {
            return Esito::errore('La persona non è nell\'anagrafe di Ateneo: cercala e sceglila dall\'elenco.');
        }
        $nome = trim(trim((string) $persona['nome']) . ' ' . trim((string) $persona['cognome']));
        // per la riserva aula_id è NULL e l'UNIQUE del database non ferma i doppioni: si controlla qui
        if ($this->db->valore('SELECT COUNT(*) FROM sos_referenti WHERE ambito = ? AND aula_id <=> ? AND persona_id = ?', [$ambito, $aulaId, (string) $persona['id']]) > 0) {
            return Esito::errore($nome . ' è già in questa lista.');
        }
        $n = $this->db->esegui(
            'INSERT INTO sos_referenti (ambito, aula_id, persona_id, nominativo, aggiunto_il, aggiunto_da) VALUES (?, ?, ?, ?, ?, ?)',
            [$ambito, $aulaId, (string) $persona['id'], mb_substr($nome, 0, 200), $this->adesso(), $da > 0 ? $da : null]
        );

        return $n === 1 ? Esito::ok($nome . ' è stato aggiunto.') : Esito::errore('Non è stato possibile salvare la persona: riprova tra qualche minuto.');
    }

    public function togliReferente(string $ambito, ?int $aulaId, string $personaId): Esito
    {
        $aulaId = $ambito === self::AULA ? (int) $aulaId : null;
        $n = $this->db->esegui('DELETE FROM sos_referenti WHERE ambito = ? AND aula_id <=> ? AND persona_id = ?', [$ambito, $aulaId, trim($personaId)]);

        return $n > 0 ? Esito::ok('Persona tolta dalla lista.') : Esito::errore('La persona non è più in questa lista.');
    }

    // ─────────────────────────── Studenti ───────────────────────────

    /** @return list<array<string, mixed>> le righe di sos_studenti con «entrato» (c'è un account con quell'email verificata) e «avvisi» */
    public function studenti(): array
    {
        $righe = $this->db->righe('SELECT * FROM sos_studenti ORDER BY cognome, nome, id');
        $entrati = [];
        if ($righe !== []) {
            $email = array_map(static fn (array $r): string => (string) $r['email'], $righe);
            foreach ($this->db->righe('SELECT * FROM utenti WHERE email IN (' . implode(', ', array_fill(0, count($email), '?')) . ')', $email) as $u) {
                $e = UtenteRepository::emailVerificata($u);
                if ($e !== null) {
                    $entrati[$e] = true;
                }
            }
        }
        $oggi = $this->oggi();
        foreach ($righe as &$r) {
            $r['entrato'] = isset($entrati[mb_strtolower((string) $r['email'])]);
            $r['avvisi'] = $r['entrato'] ? [] : ['Non è ancora entrato nel portale con questa email.'];
            // uno studente spento si vede già come spento: l'avviso del periodo serve a quello acceso che oggi non può intervenire
            if ((int) $r['attivo'] === 1 && !self::attivoIl($r, $oggi)) {
                $r['avvisi'][] = 'Fuori periodo: oggi non gli si può affidare niente.';
            }
        }
        unset($r);

        return $righe;
    }

    /** Gli studenti a cui oggi si può affidare un intervento. @return list<array<string, mixed>> */
    public function studentiAttiviOggi(): array
    {
        $oggi = $this->oggi();

        return array_values(array_filter(
            $this->db->righe('SELECT * FROM sos_studenti WHERE attivo = 1 ORDER BY cognome, nome, id'),
            static fn (array $s): bool => self::attivoIl($s, $oggi)
        ));
    }

    /** @return array<string, mixed>|null */
    public function studentePerId(int $id): ?array
    {
        return $this->db->riga('SELECT * FROM sos_studenti WHERE id = ?', [$id]);
    }

    /** Lo studente di questo account, se oggi può intervenire. @param array<string, mixed>|null $utente @return array<string, mixed>|null */
    public function studenteDi(?array $utente): ?array
    {
        $email = UtenteRepository::emailVerificata($utente);
        if ($email === null) {
            return null;
        }

        return self::studenteValido($utente, $this->db->righe('SELECT * FROM sos_studenti WHERE email = ?', [$email]), $this->oggi());
    }

    /**
     * Gli account con questa email VERIFICATA: gli stessi che studenteDi riconosce. Servono a chi deve avvisare uno studente (o chi non è nell'anagrafe).
     *
     * @return list<int>
     */
    public function accountDellEmail(string $email): array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return [];
        }
        $account = [];
        foreach ($this->db->righe('SELECT * FROM utenti WHERE LOWER(email) = ?', [$email]) as $u) {
            if (UtenteRepository::emailVerificata($u) === $email) {
                $account[] = (int) $u['id'];
            }
        }

        return $account;
    }

    /** @param array<string, mixed> $dati nome, cognome, email, dal, al (Y-m-d o vuoti), note, attivo (se manca: sì) */
    public function aggiungiStudente(array $dati, int $da): Esito
    {
        $s = $this->leggiStudente($dati, 0);
        if (is_string($s)) {
            return Esito::errore($s);
        }
        $id = $this->db->inserisci(
            'INSERT INTO sos_studenti (nome, cognome, email, dal, al, note, attivo, aggiunto_il, aggiunto_da) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$s['nome'], $s['cognome'], $s['email'], $s['dal'], $s['al'], $s['note'], $s['attivo'], $this->adesso(), $da > 0 ? $da : null]
        );

        return $id > 0 ? Esito::ok($s['nome'] . ' ' . $s['cognome'] . ' è stato aggiunto.') : Esito::errore('Non è stato possibile salvare lo studente: riprova tra qualche minuto.');
    }

    /** @param array<string, mixed> $dati come aggiungiStudente */
    public function modificaStudente(int $id, array $dati): Esito
    {
        if ($this->studentePerId($id) === null) {
            return Esito::errore('Lo studente non è più in elenco.');
        }
        $s = $this->leggiStudente($dati, $id);
        if (is_string($s)) {
            return Esito::errore($s);
        }
        $n = $this->db->esegui(
            'UPDATE sos_studenti SET nome = ?, cognome = ?, email = ?, dal = ?, al = ?, note = ?, attivo = ? WHERE id = ?',
            [$s['nome'], $s['cognome'], $s['email'], $s['dal'], $s['al'], $s['note'], $s['attivo'], $id]
        );

        return $n >= 0 ? Esito::ok($s['nome'] . ' ' . $s['cognome'] . ' è stato salvato.') : Esito::errore('Non è stato possibile salvare lo studente: riprova tra qualche minuto.');
    }

    /**
     * I controlli comuni a chi si aggiunge e a chi si modifica.
     *
     * @param array<string, mixed> $dati
     * @param int $id lo studente che si sta modificando (0 = nuovo), per non vederlo come doppione di se stesso
     * @return array{nome: string, cognome: string, email: string, dal: ?string, al: ?string, note: string, attivo: int}|string la riga pronta, o il motivo per cui non va
     */
    private function leggiStudente(array $dati, int $id): array|string
    {
        $testo = static fn (string $k): string => trim((string) ($dati[$k] ?? ''));
        $nome = $testo('nome');
        $cognome = $testo('cognome');
        $email = mb_strtolower($testo('email'));
        if ($nome === '' || $cognome === '' || mb_strlen($nome) > 100 || mb_strlen($cognome) > 100) {
            return 'Scrivi nome e cognome (al massimo 100 caratteri l\'uno).';
        }
        if (mb_strlen($email) > 150 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'Scrivi un indirizzo email valido.';
        }
        foreach (['dal', 'al'] as $k) {
            if ($testo($k) !== '' && !(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $testo($k), $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]))) {
                return 'Le date del periodo non sono valide.';
            }
        }
        if ($testo('dal') !== '' && $testo('al') !== '' && $testo('al') < $testo('dal')) {
            return 'La fine del periodo viene prima dell\'inizio.';
        }
        if ($this->db->riga('SELECT id FROM sos_studenti WHERE email = ? AND id <> ?', [$email, $id]) !== null) {
            return 'C\'è già uno studente con questa email.';
        }

        return [
            'nome' => $nome, 'cognome' => $cognome, 'email' => $email, 'dal' => $testo('dal') ?: null, 'al' => $testo('al') ?: null,
            'note' => mb_substr($testo('note'), 0, 500), 'attivo' => !array_key_exists('attivo', $dati) || !empty($dati['attivo']) ? 1 : 0,
        ];
    }

    /** Toglie lo studente; se ha segnalazioni affidate resta in elenco, spento (il registro deve continuare a dire chi è intervenuto). */
    public function togliStudente(int $id): Esito
    {
        $s = $this->studentePerId($id);
        if ($s === null) {
            return Esito::errore('Lo studente non è più in elenco.');
        }
        $nome = trim($s['nome'] . ' ' . $s['cognome']);
        if ((int) $this->db->valore('SELECT COUNT(*) FROM sos_segnalazioni WHERE assegnata_a_studente = ?', [$id]) > 0) {
            $this->db->esegui('UPDATE sos_studenti SET attivo = 0 WHERE id = ?', [$id]);

            return Esito::ok($nome . ' ha delle segnalazioni: non è stato cancellato, ma spento.');
        }
        $this->db->esegui('DELETE FROM sos_studenti WHERE id = ?', [$id]);

        return Esito::ok($nome . ' è stato tolto.');
    }

    // ─────────────────────────── Interno ───────────────────────────

    /**
     * Una lista con l'email di adesso, gli account collegati e gli avvisi per la scheda dell'amministratore.
     *
     * @return list<array{persona_id: string, nominativo: string, nome: string, email: string, in_anagrafe: bool, account: bool, utenti: list<int>, avvisi: list<string>}>
     */
    private function lista(string $ambito, ?int $aulaId): array
    {
        $righe = $this->db->righe(
            'SELECT r.persona_id, r.nominativo AS copiato, p.nome, p.cognome, p.email, p.attivo
             FROM sos_referenti r LEFT JOIN personale_ateneo p ON p.id = r.persona_id
             WHERE r.ambito = ? AND r.aula_id <=> ? ORDER BY r.nominativo, r.id',
            [$ambito, $aulaId]
        );
        $account = [];
        if ($righe !== []) {
            $ids = array_map(static fn (array $r): string => (string) $r['persona_id'], $righe);
            foreach ($this->db->righe('SELECT id, persona_id FROM utenti WHERE persona_id IN (' . implode(', ', array_fill(0, count($ids), '?')) . ')', $ids) as $u) {
                $account[(string) $u['persona_id']][] = (int) $u['id'];
            }
        }
        $out = [];
        foreach ($righe as $r) {
            $pid = (string) $r['persona_id'];
            $inAnagrafe = $r['attivo'] !== null && (int) $r['attivo'] === 1;
            $email = trim((string) ($r['email'] ?? ''));
            $email = filter_var($email, FILTER_VALIDATE_EMAIL) === false ? '' : $email;
            $nome = trim((string) ($r['nome'] ?? ''));
            $completo = trim($nome . ' ' . trim((string) ($r['cognome'] ?? '')));
            $avvisi = [];
            if (!$inAnagrafe) {
                $avvisi[] = 'Non è più in anagrafe: non riceve niente.';
            } elseif ($email === '') {
                $avvisi[] = 'Senza email in anagrafe: non riceve niente.';
            }
            if (!isset($account[$pid])) {
                $avvisi[] = 'Non è ancora entrato nel portale: non può prendere in carico.';
            }
            $out[] = [
                'persona_id' => $pid,
                'nominativo' => $completo !== '' ? $completo : (string) $r['copiato'],
                'nome' => $nome !== '' ? $nome : (string) $r['copiato'],
                'email' => $email,
                'in_anagrafe' => $inAnagrafe,
                'account' => isset($account[$pid]),
                'utenti' => $account[$pid] ?? [],
                'avvisi' => $avvisi,
            ];
        }

        return $out;
    }

    private function oggi(): string
    {
        return $this->orologio->adesso()->format('Y-m-d');
    }

    private function adesso(): string
    {
        return $this->orologio->adesso()->format('Y-m-d H:i:s');
    }
}
