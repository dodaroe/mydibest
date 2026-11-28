# Inventario degli accessi al pannello

> **Questo documento è generato** da `strumenti/mappa_accessi.php` (legge `App\Portale\Pannello\PermessiPagine`, `MenuModuli` e `App\Auth\Permessi`): non si modifica a mano. Per cambiare chi apre una pagina si cambia `PermessiPagine`, poi si rilancia `php strumenti/mappa_accessi.php`. `php strumenti/mappa_accessi.php --controlla` dice se il documento è rimasto indietro.

Ogni file di `admin/` (sottocartelle comprese) sta in uno solo di tre elenchi: **pagina d'ingresso** (passa da `admin_header.php`, che applica la regola), **scheda** (inclusa da un'altra pagina, con la guardia in testa) o **fuori** (non passa da `admin_header.php`: cron, AJAX, scaricamenti, con un controllo proprio). Dove una pagina elenca più permessi **basta uno**. I controlli dentro le singole pagine restano: sono la seconda protezione.

## 1. I permessi

Li calcola `App\Auth\Permessi` (una volta per richiesta, per l'utente e l'area aperta con `?p_id=`); i nomi sono quelli della mappa.

| Permesso | Chi ce l'ha | Pagine d'ingresso che apre |
|---|---|---|
| `is_full_admin` | Amministratore globale: ruolo 1, anche come ruolo secondario (dalla riga del database) | `anagrafe_docenti.php`, `anagrafe_insegnamenti.php`, `anagrafe_personale.php`, `anagrafe_pta.php`, `area_personale.php`, `audit_log.php`, `comunicazioni.php`, `contatti.php`, `email.php`, `log_accessi.php`, `menu.php`, `nuova_area.php`, `protocollo.php`, `simula.php`, `sistema.php`, `testata.php`, `tirocini.php`, `uffici.php`, `utenti.php` |
| `is_area_manager` | Gestisce tutta l'area aperta: gestore dell'area, abilitato al modulo a cui l'area appartiene, amministratore | `prenotazioni_risorse.php`, `risorse.php` |
| `can_manage_eventi` | Gestisce almeno un'attività dell'area aperta (assegnata una per una o per perimetro «progetti»/«eventi»), oppure tutta l'area | `archivio.php`, `eventi.php`, `progetti.php` |
| `can_manage_iscritti` | Come «can_manage_eventi»: iscritti, presenze, badge e statistiche dell'area aperta | `iscritti.php`, `partecipanti.php`, `scanner.php`, `stampa_badge.php`, `stampa_lista_iscritti.php`, `statistiche.php` |
| `can_manage_sondaggi` | Come «can_manage_eventi»: sondaggi delle attività | `sondaggi.php` |
| `can_manage_form` | Come «can_manage_eventi»: moduli di iscrizione delle attività | `form_builder.php` |
| `can_manage_settings` | Gestisce tutta l'area aperta (le impostazioni dell'area non si aprono a chi ha solo alcune attività) | `impostazioni_area.php` |
| `puo_creare_eventi` | Perimetro «eventi» dell'area aperta (o gestore di tutta l'area): può creare eventi nuovi | — |
| `puo_creare_progetti` | Perimetro «progetti» dell'area aperta (o gestore di tutta l'area): può creare progetti nuovi | — |
| `puo_fsl` | Abilitato al modulo Formazione Scuola Lavoro (anche chi ha il modulo Eventi e seminari o il perimetro «fsl») | — |
| `puo_fsl_convenzioni` | Modulo FSL, perimetro «fsl_convenzioni» o persona dell'ufficio «Ufficio convenzioni FSL» | `allegato_a.php`, `convenzione_file.php`, `email.php`, `fsl.php`, `fsl_scuole.php`, `messaggi.php` |
| `puo_fsl_scuole` | Modulo FSL o perimetro «fsl_scuole» | `scuole.php` |
| `puo_didattica` | Chi lavora in tutta la Didattica, più i referenti dei consigli dei corsi di studio (solo le sedute dei loro consigli) | `didattica.php` |
| `puo_didattica_tutto` | Amministratore, abilitato al modulo Didattica o operatore dell'Ufficio didattico | `email.php`, `sostituzioni.php`, `tirocini.php`, `uffici.php` |
| `puo_conv_tesi` | Chi lavora in tutta la Didattica e le persone dell'ufficio «Ufficio convenzioni di tesi» | `convenzioni_tesi.php`, `email.php` |
| `puo_tir_motorie` | Chi lavora in tutta la Didattica e le persone dell'ufficio «Ufficio tirocini (Scienze motorie)» | `tirocini_motorie.php` |
| `puo_tutorato` | Amministratore, abilitato al modulo Didattica o operatore dell'Ufficio didattico con il compito «bandi» | `email.php`, `tutorato.php` |
| `puo_forms` | Amministratore o abilitato al modulo MyForms: può creare form nuovi | — |
| `puo_forms_cogestore` | Co-gestore di almeno un form, senza l'abilitazione al modulo | — |
| `puo_forms_vedere` | Entra in MyForms: abilitato al modulo, oppure co-gestore di almeno un form (vede solo i suoi) | `forms.php`, `forms_allegato.php`, `forms_risposte.php` |
| `puo_amministrazione` | Tutto il modulo Amministrazione: amministratori e «Modulo Amministrazione» | `sos_aule.php`, `sos_cartello.php` |
| `puo_pubblicazioni` | Chi può tutto in Amministrazione e chi ha solo «Amministrazione · Richieste di pubblicazione» | `pubblicazioni.php` |
| `puo_amm_entrare` | Una delle due abilitazioni dell'Amministrazione: entra nel modulo | `amministrazione.php` |
| `puo_assistenza` | Amministratori e persone dell'ufficio fisso «Assistenza»: rispondono alle chat | `assistenza.php`, `didacticus.php`, `messaggi.php` |
| `puo_orientamento` | Amministratore o abilitato al modulo Eventi e seminari (Orientamento). Costa una query: si calcola solo se la pagina lo chiede | `report_ambiti.php` |
| `ha_caselle` | Gestisce almeno un'area con messaggi degli iscritti. Costa una query: si calcola solo se la pagina lo chiede | `messaggi.php` |
| `ha_sezioni` | Ha almeno un modulo o un'area da gestire (o è amministratore). Senza, il pannello rimanda all'area personale invece del 403 | `aree.php`, `cerca.php`, `dashboard.php`, `index.php`, `inizio.php` |

