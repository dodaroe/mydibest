<?php

declare(strict_types=1);

namespace App\SosAule;

use App\Core\Database;
use App\Core\Esito;
use App\Forms\CostruttoreCampi;

/**
 * I campi del form della segnalazione: quelli fissi (FISSI) con le personalizzazioni dell'amministratore, e quelli aggiunti con il costruttore di MyForms.
 * Tutto sta in un'impostazione JSON sola (sos_campi): {"fissi": {chiave: {...}}, "aggiunti": [...], "ultimo": n}. Vuota = valori del codice.
 * I valori dei campi aggiunti (chiavi x_<n>, mai riusate) si salvano in sos_segnalazioni.campi_extra_json; li legge RegoleSos.
 */
final class CampiSos
{
    /** «fisso_obb» / «fisso_vis»: l'amministratore non può cambiare obbligatorio / visibile. Etichetta e aiuto si cambiano sempre. */
    public const FISSI = [
        'docente' => ['etichetta' => 'Docente', 'aiuto' => '', 'obbligatorio' => true, 'visibile' => true, 'fisso_obb' => true, 'fisso_vis' => true],
        'telefono' => ['etichetta' => 'Cellulare per essere richiamato', 'aiuto' => '', 'obbligatorio' => false, 'visibile' => true, 'fisso_obb' => false, 'fisso_vis' => false],
        'aula' => ['etichetta' => 'Aula', 'aiuto' => '', 'obbligatorio' => true, 'visibile' => true, 'fisso_obb' => true, 'fisso_vis' => true],
        'tipo' => ['etichetta' => 'Tipo di problema', 'aiuto' => '', 'obbligatorio' => true, 'visibile' => true, 'fisso_obb' => true, 'fisso_vis' => true],
        // l'urgenza serve ai promemoria e all'ordine delle card: sempre chiesta
        'urgenza' => ['etichetta' => 'Quanto è urgente', 'aiuto' => '', 'obbligatorio' => true, 'visibile' => true, 'fisso_obb' => true, 'fisso_vis' => true],
        // con «Altro» è obbligatoria: non si può nascondere
        'descrizione' => ['etichetta' => 'Descrivi il problema', 'aiuto' => '', 'obbligatorio' => false, 'visibile' => true, 'fisso_obb' => false, 'fisso_vis' => true],
        'foto' => ['etichetta' => 'Una foto', 'aiuto' => '', 'obbligatorio' => false, 'visibile' => true, 'fisso_obb' => false, 'fisso_vis' => false],
        'insegnamento' => ['etichetta' => 'Insegnamento', 'aiuto' => '', 'obbligatorio' => false, 'visibile' => false, 'fisso_obb' => false, 'fisso_vis' => false],
    ];

    /** I tipi dei campi aggiunti: quelli di MyForms senza file e senza tabelle (come per le richieste di pubblicazione). */
    public const TIPI_AGGIUNTI = ['text', 'textarea', 'radio', 'multicheck', 'date', 'number', 'checkbox', 'url'];

    public const MAX_AGGIUNTI = 15;
    public const MAX_ETICHETTA = 200;
    public const MAX_AIUTO = 300;

    /** Il prefisso dei nomi del costruttore di MyForms (sx_etichetta[], sx_tipo[]…). */
    public const PREFISSO = 'sx';

    public function __construct(private ImpostazioniSos $impostazioni, private Database $db)
    {
    }

    /** @return array<string, array{etichetta: string, aiuto: string, obbligatorio: bool, visibile: bool, fisso_obb: bool, fisso_vis: bool}> */
    public function fissi(): array
    {
        $salvati = $this->salvati()['fissi'];
        $out = [];
        foreach (self::FISSI as $k => $c) {
            $s = $salvati[$k] ?? [];
            if (trim((string) ($s['etichetta'] ?? '')) !== '') {
                $c['etichetta'] = (string) $s['etichetta'];
            }
            if (isset($s['aiuto'])) {
                $c['aiuto'] = (string) $s['aiuto'];
            }
            if (!$c['fisso_obb'] && isset($s['obbligatorio'])) {
                $c['obbligatorio'] = (bool) $s['obbligatorio'];
            }
            if (!$c['fisso_vis'] && isset($s['visibile'])) {
                $c['visibile'] = (bool) $s['visibile'];
            }
            $out[$k] = $c;
        }

        return $out;
    }

