# -*- coding: utf-8 -*-
"""Contenuto della mappa del portale per il pubblico generale: che cos'è la piattaforma e cosa permette di fare."""
from mappa_comune import fig, gal, duo, card, griglia, passi, sezione


def costruisci():
    S = []

    # 1 ----------------------------------------------------------------------------------------------
    S.append(('cose', 'Cos\'è', sezione('cose', '1', 'In due parole',
        'MyDiBEST è il <b>portale del Dipartimento</b>: un unico sito dove si prenotano eventi e aule, si presentano le pratiche degli studenti, si stipulano le convenzioni con scuole e aziende e si consultano i verbali dei consigli. Niente più moduli cartacei, email sparse e file passati di mano in mano.',
        griglia(
            card('Orientamento', '<p>Eventi, seminari e open day; progetti di Formazione Scuola Lavoro per le scuole, con convenzioni, elenco degli studenti e attestati.</p>', '🧭'),
            card('Didattica', '<p>Pratiche degli studenti (certificati, tesi, convalide, mobilità…), sedute e verbali dei consigli, tutorato e convenzioni di tesi con le aziende.</p>', '🎓'),
            card('Prenotazioni e risorse', '<p>Aule, laboratori, sportelli e auto del Dipartimento prenotabili a slot, con approvazione, lista d\'attesa e diario di bordo.</p>', '📅'),
            card('Gestione', '<p>Un pannello per chi organizza: aree, utenti, uffici, firme, controlli e statistiche. Tutto con un registro di ogni operazione.</p>', '⚙️'),
        )
        + '<p>Ogni persona vede <b>solo ciò che la riguarda</b>: lo studente le sue pratiche, il docente le sue conferme, l\'ufficio le pratiche da lavorare, il Direttore ciò che aspetta la sua firma. Il portale tiene il filo di ogni pratica e avvisa per email a ogni passaggio importante.</p>'
        + fig('map_pub_home', '<b>La home del portale.</b> Le aree del Dipartimento e le novità, con la ricerca in alto.')
    )))

    # 2 ----------------------------------------------------------------------------------------------
    S.append(('per-chi', 'Per chi è', sezione('per-chi', '2', 'Per chi è fatto',
        'Sei in sei: ognuno ha la sua porta d\'ingresso e la sua guida «Come si fa».',
        griglia(
            card('Studenti', '<p>Presentano pratiche online, seguono l\'avanzamento, scaricano certificati e attestati, prenotano aule e partecipano agli eventi.</p><span class="pill b">Credenziali di Ateneo</span>'),
            card('Scuole e docenti referenti', '<p>Prenotano le attività di Formazione Scuola Lavoro, stipulano la convenzione online, caricano l\'elenco degli studenti e ricevono gli attestati.</p><span class="pill g">Link personale</span>'),
            card('Aziende ed enti', '<p>Stipulano la convenzione per le tesi dal proprio link personale: compilano, firmano digitalmente e ricaricano, senza registrarsi.</p><span class="pill g">Link personale</span>'),
            card('Docenti e personale', '<p>Confermano richieste (tesi, attestati), firmano, gestiscono ricevimento e risorse, partecipano alle sedute dei consigli.</p><span class="pill b">Credenziali di Ateneo</span>'),
            card('Uffici', '<p>Protocollo, uffici dei corsi di studio e segreteria lavorano le pratiche passo per passo, con tempi, solleciti e storico.</p><span class="pill">Pannello</span>'),
            card('Direttore e amministratori', '<p>Il Direttore ha un cruscotto di ciò che aspetta la sua firma; gli amministratori configurano il portale.</p><span class="pill">Pannello</span>'),
        )
    )))

    # 3 ----------------------------------------------------------------------------------------------
    S.append(('accesso', 'Come si entra', sezione('accesso', '3', 'Come si entra',
        'Quattro modi, a seconda di chi sei. Nessuno deve ricordare una password in più.',
        griglia(
            card('Credenziali di Ateneo', '<p>Studenti, docenti e personale entrano con il login unico dell\'Università (SSO). Il portale riconosce subito il ruolo.</p>'),
            card('SPID e CIE', '<p>Per chi è esterno e per le operazioni sensibili (per esempio la conferma della lettera di tutorato) si usa l\'identità digitale.</p>'),
            card('Link personale', '<p>Scuole, aziende e docenti che devono solo confermare o firmare ricevono per email un link personale: non serve creare un account.</p>'),
            card('Password dedicata', '<p>I rappresentanti esterni leggono i verbali dei consigli con una password data dall\'Ufficio didattico, valida per un periodo.</p>'),
        )
        + gal(fig('map_area_studente', '<b>Area personale dello studente.</b> In alto le icone di servizio (assistenza, messaggi, calendario, profilo, ricerca, guide) con il numero in rosso, poi «Da fare per te» e le tessere.'),
              fig('map_area_docente', '<b>Area personale del docente.</b> In più le conferme da dare, le firme dei verbali e delle lettere, la disponibilità per tesi e tirocini.'),
              fig('map_area_gestore', '<b>«Il mio lavoro»</b>, solo per chi gestisce qualcosa o fa parte di un ufficio: una riga per ogni postazione, con i pulsanti e il numero in rosso di quello che aspetta.'))
        + gal(fig('map_area_studente_scuro', '<b>Tema scuro.</b> Con un pulsante l\'area personale cambia colori; il portale ricorda la scelta.'),
              fig('map_miei_eventi', '<b>Eventi, attestati e sondaggi.</b> Prenotazioni, storico, attestati e scanner QR in una pagina.'))
        + fig('map_profilo', '<b>Il profilo.</b> I dati arrivano dal login di Ateneo; si può aggiungere un\'email personale.')
        + fig('map_calendario', '<b>Il mio calendario.</b> Eventi, aule, ricevimenti e scadenze in un posto solo, con il file .ics e il link per abbonare il telefono.')
        + '<h3>Disponibilità dei docenti per tesi e tirocini</h3><p>Ogni docente indica dalla sua Area personale se accetta nuovi tesisti e tirocinanti, quanti posti ha e quanti sono occupati, i corsi di laurea (dall\'anagrafe di Ateneo), i temi, le attività e gli obiettivi formativi. Le due pagine pubbliche mostrano un <b>semaforo</b> che si calcola da solo: <b>verde</b> con almeno due posti liberi, <b>arancione</b> con l\'ultimo posto, <b>rosso</b> al completo, <b>grigio</b> se non accetta o se non ha aggiornato da più di 90 giorni («da riconfermare»). Gli studenti filtrano per tema o corso di laurea e scrivono al docente con un clic.</p>'
        + gal(fig('map_pub_disponibilita_tesi', '<b>Disponibilità per la tesi.</b> I docenti con il semaforo, i posti liberi e il corso di laurea.'),
              fig('map_pub_disponibilita_tirocini', '<b>Disponibilità per i tirocini</b>, con gli stessi colori.'),
              fig('map_doc_disponibilita', '<b>La pagina del docente.</b> Posti, corsi, temi, attività e obiettivi; «Togli dalla pagina pubblica» quando non serve più.'),
              fig('map_doc_elenco_studenti', '<b>L\'elenco degli studenti</b>: si scrive, si incolla o si carica dal modello, con l\'autorizzazione a fianco e il contatore in alto.'))
    )))

    # 4 ----------------------------------------------------------------------------------------------
    S.append(('sito', 'Il sito pubblico', sezione('sito', '4', 'Il sito pubblico',
        'Senza accedere si può già esplorare: eventi, agenda, attività per le scuole e ricerca. Per prenotare serve il login o i propri dati.',
        gal(fig('map_pub_agenda', '<b>L\'agenda unica.</b> Eventi e seminari con filtri per ambito, ricerca e calendario da aggiungere a Google o Outlook.'),
            fig('map_pub_orientamento', '<b>La vetrina dell\'Orientamento.</b> Per i futuri studenti e per le scuole.'),
            fig('map_pub_ricerca', '<b>La ricerca.</b> Trova eventi, attività e aule per parola.'),
            fig('map_pub_ricerca_gruppi', '<b>La ricerca unica.</b> Eventi, notizie, guide, docenti e risposte di Didacticus, raggruppati.'),
            fig('map_pub_avvisi', '<b>Gli avvisi per email.</b> Ci si iscrive per argomento e si riceve un riepilogo dei nuovi eventi.'))
        + '<p>Ogni area ha il suo colore e il suo aspetto, scelto da chi la gestisce (griglia, lista, calendario, timeline, agenda, gruppi, progetti). Le pagine sono pensate anche per il telefono e rispettano le regole di accessibilità.</p>'    )))

    # 5 ----------------------------------------------------------------------------------------------
    S.append(('eventi', 'Eventi e seminari', sezione('eventi', '5', 'Eventi, seminari e attestati',
        'Open day, seminari di ricerca, giornate per le scuole: ogni evento ha la sua scheda, i suoi turni e i suoi posti. '
        'Un evento può chiedere la prenotazione o lasciare entrare liberamente, e può rilasciare l\'attestato o limitarsi a contare i presenti.',
        gal(fig('map_pub_area', '<b>Un\'area del portale</b> (qui OpenLab): le attività con date, posti e luogo.'),
            fig('map_pub_evento', '<b>La scheda di un evento.</b> Turni, posti liberi, referenti, luogo con mappa e pulsante per prenotare.'))
        + griglia(
            card('Prenotazione', '<p>Con o senza accesso, a seconda dell\'evento. Posti limitati o «senza limite», approvazione manuale se serve, conferma per email con <b>QR code</b>.</p>'),
            card('Lista d\'attesa', '<p>Se i posti sono finiti ci si mette in coda e si vede la propria posizione. Se si libera un posto arriva un\'email e si hanno 24 ore per confermare.</p>'),
            card('Check-in con QR', '<p>All\'ingresso lo staff legge il QR del biglietto con il telefono; chi partecipa può anche inquadrare il <b>QR d\'aula</b> e registrarsi da s\u00e9. '
                 'Il check-in si apre poco prima dell\'inizio: quanto prima lo decide la segreteria (di norma un\'ora), e fino a quel momento chi prova vede scritto quando aprir\u00e0. '
                 'Le presenze si contano in tempo reale.</p>'),
            card('Attestati', '<p>A evento concluso si generano gli attestati in PDF con codice e QR di verifica. '
                 'Si può anche <b>spegnere l\'attestato</b> per un singolo evento: serve per gli incontri in cui la presenza si prende solo per contare quanti erano in aula.</p>'),
            card('Solo la presenza', '<p>Per un evento <b>senza prenotazione</b> non c\'è nessun biglietto da fare: si entra, si inquadra il QR d\'aula con le credenziali di Ateneo '
                 'e si risulta presenti. Niente email di conferma, niente codice da conservare, e se l\'attestato è spento nessun attestato: nell\'area personale compare '
                 '\u00abPresenza registrata\u00bb e una ricevuta che vale come promemoria.</p>'),
        )
        + gal(fig('map_pub_verifica', '<b>Verifica di un attestato.</b> Chiunque può controllare l\'autenticità dal codice o dal QR.'),
              fig('map_adm_iscritti', '<b>Chi gestisce</b> vede gli iscritti, le presenze e può agire in blocco.'))
    )))

    # 6 ----------------------------------------------------------------------------------------------
    S.append(('fsl', 'Formazione Scuola Lavoro', sezione('fsl', '6', 'Formazione Scuola Lavoro',
        'Il percorso di una scuola, dalla prenotazione all\'attestato, senza carta e con ogni passaggio visibile.',
        passi('<b>Prenota</b> le attività dal sito (anche se la convenzione non c\'è ancora: la prenotazione resta in attesa).',
              '<b>Consegna i documenti</b>: il docente referente carica l\'elenco degli studenti e l\'autorizzazione della scuola prima dell\'attività.',
              '<b>Stipula la convenzione</b> online (vedi sotto): compila, il Dirigente firma, il Dipartimento protocolla e firma.',
              '<b>Partecipa</b>: la presenza della classe si registra con il check-in.',
              '<b>Ricevi gli attestati</b> di tutta la classe, con codice di verifica.',
              '<b>Valuta</b> l\'esperienza con una breve scheda.')
        + fig('map_pub_fsl', '<b>I progetti per le scuole.</b> Ogni progetto ha periodo, requisiti, ore, referenti ed edizioni.')
        + '<h3>La convenzione online, in cinque tappe</h3>'
        + passi('<b>La scuola compila</b> i dati dell\'istituto e del Dirigente: il portale prepara Convenzione e Allegato A sui modelli del Dipartimento.',
                '<b>Il Dirigente firma</b> la convenzione in formato PAdES (un PDF firmato digitalmente) e la scuola la carica.',
                '<b>Il Dipartimento prende in carico</b>, protocolla in entrata e avvisa la scuola.',
                '<b>Il Direttore firma</b> dal suo link personale.',
                '<b>Protocollo definitivo</b>: la convenzione è attiva, vale un anno e le prenotazioni in attesa si confermano da sole.')
        + gal(fig('fsl_modulo', '<b>1 · La scuola compila</b> i dati dell\'istituto, del Dirigente e delle attività.'),
              fig('fsl_documenti_pronti', '<b>2 · Documenti pronti.</b> Allegato A in PDF e Convenzione in Word da scaricare e far firmare.'),
              fig('fsl_firmata_caricata', '<b>3 · Caricamento</b> del PDF firmato: conferma di ricezione.'),
              fig('fsl_presa_in_carico', '<b>4 · Presa in carico</b> con il protocollo in entrata.'),
              fig('fsl_attiva', '<b>5 · Convenzione attiva.</b> Le prenotazioni in attesa sono confermate.'),
              fig('fsl_email_link', '<b>Le email</b> accompagnano ogni passaggio, con il link personale.'))
        + '<h3>Parlare con la scuola</h3><p>Se una scuola non ha ancora caricato il PDF, l\'ufficio può mandare con un clic un <b>promemoria</b> (o a tutte le scuole in attesa) oppure scrivere un <b>messaggio</b>. La scuola lo legge e risponde dalla propria pagina; l\'ufficio vede il bollino «Nuova risposta». Anche dalla scheda delle scuole senza convenzione ci sono due pulsanti: <b>Chiedi la convenzione</b> (email con i modelli e la PEC ai docenti della scuola) e <b>Scrivi alla scuola</b> (un messaggio ai docenti, che resta anche nella chat della prenotazione).</p>'
        + '<h3>Per l\'ufficio: cosa devo fare</h3><p>La prima schermata di <b>Formazione Scuola Lavoro</b> mostra solo quello che c\'è da fare, con un numero e un pulsante: PDF da prendere in carico, convenzioni da protocollare, convenzioni dal Direttore, scuole senza convenzione. Nella scheda di ogni scuola i cinque passi della convenzione e l\'unica cosa da fare adesso. Le azioni rare sono nel menu «Altro»; il registro si scarica in Excel, PDF e CSV.</p>'
        + gal(fig('map_adm_fsl_home', '<b>Convenzioni con le scuole:</b> le schede «cosa devo fare», la ricerca di una scuola e il menu «Altro».'),
              fig('map_adm_fsl_scuola', '<b>Scheda della scuola:</b> i cinque passi e il riquadro con la cosa da fare adesso.'))
        + gal(fig('map_adm_fsl_iter', '<b>Convenzioni da seguire:</b> le convenzioni suddivise per tappa, con i pulsanti per ricordare e scrivere.'),
              fig('map_adm_fsl_registro', '<b>Registro delle convenzioni:</b> in vigore o scadute, con «Rinnova» e il menu «Scarica» (Excel, PDF, CSV).'))
        + fig('map_adm_fsl_stipulare', '<b>Scuole senza convenzione:</b> una scheda per ogni scuola, con «Registra la convenzione ricevuta», «Chiedi la convenzione», «Scrivi alla scuola» e i documenti già compilati.')
        + '<h3>Una scuola che fa l\'attività solo come orientamento</h3><p>Se una scuola non ha la convenzione e sceglie di partecipare solo come <b>orientamento</b> e non come Formazione Scuola Lavoro, dal menu «…» della sua iscrizione si sceglie <b>Segna come solo orientamento</b>. Per quella prenotazione il portale non chiede più né la convenzione né l\'elenco degli studenti e l\'autorizzazione, non manda promemoria, non la conta nel registro FSL e nelle valutazioni, e l\'attestato non porta la dicitura FSL. Un clic sulla stessa voce («Riporta a Formazione Scuola Lavoro») la rimette com\'era. Le altre scuole della stessa attività non cambiano.</p>'
        + "<h3>Un promemoria per ogni passo, a una persona sola</h3><p>L'amministratore può ricordare a <b>un solo docente</b> quello che gli manca, con l'email che contiene già il link diretto alla pagina giusta. Per le scuole i pulsanti sono tre: <b>«Ricorda l'elenco»</b> (accanto al badge «Elenco mancante» in Iscritti e nella scheda della scuola, e nel menu «Altro» per mandarlo a tutti i docenti a cui manca), <b>«Ricorda la valutazione»</b> (dopo l'attività, con il link alla scheda) e <b>«Ricorda di caricare il PDF»</b> della convenzione. Nelle pratiche della Didattica c'è <b>«Ricorda a chi deve agire»</b>: scrive allo studente che deve integrare, al firmatario o al Direttore che deve firmare, ai docenti che devono confermare. Lo stesso promemoria non riparte prima di 24 ore, e ogni invio finisce nel registro operazioni.</p>"
        + fig('map_adm_iscritti_orientamento', '<b>Iscritti:</b> la prima scuola ha la convenzione da stipulare e i documenti mancanti; la seconda è segnata «Solo orientamento» e non ha nulla da consegnare.')
        + '<h3>L\'elenco degli studenti</h3><p>Il docente referente riceve un link e inserisce i nomi (a mano, incollando da Excel o con il modello): bastano cognome e nome. Con quell\'elenco il portale prepara gli attestati.</p>'
        + gal(fig('fsl_elenco_2_pagina', '<b>La pagina dei documenti</b> da consegnare: elenco, autorizzazione e modello Excel.'),
              fig('fsl_elenco_4_compilato', '<b>Elenco compilato</b> riga per riga, con il contatore degli studenti.'))
        + gal(fig('map_adm_fsl_riepilogo', '<b>Riepilogo</b> per anno scolastico: attività, scuole, studenti, presenze.'),
              fig('map_adm_fsl_report', '<b>Report FSL</b> per scuola, con esportazione in Excel.'))
    )))

    # 7 ----------------------------------------------------------------------------------------------
    S.append(('pratiche', 'Pratiche studenti', sezione('pratiche', '7', 'Le pratiche degli studenti',
        'Certificati, convalide di esami, tesi, cambi di curriculum, mobilità internazionale… Ogni richiesta è un modulo online che segue un percorso fatto di uffici e firme.',
        passi('<b>Lo studente sceglie la pratica</b> dalla Modulistica e la compila a pagine, con campi guidati (corso, insegnamento, docente, scuola…). Può salvare una bozza.',
              '<b>L\'ufficio protocollo</b> scarica la domanda (PDF/A), la protocolla e registra il numero. Dove serve verifica anche la <b>marca da bollo</b>.',
              '<b>L\'ufficio del corso di studio</b> istruisce la pratica e, se deve decidere il consiglio, la porta in seduta.',
              '<b>Il consiglio delibera</b>; l\'esito entra nel verbale e lo studente riceve l\'estratto.',
              '<b>La segreteria</b> registra in carriera e la pratica si chiude. Per certificati e attestati firma il <b>Direttore</b>.')
        + gal(fig('map_pub_modulistica', '<b>La Modulistica.</b> Documenti da scaricare e richieste da compilare online.'),
              fig('stud_modulo_tesi', '<b>Il modulo a pagine</b> (qui la richiesta di tesi) con «Salva come bozza».'),
              fig('map_pub_pratiche', '<b>Le mie pratiche.</b> Stato e ultimo aggiornamento di ognuna.'),
              fig('stud_pratica_tesi', '<b>Il dettaglio</b>: dove si trova la pratica, la richiesta, lo storico e i messaggi.'))
        + '<h3>Chi lavora la pratica</h3>'
        + gal(fig('proto_scheda', '<b>Ufficio protocollo.</b> Scarica la domanda e registra numero e data.'),
              fig('ref_scheda', '<b>Ufficio del corso.</b> L\'iter con la persona in carico e il pulsante per passare avanti.'),
              fig('ref_marche', '<b>Marche da bollo:</b> l\'ufficio le carica su Esse3, avvisa lo studente e registra il pagamento.'),
              fig('rel_conferma', '<b>Conferma del docente</b> (per tesi e attestati): una pagina semplice, senza firma digitale.'))
        + griglia(
            card('Tempi e solleciti', '<p>Ogni passo ha un tempo massimo. Se una pratica si ferma, l\'ufficio riceve un promemoria; chi deve firmare viene sollecitato.</p>'),
            card('Storico completo', '<p>Ogni passaggio, messaggio e documento resta registrato. Chi ha avuto la pratica in carico continua a vederla.</p>'),
            card('Firme', '<p>Relatore e Direttore firmano dal loro link personale, in formato PAdES. Per il Direttore assente firma il vice.</p>'),
            card('Per gli studenti', '<p>Lo studente può sempre aggiungere documenti e rispondere ai messaggi dell\'ufficio, e scarica il certificato firmato.</p>'),
        )
        + '<h3>Per l\'ufficio: la prima schermata</h3><p>La Didattica si apre su <b>«Cosa devo fare»</b>: poche schede con un numero e un pulsante (pratiche da lavorare, da assegnare, verbali da firmare, da decidere in seduta, in attesa di altri) e, sotto, il collegamento a tutte le sezioni, <b>Tirocini</b> compresi. Una pratica mostra in alto <b>cosa fare adesso</b>; una seduta mostra il passo corrente con un solo pulsante. Le azioni rare sono in «Altro», i file in «Scarica» (Excel, PDF, CSV).</p>'
        + gal(fig('map_adm_did_home', '<b>Cosa devo fare:</b> i numeri e, sotto, tutte le sezioni della Didattica.'),
              fig('map_adm_did_pratica', '<b>Una pratica:</b> cosa fare adesso, iter, assegnazione nascosta in un riquadro.'),
              fig('map_adm_did_seduta', '<b>Una seduta:</b> il passo corrente e un solo pulsante per andare avanti.'))
        + gal(fig('map_adm_did_pratiche', '<b>Il pannello delle pratiche:</b> filtri, fase e «cosa fare» per ognuna.'),
              fig('map_adm_did_moduli', '<b>I moduli si costruiscono da pannello</b>, senza programmare: campi, iter, testi ed email.'),
              fig('map_adm_did_statistiche', '<b>Statistiche</b> per modulo: aperte, accolte, tempi.'),
              fig('dir_firma', '<b>La firma del Direttore</b> dal link personale (firma remota o PDF firmato).'))
    )))

    # 8 ----------------------------------------------------------------------------------------------
    S.append(('sedute', 'Sedute e verbali', sezione('sedute', '8', 'Sedute dei consigli e verbali',
        'Dalla convocazione al verbale firmato, ogni seduta ha i suoi passi e i suoi documenti.',
        passi('<b>Convocazione</b>: ordine del giorno e invito per email, con il link per giustificare l\'assenza.',
              '<b>Presenze</b> dei componenti (presente, assente giustificato o no).',
              '<b>Punti all\'ordine del giorno</b>: ogni punto ha il suo testo; le pratiche degli studenti stanno sotto il punto giusto.',
              '<b>Decisioni</b> sulle pratiche, con l\'estratto per lo studente.',
              '<b>Verbale</b> in Word con testata e piede del Dipartimento, poi <b>firma digitale</b> del segretario e del coordinatore.')
        + gal(fig('ref_sedute', '<b>I consigli dei corsi di studio</b> e le sedute prossime e passate.'),
              fig('ref_seduta', '<b>Una seduta:</b> sette tappe guidate, dalla convocazione al verbale firmato.'))
        + '<h3>L\'archivio dei verbali</h3><p>Tutti i verbali, quelli nati nel portale e quelli storici in PDF, in un archivio con ricerca anche nel testo. L\'Ufficio didattico può caricare cartelle intere; ogni referente vede i verbali del proprio consiglio.</p>'
        + fig('map_adm_did_archivio', '<b>L\'archivio verbali.</b> Carica, cerca e scegli quali verbali pubblicare.')
    )))

    # 9 ----------------------------------------------------------------------------------------------
    S.append(('verbali', 'Verbali online', sezione('verbali', '9', 'I verbali online: leggere o scaricare',
        'I verbali pubblicati si consultano da una pagina dedicata. Cambia ciò che si può fare in base a <b>come</b> si entra.',
        '<div class="grid">'
        + card('Con la sola password', '<p><b>Sola lettura.</b> Il verbale si legge nella pagina con una filigrana che riporta chi lo sta consultando, la data e l\'ora. Non c\'è il pulsante per scaricare né per stampare.</p><span class="pill a">Rappresentanti esterni</span>')
        + card('Con le credenziali di Ateneo', '<p><b>Lettura e download</b>, ma solo per le persone abilitate dall\'Ufficio didattico, scelte dall\'anagrafe di Ateneo, e per gli amministratori.</p><span class="pill g">Persone abilitate</span>')
        + '</div>'
        + '<p>La pagina offre due modi per trovare un verbale: <b>«Per corso»</b> (si sceglie il corso, poi l\'organo, poi l\'anno) e <b>«Cerca»</b> (ricerca per data, numero, anno o organo, con filtri e tabella ordinata).</p>'
        + gal(fig('map_pub_verbali_password', '<b>L\'ingresso</b> con la password, oppure con le credenziali di Ateneo.'),
              fig('map_pub_verbali_sola_lettura', '<b>Vista «Per corso»</b> con la sola password: solo «Leggi».'),
              fig('map_pub_verbali_sola_lettura_cerca', '<b>Vista «Cerca»</b>: filtri e tabella per data.'),
              fig('map_pub_verbale_lettura_password', '<b>La lettura</b>: il verbale è disegnato nella pagina, con filigrana.'),
              fig('map_pub_verbali', '<b>Chi è abilitato</b> vede anche «Scarica» su ogni verbale.'),
              fig('map_adm_did_accessi', '<b>Per l\'Ufficio didattico:</b> le password, i consigli visibili, chi è abilitato a scaricare e il registro di chi è entrato.'))
        + '<div class="nota"><b>Una precisazione onesta.</b> Un file che si mostra in un browser non può essere reso davvero «non scaricabile»: uno screenshot è sempre possibile. La filigrana personale e il registro degli accessi servono a scoraggiare e a risalire a chi ha consultato.</div>'
    )))

    # 10 ---------------------------------------------------------------------------------------------
    S.append(('tutorato', 'Tutorato', sezione('tutorato', '10', 'Il tutorato: dal bando al pagamento',
        'Gli studenti tutor vincono un bando e il portale accompagna tutto: lettera di incarico, firme, registro delle ore e pagamento.',
        passi('<b>Il bando</b> e i vincitori: l\'elenco si importa dal decreto di approvazione atti (Word) e ogni vincitore è abbinato al suo insegnamento e al docente.',
              '<b>La lettera di incarico</b> arriva allo studente, che la <b>conferma con SPID o CIE</b>.',
              '<b>Le firme</b> in PAdES: docente responsabile e Direttore; poi il protocollo.',
              '<b>Il registro</b>: il tutor segna giorno, ore e attività; il docente approva.',
              '<b>Fine attività</b>: il portale compila la dichiarazione, il docente la firma e il referente valida.',
              '<b>Promemoria a mano</b>: dalla scheda della lettera l\'ufficio può invitare il tutor a compilare il registro (anche in blocco, per tutti i tutor in ritardo di un bando) e mandare al docente la dichiarazione di fine attività già pronta da firmare.',
              '<b>Il pagamento</b>: lo studente indica i dati (l\'IBAN è conservato cifrato) e l\'ufficio pagamenti procede.')
        + gal(fig('tutorato_1_dati_pagamento', '<b>La dichiarazione individuale</b> compilata online dal tutor.'),
              fig('tutorato_7_area_personale', '<b>L\'Area personale del tutor</b> con la linea del tempo dell\'incarico.'),
              fig('map_adm_tutorato', '<b>Il pannello del tutorato:</b> cosa devo fare (lettere da inviare, da firmare, da protocollare, pagamenti) e l\'elenco dei bandi. Il percorso dopo la firma è lo stesso anche con l\'iter per Uniticket.'))
    )))

    # 11 ---------------------------------------------------------------------------------------------
    S.append(('tesi', 'Convenzioni di tesi', sezione('tesi', '11', 'Convenzioni di tesi con aziende ed enti',
        'Per fare la tesi in azienda serve una convenzione. L\'azienda fa tutto da un link, senza registrarsi.',
        passi('<b>Il link</b>: l\'ufficio lo manda all\'azienda, oppure l\'azienda lo chiede da sola dalla pagina pubblica.',
              '<b>L\'azienda compila</b> i dati dell\'ente e del legale rappresentante e può proporre <b>modifiche agli articoli</b>.',
              '<b>Scarica</b> la convenzione precompilata (Word o PDF/A), la <b>firma in PAdES</b> e la ricarica.',
              '<b>L\'operatore</b> prende in carico, protocolla e registra il numero.',
              '<b>Il Direttore firma</b> dal suo link; l\'ufficio protocolla il firmato e carica il file protocollato.',
              '<b>Stipulata</b>: entra nell\'<b>archivio</b> delle convenzioni, dove si caricano anche quelle già in essere.')
        + gal(fig('map_pub_convenzione_tesi_richiesta', '<b>La richiesta dell\'azienda</b>, dalla pagina pubblica.'),
              fig('tesi_2_azienda_modulo', '<b>La pagina dell\'azienda:</b> dati, articoli e modifiche proposte.'),
              fig('tesi_6_direttore_firma', '<b>La firma del Direttore</b> sul PDF già firmato dall\'azienda.'),
              fig('map_adm_convenzioni_tesi', '<b>Il pannello:</b> le convenzioni per tappa e l\'archivio.'),
              fig('tesi_3_archivio', '<b>L\'archivio</b> delle convenzioni stipulate, con scadenze.'),
              fig('tesi_4_elenco_pubblico', '<b>L\'elenco</b> delle aziende convenzionate, da cui lo studente sceglie nella richiesta di tesi. Si vede solo dopo l\'accesso.'))
    )))

    # 12 ---------------------------------------------------------------------------------------------
    S.append(('tirocini', 'Tirocini', sezione('tirocini', '12', 'Tirocini curriculari: dove farli',
        'Una pagina, riservata a chi ha fatto l\'accesso, con le aziende convenzionate e i laboratori dell\'Ateneo. Il tirocinio si attiva dalla piattaforma TSP di Cineca.',
        passi('<b>L\'ufficio</b> scarica dal TSP il report delle aziende e lo carica nel pannello: restano solo quelle con la convenzione attiva.',
              '<b>Gli indirizzi</b> si aggiungono a mano oppure con un file Excel; la regione si ricava dalla provincia. Un indirizzo trovato in rete si pubblica solo dopo la conferma.',
              '<b>Le ditte individuali</b> non si pubblicano se non lo si decide: sono dati di una persona.',
              '<b>I laboratori</b> arrivano da soli dalle API pubbliche di Ateneo, insieme all\'anagrafe; se ne può nascondere qualcuno.',
              '<b>Lo studente</b> cerca per regione, provincia, comune o settore, oppure sceglie un laboratorio interno.')
        + gal(fig('map_pub_tirocini_accesso', '<b>Senza accesso</b> la pagina dice subito che bisogna accedere, poi riporta qui.'),
              fig('map_pub_tirocini', '<b>Aziende ed enti</b> con ricerca per luogo e settore.'),
              fig('map_pub_tirocini_lab', '<b>Laboratori dell\'Ateneo</b>, presi dalle anagrafi di Ateneo.'),
              fig('map_adm_tirocini', '<b>Il pannello:</b> carica il report, completa gli indirizzi, conferma quelli trovati.'),
              fig('map_adm_tirocini_lab', '<b>I laboratori</b> nel pannello: si possono nascondere dall\'elenco.'))
    )))

    # 13 ---------------------------------------------------------------------------------------------
    S.append(('risorse', 'Prenotazioni e risorse', sezione('risorse', '13', 'Aule, laboratori, sportelli e auto',
        'Le risorse del Dipartimento si prenotano a slot da un calendario, con le regole scelte per ognuna.',
        gal(fig('map_pub_risorse', '<b>Le risorse</b> con orari e regole.'),
            fig('stud_calendario', '<b>Il calendario.</b> Gli slot liberi si prenotano con un clic; su quelli occupati ci si mette in lista d\'attesa.'))
        + griglia(
            card('Regole su misura', '<p>Orari settimanali, durata dello slot, preavviso, chi può prenotare, approvazione sì/no, chiusure e festività.</p>'),
            card('Ripetizioni', '<p>Ogni settimana, ogni due settimane o ogni mese; i giorni non disponibili vengono saltati e segnalati.</p>'),
            card('Lista d\'attesa', '<p>Se si libera uno slot, il primo in coda ha 24 ore per prenotarlo, poi passa al successivo.</p>'),
            card('Responsabili', '<p>Una o più persone (o un intero ufficio) ricevono le richieste e le approvano dalla loro Area personale.</p>'),
            card('Diario di bordo', '<p>Per auto e mezzi: chi li usa compila km, rifornimenti e segnalazioni; il responsabile controlla ed esporta in Excel.</p>'),
            card('Scadenze', '<p>Bollo, assicurazione, tagliando, tarature: avviso prima e dopo la data e rinnovo con un clic.</p>'),
        )
        + gal(fig('resp_risorse', '<b>«Le mie risorse»</b> del responsabile: richieste da approvare e prenotazioni in arrivo.'),
              fig('resp_cruscotto', '<b>Il cruscotto della risorsa:</b> utilizzo, fasce più richieste, chi la usa di più.'),
              fig('resp_diario', '<b>Il diario di bordo</b> di un\'auto.'),
              fig('map_adm_risorse', '<b>Chi gestisce</b> imposta orari, chiusure, campi e responsabili.'))
    )))

    # 13 ---------------------------------------------------------------------------------------------
    # 14 ---------------------------------------------------------------------------------------------
    S.append(('assistenza', 'Assistenza e Didacticus', sezione('assistenza', '14', 'Assistenza e Didacticus, l\'assistente virtuale',
        'Per qualsiasi dubbio (tirocini, certificati, pratiche, tutorato, tesi, eventi, accesso) studenti, personale e utenti esterni scrivono dal pulsante con il <b>gufo di Didacticus</b> («Chiedi a Didacticus»), in basso a destra su ogni pagina. Risponde prima <b>Didacticus</b>, l\'assistente virtuale; se non basta, passa la parola a una persona dell\'ufficio, che riceve un avviso e continua la stessa chat.',
        griglia(
            card('Per tutti', '<p>Chi ha il login ritrova la sua chat e le sue pratiche; gli esterni scrivono nome ed email e ricevono un <b>link personale</b> per riprendere la conversazione, senza registrarsi.</p>', '💬'),
            card('Didacticus', '<p>Risponde subito alle domande più comuni con le risposte scritte dal Dipartimento, con <b>pulsanti, link e allegati</b>. Se non sa, lo dice: non inventa. Se la risposta non basta, un clic e arriva una persona.</p>', '🦉'),
            card('Gli operatori', '<p>Le persone dell\'ufficio «Assistenza» vedono le chat da prendere, le loro, quelle ancora con Didacticus e quelle chiuse. «Prendo io», «Passa a…», note interne, «Chiudi».</p>', '🎧'),
            card('Avvisi a finestrella', '<p>Gli operatori possono ricevere una <b>finestrella di Windows</b> a ogni richiesta, anche a sito chiuso; chi ha chiesto aiuto può essere avvisato delle risposte (senza il testo).</p>', '🔔'),
            card('Orari e privacy', '<p>Gli orari si scrivono dal pannello e fuori orario si può scrivere lo stesso. Le chat si cancellano dopo 12 mesi; restano solo i numeri per le statistiche.</p>', '🛡️'),
            card('Impara dall\'uso', '<p>Le domande a cui Didacticus non ha saputo rispondere finiscono in una lista: con un clic diventano nuove voci, con la risposta data dall\'operatore come bozza.</p>', '🌱'),
        )
        + '<h3>Come funziona, per chi chiede aiuto</h3>'
        + passi(
            'Dal pulsante con il gufo (o dalla pagina Assistenza, o dal pulsante «Chiedi aiuto su questa pratica») si sceglie l\'argomento e si scrive la domanda. Gli esterni indicano nome ed email.',
            '<b>Didacticus risponde subito</b>, con il testo, i pulsanti dei link e gli eventuali allegati. Se non è sicuro propone delle voci vicine («intendevi…?»).',
            'Sotto ogni risposta: <b>«Sì, grazie»</b> chiude la chat; <b>«Non mi basta»</b> o <b>«Parla con un operatore»</b> (o la parola «operatore» scritta in chat) passano la parola a una persona.',
            'La chat passa agli operatori, che ricevono email e finestrella con la domanda e vedono già quello che Didacticus ha risposto. Chi ha chiesto aiuto continua a scrivere nella stessa chat e, se chiude la pagina, riceve la risposta per email.',
            'A fine conversazione si può dire se l\'assistenza è stata utile.')
        + '<p>La chat si apre con <b>«Ciao <i>Nome</i>! Dimmi cosa ti serve»</b> (chi non ha l\'accesso indica prima nome ed email) e gli argomenti da scegliere nella finestra. Se Didacticus non sa rispondere («Mi dispiace, ti passo un operatore») o si chiede un operatore, Didacticus domanda <b>a quale corso di laurea si è iscritti</b> e avvisa solo le persone dell\'ufficio che seguono quel corso; se nessuno lo segue, avvisa tutti.</p>'
        + fig('map_pub_didacticus_widget', '<b>Il pulsante di Didacticus</b> in basso a destra su ogni pagina: la riga «Ti serve aiuto?» compare un istante e si riapre ogni volta che si passa con il mouse sul gufo.')
        + gal(fig('map_pub_assistenza_form', '<b>Il modulo classico</b> (con «Preferisco il modulo»): argomento, domanda e, per gli esterni, nome ed email.'),
              fig('map_pub_assistenza_didacticus', '<b>La chat parte da Didacticus:</b> saluta per nome, fa scegliere l\'argomento e poi risponde con link e «Ti è stato utile?».'),
              fig('map_pub_assistenza_esterno', '<b>La chat di un utente esterno</b>, ripresa dal suo link personale.'))
        + '<h3>Per chi risponde</h3><p>Le persone dell\'ufficio <b>Assistenza</b> (scelte da Gestione del portale → Uffici) trovano la voce <b>Assistenza</b> nel menu di Didattica e le chat anche nella casella <b>Messaggi</b>. La chat si aggiorna da sola; il pulsante «Prendo io» assegna la chat, «Passa a…» la gira a un collega, le <b>note interne</b> non le vede chi ha chiesto aiuto.</p>'
        + gal(fig('map_adm_assistenza_coda', '<b>Una chat da prendere</b>, con le schede Da prendere · Mie · Tutte · Con Didacticus · Chiuse.'),
              fig('map_adm_assistenza', '<b>Una chat in carico</b>: la domanda, la risposta di Didacticus, la nota interna e la risposta dell\'operatrice.'),
              fig('map_adm_assistenza_statistiche', '<b>Le statistiche</b>: chat per argomento e per operatore, tempo di prima risposta, utilità, e quante ne ha risolte Didacticus. Si scaricano in Excel, PDF e CSV.'))
        + '<h3>Istruire Didacticus</h3><p>Da <b>Didattica → Didacticus</b> chi gestisce l\'assistenza scrive e cura la <b>base di conoscenza</b>: ogni <b>voce</b> ha le domande che la persona potrebbe fare (una per riga), le parole chiave e i sinonimi, la risposta, i <b>link</b> che diventano pulsanti e gli <b>allegati</b> da scaricare (PDF, Word, Excel, immagini). Una voce si può spegnere, riservare a chi ha fatto l\'accesso o impostare perché proponga sempre un operatore. L\'elenco si scarica e si può importare da un file CSV per scrivere tante voci in una volta.</p>'
        + passi(
            '<b>Voci</b>: l\'elenco con quante volte ogni risposta è stata usata e quanti l\'hanno trovata utile.',
            '<b>Prova</b>: si scrive una domanda come la scriverebbe uno studente e si vede cosa risponderebbe Didacticus, con il punteggio di ogni voce.',
            '<b>Domande senza risposta</b>: quelle che Didacticus non ha saputo gestire; «Crea una voce» le trasforma in risposte.',
            '<b>Pagine di Ateneo</b>: gli indirizzi delle pagine pubbliche di unical.it che Didacticus legge ogni settimana come fonti (servono solo se è collegato un modello linguistico).',
            '<b>Impostazioni</b>: acceso o spento, saluto, soglia di sicurezza, immagine di profilo e lo stato del «significato delle domande».')
        + '<h3>Capire il significato delle domande</h3><p>Un piccolo modello che gira sul server dell\'Ateneo (<b>bge-m3</b>) confronta il <b>significato</b> della domanda con quello delle voci: «stage fuori dall\'Italia» trova «Tirocinio all\'estero» anche senza parole in comune. <b>Non scrive risposte</b>: mostra sempre il testo delle voci, quindi non può inventare. Interviene solo quando le regole non sono sicure (o lo sono poco); se non risponde, Didacticus torna alle regole. È spento di partenza e si accende dal file di configurazione del server; i vettori delle voci si aggiornano ogni notte e con «Aggiorna ora». Nella <b>Prova</b> si vedono le due classifiche, per parole e per significato. Un modello che scrive le risposte (Llama) è stato provato ma resta spento: sul server senza scheda grafica è troppo lento e a volte inventa.</p>'
        + gal(fig('map_adm_didacticus_voci', '<b>La base di conoscenza:</b> voci, usi, utilità, stato e azioni.'),
              fig('map_adm_didacticus_voce', '<b>Una voce:</b> domande, parole chiave, risposta, link e allegati.'),
              fig('map_adm_didacticus_prova', '<b>La prova:</b> cosa risponderebbe, le voci più vicine per parole (punteggio) e per significato (somiglianza).'),
              fig('map_adm_didacticus_pagine', '<b>Pagine di Ateneo:</b> gli indirizzi letti ogni settimana, con l\'esito dell\'ultima lettura.'),
              fig('map_adm_didacticus_mancate', '<b>Domande senza risposta</b> da trasformare in voci.'),
              fig('map_adm_didacticus_impostazioni', '<b>Impostazioni:</b> lo stato del significato delle domande, acceso/spento, saluto, soglia e immagine del gufo.'))
    )))

    # 13a (recuperata il 23/11/2026 dall'HTML committato del 20/11: il generatore l'aveva persa)
    S.append(('myforms', 'MyForms: moduli da compila', sezione('myforms', '13a', 'MyForms: moduli da compilare',
        'Questionari, raccolte di informazioni e iscrizioni, come con Google Moduli ma dentro il portale: chi organizza crea il modulo dal pannello, lo condivide con un link o un QR, e trova le risposte in tabella e in Excel.',
        '<ol class="steps"><li><b>Si crea</b> dal pannello, modulo <b>MyForms</b> › «Nuovo form»: titolo, descrizione, domande (testo, scelta singola o multipla, tendina, date, numeri, sì/no, tabelle, allegati…). Una domanda può comparire solo se a un\'altra si è risposto in un certo modo. Non serve creare nessuna area.</li><li><b>Si sceglie a chi è rivolto</b>: chiunque (anche senza accesso), chi ha fatto l\'accesso, studenti, docenti, personale tecnico-amministrativo, personale, esterni, scuole, aziende. Il profilo «scuole» lo dichiara la persona stessa: non va usato per cose riservate.</li><li><b>Si apre</b>, anche con un periodo (dal… al…) e un numero massimo di risposte. Il modulo riceve un link proprio e un QR da stampare o condividere.</li><li><b>Si risponde</b> dal link, dalla pagina «MyForms · moduli da compilare» o dalla ricerca del sito: ognuno vede solo i moduli rivolti a lui. Dopo l\'invio compare un codice di ricevuta; se il modulo lo permette, la risposta si corregge finché è aperto.</li><li><b>Si leggono le risposte</b> nel pannello: tabella con ricerca e filtri, dettaglio, riepilogo con le percentuali, scaricamento in Excel, CSV e PDF, allegati in ZIP.</li></ol><div class="grid"><div class="card"><span class="ic">🛡️</span><h4>Anche senza accesso</h4><p>Se il modulo è aperto a chiunque si risponde senza accedere, con una domanda anti-robot e un limite di invii. Gli allegati invece chiedono sempre l\'accesso.</p></div><div class="card"><span class="ic">🕶️</span><h4>Moduli anonimi</h4><p>Anonimo per chi gestisce il modulo: nelle risposte, nelle email e nei file non compaiono né il nome né l\'email. Il portale tiene solo un\'impronta per evitare le doppie risposte; la ricevuta resta a schermo e non arriva per email.</p></div><div class="card"><span class="ic">👥</span><h4>Chi lo gestisce</h4><p>Gli amministratori e chi è abilitato a MyForms; ognuno vede solo i suoi moduli e può aggiungere dei co-gestori, che vedono quel modulo e le sue risposte.</p></div><div class="card"><span class="ic">⏱️</span><h4>Si chiude da solo</h4><p>A fine periodo o al numero massimo di risposte il modulo si chiude e il proprietario riceve un\'email. Se si sceglie per quanti mesi tenere le risposte, le più vecchie si cancellano da sole, con un avviso 7 giorni prima.</p></div></div><p>Chi ha fatto l\'accesso trova nell\'area personale la tessera <b>«I miei form»</b>: i moduli compilati con la ricevuta e, se possibile, il pulsante per modificarli, e quanti moduli sono aperti per lui. La guida «MyForms: creare i moduli» è nel pannello, accanto a «Nuovo form».</p><div class="gal"><figure><img src="img/map_adm_myforms_campi.jpg" loading="lazy" alt="Il costruttore delle domande. Per ogni domanda il tipo, le opzioni, «obbligatorio» e, se serve, «Mostra solo se…»."><figcaption><b>Il costruttore delle domande.</b> Per ogni domanda il tipo, le opzioni, «obbligatorio» e, se serve, «Mostra solo se…».</figcaption></figure><figure><img src="img/map_adm_myforms_qr.jpg" loading="lazy" alt="Link e QR da condividere. Si apre il modulo con un pulsante; le risposte sono a un clic."><figcaption><b>Link e QR da condividere.</b> Si apre il modulo con un pulsante; le risposte sono a un clic.</figcaption></figure><figure><img src="img/map_pub_myforms_form.jpg" loading="lazy" alt="Il modulo da compilare. Le pagine, l&#039;allegato, le caselle e la dichiarazione da accettare."><figcaption><b>Il modulo da compilare.</b> Le pagine, l\'allegato, le caselle e la dichiarazione da accettare.</figcaption></figure><figure><img src="img/map_adm_myforms_riepilogo.jpg" loading="lazy" alt="Il riepilogo delle risposte. Per le domande a scelta, conteggi e percentuali con le barre."><figcaption><b>Il riepilogo delle risposte.</b> Per le domande a scelta, conteggi e percentuali con le barre.</figcaption></figure></div>'
    )))

    # 13b (recuperata il 23/11/2026 dall'HTML committato del 20/11: il generatore l'aveva persa)
    S.append(('pubblicazioni', 'Richieste di pubblicazione', sezione('pubblicazioni', '13b', 'Richieste di pubblicazione: sito e social del Dipartimento',
        "Chi lavora al Dipartimento, docenti e personale, e vuole far pubblicare un evento o un'iniziativa compila una richiesta guidata dal portale. Se l'approvazione è accesa, prima la approva il Direttore o chi è incaricato; poi l'ufficio comunicazione la lavora e tiene aggiornata la tabella di monitoraggio, il file Excel che prima si compilava a mano. Tutto si gestisce dal modulo <b>Amministrazione</b> del pannello.",
        '<ol class="steps"><li><b>Chi invia compila</b> la richiesta in <b>sette passi</b> con barra di avanzamento: note da leggere, evento (data, modalità, link), testi (titolo, sottotitolo, breve descrizione), a chi è rivolto, la <b>locandina</b> (obbligatoria) con altri allegati e immagini (PDF, JPG, PNG), tag e data di pubblicazione, riepilogo finale con «Modifica» accanto a ogni parte. La pagina si apre dalla tessera <b>«Le mie richieste di pubblicazione»</b> dell\'area personale o dalla pagina «Modulistica amministrativa». Un passo senza campi da compilare si salta da solo.</li><li><b>Il portale controlla</b> tutto prima dell\'invio (limiti dei testi, destinatari, tipo vero dei file, date); se qualcosa non va la pagina si riapre sul passo con l\'errore, senza perdere quanto scritto. Chi ha inviato riceve una <b>ricevuta</b> per email (se è accesa nelle impostazioni).</li><li><b>Chi approva decide</b> (se l\'approvazione è accesa): il Direttore o le persone incaricate, scelte dall\'anagrafe di Ateneo, ricevono un\'email, aprono la richiesta e premono <b>«Approva»</b> o <b>«Respingi»</b> con il motivo. Basta una persona sola: vale la prima decisione. La trovano anche nell\'area personale, senza entrare nel pannello.</li><li><b>L\'ufficio comunicazione riceve la richiesta approvata</b> con tutto il testo, pronto da copiare, e il link alla scheda; chi deve solo saperlo la riceve per conoscenza. Chi l\'ha inviata ha la notizia, o il motivo del rifiuto. Con l\'approvazione spenta la richiesta arriva all\'ufficio subito, già approvata.</li><li><b>L\'ufficio guarda le «Richieste arrivate»</b> nel pannello (modulo <b>Amministrazione</b>, voce <b>Richieste di pubblicazione</b>): quelle approvate e non ancora nel file Excel, con quante sono da guardare, in lavorazione o respinte; quelle ancora in attesa di approvazione stanno in un riquadro a parte, in sola lettura. Le richieste da guardare sono anche nella Scrivania e nella tessera «Il mio lavoro».</li><li><b>«Invia al file Excel»</b> fa entrare la richiesta nella tabella di monitoraggio e precompila il link, gli altri link e i destinatari scelti. Si può tornare indietro con «Togli dal file Excel»: quanto scritto resta salvato. Le iniziative arrivate per altre vie si aggiungono con «Nuova riga a mano».</li><li><b>Ognuno completa le sue colonne</b>: nella scheda della richiesta o nella vista <b>«Da compilare»</b>, una scheda per richiesta con le sole colonne di chi guarda. Si cambia lo stato (Approvata, In lavorazione, Pubblicata, Respinta) e si <b>scarica</b> la tabella in Excel, PDF o CSV, con i filtri scelti; chi vede solo le sue colonne scarica una copia parziale.</li></ol><div class="grid"><div class="card"><span class="ic">🎓</span><h4>Docenti e personale</h4><p>Di partenza possono inviarla i docenti e il personale tecnico amministrativo; l\'amministrazione può cambiare i profili e aprire o chiudere la procedura. Chi non ha fatto l\'accesso vede «Accedi per compilare»; chi non è tra i profili ammessi vede a chi è riservata. Chi invia vede nell\'area personale le sue richieste e lo stato di ciascuna.</p></div><div class="card"><span class="ic">👥</span><h4>Chi gestisce</h4><p>Il modulo Amministrazione del pannello, con due abilitazioni: «Modulo Amministrazione» (tutto) e «Amministrazione · Richieste di pubblicazione» (solo le richieste). Con <b>«Chi compila cosa»</b> si decide quali colonne vede e compila ogni operatore (finché non si assegna niente, tutti compilano tutto; chi ha tutto il modulo compila sempre tutto). Ogni modifica resta nel registro con chi e quando.</p></div><div class="card"><span class="ic">⚙️</span><h4>Si gestisce da soli</h4><p>Chi ha tutto il modulo cambia, senza toccare il codice, chi può inviare, i testi e i limiti (scheda «Impostazioni»), i nomi e l\'obbligo dei campi, le intestazioni e l\'ordine delle colonne del file Excel e può aggiungere campi e colonne nuovi (scheda «Campi e colonne», con «Torna all\'originale»), le note operative del primo passo, le pagine dove si può pubblicare, le tre liste di persone (chi approva, chi lavora, chi è informato) e l\'approvazione, che si accende e si spegne.</p></div><div class="card"><span class="ic">📎</span><h4>I file</h4><p>Gli allegati restano in una cartella protetta e si scaricano solo dalle pagine del portale: chi li ha inviati, chi approva e chi gestisce le richieste. La locandina si scarica con il nome «Locandina_titolo_breve».</p></div><div class="card"><span class="ic">🗂️</span><h4>Niente si cancella da solo</h4><p>Righe e allegati restano finché qualcuno non li elimina dalla scheda (con conferma). Per quanto tempo tenerli è ancora da decidere.</p></div></div><p>Le guide «Docenti e personale: chiedere la pubblicazione di un\'iniziativa» «Richieste di pubblicazione: per chi le gestisce» e «Richieste di pubblicazione: per chi le approva» sono nella pagina «Come si fa».</p>'
    )))

    # 13c (recuperata il 23/11/2026 dall'HTML committato del 20/11: il generatore l'aveva persa)
    S.append(('amministrazione', 'Amministrazione: modulisti', sezione('amministrazione', '13c', 'Amministrazione: modulistica amministrativa e procedure online',
        'Il modulo <b>Amministrazione</b> del pannello raccoglie le cose amministrative del personale: i documenti e i link della <b>Modulistica amministrativa</b> e le <b>procedure online</b>, per ora la richiesta di pubblicazione. Nasce accanto alla Didattica con lo stesso stile: una prima schermata «Cosa devo fare» e un menu a sinistra.',
        '<div class="grid"><div class="card"><span class="ic">🧭</span><h4>Cosa devo fare</h4><p>La prima schermata dice quante richieste di pubblicazione sono da guardare, quante in lavorazione e quante righe del file Excel hanno ancora colonne da completare, con il pulsante che porta alla cosa giusta. Sotto, l\'elenco di tutte le sezioni.</p></div><div class="card"><span class="ic">📄</span><h4>Modulistica amministrativa</h4><p>Una pagina pubblica, con la stessa grafica della modulistica della Didattica: in cima le procedure online (la richiesta di pubblicazione, con «Compila online», «Accedi e compila» o «Riservata a…»), sotto i documenti da scaricare e i link divisi per categoria, con la ricerca. La pagina non entra da sola nel menu del sito: la mette l\'amministratore.</p></div><div class="card"><span class="ic">🗃️</span><h4>Chi aggiorna i documenti</h4><p>Chi ha tutto il modulo aggiunge, modifica ed elimina documenti e link dalla scheda «Modulistica», con le stesse regole e lo stesso caricamento dei file della Didattica. I documenti dell\'Amministrazione e quelli della Didattica non si mescolano.</p></div><div class="card"><span class="ic">🔑</span><h4>Chi abilita cosa</h4><p>Gli amministratori entrano sempre; per gli altri l\'amministratore sceglie in Utenti «Modulo Amministrazione» (tutto) oppure «Amministrazione · Richieste di pubblicazione» (solo le richieste: nessun altro cambiamento per chi era già abilitato).</p></div></div><p>Più avanti arriveranno i moduli online che aprono una pratica (missioni, rimborsi, acquisti), partendo dai materiali della segreteria amministrativa. La guida «Amministrazione: modulistica e procedure online» è nella pagina «Come si fa».</p>'
    )))

    # 13d SOS aule ------------------------------------------------------------------------------------
    S.append(('sos', 'SOS aule', sezione('sos', '13d', 'SOS aule: assistenza didattica in aula',
        'Un problema durante la lezione (proiettore, computer, porta chiusa, materiale che manca) si segnala dal telefono inquadrando il <b>QR sul cartello dell\'aula</b>. Si entra con le credenziali di Ateneo e la richiesta arriva subito a chi segue quell\'aula. Il modulo nasce spento: lo accende l\'amministratore.',
        griglia(
            card('Il docente', '<p>Pagina <code>sos.php</code>: aula già scelta dal QR, tipo di problema, urgenza, descrizione, foto. Poi segue lo stato, aggiunge note, annulla se ha risolto da solo e, entro 24 ore, può dire «Non è risolto».</p>', '🧑‍🏫'),
            card('Il referente dell\'aula', '<p>Pagina <code>sos_interventi.php</code>: «Ci penso io», manda uno studente, inoltra a un altro ufficio o chiude. Email e notifiche sul telefono, con un promemoria se nessuno risponde.</p>', '🛠️'),
            card('Lo studente part-time', '<p>Vede solo gli interventi affidati a lui: «Sto andando», «Risolto» o «Non risolto», con i minuti se vuole.</p>', '🚶'),
            card('L\'amministratore', '<p>In Amministrazione, «SOS aule» (<code>admin/sos_aule.php</code>): aule, persone, modulo, impostazioni e registro con i numeri. I cartelli si stampano da <code>admin/sos_cartello.php</code>.</p>', '🪧'),
        ))))

    # 17 (recuperata il 23/11/2026 dall'HTML committato del 20/11: il generatore l'aveva persa)
    S.append(('titulus', 'Il protocollo in Titulus', sezione('titulus', '17', 'Il protocollo in Titulus',
        "Il portale parla direttamente con <b>Titulus</b>, il protocollo informatico dell'Ateneo: i documenti importanti ricevono il numero di protocollo da soli, senza copiare niente a mano.",
        '<div class="info"><b>Come funziona, in una riga.</b> Il portale manda a Titulus il PDF (e gli allegati) con i dati di chi scrive e dell\'ufficio responsabile; Titulus risponde con il <b>numero e la data di protocollo</b>; il portale li scrive nella pratica e la fa andare avanti.</div>\n<h3>Quando protocolla, e chi deve fare qualcosa</h3>\n<table><thead><tr><th>Documento</th><th>Quando si protocolla</th><th>Chi preme un pulsante?</th></tr></thead><tbody>\n<tr><td><b>Pratiche degli studenti</b> (certificati, richieste, immatricolazioni, mobilità…)</td><td>Subito, quando lo studente <b>invia</b> la domanda: il protocollo è in entrata.</td><td>Nessuno. La pratica passa da sola al passo successivo.</td></tr>\n<tr><td><b>Richiesta di tesi</b> (e gli altri moduli che cominciano con una conferma o una firma)</td><td>Dopo la <b>conferma del relatore</b>: finché non l\'ha approvata la domanda non è ancora «in entrata».</td><td>Nessuno: quando la pratica arriva all\'ufficio protocollo, il portale protocolla.</td></tr>\n<tr><td><b>Lettera di incarico del tutorato</b></td><td>Dopo la firma del <b>Direttore</b>, ultima della catena (tutor → docente → Direttore). Va nel <b>repertorio dei contratti di lavoro</b> del Dipartimento, in automatico. Per chi usa Uniticket il portale crea la copia con la <b>stampigliatura rossa</b> di protocollo (la firma digitale resta valida) e il gestore riceve un\'email con la lettera protocollata e la dichiarazione individuale da caricare sul ticket. Se il docente o il Direttore tardano, il gestore ha il pulsante <b>Sollecita</b>.</td><td>Nessuno.</td></tr>\n<tr><td><b>Convenzioni FSL</b> con le scuole e <b>convenzioni di tesi</b> con aziende ed enti</td><td>Solo dopo il <b>controllo dell\'ufficio</b>: la convenzione firmata non si protocolla da sola.</td><td>L\'ufficio protocollo preme <b>«Protocolla in Titulus»</b>; il portale interroga Titulus e registra il numero. Lo stesso per il protocollo definitivo, dopo la firma del Direttore.</td></tr>\n</tbody></table>\n<div class="nota"><b>Se Titulus non risponde</b> (manutenzione, password cambiata, dati non riconosciuti) la pratica <b>non si blocca</b>: resta all\'ufficio con il pulsante «Protocolla in Titulus» e nello storico interno compare il motivo. Un documento <b>non viene mai protocollato due volte</b>: se ha già un numero, il portale usa quello.</div>\n<h3>Per chi amministra: dove si imposta</h3>\n<p>Nel pannello di gestione, voce <b>Protocollo</b>, ci sono quattro schede:</p>\n<div class="grid"><div class="card"><h4>1 · Collegamento</h4><p>Indirizzo del servizio di Titulus, utente e password (conservata cifrata), codice AOO. Il pulsante <b>«Prova il collegamento»</b> verifica tutto senza creare nessun documento.</p></div><div class="card"><h4>2 · Configurazioni per richiesta</h4><p>Per ogni tipo di richiesta si decide: in entrata, in partenza o tra uffici; l\'ufficio (UO); il responsabile (RPA) con la sua matricola; il titolario; il fascicolo; chi riceve per conoscenza. Una configurazione «generale» vale per tutto ciò che non ne ha una propria; quella di una pratica vale solo per il suo modulo.</p></div><div class="card"><h4>3 · Voci d\'indice e repertori</h4><p>L\'elenco delle voci per gli iter di approvazione e dei <b>repertori</b> (per esempio «contratti di lavoro DiBEST») da scegliere nelle configurazioni.</p></div><div class="card"><h4>4 · Registro degli invii</h4><p>Ogni invio a Titulus, riuscito o no, con data, oggetto, numero ricevuto e, se è andata male, il motivo scritto in italiano. Il numero si può riaggiornare.</p></div></div>\n<div class="nota"><b>Attenzione all\'ambiente.</b> Il collegamento può puntare a Titulus di <b>prova</b> (pre-produzione) oppure a quello <b>vero</b>. I numeri presi in prova non sono validi. Prima di aprire un modulo agli studenti veri, controllare che l\'indirizzo sia quello di produzione e fare «Prova il collegamento».</div>\n<p>Il collegamento a Titulus è un pacchetto a sé (<code>Unical\\Titulus</code>), pensato per essere riusato anche da altri programmi dell\'Ateneo: i dettagli sono nella <a href="tecnica.html#titulus">mappa per sviluppatori</a>.</p>'
    )))

    S.append(('direttore', 'Il Direttore', sezione('direttore', '15', 'Il Direttore: un cruscotto e le firme',
        'Una pagina sola con le cose che aspettano la firma del Direttore e quelle che si sono fermate nei vari moduli.',
        gal(fig('map_dir_cruscotto', '<b>Il Cruscotto del Direttore.</b> Documenti da firmare, pratiche ferme, conferme in ritardo, verbali, convenzioni, tutorato e risorse.'),
            fig('map_dir_assente', '<b>«Direttore assente».</b> Con un interruttore firme e avvisi passano al vice direttore, fino al rientro.'))
        + '<p>Direttore e vice direttore hanno gli stessi permessi. L\'interruttore «Direttore assente» può essere attivato dal Direttore, dal vice, dalla segreteria di direzione e dagli amministratori; ogni volta è registrato nel registro operazioni. Una volta a settimana il Direttore (come chiunque abbia cose in sospeso) riceve per email un riepilogo di ciò che lo aspetta.</p>'
    )))

    # 14 ---------------------------------------------------------------------------------------------
    S.append(('pannello', 'Il pannello', sezione('pannello', '16', 'Il pannello di gestione',
        'Per chi organizza: un pannello unico, diviso in cinque moduli. Ognuno entra solo in quelli per cui è abilitato.',
        fig('map_adm_inizio', '<b>L\'ingresso.</b> Le cose da fare e i moduli a cui si ha accesso.')
        + griglia(
            card('Eventi e seminari', '<p>Aree, eventi e turni, progetti, iscritti e presenze, scanner QR, sondaggi, statistiche e report per ambito.</p>'),
            card('Formazione Scuola Lavoro', '<p>Convenzioni, verifica delle iscrizioni, valutazioni, anagrafe delle scuole e report.</p>'),
            card('Prenotazioni e risorse', '<p>Risorse, orari, prenotazioni, approvazioni e cruscotto.</p>'),
            card('Didattica', '<p>Pratiche, sedute e verbali, moduli e documenti, ricevimento, statistiche, tutorato, convenzioni di tesi.</p>'),
            card('Gestione del portale', '<p>Anagrafi di Ateneo, testata e menu del sito, utenti e abilitazioni, uffici e persone, sistema e registri.</p>'),
        )
        + '<h3>Aree, eventi e iscritti</h3>'
        + gal(fig('map_adm_aree', '<b>Le aree</b> del portale: visibili o nascoste, con i loro gestori.'),
              fig('map_adm_nuova_area', '<b>Nuova area</b> in pochi campi: nasce nascosta, pronta da preparare.'),
              fig('map_adm_dashboard', '<b>La dashboard</b> di un\'area: prenotazioni, presenze, messaggi.'),
              fig('map_adm_eventi', '<b>Eventi e turni.</b> Posti, orari, aula, duplicazione.'),
              fig('map_adm_progetti', '<b>Progetti</b> con edizioni, requisiti e attestati.'),
              fig('map_adm_form', '<b>Il Form Builder</b>: campi personalizzati per ogni prenotazione.'),
              fig('map_adm_iscritti', '<b>Iscritti e presenze</b>, con azioni di massa.'),
              fig('map_adm_statistiche', '<b>Statistiche e report</b> con esportazione.'),
              fig('map_adm_report_ambiti', '<b>Report per ambito</b>, anche per la Terza missione.'),
              fig('map_adm_impostazioni_area', '<b>Impostazioni dell\'area</b>: colori, layout e regole di iscrizione.'),
              fig('map_adm_sondaggi', '<b>Sondaggi</b> e questionari collegati agli eventi.'))
        + '<h3>I messaggi, tutti in un posto</h3><p>Il pulsante <b>Messaggi</b> in alto apre una casella unica: le conversazioni sulle prenotazioni di tutte le aree che gestisci e le risposte delle scuole sulle convenzioni FSL, ognuna con il <b>modulo</b> da cui arriva. Si filtra per modulo o per «da leggere», e il numero rosso nel pulsante somma tutto. Si risponde da lì e una conversazione finita si <b>archivia</b>: sparisce dalla casella ma non si cancella, si ritrova in «Archiviate» e torna da sola se l\'altra parte scrive di nuovo. Chi gestisce solo alcune attività vede solo i loro messaggi.</p>'
        + fig('map_adm_messaggi', '<b>La casella dei messaggi:</b> prenotazioni e convenzioni insieme, col modulo, i filtri, «Leggi / Rispondi» e «Archivia».')
        + '<h3>Il sito e le persone</h3>'
        + gal(fig('map_adm_testata', '<b>Testata e home</b>: logo, carosello e widget.'),
              fig('map_adm_menu', '<b>Il menu</b> a tre livelli, con ordine a trascinamento.'),
              fig('map_adm_utenti', '<b>Utenti e abilitazioni</b>: chi può fare cosa e dove.'),
              fig('map_adm_anagrafe_docenti', '<b>Le anagrafi di Ateneo</b>: docenti e personale, aggiornati ogni settimana.'),
              fig('map_adm_anagrafe_strutture', '<b>Strutture</b> e corsi di studio.'),
              fig('map_adm_scuole', '<b>L\'anagrafe delle scuole</b> del Ministero.'),
              fig('map_adm_anagrafe_corsi', '<b>I corsi di studio</b> di tutto l\'Ateneo, con i filtri per dipartimento, tipo e codice.'),
              fig('map_adm_anagrafe_insegnamenti', '<b>Gli insegnamenti di Ateneo</b>: l\'integrato con i suoi moduli sotto, il docente, il corso e il link alla pagina di Ateneo.'))
        + '<h3>Anagrafi di Ateneo: insegnamenti e corsi di studio</h3><p>Dalla pagina <b>Anagrafi di Ateneo</b> si consultano, oltre a docenti e personale, gli <b>insegnamenti di tutto l\'Ateneo</b> dall\'anno accademico 2026/2027 (e i successivi già pubblicati) e i <b>corsi di studio di tutti i dipartimenti</b>. Per ogni insegnamento: codice, descrizione, settore, CFU, semestre, docente, corso di studio con il suo codice, dipartimento e il link alla pagina sul portale di Ateneo. Gli insegnamenti <b>integrati</b> compaiono con i loro <b>moduli</b> sotto («modulo di…») e, quando si crea un\'attività, si sceglie l\'integrato intero e non i singoli moduli; il codice dell\'integrato lo ricava il portale dalla scheda dei suoi moduli. I corsi di studio si filtrano per <b>dipartimento</b>, per <b>tipo</b> (laurea, magistrale, ciclo unico…) e per <b>codice o nome</b>.</p><div class="gal"><figure><img src="img/map_adm_anagrafe_insegnamenti.jpg" loading="lazy" alt="Insegnamenti di Ateneo: l&#x27;integrato con i suoi moduli sotto, il docente, il corso con il suo codice e il link alla pagina di Ateneo."><figcaption><b>Insegnamenti di Ateneo:</b> l\'integrato con i suoi moduli sotto, il docente, il corso con il suo codice e il link alla pagina di Ateneo.</figcaption></figure><figure><img src="img/map_adm_anagrafe_corsi.jpg" loading="lazy" alt="Corsi di studio di tutto l&#x27;Ateneo, con i filtri per dipartimento, tipo e codice."><figcaption><b>Corsi di studio di tutto l\'Ateneo,</b> con i filtri per dipartimento, tipo e codice.</figcaption></figure></div>'
        + "<h3>Le novità di fine ottobre</h3><ul><li><b>«Da fare per te»</b>: una pagina con tutto quello che aspetta una persona (pratiche da integrare, convenzioni da firmare, elenco degli studenti da consegnare, sondaggi di gradimento…), con la scadenza («entro il…», in rosso negli ultimi tre giorni) e il link diretto; il numero compare nella barra di ogni pagina.</li><li><b>Firme in un posto solo</b>: la pagina «Da firmare» raccoglie pratiche, convenzioni, lettere di tutorato e verbali, con anteprima del PDF; si possono scaricare i selezionati in un solo ZIP, firmarli fuori dal portale e ricaricarli, e confermare gli attestati in blocco.</li><li><b>Le scuole</b>: la tessera «La mia scuola» mostra per ogni prenotazione i passi del percorso e un solo pulsante; al primo accesso «Sono una scuola» porta al profilo giusto. Esistono anche i gruppi <b>Scuole</b> e <b>Aziende</b>.</li><li><b>Docenti e studenti</b>: «I miei studenti» (le richieste di cui si è relatori), «Le mie approvazioni», la tessera «Tesi e laurea» e, dalla pratica di tesi, «Invita l'azienda».</li><li><b>Convenzioni più sicure</b>: la bozza si salva da sola ogni 30 secondi e si riprende alla riapertura; prima di lasciare un modulo lungo con modifiche non salvate il browser avvisa.</li><li><b>Tutorato</b>: nel registro si può correggere una riga, ripetere l'ultima, usare la stessa attività su più giorni e scaricare il riepilogo mensile in PDF; se il registro resta fermo il tutor riceve un promemoria dopo 7 giorni.</li><li><b>Direttore</b>: dopo l'accesso, il Direttore e il vice possono scegliere di aprire il Cruscotto.</li><li><b>Tirocini di Scienze motorie</b>: un numero massimo di tirocinanti per azienda (di partenza 2), una pagina con accesso e senza nomi, e l'importazione del report del TSP.</li><li><b>Didacticus</b>: può usare Gemini per rispondere (se acceso dall'amministratore) e legge le pagine di unical.it e dibest.unical.it; l'amministratore può simulare l'accesso di un'altra persona con «Entra come», ogni volta registrato.</li><li><b>Sicurezza dei dati personali</b>: l'email del profilo cambia solo con il codice di 6 cifre inviato al nuovo indirizzo, con avviso al vecchio.</li><li><b>Telefono e tema scuro</b>: tabelle a schede e pulsanti da 44 px, contrasti rivisti nel tema scuro e avvisi sul dispositivo per i compiti in scadenza (al massimo uno al giorno).</li></ul>"
        + '<h3>Testi delle email</h3><p>Tutte le email che il portale invia hanno <b>oggetto, testo e pulsante modificabili</b> dalla pagina «Testi delle email», senza toccare il codice: per ognuna si vede quando parte e a chi arriva, si scrive con i segnaposto (<code>{{tutor}}</code>, <code>{{bando}}</code>…) che il portale riempie da solo, si guarda l\'anteprima, ci si manda una prova e si può tornare al testo originale. I moduli sono <b>schede</b> in alto e la ricerca cerca in tutti. Chi gestisce il tutorato modifica quelle del tutorato; gli amministratori tutte. Le modifiche restano nel registro attività.</p>'
        + '<h3>Uffici e persone</h3><p>Gli uffici del Dipartimento (Direzione, protocollo, uffici dei corsi, segreteria…) e di Ateneo (per esempio i pagamenti) si creano <b>una volta sola</b> e si richiamano in pratiche, tutorato, risorse e convenzioni. Le persone si scelgono <b>sempre dall\'anagrafe di Ateneo</b>; la Direzione ha Direttore e vice.</p>'
        + gal(fig('map_adm_uffici', '<b>Un ufficio</b> con le sue persone e il suo ruolo nelle pratiche.'),
              fig('map_adm_uffici_direzione', '<b>La Direzione</b>: chi firma ora e l\'interruttore «Direttore assente».'),
              fig('map_adm_uffici_ateneo', '<b>Gli uffici di Ateneo</b>.'))
        + '<h3>Sistema e controlli</h3>'
        + fig('map_adm_portale_home', '<b>Gestione del portale:</b> in alto i controlli da guardare (controlli del sito, email non partite, persone non più in anagrafe, Direttore), poi tutte le sezioni.')
        + gal(fig('map_adm_sistema', '<b>Email e controlli:</b> invio, backup, controllo notturno del sito.'),
              fig('map_adm_sistema_promemoria', '<b>Promemoria e riepilogo con l\'agenda:</b> i promemoria si sospendono per Direttore e uffici; il riepilogo settimanale con l\'agenda parte spento e lo accende l\'amministratore.'),
              fig('map_adm_audit', '<b>Il registro operazioni</b>: chi ha fatto cosa e quando.'))
    )))

    # 15 ---------------------------------------------------------------------------------------------
    S.append(('fiducia', 'Fiducia e privacy', sezione('fiducia', '17', 'Firme, protocollo, privacy',
        'Il portale è pensato per documenti che valgono: firmati, protocollati, tracciati e protetti.',
        griglia(
            card('Firme digitali', '<p>I documenti importanti si firmano in formato <b>PAdES</b> (PDF firmato). Il portale controlla che la firma sia integra e del titolare.</p>'),
            card('Protocollo', '<p>Numero e data si registrano passo per passo; il file protocollato resta collegato alla pratica.</p>'),
            card('Privacy', '<p>Informativa, cookie solo tecnici, dati conservati per il tempo necessario e poi cancellati o anonimizzati. Nessun servizio esterno riceve i tuoi dati.</p>'),
            card('Tracciabilità', '<p>Ogni operazione di gestione e ogni accesso ai verbali pubblici è registrato, con l\'indirizzo IP reso anonimo.</p>'),
            card('Copie di sicurezza', '<p>Ogni notte il database e i file sono copiati e riletti per verificarli; il sito si autocontrolla e avvisa se qualcosa non va.</p>'),
            card('Accessibilità', '<p>Struttura dei titoli, navigazione da tastiera, contrasti verificati e carattere ingrandibile.</p>'),
        )
    )))

    # 16 ---------------------------------------------------------------------------------------------
    S.append(('guide', 'Guide', sezione('guide', '18', 'Se hai bisogno di aiuto',
        'Il portale ha le sue guide «Come si fa», una per ruolo, con le schermate. Si trovano nella pagina <code>guida.php</code> e si possono stampare.',
        griglia(
            card('Studente', '<p>Presentare una pratica, seguirla, prenotare aule.</p>'),
            card('Docente e relatore', '<p>Confermare, firmare, gestire il ricevimento.</p>'),
            card('Uffici', '<p>Protocollo, ufficio del corso, referente del consiglio.</p>'),
            card('Responsabile di risorsa', '<p>Approvare, controllare il diario e le scadenze.</p>'),
            card('Tutor', '<p>Lettera di incarico, registro e pagamento.</p>'),
            card('Scuole', '<p>La convenzione FSL passo per passo; l\'elenco degli studenti.</p>'),
            card('Chi risponde all\'assistenza', '<p>Le chat, «Prendo io», le note e le statistiche; come istruire Didacticus.</p>'),
            card('Direttore', '<p>Cruscotto, firme e «Direttore assente».</p>'),
            card('Amministratore', '<p>Moduli, uffici, utenti, backup e ripristino.</p>'),
        )
        + gal(fig('map_pub_guida', '<b>L\'indice delle guide.</b>'), fig('map_pub_guida_studente', '<b>Una guida</b> (qui quella dello studente), con le schermate.'))
    )))
    return S
