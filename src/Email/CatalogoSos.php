<?php

declare(strict_types=1);

namespace App\Email;

/** Le email di SOS aule (le manda App\SosAule\NotificheSos). Formato dei testi: vedi ModelliEmail. */
final class CatalogoSos
{
    public static function modelli(): array
    {
        $R = 'SOS aule · A chi segue l\'aula';
        $D = 'SOS aule · Al docente';
        $S = 'SOS aule · Allo studente';
        $sp = static fn (string $d, string $esempio): array => ['d' => $d, 'esempio' => $esempio];

        $codice = $sp('Codice della segnalazione', 'SOS-2026-0042');
        $aula = $sp('L\'aula, con la parola «aula» davanti', 'aula 5C2');
        $tipo = $sp('Tipo di problema', 'Proiettore o schermo');
        $ora = $sp('L\'ora', '10:12');
        $citazione = static fn (string $testo): string => '<blockquote style="margin:12px 0;padding:8px 14px;border-left:4px solid #b45309;background:#fffbeb;">' . $testo . '</blockquote>';

        return [
            'sos_nuova' => CatalogoEmail::m($R, 'Nuova segnalazione SOS', 'Appena un docente o una persona del personale invia la richiesta di aiuto (parte anche la notifica sul telefono, a chi l\'ha attivata).', 'I referenti dell\'aula con un indirizzo email; se non ce ne sono, i responsabili di riserva',
                ['codice' => $codice, 'aula' => $aula, 'edificio' => $sp('Dove si trova l\'aula, tra parentesi con lo spazio davanti (vuoto se non è scritto)', ' (Cubo 5C, piano terra)'), 'tipo' => $tipo,
                    'urgenza' => $sp('Quanto è urgente', 'La lezione è ferma'), 'docente' => $sp('Cognome e nome di chi ha inviato', 'Rossi Maria'),
                    'telefono_testo' => $sp('Il cellulare per richiamare, con la virgola davanti (vuoto se non c\'è)', ', cellulare 333 000 0000'), 'ora' => $ora,
                    '!descrizione' => $sp('La descrizione scritta da chi ha inviato (vuota se non c\'è)', $citazione('Il proiettore non si accende.'))],
                'SOS {{aula}} — {{urgenza}} — {{tipo}}',
                "Nuova richiesta di aiuto **{{codice}}**, inviata alle {{ora}}.\n\n- Dove: {{aula}}{{edificio}}\n- Problema: {{tipo}}\n- Urgenza: **{{urgenza}}**\n- Chi chiede: {{docente}}{{telefono_testo}}\n\n{{!descrizione}}",
                'Apri'),
            'sos_nota' => CatalogoEmail::m($R, 'Nota aggiunta a una segnalazione SOS', 'Quando il docente, o un collega dalla stessa aula, aggiunge una nota a una segnalazione aperta (parte anche la notifica sul telefono).', 'Chi ha in mano la segnalazione (referente che l\'ha presa e studente affidato); se non è ancora di nessuno, chi riceve le segnalazioni dell\'aula',
                ['codice' => $codice, 'aula' => $aula, 'tipo' => $tipo, 'autore' => $sp('Chi ha scritto la nota', 'Rossi Maria (docente)'), 'ora' => $ora,
                    '!nota' => $sp('Il testo della nota', $citazione('Ho provato a cambiare cavo, non va.'))],
                'SOS {{aula}} — nota su {{codice}}',
                "**{{autore}}** ha aggiunto una nota alla segnalazione **{{codice}}** ({{tipo}}) alle {{ora}}.\n\n{{!nota}}",
                'Apri'),
            'sos_affidata' => CatalogoEmail::m($S, 'Un intervento SOS affidato a uno studente', 'Quando chi segue l\'aula manda uno studente o passa l\'intervento a un altro (parte anche la notifica sul telefono).', 'Lo studente a cui è affidato',
                ['nome' => $sp('Nome dello studente', 'Luca'), 'codice' => $codice, 'aula' => $aula, 'edificio' => $sp('Dove si trova l\'aula, tra parentesi con lo spazio davanti (vuoto se non è scritto)', ' (Cubo 5C, piano terra)'), 'tipo' => $tipo,
                    'urgenza' => $sp('Quanto è urgente', 'La lezione è ferma'), 'docente' => $sp('Cognome e nome di chi ha inviato', 'Rossi Maria'),
                    'telefono_testo' => $sp('Il cellulare per richiamare, con la virgola davanti (vuoto se non c\'è)', ', cellulare 333 000 0000'),
                    '!descrizione' => $sp('La descrizione scritta da chi ha inviato (vuota se non c\'è)', $citazione('Il proiettore non si accende.'))],
                'SOS {{aula}} — affidata a te — {{tipo}}',
                "Ciao **{{nome}}**,\n\nti è stato affidato l'intervento **{{codice}}**.\n\n- Dove: {{aula}}{{edificio}}\n- Problema: {{tipo}}\n- Urgenza: **{{urgenza}}**\n- Chi chiede: {{docente}}{{telefono_testo}}\n\n{{!descrizione}}",
                'Apri'),
            'sos_tolta' => CatalogoEmail::m($S, 'Un intervento SOS non è più affidato allo studente', 'Quando chi segue l\'aula riprende l\'intervento, lo passa a un altro studente o lo segna risolto mentre era affidato a lui (parte anche la notifica sul telefono).', 'Lo studente a cui era affidato',
                ['nome' => $sp('Nome dello studente', 'Luca'), 'codice' => $codice, 'aula' => $aula, 'nome_aula' => $sp('Solo il nome dell\'aula', '5C2'), 'tipo' => $tipo],
                'SOS {{aula}} — non serve più che tu vada ({{codice}})',
                "Ciao **{{nome}}**,\n\nla segnalazione SOS {{nome_aula}} non è più affidata a te: non serve più che tu vada.\n\nCodice **{{codice}}**, problema: {{tipo}}.",
                'Apri i tuoi interventi'),
            'sos_promemoria' => CatalogoEmail::m($R, 'Promemoria: segnalazione SOS ancora senza nessuno', 'Una volta sola, quando una segnalazione resta «In attesa» più dei minuti scelti nelle impostazioni di SOS aule (parte anche la notifica sul telefono).', 'Tutti i referenti dell\'aula e i responsabili di riserva',
                ['codice' => $codice, 'aula' => $aula, 'edificio' => $sp('Dove si trova l\'aula, tra parentesi con lo spazio davanti (vuoto se non è scritto)', ' (Cubo 5C, piano terra)'), 'tipo' => $tipo,
                    'urgenza' => $sp('Quanto è urgente', 'La lezione è ferma'), 'docente' => $sp('Cognome e nome di chi ha inviato', 'Rossi Maria'),
                    'telefono_testo' => $sp('Il cellulare per richiamare, con la virgola davanti (vuoto se non c\'è)', ', cellulare 333 000 0000'), 'ora' => $ora,
                    'minuti' => $sp('Da quanti minuti aspetta', '12'), '!descrizione' => $sp('La descrizione scritta da chi ha inviato (vuota se non c\'è)', $citazione('Il proiettore non si accende.'))],
                'SOS {{aula}} — ancora in attesa — {{urgenza}}',
                "La richiesta **{{codice}}**, inviata alle {{ora}}, **non è ancora stata presa da nessuno** (minuti passati: {{minuti}}).\n\n- Dove: {{aula}}{{edificio}}\n- Problema: {{tipo}}\n- Urgenza: **{{urgenza}}**\n- Chi chiede: {{docente}}{{telefono_testo}}\n\n{{!descrizione}}",
                'Apri'),
            'sos_non_risolta' => CatalogoEmail::m($R,'Una segnalazione SOS non risolta torna a chi segue l\'aula', 'Quando lo studente segna «Non risolto», o chi ha segnalato preme «Non è risolto» (parte anche la notifica sul telefono).', 'Dopo lo studente: chi riceve le segnalazioni dell\'aula. Dopo «Non è risolto»: chi ha in mano la segnalazione o, se nessuno, chi riceve quelle dell\'aula',
                ['codice' => $codice, 'aula' => $aula, 'tipo' => $tipo, 'esito' => $sp('Che cosa non ha funzionato', 'Serve un pezzo di ricambio'), 'autore' => $sp('Chi lo dice', 'Bianchi Luca'), 'ora' => $ora,
                    '!nota' => $sp('La nota (vuota se non c\'è)', $citazione('Il cavo è rotto.'))],
                'SOS {{aula}} — non risolta ({{codice}})',
                "La segnalazione **{{codice}}** ({{aula}}, {{tipo}}) è **non risolta** e torna a chi segue l'aula.\n\n- Che cosa non ha funzionato: {{esito}}\n- Lo dice: {{autore}}, alle {{ora}}\n\n{{!nota}}",
                'Apri'),
            'sos_annullata' => CatalogoEmail::m($R, 'Segnalazione SOS annullata da chi l\'ha inviata', 'Quando chi ha segnalato preme «Annulla, ho risolto da solo» (parte anche la notifica sul telefono).', 'Chi ha in mano la segnalazione; se nessuno l\'ha presa, chi riceve le segnalazioni dell\'aula',
                ['codice' => $codice, 'aula' => $aula, 'nome_aula' => $sp('Solo il nome dell\'aula', '5C2'), 'tipo' => $tipo],
                'SOS {{aula}} — annullata ({{codice}})',
                "La segnalazione SOS {{nome_aula}} è stata annullata da chi l'ha inviata: non serve andare.\n\nCodice **{{codice}}**, problema: {{tipo}}.",
                'Apri'),
            'sos_chiusa' => CatalogoEmail::m($D,'La segnalazione SOS è stata chiusa o inoltrata', 'Quando la segnalazione diventa «Risolta», «Chiusa senza soluzione» o «Inoltrata a un altro ufficio».', 'Chi l\'ha inviata (solo se il suo account ha un\'email verificata)',
                ['nome' => $sp('Nome di chi ha inviato', 'Maria'), 'codice' => $codice, 'aula' => $aula, 'tipo' => $tipo, 'stato' => $sp('Lo stato nuovo', 'Risolta'),
                    '!nota' => $sp('Il motivo, o l\'ufficio a cui è inoltrata (vuoto se è risolta)', ''),
                    'se_risolta' => $sp('La frase su «Non è risolto» (vuota se non è risolta)', 'Se il problema c\'è ancora, hai 24 ore per dirlo con il pulsante qui sotto.')],
                'SOS {{aula}} — {{stato}} ({{codice}})',
                "Gentile **{{nome}}**,\n\nla tua segnalazione **{{codice}}** ({{aula}}, {{tipo}}) è ora: **{{stato}}**.\n\n{{!nota}}\n\n{{se_risolta}}",
                'Non è risolto'),
        ];
    }
}