    /** @return list<array{chiave: string, etichetta: string, tipo: string, opzioni: list<string>, obbligatorio: bool, aiuto: string, visibile: bool}> */
    public function aggiunti(): array
    {
        $out = [];
        foreach ($this->salvati()['aggiunti'] as $c) {
            if (!is_array($c) || !in_array($c['tipo'] ?? '', self::TIPI_AGGIUNTI, true) || trim((string) ($c['etichetta'] ?? '')) === '') {
                continue;
            }
            $out[] = [
                'chiave' => (string) ($c['chiave'] ?? ''), 'etichetta' => (string) $c['etichetta'], 'tipo' => (string) $c['tipo'],
                'opzioni' => array_values(array_map('strval', (array) ($c['opzioni'] ?? []))), 'obbligatorio' => !empty($c['obbligatorio']),
                'aiuto' => (string) ($c['aiuto'] ?? ''), 'visibile' => (bool) ($c['visibile'] ?? true),
            ];
        }

        return $out;
    }

    /** @param array<string, mixed> $dati etichetta, aiuto, obbligatorio, visibile (le caselle non spuntate valgono «no») */
    public function salvaFisso(string $chiave, array $dati): Esito
    {
        if (!isset(self::FISSI[$chiave])) {
            return Esito::errore('Quel campo non c\'è.');
        }
        $etichetta = trim((string) ($dati['etichetta'] ?? ''));
        $aiuto = trim((string) ($dati['aiuto'] ?? ''));
        if ($etichetta === '' || mb_strlen($etichetta) > self::MAX_ETICHETTA || mb_strlen($aiuto) > self::MAX_AIUTO) {
            return Esito::errore('Scrivi il nome del campo (massimo ' . self::MAX_ETICHETTA . ' caratteri; aiuto massimo ' . self::MAX_AIUTO . ').');
        }
        $s = $this->salvati();
        $c = ['etichetta' => $etichetta, 'aiuto' => $aiuto];
        foreach (['obbligatorio' => 'fisso_obb', 'visibile' => 'fisso_vis'] as $k => $fisso) {
            if (!self::FISSI[$chiave][$fisso]) {
                $c[$k] = !empty($dati[$k]);
            }
        }
        $s['fissi'][$chiave] = $c;

        return $this->salva($s);
    }

    /** «Torna all'originale». */
    public function ripristina(string $chiave): Esito
    {
        $s = $this->salvati();
        unset($s['fissi'][$chiave]);

        return $this->salva($s);
    }