## 2. Il menu, modulo per modulo

Le voci fisse stanno in `MenuModuli`. Le voci che seguono l'area aperta (Dashboard, Eventi, Iscritti…) non sono qui: dipendono dall'area, non dal modulo.

### Eventi e seminari (`orientamento`)

| Voce | Indirizzo | Chi la vede | Pagine che la accendono |
|---|---|---|---|
| Report per ambito | `report_ambiti.php` | modulo Eventi e seminari | `report_ambiti.php` |

### Formazione Scuola Lavoro (`fsl`)

| Voce | Indirizzo | Chi la vede | Pagine che la accendono |
|---|---|---|---|
| Scuole e convenzioni | `fsl_scuole.php` | convenzioni FSL | `fsl_scuole.php`, `convenzione_file.php` |
| Numeri e report | `fsl.php` | modulo FSL | `fsl.php` |
| Anagrafe delle scuole | `scuole.php` | scuole FSL | `scuole.php` |

### Prenotazioni e risorse (`calendari`)

Nessuna voce fissa: le sue pagine seguono le aree del modulo.

### Didattica (`didattica`)

| Voce | Indirizzo | Chi la vede | Pagine che la accendono |
|---|---|---|---|
| Cosa devo fare | `didattica.php?tab=home` | tutta la Didattica | `didattica.php` |
| Pratiche degli studenti | `didattica.php?tab=pratiche` | tutta la Didattica | `didattica.php` |
| Sedute e verbali | `didattica.php?tab=sedute` | Didattica | `didattica.php` |
| Moduli e documenti | `didattica.php?tab=moduli` | tutta la Didattica | `didattica.php` |
| Statistiche | `didattica.php?tab=statistiche` | tutta la Didattica | `didattica.php` |
| Tirocini | `tirocini.php` | tutta la Didattica | `tirocini.php` |
| Tirocini Scienze motorie | `tirocini_motorie.php` | tirocini Scienze motorie | `tirocini_motorie.php` |
| Convenzioni di tesi | `convenzioni_tesi.php` | convenzioni di tesi | `convenzioni_tesi.php` |
| Tutorato · lettere di incarico | `tutorato.php?vista=cosa` | tutorato | `tutorato.php` |
| Sostituzioni | `sostituzioni.php` | tutta la Didattica | `sostituzioni.php` |
| Ricevimento | `didattica.php?tab=ufficio` | tutta la Didattica | `didattica.php` |
| Assistenza | `assistenza.php` | Assistenza | `assistenza.php` |
| Domande e risposte | `didacticus.php` | Assistenza | `didacticus.php` |

