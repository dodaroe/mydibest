# SOS aule — stato dei lavori

## Passo S1 — tabelle, costanti, repository, servizio con l'iter (28/11/2026)

Nessuna pagina, nessuna email, nessuna push. Niente commit.

### File toccati

| File | Cosa fa |
|---|---|
| `src/Core/Schema/Definizioni.php` | v147: tabelle `sos_aule`, `sos_referenti`, `sos_studenti`, `sos_tipi`, `sos_esiti`, `sos_segnalazioni`, `sos_cronologia`; colonne `sos_*` di `impostazioni_sistema` |
| `src/Core/Schema/Migrazioni.php` | `VERSIONE = 147`, commento della 147, inserimento di tipi ed esiti di partenza (solo se la tabella è vuota) |
| `src/SosAule/Costanti.php` | stati con etichetta e colore (classi di `.ui-stato`), stati aperti, urgenze, **tabella delle transizioni** e `ammessa(da, a, ruolo)`, limiti, cartella foto |
| `src/SosAule/VociDiPartenza.php` | i 9 tipi di problema e i 5 esiti «non risolto» |
| `src/SosAule/AuleRepository.php` | aule, `generaCodice`, `rigeneraCodice`, `perCodice` (dice se il codice è vecchio) |
| `src/SosAule/TipiRepository.php` | tipi ed esiti: elenco, aggiungi, rinomina, ordina, accendi/spegni, elimina (una voce usata non si cancella) |
| `src/SosAule/SegnalazioniRepository.php` | inserimento, numero progressivo, l'UPDATE del passaggio (`passa`), elenchi (aula, docente, referente, studente, registro con filtri), cronologia |
| `src/SosAule/PersoneSos.php` | referenti, riserva, `eReferente` (per `persona_id`), `studenteDi` (email verificata + attivo + dal/al), destinatari, avvisi, aggiungi/togli |
| `src/SosAule/ImpostazioniSos.php` | legge e salva le colonne `sos_*`; `aperto()` falso anche se nessuno riceverebbe |
| `src/SosAule/CampiSos.php` | campi fissi con le personalizzazioni e campi aggiunti (`CostruttoreCampi`), tutto in `sos_campi` |
| `src/SosAule/RegoleSos.php` | `valida(dati, file, campi)`: errori per campo; i campi aggiunti con `LetturaRisposte` |
| `src/SosAule/FotoSos.php` | foto in `uploads/sos/` (bloccata al web), con i controlli di `AllegatiPubblicazioni` |
| `src/SosAule/EsitoInvio.php` | `Esito` + id della segnalazione + errori per campo |
| `src/SosAule/ServizioSos.php` | `puoInviare`, `invia`, `aggiungiNota` e tutti i passaggi di stato |
| `tests/Unit/SosAule/RegoleSosTest.php` | transizioni, descrizione 500/501, «Altro», studente senza database |
| `tests/Integration/SosAule/ServizioSosTest.php` | invio, «due prendono insieme», il giro completo |

`.gitignore` risultava già modificato prima di questo passo: non l'ho toccato.

### Scelte decise con te (non erano nel piano)

1. **Da «Presa in carico»** il referente può: risolta, inoltra, chiudi senza soluzione, affida a uno studente.
2. **Da «Affidata» e «Intervento in corso»** il referente può: riprenderla, passarla a un altro studente, segnarla risolta.
3. **«Inoltrata» resta aperta**: il referente la segna risolta, la chiude senza soluzione o la riprende.
4. **«Altro»**: colonna `chiede_testo` in `sos_tipi` e `sos_esiti`, accesa solo su «Altro»; vale anche se la voce viene rinominata.

**I testi dei passi S3 e S5 vanno allineati ai punti 1-3** (S5 oggi nomina solo «Risolta» sulle prese in carico).

### Scelte mie (da controllare)