    /**
     * Salva i campi aggiunti come arrivano dal costruttore (sx_etichetta[], sx_tipo[], sx_opzioni[], sx_obbl[], sx_aiuto[]) più, nello stesso ordine,
     * sx_chiave[] (vuota = campo nuovo) e sx_vis[]. Un campo tolto che ha già risposte non si può eliminare: si può solo nascondere.
     *
     * @param array<string, mixed> $post
     */
    public function salvaAggiunti(array $post): Esito
    {
        $p = self::PREFISSO;
        $s = $this->salvati();
        $esistenti = [];
        foreach ($this->aggiunti() as $c) {
            $esistenti[$c['chiave']] = $c;
        }
        $ultimo = (int) $s['ultimo'];
        $campi = [];
        $tenuti = [];
        foreach ((array) ($post["{$p}_etichetta"] ?? []) as $i => $etichetta) {
            $sub = [];
            foreach (['etichetta', 'tipo', 'opzioni', 'obbl', 'aiuto'] as $nome) {
                $sub["{$p}_{$nome}"] = [$post["{$p}_{$nome}"][$i] ?? ''];
            }
            $json = CostruttoreCampi::daPost($sub, $p, self::TIPI_AGGIUNTI);
            $campo = $json === null ? null : (json_decode($json, true)[0] ?? null);
            if ($campo === null) {
                continue; // riga vuota
            }
            $nome = '«' . $campo['etichetta'] . '»';
            $tipo = (string) $campo['tipo'];
            $opzioni = in_array($tipo, ['radio', 'multicheck'], true)
                ? array_values(array_filter(array_map('trim', preg_split('/[,;\n]/', (string) $campo['opzioni']) ?: []), static fn (string $o): bool => $o !== ''))
                : [];
            $chiave = (string) ($post["{$p}_chiave"][$i] ?? '');
            if (!isset($esistenti[$chiave]) || isset($tenuti[$chiave])) {
                $chiave = 'x_' . (++$ultimo);
            } elseif ($esistenti[$chiave]['tipo'] !== $tipo && $this->conRisposte($chiave)) {
                return Esito::errore("$nome: il tipo non si può cambiare, perché ci sono già risposte. Puoi nasconderlo e crearne uno nuovo.");
            }
            if (mb_strlen((string) $campo['etichetta']) > self::MAX_ETICHETTA || mb_strlen(trim((string) $campo['aiuto'])) > self::MAX_AIUTO) {
                return Esito::errore("$nome: il nome o il testo di aiuto è troppo lungo.");
            }
            if (in_array($tipo, ['radio', 'multicheck'], true) && $opzioni === []) {
                return Esito::errore("$nome: scrivi le scelte, separate da virgola.");
            }
            $tenuti[$chiave] = true;
            $campi[] = [
                'chiave' => $chiave, 'etichetta' => (string) $campo['etichetta'], 'tipo' => $tipo, 'opzioni' => $opzioni, 'obbligatorio' => (bool) $campo['obbligatorio'],
                'aiuto' => trim((string) $campo['aiuto']), 'visibile' => ($post["{$p}_vis"][$i] ?? '1') === '1',
            ];
        }
        foreach ($esistenti as $chiave => $c) {
            if (!isset($tenuti[$chiave]) && $this->conRisposte($chiave)) {
                return Esito::errore('«' . $c['etichetta'] . '» ha già delle risposte: non si può eliminare, puoi solo nasconderlo.');
            }
        }
        if (count($campi) > self::MAX_AGGIUNTI) {
            return Esito::errore('Ci possono essere al massimo ' . self::MAX_AGGIUNTI . ' campi aggiunti.');
        }
        $s['aggiunti'] = $campi;
        $s['ultimo'] = $ultimo;

        return $this->salva($s);
    }

    private function conRisposte(string $chiave): bool
    {
        return (int) $this->db->valore('SELECT COUNT(*) FROM sos_segnalazioni WHERE campi_extra_json LIKE ?', ['%"' . addcslashes($chiave, '%_\\') . '":%']) > 0;
    }

    /** @return array{fissi: array<string, array<string, mixed>>, aggiunti: list<mixed>, ultimo: int} */
    private function salvati(): array
    {
        $s = json_decode((string) $this->impostazioni->tutte()['campi'], true);
        $s = is_array($s) ? $s : [];

        return [
            'fissi' => is_array($s['fissi'] ?? null) ? $s['fissi'] : [],
            'aggiunti' => is_array($s['aggiunti'] ?? null) ? array_values($s['aggiunti']) : [],
            'ultimo' => (int) ($s['ultimo'] ?? 0),
        ];
    }

    /** @param array<string, mixed> $s */
    private function salva(array $s): Esito
    {
        $errori = $this->impostazioni->aggiorna(['campi' => (string) json_encode($s, JSON_UNESCAPED_UNICODE)])['errori'];

        return $errori ? Esito::errore((string) reset($errori)) : Esito::ok('Campi salvati.');
    }
}