### MyForms (`forms`)

| Voce | Indirizzo | Chi la vede | Pagine che la accendono |
|---|---|---|---|
| MyForms | `forms.php` | MyForms | `forms.php`, `forms_risposte.php` |

### Amministrazione (`amministrazione`)

| Voce | Indirizzo | Chi la vede | Pagine che la accendono |
|---|---|---|---|
| Cosa devo fare | `amministrazione.php?tab=home` | Amministrazione (entra) | `amministrazione.php` |
| Modulistica | `amministrazione.php?tab=modulistica` | Amministrazione | `amministrazione.php` |
| Richieste di pubblicazione | `pubblicazioni.php?vista=arrivate` | richieste di pubblicazione | `pubblicazioni.php` |
| Da compilare | `pubblicazioni.php?vista=da_compilare` | richieste di pubblicazione | `pubblicazioni.php` |
| Impostazioni | `amministrazione.php?tab=impostazioni` | Amministrazione | `amministrazione.php` |
| Campi e colonne | `amministrazione.php?tab=campi` | Amministrazione | `amministrazione.php` |
| SOS aule | `sos_aule.php` | Amministrazione | `sos_aule.php`, `sos_cartello.php` |

### Persone e permessi (`persone`)

| Voce | Indirizzo | Chi la vede | Pagine che la accendono |
|---|---|---|---|
| Utenti e abilitazioni | `utenti.php` | amministratori | `utenti.php` |
| Uffici e persone | `uffici.php` | amministratori · tutta la Didattica | `uffici.php`, `uffici_ateneo.php` |
| Docenti | `anagrafe_docenti.php` | amministratori | `anagrafe_docenti.php` |
| Personale TA | `anagrafe_pta.php` | amministratori | `anagrafe_pta.php` |
| Insegnamenti | `anagrafe_insegnamenti.php` | amministratori | `anagrafe_insegnamenti.php` |
| Corsi di studio | `anagrafe_personale.php?vista=corsi` | amministratori | `anagrafe_personale.php` |
| Strutture e aggiornamento | `anagrafe_personale.php?vista=strutture` | amministratori | `anagrafe_personale.php` |

### Sito pubblico (`sito`)

| Voce | Indirizzo | Chi la vede | Pagine che la accendono |
|---|---|---|---|
| Testata e home | `testata.php` | amministratori | `testata.php` |
| Menu | `menu.php` | amministratori | `menu.php` |
| Notizie e pagine | `comunicazioni.php` | amministratori | `comunicazioni.php` |
| Contatti per gli studenti | `contatti.php` | amministratori | `contatti.php` |
| Area personale (riquadri) | `area_personale.php` | amministratori | `area_personale.php` |
| Aree di tutti i moduli | `aree.php` | amministratori | `aree.php`, `nuova_area.php` |

### Sistema (`sistema`)

| Voce | Indirizzo | Chi la vede | Pagine che la accendono |
|---|---|---|---|
| Protocollo (Titulus) | `protocollo.php` | amministratori | `protocollo.php` |
| Testi delle email | `email.php` | amministratori | `email.php` |
| Sistema e registri | `sistema.php` | amministratori | `sistema.php`, `audit_log.php`, `log_accessi.php` |
| Entra come… | `simula.php` | amministratori | `simula.php` |

## 3. Pagina per pagina

L'indirizzo è quello del file (si apre anche senza «.php»); la colonna «Nucleo» dice se la pagina è una rotta di `nucleo.php` (vedi la sezione 4).