**Iter**
- Prima si controlla CHI è (referente, studente a cui è affidata, chi l'ha inviata), poi parte il solo UPDATE condizionato allo stato; la riga è bloccata nella stessa transazione, così la cronologia ha lo stato di partenza esatto.
- Chi è sia referente sia studente agisce da referente.
- Una nota non è un passaggio: in cronologia ha `da_stato` e `a_stato` vuoti. Si può scrivere solo su una segnalazione aperta (massimo 500 caratteri).
- «Riprendi» e «Inoltra» tolgono lo studente dalla segnalazione. «Affida» segna anche `presa_da`/`presa_il` (chi ce l'ha in mano); `presa_il` resta l'ora della prima presa.
- Minuti dell'intervento: 0-1440, scritti in colonna e anche nel testo della cronologia.
- `invia` rifiuta un'aula per cui nessuno riceverebbe la segnalazione, anche se il servizio è aperto.
- Codice `SOS-AAAA-NNNN`: se due invii scelgono lo stesso numero si riprova (3 volte).

**Form**
- Urgenza: sempre visibile e obbligatoria. Descrizione: sempre visibile (con «Altro» è obbligatoria); l'obbligo per gli altri tipi lo decide l'admin.
- Telefono: massimo 30 caratteri, con almeno una cifra.
- Campi aggiunti: gli stessi tipi delle richieste di pubblicazione, massimo 15; uno con risposte non si elimina e non cambia tipo, si nasconde.

**Persone, impostazioni, elenchi**
- Togliere uno studente che ha segnalazioni lo spegne invece di cancellarlo.
- L'ultima voce accesa di tipi/esiti non si spegne.
- Il servizio non si può accendere se nessuno riceverebbe le segnalazioni.
- Testo introduttivo salvato come testo semplice. Limiti: promemoria 0-1440 minuti, foto 1-1095 giorni.
- Codice delle aule senza 0, O, 1, I.
- `AuleRepository::aggiungi`/`modifica` aggiunti perché servono a S2.

### Prove lanciate

| File | Esito |
|---|---|
| `tests/Unit/SosAule/RegoleSosTest.php` | OK, 8 prove, 12 controlli |
| `tests/Integration/SosAule/ServizioSosTest.php` (MariaDB accesa, poi spenta) | OK, 3 prove, 32 controlli |

`php -l` pulito e nessun BOM su tutti i file PHP toccati.

«Due prendono insieme» è provata con due chiamate una dopo l'altra (passano dallo stesso UPDATE condizionato), non con due connessioni in parallelo.

### Cosa NON ho lanciato né provato

- La suite intera, `esegui.php`, `apache_nucleo.sh` (previsti a S8).
- La migrazione vera (`Migrazioni::aggiorna`) su un database: le prove usano le `CREATE`, le `ALTER` e le voci di partenza, ma non la sequenza completa.
- Senza prove: `inoltra`, `chiudiNonRisolta`, `annulla`, `riapri`, `aggiungiNota`, il salvataggio della foto, i campi aggiunti, il salvataggio di impostazioni, campi, tipi ed esiti, la rigenerazione del codice, la riserva e gli avvisi delle persone, gli elenchi del repository.

### Dubbi (da decidere prima del passo indicato)

1. **Destinatari (prima di S4).** La sezione 0bis dice che un'aula con referenti «tutti senza account» va alla riserva; il testo di S1 dice «referenti con email, o la riserva se non ce ne sono». Ho seguito S1: chi ha l'email ma non è mai entrato riceve l'avviso, non può prendere in carico, e la riserva non viene avvisata.
2. **Minuti degli studenti (prima di S6).** `minuti_intervento` è una colonna sola: con due interventi resta l'ultimo valore, e «Riprendi» toglie lo studente. Per il totale per studente e per mese (D10) conviene aggiungere `studente_id` e `minuti` a `sos_cronologia` (resta v147).
3. **Copia di prova (prima di S8).** `strumenti/crea_ambiente_prova.sh` copierebbe `uploads/sos/` e non rende anonime le tabelle `sos_*`.
4. Il limite di invii (5 in 10 minuti) e l'avviso dei doppioni sono della pagina (S4); `aperteDellAula` è già pronta.

## Passo S2 — configurazione dal pannello e cartello (28/11/2026)

Niente commit. La versione dello schema resta 147.

### Correzioni di S1

- **C1.** `ServizioSos::invia` copia nella segnalazione solo l'email verificata (`UtenteRepository::emailVerificata`), o vuoto.
- **C2.** Tolto `minuti_intervento` da `sos_segnalazioni`; `sos_cronologia` ha ora `studente_id` e `minuti`. `risolta()` e `nonRisolta()` li scrivono nella propria riga, quindi con due interventi contano entrambi. `ServizioSosTest` controlla la riga del «non risolto». Chiude il dubbio 2 qui sopra.

### File toccati

| File | Cosa fa |
|---|---|
| `admin/sos_aule.php`, `admin/sos_cartello.php` | i due gusci |
| `src/Portale/Pagine/PaginaSosPannello.php` | permesso (`puoTutto`), scelta della scheda, dati, QR |
| `src/Portale/Pagine/AzioniSosPannello.php` | tutti gli invii del pannello, con AuditLog |
| `src/Portale/Pagine/PaginaSosCartello.php` | `?aula=ID` o `?tutte=1` (solo le attive) |
| `viste/pannello/sos_aule.php` e `_sos_*.php` (9) | intestazione, schede e loro pezzi; «Registro» dice solo che arriva con S6 |
| `viste/pannello/sos_cartello.php` | il cartello A4 |
| `src/Portale/Instradamento/Rotte.php`, `src/Portale/Pannello/PermessiPagine.php`, `MenuModuli.php`, `inc/sezioni.php`, `admin/admin_header.php` | rotte, permesso `puo_amministrazione`, voce «SOS aule» |
| `assets/css/portale-ui.css` | classi `.ui-sos-*`, `.ui-cartello*` e regole di stampa |
| `assets/js/conferma.js` | nuovo `data-conferma-pulsante` (testo del pulsante di conferma) |
| `src/SosAule/AuleRepository.php` | `conConteggi`, `prossimoOrdine`, `sposta`, `elimina` |
| `src/SosAule/PersoneSos.php` | `emailDiAteneo`, `modificaStudente`, avviso «fuori periodo» |
| `src/SosAule/TipiRepository.php`, `Costanti.php`, `ImpostazioniSos.php` | «chiede testo» modificabile; 23 icone; controllo e lettura degli orari (`fasce()`, pronta per S4) |
| `documentazione/inventario_accessi.md` | rigenerato (`strumenti/mappa_accessi.php`) |
| `tests/Integration/Portale/PaginaSosBase.php`, `PaginaSosPannelloTest.php`, `PaginaSosCartelloTest.php` | le prove |
| `documentazione/schermate_sos_S2/` | `aule.png`, `cartello.png` (dati finti) |

### Scelte mie (da controllare)

- **Orari di presidio:** una fascia per riga, «giorni ore» (`lun-ven 8-19`, `sab 8:30-13`); un giorno o un intervallo; vuoto = sempre. Si controllano al salvataggio.
- **Campi aggiunti:** la parte a schermo è disegnata nella vista di SOS con gli stessi nomi `sx_*`; a leggerli resta `CostruttoreCampi`. Il parziale `admin/parziali/costruttore_campi.php` non si può includere da una pagina del nucleo (la sua guardia vuole la testata già caricata).
- **Cartello:** sta nell'impaginazione del pannello (lo chiede `PermessiPagineTest`); menu e testata spariscono in stampa.
- **Email di uno studente fuori `@studenti.unical.it`:** la casella di conferma è richiesta solo quando l'email cambia.
- Cancellare un'aula cancella anche i suoi referenti. `risorsa_id` non compare nel pannello.
- Ogni modifica va in AuditLog («SOS aule: …»), non solo aggiunte e tolte di persone.

### Prove lanciate (un file alla volta)

| File | Esito |
|---|---|
| `tests/Integration/Portale/PaginaSosPannelloTest.php` | OK, 3 prove, 9 controlli |
| `tests/Integration/Portale/PaginaSosCartelloTest.php` | OK, 1 prova, 3 controlli |
| `tests/Unit/SosAule/RegoleSosTest.php` | OK, 8 prove, 12 controlli |
| `tests/Integration/SosAule/ServizioSosTest.php` | OK, 3 prove, 33 controlli |

`php -l` pulito e nessun BOM sui 40 file PHP nuovi o modificati. MariaDB spenta alla fine.

Nel browser, su una copia usa e getta con un database temporaneo poi cancellato: referente aggiunto dalla ricerca in anagrafe, campo aggiunto e salvato, «scrivi RIGENERA» con codice cambiato, nessun errore in console; le cinque schede a 375 px senza scorrimento orizzontale; tre aule = tre fogli in PDF.

### Cosa NON ho lanciato né provato

- La suite intera, `esegui.php`, `apache_nucleo.sh`.
- `MenuModuliTest`, `PermessiPagineTest`, `RotteTest`, `VistaTest`, `strumenti/avanzamento_nucleo.php`: toccano ciò che ho modificato ma non erano nell'elenco (`mappa_accessi.php --controlla` è a posto).
- Nel browser: tema scuro, studenti, impostazioni, tipi ed esiti, cancellazione di un'aula, una stampa su carta, la lettura del QR con un telefono (la pagina `sos.php` arriva in S4).

### Dubbi

- Un database locale che avesse già le tabelle della prima 147 non riceverebbe le due colonne nuove (stessa versione, `CREATE IF NOT EXISTS`): vanno cancellate le `sos_*` o aggiunte a mano. Su un database vuoto le tabelle nascono giuste.
- Restano i dubbi 1 (prima di S4) e 3 (prima di S8) di S1.
- I testi di S3 e S5 nel piano sono ancora da allineare alle decisioni 1-3 di S1.

## Passo S3 — i due schizzi delle pagine da telefono (28/11/2026)

Niente commit, nessun codice PHP del portale. **Gli schizzi vanno approvati prima di S4.**

### Correzione di S2

- **C3.** `tests/Unit/Http/VistaTest.php` ha ora i dati di prova di `pannello/sos_aule` e `pannello/sos_cartello` (`datiSosAule`): le cinque schede, piene e con gli elenchi vuoti, con tutti i parziali `_sos_*` resi davvero.

### File

| File | Cosa fa |
|---|---|
| `documentazione/schizzo_sos_docente.html` | `sos.php`: a) dal QR · b) già segnalata · c) fuori orario · d1 manca il tipo · d2 «Altro» senza descrizione · e) inviata · f) risolta |
| `documentazione/schizzo_sos_interventi.html` | `sos_interventi.php`: a) referente, una card per stato · b1) studente · b2) «Non risolto» aperto · c) chi arriva secondo |