| File | Indirizzo | Tipo | Chi la apre | Modulo del menu | Voce | Nucleo |
|---|---|---|---|---|---|---|
| `admin_footer.php` | — | scheda | non si apre: la include ogni pagina d'ingresso | — | — | — |
| `admin_header.php` | — | scheda | non si apre: la include ogni pagina d'ingresso | — | — | — |
| `allegato_a.php` | `admin/allegato_a.php` | pagina | convenzioni FSL | — | — | — |
| `amministrazione.php` | `admin/amministrazione.php` | pagina | Amministrazione (entra) | Amministrazione | Cosa devo fare; Modulistica; Impostazioni; Campi e colonne | — |
| `amministrazione_campi.php` | — | scheda | come la pagina che la include (`amministrazione.php`); da sola dà 403 | Amministrazione | — | — |
| `amministrazione_home.php` | — | scheda | come la pagina che la include (`amministrazione.php`); da sola dà 403 | Amministrazione | — | — |
| `amministrazione_impostazioni.php` | — | scheda | come la pagina che la include (`amministrazione.php`); da sola dà 403 | Amministrazione | — | — |
| `amministrazione_modulistica.php` | — | scheda | come la pagina che la include (`amministrazione.php`); da sola dà 403 | Amministrazione | — | — |
| `anagrafe_docenti.php` | `admin/anagrafe_docenti.php` | pagina | amministratori | Persone e permessi | Docenti | — |
| `anagrafe_insegnamenti.php` | `admin/anagrafe_insegnamenti.php` | pagina | amministratori | Persone e permessi | Insegnamenti | — |
| `anagrafe_personale.php` | `admin/anagrafe_personale.php` | pagina | amministratori | Persone e permessi | Corsi di studio; Strutture e aggiornamento | — |
| `anagrafe_pta.php` | `admin/anagrafe_pta.php` | pagina | amministratori | Persone e permessi | Personale TA | — |
| `api_unread.php` | `admin/api_unread.php` | fuori | controllo proprio (sessione, chiave del cron o ruolo) | — | — | — |
| `archivio.php` | `admin/archivio.php` | pagina | chi gestisce attività dell'area | segue l'area aperta | — | — |
| `area_personale.php` | `admin/area_personale.php` | pagina | amministratori | Sito pubblico | Area personale (riquadri) | — |
| `aree.php` | `admin/aree.php` | pagina | chi ha almeno un modulo o un'area (senza: rimando all'area personale) | Sito pubblico | Aree di tutti i moduli | — |
| `assistenza.php` | `admin/assistenza.php` | pagina | Assistenza | Didattica | Assistenza | — |
| `assistenza_ajax.php` | `admin/assistenza_ajax.php` | fuori | controllo proprio (sessione, chiave del cron o ruolo) | — | — | — |
| `audit_log.php` | `admin/audit_log.php` | pagina | amministratori | Sistema | Sistema e registri | — |
| `cerca.php` | `admin/cerca.php` | pagina | chi ha almeno un modulo o un'area (senza: rimando all'area personale) | ingresso del pannello | — | — |
| `cerca_personale.php` | `admin/cerca_personale.php` | fuori | controllo proprio (sessione, chiave del cron o ruolo) | — | — | — |
| `comunicazioni.php` | `admin/comunicazioni.php` | pagina | amministratori | Sito pubblico | Notizie e pagine | — |
| `contatti.php` | `admin/contatti.php` | pagina | amministratori | Sito pubblico | Contatti per gli studenti | `admin/contatti` |
| `convenzione_file.php` | `admin/convenzione_file.php` | pagina | convenzioni FSL | Formazione Scuola Lavoro | Scuole e convenzioni | — |
| `convenzioni_tesi.php` | `admin/convenzioni_tesi.php` | pagina | convenzioni di tesi | Didattica | Convenzioni di tesi | — |
| `cron_backup.php` | `admin/cron_backup.php` | fuori | controllo proprio (sessione, chiave del cron o ruolo) | — | — | — |
| `cron_reminders.php` | `admin/cron_reminders.php` | fuori | controllo proprio (sessione, chiave del cron o ruolo) | — | — | — |
| `dashboard.php` | `admin/dashboard.php` | pagina | chi ha almeno un modulo o un'area (senza: rimando all'area personale) | ingresso del pannello | — | — |
| `didacticus.php` | `admin/didacticus.php` | pagina | Assistenza | Didattica | Domande e risposte | — |
| `didattica.php` | `admin/didattica.php` | pagina | Didattica | Didattica | Cosa devo fare; Pratiche degli studenti; Sedute e verbali; Moduli e documenti; Statistiche; Ricevimento | — |
| `didattica_accessi.php` | — | scheda | come la pagina che la include (`didattica.php`, `didattica_sedute.php`); da sola dà 403 | Didattica | — | — |
| `didattica_archivio.php` | — | scheda | come la pagina che la include (`didattica.php`, `didattica_sedute.php`); da sola dà 403 | Didattica | — | — |
| `didattica_home.php` | — | scheda | come la pagina che la include (`didattica.php`); da sola dà 403 | Didattica | — | — |
| `didattica_moduli.php` | — | scheda | come la pagina che la include (`didattica.php`); da sola dà 403 | Didattica | — | — |
| `didattica_pratiche.php` | — | scheda | come la pagina che la include (`didattica.php`); da sola dà 403 | Didattica | — | — |
| `didattica_sedute.php` | — | scheda | come la pagina che la include (`didattica.php`); da sola dà 403 | Didattica | — | — |
| `didattica_statistiche.php` | — | scheda | come la pagina che la include (`didattica.php`); da sola dà 403 | Didattica | — | — |
| `didattica_ufficio.php` | — | scheda | come la pagina che la include (`didattica.php`); da sola dà 403 | Didattica | — | — |
| `editor_upload.php` | `admin/editor_upload.php` | fuori | controllo proprio (sessione, chiave del cron o ruolo) | — | — | — |
| `email.php` | `admin/email.php` | pagina | amministratori · tutorato · tutta la Didattica · convenzioni FSL · convenzioni di tesi | Sistema | Testi delle email | — |
| `esporta_tabella.php` | `admin/esporta_tabella.php` | fuori | controllo proprio (sessione, chiave del cron o ruolo) | — | — | — |
| `eventi.php` | `admin/eventi.php` | pagina | chi gestisce attività dell'area | segue l'area aperta | — | — |
| `form_builder.php` | `admin/form_builder.php` | pagina | chi gestisce attività dell'area | segue l'area aperta | — | — |
| `forms.php` | `admin/forms.php` | pagina | MyForms | MyForms | MyForms | — |
| `forms_allegato.php` | `admin/forms_allegato.php` | pagina | MyForms | — | — | — |
| `forms_risposte.php` | `admin/forms_risposte.php` | pagina | MyForms | MyForms | MyForms | — |
| `fsl.php` | `admin/fsl.php` | pagina | convenzioni FSL | Formazione Scuola Lavoro | Numeri e report | `admin/fsl` |
| `fsl_scuole.php` | `admin/fsl_scuole.php` | pagina | convenzioni FSL | Formazione Scuola Lavoro | Scuole e convenzioni | `admin/fsl_scuole` |
| `impostazioni_area.php` | `admin/impostazioni_area.php` | pagina | gestori di tutta l'area | segue l'area aperta | — | — |
| `index.php` | `admin/index.php` | pagina | chi ha almeno un modulo o un'area (senza: rimando all'area personale) | ingresso del pannello | — | — |
| `inizio.php` | `admin/inizio.php` | pagina | chi ha almeno un modulo o un'area (senza: rimando all'area personale) | ingresso del pannello | — | — |
| `iscritti.php` | `admin/iscritti.php` | pagina | chi gestisce attività dell'area | segue l'area aperta | — | — |
| `log_accessi.php` | `admin/log_accessi.php` | pagina | amministratori | Sistema | Sistema e registri | — |
| `menu.php` | `admin/menu.php` | pagina | amministratori | Sito pubblico | Menu | — |
| `messaggi.php` | `admin/messaggi.php` | pagina | chi ha una casella di messaggi · convenzioni FSL · Assistenza | — | — | — |
| `nuova_area.php` | `admin/nuova_area.php` | pagina | amministratori | Sito pubblico | Aree di tutti i moduli | — |
| `partecipanti.php` | `admin/partecipanti.php` | pagina | chi gestisce attività dell'area | segue l'area aperta | — | — |
| `parziali/chi_compila_cosa.php` | — | scheda | come la pagina che la include (`amministrazione_campi.php`); da sola dà 403 | Amministrazione | — | — |
| `parziali/costruttore_campi.php` | — | scheda | come la pagina che la include (`amministrazione_campi.php`, `forms.php`); da sola dà 403 | Amministrazione, MyForms | — | — |
| `parziali/da_compilare_schede.php` | — | scheda | come la pagina che la include (`pubblicazioni.php`); da sola dà 403 | Amministrazione | — | — |
| `parziali/impostazioni_pubblicazioni_email.php` | — | scheda | come la pagina che la include (`amministrazione_impostazioni.php`, `pubblicazioni.php`); da sola dà 403 | Amministrazione | — | — |
| `parziali/modulo_documento_campi.php` | — | scheda | come la pagina che la include (`amministrazione_modulistica.php`, `didattica_moduli.php`); da sola dà 403 | Amministrazione, Didattica | — | — |
| `prenotazioni_risorse.php` | `admin/prenotazioni_risorse.php` | pagina | gestori di tutta l'area | segue l'area aperta | — | — |
| `progetti.php` | `admin/progetti.php` | pagina | chi gestisce attività dell'area | segue l'area aperta | — | — |
| `protocollo.php` | `admin/protocollo.php` | pagina | amministratori | Sistema | Protocollo (Titulus) | — |
| `pubblicazioni.php` | `admin/pubblicazioni.php` | pagina | richieste di pubblicazione | Amministrazione | Richieste di pubblicazione; Da compilare | — |
| `push_ajax.php` | `admin/push_ajax.php` | fuori | controllo proprio (sessione, chiave del cron o ruolo) | — | — | — |
| `report_ambiti.php` | `admin/report_ambiti.php` | pagina | modulo Eventi e seminari | Eventi e seminari | Report per ambito | — |
| `risorse.php` | `admin/risorse.php` | pagina | gestori di tutta l'area | segue l'area aperta | — | — |
| `scanner.php` | `admin/scanner.php` | pagina | chi gestisce attività dell'area | segue l'area aperta | — | — |
| `scuole.php` | `admin/scuole.php` | pagina | scuole FSL | Formazione Scuola Lavoro | Anagrafe delle scuole | — |
| `simula.php` | `admin/simula.php` | pagina | amministratori | Sistema | Entra come… | — |
| `sistema.php` | `admin/sistema.php` | pagina | amministratori | Sistema | Sistema e registri | — |
| `sondaggi.php` | `admin/sondaggi.php` | pagina | chi gestisce attività dell'area | segue l'area aperta | — | — |
| `sos_aule.php` | `admin/sos_aule.php` | pagina | Amministrazione | Amministrazione | SOS aule | `admin/sos_aule` |
| `sos_cartello.php` | `admin/sos_cartello.php` | pagina | Amministrazione | Amministrazione | SOS aule | `admin/sos_cartello` |
| `sostituzioni.php` | `admin/sostituzioni.php` | pagina | tutta la Didattica | Didattica | Sostituzioni | — |
| `stampa_badge.php` | `admin/stampa_badge.php` | pagina | chi gestisce attività dell'area | segue l'area aperta | — | — |
| `stampa_lista_iscritti.php` | `admin/stampa_lista_iscritti.php` | pagina | chi gestisce attività dell'area | segue l'area aperta | — | — |
| `stampa_qr_aula.php` | `admin/stampa_qr_aula.php` | pagina | qualunque utente con l'accesso (controlla il singolo dato) | — | — | — |
| `statistiche.php` | `admin/statistiche.php` | pagina | chi gestisce attività dell'area | segue l'area aperta | — | — |
| `testata.php` | `admin/testata.php` | pagina | amministratori | Sito pubblico | Testata e home | — |
| `tirocini.php` | `admin/tirocini.php` | pagina | amministratori · tutta la Didattica | Didattica | Tirocini | — |
| `tirocini_motorie.php` | `admin/tirocini_motorie.php` | pagina | tirocini Scienze motorie | Didattica | Tirocini Scienze motorie | — |
| `tutorato.php` | `admin/tutorato.php` | pagina | tutorato | Didattica | Tutorato · lettere di incarico | — |
| `uffici.php` | `admin/uffici.php` | pagina | amministratori · tutta la Didattica | Persone e permessi | Uffici e persone | — |
| `uffici_ateneo.php` | `admin/uffici_ateneo.php` | fuori | controllo proprio (sessione, chiave del cron o ruolo) | Persone e permessi | Uffici e persone | — |
| `utenti.php` | `admin/utenti.php` | pagina | amministratori | Persone e permessi | Utenti e abilitazioni | — |

## 4. Le rotte del Nucleo

Le pagine servite da `App\Http\Nucleo` (tabella `App\Portale\Instradamento\Rotte`). Il Nucleo chiede l'accesso a `PermessiPagine` PRIMA di costruire la pagina: la rotta non porta permessi, e la colonna «Chi la apre» qui sotto è letta dalla mappa. Il file vecchio resta dov'è, come guscio di due righe, quindi il suo indirizzo non muore.

| Rotta | Indirizzi | Pagina (classe) | File di oggi | Chi la apre |
|---|---|---|---|---|
| `contatti` | `contatti`, `contatti.php` | `App\Portale\Pagine\PaginaContatti` | `contatti.php` | pubblica (le regole proprie della pagina restano dentro la pagina) |
| `approva_pubblicazioni` | `approva_pubblicazioni`, `approva_pubblicazioni.php` | `App\Portale\Pagine\PaginaApprovaPubblicazioni` | `approva_pubblicazioni.php` | pubblica (le regole proprie della pagina restano dentro la pagina) |
| `sos` | `sos`, `sos.php` | `App\Portale\Pagine\PaginaSos` | `sos.php` | pubblica (le regole proprie della pagina restano dentro la pagina) |
| `sos_interventi` | `sos_interventi`, `sos_interventi.php` | `App\Portale\Pagine\PaginaSosInterventi` | `sos_interventi.php` | pubblica (le regole proprie della pagina restano dentro la pagina) |
| `admin/contatti` | `admin/contatti`, `admin/contatti.php` | `App\Portale\Pagine\PaginaContattiPannello` | `admin/contatti.php` | amministratori |
| `admin/fsl` | `admin/fsl`, `admin/fsl.php` | `App\Portale\Pagine\PaginaNumeriFsl` | `admin/fsl.php` | convenzioni FSL |
| `admin/fsl_scuole` | `admin/fsl_scuole`, `admin/fsl_scuole.php` | `App\Portale\Pagine\PaginaScuoleFsl` | `admin/fsl_scuole.php` | convenzioni FSL |
| `admin/sos_aule` | `admin/sos_aule`, `admin/sos_aule.php` | `App\Portale\Pagine\PaginaSosPannello` | `admin/sos_aule.php` | Amministrazione |
| `admin/sos_cartello` | `admin/sos_cartello`, `admin/sos_cartello.php` | `App\Portale\Pagine\PaginaSosCartello` | `admin/sos_cartello.php` | Amministrazione |
| `stampa_ricevuta` | `stampa_ricevuta`, `stampa_ricevuta.php` | `App\Portale\Pagine\PaginaStampaRicevuta` | `stampa_ricevuta.php` | pubblica (le regole proprie della pagina restano dentro la pagina) |

## 5. SOS aule: chi entra dove

| Pagina | Chi entra |
|---|---|
| `sos.php` | Senza accesso: login, poi si torna all'aula del QR. Invia chi ha un profilo ammesso dalle impostazioni, con il modulo acceso. Una segnalazione la vede solo chi l'ha inviata e chi la gestisce (referente, studente a cui è affidata). |
| `sos_interventi.php` | Referenti delle aule (solo le loro), responsabili di riserva (tutte), studenti part-time in servizio (solo quelle affidate a loro). |
| `admin/sos_aule.php` (anche foto e scarichi del registro) | Tutto il modulo Amministrazione. |
| `admin/sos_cartello.php` | Tutto il modulo Amministrazione. |