Si aprono con un doppio clic in Chrome o Edge (il pannello dell'app li mostra senza stili). Ogni schermata è due volte (375 px e tutta larghezza), con tema chiaro/scuro.

### Scelte mie (da controllare)

- Gli schizzi usano gli stili delle pagine pubbliche (Bootstrap Italia, Titillium), non quelli del pannello. La testata è solo un segnaposto.
- Le classi nuove `ui-sos-*` stanno nel `<style>` degli schizzi (blocco «PROPOSTE»): in S4/S5 vanno in `portale-ui.css`, con `@media` al posto di `@container`. `portale-ui.css` non è stato toccato. Riusate: `ui-percorso`, `ui-stato`, `ui-sos-tipi`/`ui-sos-tipo`, `ui-comp-errore`, `ap-grande`.
- I due errori chiesti non possono comparire insieme (senza tipo non c'è «Altro»): sono due schermate, d1 e d2.
- «Le mie segnalazioni» è anche sotto il form (come dice S4 del piano).
- Dopo un errore: avviso in alto e «Se avevi scelto una foto, sceglila di nuovo» (il browser non la può rimettere).
- Etichette brevi dell'urgenza sulle card: «Lezione ferma», «Serve aiuto», «Prossime lezioni» (le ultime due sono mie).
- Nella vista del referente le cinque card stanno tutte insieme; i numeri delle schede (3, 1, 5) sono quelli chiesti, non il conto delle card.

### Scelte da far decidere

1. **Ordine dei campi.** Nello schizzo: aula, docente, tipo, urgenza, descrizione, foto, cellulare. La 0ter e l'anteprima del pannello mettono docente e cellulare prima. Proposta: tenere lo schizzo e allineare l'anteprima in S4.
2. **Schede di chi interviene.** Proposta: «Da fare» = In attesa, Presa in carico, Non risolta; «In corso» = Affidata, Intervento in corso, Inoltrata.
3. **Studente, card «Affidata».** Nello schizzo solo «Sto andando», ma `Costanti::TRANSIZIONI` gli permette anche «Risolto» e «Non risolto» senza averlo premuto. Proposta: solo «Sto andando» sulla pagina.
4. **Minuti con «Risolto».** Nello schizzo «Risolto» apre un riquadrino (minuti facoltativi + «Conferma»): due tocchi. L'alternativa è un tocco solo, senza minuti.
5. **Barra dello stato** per Non risolta, Inoltrata, Chiusa senza soluzione, Annullata (non disegnata). Proposta: il passo in cui si è fermata prende il nome vero dello stato (rosso per «Non risolta»).

### Prova lanciata

| File | Esito |
|---|---|
| `tests/Unit/Http/VistaTest.php` | OK, 23 prove, 422 controlli |

Schizzi controllati in Chrome senza finestra a 375 e 1280 px, chiaro e scuro: nessuno scorrimento orizzontale, nessun errore in console; immagini guardate a campione. Non provati su un telefono vero.

### Cosa NON ho lanciato

- La suite intera, `esegui.php`, `apache_nucleo.sh`.

### Dubbi (prima di S4)

- **«Aggiungi una nota a quella»**: oggi `ServizioSos::aggiungiNota` lascia scrivere solo chi ha inviato la segnalazione, il referente e lo studente. Un **altro** docente nella stessa aula riceverebbe «Non puoi scrivere su questa segnalazione». O si permette la nota a chi può inviare, o a lui il pulsante non si mostra.
- **«Invia comunque»**: l'avviso compare prima di compilare. Proposta: toglie l'avviso e si compila il form sotto.
- Il pulsante qui è «Invia la richiesta di aiuto», l'anteprima del pannello dice «Invia la segnalazione»: in S4 va scelto un testo solo.

## Passo S4 — la pagina del docente (`sos.php`) e gli avvisi (28/11/2026)

Schema invariato (versione 147, nessuna tabella o colonna). Nessun commit.

### Correzioni

- **C4.** Da «Affidata» lo studente va solo a «Intervento in corso»; «Risolta» da «Affidata» resta al referente (tolta anche «Non risolta» da «Affidata», che era solo dello studente). Tre casi nuovi in `RegoleSosTest`; il giro di `ServizioSosTest` non passava di lì.
- **C5.** `Costanti::COLLEGA`: `ServizioSos::aggiungiNota` accetta un collega (può inviare, nessun ruolo sulla segnalazione) solo su una segnalazione aperta e solo con l'aula scelta nel suo form. `ruoli()` continua a non dargli niente.
- **C6.** Anteprima della scheda «Modulo» nell'ordine dello schizzo; pulsante «Invia la richiesta di aiuto».

### File

| File | Cosa fa |
|---|---|
| `sos.php`, `Rotte.php` | guscio e rotta `sos` |
| `src/Portale/Pagine/PaginaSos.php` | accesso, form, doppioni, limite di invii, invio, pagina della segnalazione, frammento, foto protetta, nota / annulla / «Non è risolto» |
| `viste/pubblico/sos.php`, `_sos_form.php`, `_sos_stato.php`, `_sos_mie.php` | le viste; l'aggiornamento ogni 20 secondi sta in `sos.php` (script col nonce) |
| `src/SosAule/NotificheSos.php` | `nuova`, `nota`, `chiusa`: email e notifica, mai eccezioni |
| `src/Email/CatalogoSos.php`, `CatalogoEmail.php` | `sos_nuova`, `sos_nota`, `sos_chiusa`, modificabili dalla pagina «Email» |
| `inc/area/segnalazioni_sos.php`, `CatalogoRiquadri.php` | tessera «Le mie segnalazioni SOS» con il numero di aperte |
| `src/SosAule/ServizioSos.php`, `Costanti.php` | C4, C5, `insegnamentiDelDipartimento()`; l'insegnamento vale sia da `insegnamenti` sia dal catalogo di Ateneo |
| `src/SosAule/ImpostazioniSos.php` | `fuoriOrario()` |
| `inc/ui.php`, `assets/css/portale-ui.css` | stato grigio `fermo` in `ui_passi`; classi `.ui-sos-*` della pagina |
| `PaginaSosPannello.php`, `viste/pannello/_sos_modulo.php` | C6 |
| `tests/…` | `PaginaSosTest`, `NotificheSosTest`, dati in `VistaTest`, casi in `RegoleSosTest`; `PaginaSosBase` copia anche le viste pubbliche |
| `documentazione/schermate_sos_S4/` | `form.png`, `dopo_invio.png` (375 px, dati finti, tema scuro) |

### Scelte mie (da controllare)

- **Insegnamento:** campo in sola lettura + finestra del catalogo (`campi-pratica.js`); senza JavaScript un elenco degli insegnamenti del Dipartimento. Si salva solo l'id. Il campo resta nascosto finché non lo accendi dal pannello.
- **Cellulare:** precompilato solo con il numero che la persona ha scritto nel proprio profilo.
- Il «testo introduttivo» del pannello sostituisce il sottotitolo.
- **Limite:** contano solo gli invii riusciti.
- **«Invia comunque»** è un invio del form che toglie solo l'avviso (va anche senza JavaScript, il testo resta, la foto va riscelta). Se l'aula è scelta dall'elenco e ha già una segnalazione aperta, il primo invio mostra l'avviso senza inviare.
- Chi ha un ruolo su una segnalazione la vede anche a servizio spento; «Le mie segnalazioni» compare anche con servizio spento o persona non ammessa.
- Il collega, dopo la nota, torna a `sos.php?aula=…`.
- L'aggiornamento automatico gira solo finché la segnalazione è aperta e non ricarica mentre si scrive una nota.
- La cronologia dice chi ha agito anche nel passaggio «affidata a».
- `sos_chiusa` non manda la notifica sul telefono (non era chiesta).
- Nelle email `{{aula}}` è «aula 5C2», e la riga del corpo si chiama «Dove».

### Prove lanciate (un file alla volta)

| File | Esito |
|---|---|
| `tests/Unit/SosAule/RegoleSosTest.php` | OK, 11 prove, 15 controlli |
| `tests/Integration/SosAule/ServizioSosTest.php` | OK, 3 prove, 33 controlli |
| `tests/Unit/Http/VistaTest.php` | OK, 23 prove, 461 controlli |
| `tests/Integration/Portale/PaginaSosTest.php` | OK, 4 prove, 22 controlli |
| `tests/Integration/SosAule/NotificheSosTest.php` | OK, 1 prova, 8 controlli |

`NotificheSosTest` al primo giro era rossa per un difetto vero: descrizione e nota non entravano nelle email (chiavi dei dati scritte con il «!»). Corretto.

`php -l` pulito e nessun BOM sui file PHP nuovi o modificati. MariaDB spenta.

Nel browser, su una copia usa e getta con database temporaneo poi cancellati, a 375 px: nessuno scorrimento orizzontale, «Cambia» apre la scelta dell'aula, frammento di aggiornamento 200, conferma di «Annulla», avviso di doppione con il campo nota, finestra dell'insegnamento, tessera nell'area personale. In console solo l'errore di registrazione del service worker, che esce anche sulla pagina Privacy.

### Cosa NON ho lanciato né provato

- La suite intera, `esegui.php`, `apache_nucleo.sh`, `avanzamento_nucleo.php`.
- `PaginaSosPannelloTest` e `PaginaSosCartelloTest` (usano `PaginaSosBase`, che ho toccato; C6 cambia la pagina del pannello), le altre prove su cataloghi email e `ui_passi`.
- Il ritorno dal login vero (SSO) con `?aula=`: controllato solo leggendo il codice.
- Un telefono vero, l'email e la notifica reali, la scelta di un insegnamento dal catalogo con i dati.

### Dubbi

- **«Non è risolto» e «Annulla» non avvisano nessuno:** in S4 non erano chiesti. Chi segue l'aula non lo sa finché non guarda. Va deciso in S5.
- Il pulsante «Apri» porta a `sos_interventi.php`, che nasce in S5: non aprire il servizio sul server prima di S5.
- Uno studente su «Affidata» che chiamasse «Risolto» riceve un messaggio poco chiaro («già affidata a uno studente…»); con la pagina di S5 (solo «Sto andando») non può succedere dai pulsanti.
- La finestra del catalogo insegnamenti dice «Se non lo trovi chiudi e scrivilo a mano», ma qui a mano non si può.

## Passo S5 — la pagina di chi interviene (`sos_interventi.php`) (28/11/2026)

Nessuna tabella o colonna nuova; versione sempre 147 (commento aggiornato in `Migrazioni` per il profilo studente di «Il mio lavoro»). Nessun commit.

### Correzioni

- **C7.** «Non è risolto» manda `sos_non_risolta` a chi ha in mano la segnalazione o, se nessuno, ai destinatari dell'aula; «Annulla, ho risolto da solo» manda il nuovo `sos_annullata` (email e notifica). Una email per persona, chi ha agito escluso.
- **C8.** La finestra del catalogo insegnamenti in `sos.php` dice di scriverlo nella descrizione del problema; i testi si passano con `data-se-manca` e `data-anno-vuoto`, le altre pagine restano come prima.
- **C9.** `PersoneSos::accountDellEmail`, usato da `NotificheSos`.

### File

| File | Cosa fa |
|---|---|
| `sos_interventi.php`, `Rotte.php` | guscio e rotta |
| `src/Portale/Pagine/PaginaSosInterventi.php` | accesso, controllo di `?id=` (pagina, frammento, invii), schede, card, pulsanti, avvisi dopo l'esito |
| `src/SosAule/CodaInterventi.php` | chi è ammesso e che cosa c'è nelle tre schede; lo usano la pagina e «Il mio lavoro» |
| `src/SosAule/Costanti.php`, `SegnalazioniRepository.php` | `STATI_CHIUSI`, `SCHEDE`, filtro «chiuse da», conteggio delle non risolte ferme |
| `viste/pubblico/sos_interventi.php`, `_sos_interventi_elenco.php`, `_sos_card.php` | le viste; aggiornamento ogni 30 secondi e «Attiva le notifiche» (script col nonce, `assistenza-push.js` e `push_persona_ajax.php`) |
| `assets/css/portale-ui.css` | classi dello schizzo (card, linguette, comandi) |
| `src/SosAule/NotificheSos.php`, `PersoneSos.php`, `src/Email/CatalogoSos.php` | `affidata`, `nonRisolta`, `annullata`, `staArrivando` (solo notifica al docente); testi `sos_affidata`, `sos_non_risolta`, `sos_annullata` |
| `src/Portale/Pagine/PaginaSos.php`, `viste/pubblico/_sos_form.php`, `assets/js/campi-pratica.js` | C7, C8; `frase()` e `nome()` ora pubbliche |
| `PostazioniLavoro.php`, `inc/area/_permessi_lavoro.php`, `inc/area/lavoro.php` | postazione «SOS aule» col numero di quelle da fare |
| `CatalogoRiquadri.php`, `Migrazioni.php` | «Il mio lavoro» anche al profilo studente (scelta tua), solo dove i profili non erano stati cambiati |
| `CoseDaFareAmministrazione.php`, `admin/amministrazione_home.php`, `admin/inizio.php` | per chi può tutto: non risolte ferme da più di un giorno |
| `tests/…` | `PaginaSosInterventiTest` (nuova), un caso in `NotificheSosTest`, dati in `VistaTest` |
| `documentazione/schermate_sos_S5/` | `referente.png`, `studente.png` (375 px, dati finti) |

### Scelte mie (da controllare)

- In ogni scheda prima «La lezione è ferma», poi tutte le altre dalla più vecchia (senza distinguere «aiuto» da «prossime»).
- Una segnalazione aperta con `?id=` che non sta in nessuna scheda (chiusa un altro giorno, o appena segnata «non risolta» dallo studente) compare in cima.
- Chi è sia referente sia studente vede le schede del referente.
- «Non risolto»: nessun esito già scelto (nello schizzo il primo è segnato), per non inviarne uno per sbaglio. In «Quale studente?» il primo è scelto, come nello schizzo.
- Conferma su «Risolta», «Inoltra», «Chiudi» del referente e su «La riprendo io» / «La passo» quando toglie l'intervento a uno studente.
- «Chiama» come nello schizzo; il numero si legge passando sopra il pulsante.
- «Attiva le notifiche» solo attiva (per disattivare resta il Profilo, perché l'iscrizione è la stessa degli altri avvisi); il pulsante non c'è se le notifiche sono spente sul portale.
- La postazione compare a chi è ammesso anche a servizio spento.
- La voce dell'amministratore porta a `sos_aule.php?scheda=registro`, che è vuoto fino a S6.
- La foto è quella protetta di S4 (`sos.php?segnalazione=…&foto=1`).

### Prove lanciate (un file alla volta)

| File | Esito |
|---|---|
| `tests/Integration/Portale/PaginaSosInterventiTest.php` | OK, 3 prove, 32 controlli |
| `tests/Integration/SosAule/NotificheSosTest.php` | OK, 2 prove, 15 controlli |
| `tests/Unit/SosAule/RegoleSosTest.php` | OK, 11 prove, 15 controlli |
| `tests/Integration/SosAule/ServizioSosTest.php` | OK, 3 prove, 33 controlli |
| `tests/Integration/Portale/PaginaSosTest.php` | OK, 4 prove, 22 controlli |
| `tests/Unit/Http/VistaTest.php` | OK, 23 prove, 488 controlli |

`php -l` pulito e nessun BOM sui file PHP. MariaDB spenta.

Nel browser, su una copia usa e getta con database temporaneo poi cancellati, a 375 px: nessuno scorrimento orizzontale, nessun errore in console, «Sto andando», «Non risolto», «La riprendo io», finestra di conferma di «Risolta», risposta del frammento, tessera in «Il mio lavoro» per referente e studente. Per le schermate è stata aggiunta e poi tolta una voce in `.claude/launch.json`.

### Cosa NON ho lanciato né provato

- La suite intera, `esegui.php`, `apache_nucleo.sh`, `avanzamento_nucleo.php`.
- `PostazioniLavoroTest`, le prove su `CoseDaFareAmministrazione` (`ModuloAmministrazioneTest`, `CompilazioneInBloccoTest`: ho cambiato il costruttore), cataloghi email, `PaginaSosPannelloTest`, `PaginaSosCartelloTest`.
- L'aggiornamento dei profili su un'installazione che ha già «Il mio lavoro» (visto solo il database nuovo).
- Il sostituirsi della pagina dopo 30 secondi (controllata solo la risposta del frammento), la miniatura della foto (nei dati finti non c'era), le voci nel pannello dell'amministratore.
- Email e notifiche vere, il ritorno dal login SSO con `?id=`, un telefono vero.

### Dubbi

- Chi perde un intervento (ripreso, passato a un altro, o segnato risolto dal referente) non riceve avvisi: non erano chiesti.
- Le card non mostrano l'insegnamento né i campi aggiunti dal pannello: non erano chiesti.
- Gli studenti vedono «Il mio lavoro» solo se hanno il profilo studente (ruolo Studente o matricola), come succede con l'accesso SSO.
- Se un database ha già il marcatore `cache/schema_v147.ok` (prove di S1-S4), il profilo studente si aggiunge solo cancellandolo.
- Resta da S4: uno studente su «Affidata» che forzasse «Risolto» avrebbe un messaggio poco chiaro; dai pulsanti non può succedere.

## Passo S6 — registro, promemoria e pulizia delle foto (28/11/2026)

Nessuna tabella o colonna nuova; versione sempre 147 (commento aggiornato in `Migrazioni`). Nessun commit.

### Correzioni

- **C10.** `sos_tolta` (email e notifica) allo studente che perde un intervento: ripreso, passato a un altro studente, segnato risolto. Il referente non riceve niente. Con `TRANSIZIONI` di oggi il referente non può chiudere né inoltrare una segnalazione in mano a uno studente, quindi lì non scatta; l'annullamento del docente manda già `sos_annullata`.
- **C11.** Le card mostrano insegnamento e campi aggiunti, solo se compilati (`ServizioSos::datiCompilati`).
- **C12.** **Il difetto non si è riprodotto**: su una pagina di prova con i fogli di stile, gli stili in linea e il contenitore del sito, a 375 e a 320 px, anche col testo al doppio, niente esce dalla card e la pagina non scorre. Nelle schermate di S5 l'ultima «i» è a filo del margine interno, dentro la card. Aggiunta solo una protezione in `portale-ui.css` (`min-width: 0`, a capo esplicito, 2 px a destra). Da ricontrollare dove l'hai visto.

### S6: file

| File | Cosa fa |
|---|---|
| `src/Portale/Pagine/RegistroSosPannello.php`, `viste/pannello/_sos_registro.php` | scheda «Registro»: filtri, numeri del periodo, aule malate, minuti degli studenti per mese, elenco con cronologia, scarichi Excel, PDF e CSV |
| `src/Portale/Pagine/PaginaSosPannello.php` | usa il registro e consegna gli scarichi |
| `src/SosAule/NumeriSos.php` | i numeri, dalle segnalazioni e dalla cronologia |
| `src/SosAule/SegnalazioniRepository.php`, `Costanti.php`, `ServizioSos.php` | letture del registro e del cron, soglia delle aule malate, urgenze brevi |
| `src/SosAule/CronSos.php`, `cron_background.php`, `StatoCron.php`, `Sistema/SaluteSito.php` | promemoria (una volta sola), cancellazione delle foto, riga `cron_sos` in Salute del sito |
| `src/SosAule/NotificheSos.php`, `src/Email/CatalogoSos.php` | `tolta` e `promemoria`; testi `sos_tolta`, `sos_promemoria` |
| `viste/pubblico/_sos_card.php`, `PaginaSosInterventi.php`, `portale-ui.css` | C10-C12, stile del registro |
| `admin/inizio.php`, `admin/amministrazione_home.php` | la voce «non risolte» apre il registro già filtrato |
| `tests/…` | `NumeriSosTest` e `CronSosTest` (nuove), un caso in `NotificheSosTest`, dati in `VistaTest` e `SaluteSitoTest` |

### Scelte tue

- Periodo di partenza: anno accademico (1° ottobre → oggi). Arrivando dalla voce «non risolte» il periodo è vuoto.
- «Risolte al primo intervento»: mai passate da «non risolta».
- «Aule malate»: stessa aula e stesso tipo almeno 3 volte nel periodo, annullate escluse.

### Scelte mie (da controllare)

- I numeri seguono solo le date; gli altri filtri restringono l'elenco.
- Presa in carico = prima riga «presa» o «assegnata»; chiusura = ultima chiusura di chi ora è risolta o chiusa non risolta (annullate escluse).
- I conteggi per aula e per tipo comprendono anche le annullate.
- I minuti contano nel periodo in cui l'intervento è stato segnato; il filtro studente comprende le segnalazioni su cui ha lavorato anche se poi sono passate ad altri.
- L'elenco a video si ferma a 200 righe (gli scarichi no); il PDF ha meno colonne di Excel e CSV; insegnamento e campi aggiunti stanno in una sola colonna «Altri dati».
- Un campo poi nascosto dal pannello si vede ancora se era compilato.
- L'esportazione resta nel registro delle attività.
- Il pulsante di `sos_tolta` porta all'elenco degli interventi, non alla segnalazione.
- Il promemoria parte anche a servizio spento; va ai referenti ancora in anagrafe e alla riserva; la segnalazione è segnata prima dell'invio (se l'invio fallisce non si ritenta).
- La foto si cancella e la colonna si svuota solo se il file è andato via davvero.
- In `cron_background.php` il compito è all'inizio, subito dopo l'archiviazione automatica; `cron_sos` entra anche nell'avviso notturno di salute.

### Prove lanciate (un file alla volta)

| File | Esito |
|---|---|
| `tests/Unit/SosAule/NumeriSosTest.php` | OK, 1 prova, 5 controlli |
| `tests/Integration/SosAule/CronSosTest.php` | OK, 1 prova, 7 controlli |
| `tests/Integration/SosAule/NotificheSosTest.php` | OK, 3 prove, 22 controlli |
| `tests/Integration/Portale/PaginaSosInterventiTest.php` | OK, 3 prove, 32 controlli |
| `tests/Integration/Portale/PaginaSosPannelloTest.php` | OK, 3 prove, 9 controlli |
| `tests/Unit/Http/VistaTest.php` | OK, 23 prove, 491 controlli |

`php -l` pulito e nessun BOM sui file PHP toccati. MariaDB spenta.

Nessuna di queste prove apre il registro con dati veri, perciò ho fatto un controllo usa e getta fuori dal progetto (1 prova, 29 controlli, OK): filtri, scarichi di elenco e numeri nei tre formati (apostrofo anti-formula nel CSV), card con insegnamento e campi aggiunti, cancellazione di una foto vecchia. Nel browser, sulle pagine generate da quel controllo, a 375 px: card e registro senza scorrimento orizzontale. Voce temporanea in `.claude/launch.json` aggiunta e poi tolta.

### Cosa NON ho lanciato né provato

- La suite intera, `esegui.php`, `apache_nucleo.sh`, `avanzamento_nucleo.php`.
- **`SaluteSitoTest`, che ho modificato** (non era nell'elenco).
- `ServizioSosTest`, `RegoleSosTest`, `PaginaSosTest`, `PaginaSosCartelloTest`, cataloghi email, `ModuloAmministrazioneTest` e `CompilazioneInBloccoTest` (è cambiato il collegamento della voce).
- `cron_background.php` per intero, email e notifiche vere, il registro dentro la cornice vera del pannello, il tema scuro del registro, un telefono vero.

### Dubbi

- `cron_background.php` gira ogni 15 minuti: il promemoria dei 10 minuti può arrivare dopo circa 25. Per stringere serve una riga di crontab più frequente (decisione tua).
- Dopo il caricamento `cron_sos` è rosso («mai girato») fino al primo giro del cron.
- Togliendo uno studente a cui erano state riprese tutte le segnalazioni, i suoi minuti restano ma il registro mostra «Studente tolto dall'elenco». Non corretto.
- Il registro non ha il collegamento alla foto: non era chiesto.

## Passo S7 — rifiniture: guide, mappe, accessi, Didacticus, giro HTTP (28/11/2026)

Nessuna tabella o colonna; versione sempre 147. Nessun commit.

### Correzioni

- **C13.** Nei minuti per mese (a video e nello scarico dei numeri) uno studente tolto dall'elenco ha il nome salvato in `sos_cronologia.chi`, senza «(studente)». «Studente tolto dall'elenco» resta solo se quel nome fosse vuoto. La colonna «Studente» dell'elenco non cambia: viene da `assegnata_a_studente`, e uno studente con segnalazioni assegnate si spegne, non si cancella.
- **C14.** Collegamento «Foto» sulle righe del registro che ne hanno una (`sos_aule.php?…&scheda=registro&foto=ID`), con il permesso del pannello. Intestazioni e controlli sono gli stessi di `sos.php`: `PaginaSos::scaricaFoto` è ora statica e la usano entrambe.
- **C15.** La frase sul cron è accanto ai minuti del promemoria.

### File

| File | Cosa fa |
|---|---|
| `SegnalazioniRepository.php`, `RegistroSosPannello.php`, `PaginaSosPannello.php`, `PaginaSos.php`, `viste/pannello/_sos_registro.php`, `_sos_impostazioni.php` | C13-C15 |
| `src/Portale/GuidePerRuolo.php` | quattro guide: `sos` (docente), `sos_referente`, `sos_studente`, `sos_gestione` (admin) |
| `viste/pannello/sos_aule.php` | pulsante «Guida» (`guida.php?r=sos_gestione`) |
| `strumenti/mappa/mappa_pubblica.py`, `mappa_tecnica.py`, `documentazione/mappa/pubblico.html`, `tecnica.html` | sezione «SOS aule» (13d e 13k), mappe rigenerate |
| `strumenti/mappa_accessi.php`, `documentazione/inventario_accessi.md` | nuova sezione 5 «SOS aule: chi entra dove»; inventario rigenerato (mancavano le rotte `sos` e `sos_interventi`) |
| `documentazione/didacticus_voci_sos_aule_28112026.csv` | 7 domande, formato degli altri CSV |
| `strumenti/prove/oop/pagine_sos.php` | il giro HTTP (lo include `esegui.php`, che non è cambiato) |
| `PaginaSosPannelloTest.php`, `VistaTest.php` | caso della foto; campo `foto` nei dati di prova |

### Scelte mie (da controllare)

- **Giro HTTP:** il piano dice affida → «Non risolto» → «Risolta», ma le transizioni decise non lo permettono. Il giro è: affida → **«Sto andando»** → «Non risolto» → **«La riprendo io»** → «Risolta».
- Il giro sta in `strumenti/prove/oop/`, come gli altri giri di pagina, non dentro `esegui.php`.
- «Aiuto breve per l'admin» = una guida con il pulsante nel pannello, come per MyForms.
- Le guide sono pubbliche: non scrivono il numero di riserva, dicono che è sul cartello. Sono senza schermate e senza PDF.
- La mappa pubblica non ha schermate; la data in testa alle mappe è rimasta quella di prima.
- Nel CSV la domanda sull'accensione del modulo ha «solo con accesso» come le altre.

### Prove lanciate (un file alla volta)

| File | Esito |
|---|---|
| `tests/Integration/Portale/PaginaSosPannelloTest.php` | OK, 4 prove, 10 controlli |
| `tests/Unit/Http/VistaTest.php` | OK, 23 prove, 491 controlli |
| `php strumenti/mappa_accessi.php --controlla` | aggiornato |
| `php strumenti/avanzamento_nucleo.php --controlla` | la tabella delle rotte e i gusci coincidono (uscita 0) |

`php -l` pulito e nessun BOM sui file PHP toccati. MariaDB spenta.

### Cosa NON ho lanciato né provato

- **Il giro nuovo `pagine_sos.php`** (S8), `esegui.php`, la suite intera, `apache_nucleo.sh`.
- `PaginaSosTest` (ho reso statica `scaricaFoto`) e `GuidePubblicazioniTest` (ho aggiunto guide).
- **C13 non ha una prova:** la query con `MAX(c.chi)` non è stata eseguita su dati veri.
- Nessuna pagina aperta nel browser (guide, mappe, pulsante «Guida», collegamento «Foto»).
- Il CSV non è stato importato in Didacticus.

### Dubbi

- Il piano chiede 6-8 domande e il pacchetto per il server è di S8: il CSV va importato a mano dal pannello, da scrivere nelle istruzioni.
- Restano da S6: `cron_sos` rosso fino al primo giro (istruzioni di S8) e la copia di prova di `uploads/sos/` e tabelle `sos_*` (S8, punto 0).

## Passo S8 — verifica finale, messaggio di commit, istruzioni per il server (28/11/2026)

Nessun commit, nessun pacchetto. Schema sempre 147. MariaDB e portale locale spenti alla fine.

### Suite: differenze dalla base

- **PHPUnit:** nessuna differenza. Unit 776, Integration 1222 (una sottocartella alla volta), tutte verdi, 2 saltate (le solite due sui collegamenti simbolici).
- **`esegui.php`:** 3392 superate, **2 rosse nuove**, tutte e due in `oop/area_personale.php` e dovute a S5: le postazioni di «Il mio lavoro» sono 11 e non più 10, e il riquadro lo hanno anche gli studenti. Aggiornate le due prove, non il codice, e rilanciato solo quel modulo (`PROVE_SOLO=area_personale`): 781 superate. La suite intera non è stata rilanciata.
- La «rossa nota» della base non è comparsa.
- Il giro nuovo `oop/pagine_sos.php`: 8 su 8.

### File toccati

| File | Cosa fa |
|---|---|
| `strumenti/crea_ambiente_prova.sh` | la copia non porta `uploads/sos/` (resta il `.htaccess`); con `--anonimizza` rende anonime `sos_segnalazioni`, `sos_cronologia`, `sos_studenti`, `sos_referenti` |
| `strumenti/prove/oop/area_personale.php` | le due prove allineate a S5 |
| `documentazione/commit_sos_aule.txt` | i due comandi (94 file) e il messaggio; in fondo «Non inclusi» |
| `documentazione/istruzioni_aggiornamento_sos_aule.txt` | caricamento in due tempi |

### Scelte tue

- Rese anonime anche `sos_referenti.nominativo` e `sos_segnalazioni.campi_extra_json` (non erano nell'elenco).
- I due schizzi di SOS aule restano fuori dal commit.

### Scelte mie (da controllare)

Nelle istruzioni ci sono cose che il comando non diceva o diceva diversamente:

- «Entra come…» è di **sola lettura**: la segnalazione finta si invia e si chiude con il tuo account (referente di un'aula di prova); i pulsanti dello studente si provano solo con un secondo account vero.
- Non c'è un pulsante per **cancellare** una segnalazione: le istruzioni usano `DELETE` sul database della copia.
- P2 copia anche `crea_ambiente_prova.sh` (il pacchetto non porta `strumenti/`).
- Aggiunti: creazione di `uploads/sos` con il controllo del 403, controllo delle tabelle dopo la migrazione, controllo delle chiavi delle notifiche, referenti e studenti che entrano almeno una volta, telefono di riserva scritto prima di stampare i cartelli, pubblico delle voci di Didacticus.

### Prove su S8

- L'anonimizzazione è stata eseguita su un database temporaneo (poi cancellato) con righe finte: i campi elencati cambiano o si svuotano.
- Il comando di controllo delle tabelle dà sul database locale 7 tabelle, modulo spento, 9 tipi, 5 esiti.

### Cosa NON ho lanciato né provato

- `apache_nucleo.sh` (non richiesto).
- La copia vera con `rsync` (esclusione di `uploads/sos/`) e le istruzioni sul server: solo `bash -n` sullo script.
- Il pacchetto non è stato creato.

### Cosa resta a te

1. Controllare il diff, poi i due comandi in cima a `commit_sos_aule.txt`.
2. `bash strumenti/crea_pacchetto.sh`, poi le istruzioni: `mydibest_pp`, produzione, configurazione dal pannello.
