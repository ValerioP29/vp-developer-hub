# Guida amministratore

## Primo accesso

Creare l’account con `php artisan hub:create-admin`. Impostare una password lunga e personale; completare la configurazione TOTP e conservare i codici di recupero fuori dal server. La pagina Sicurezza richiede nuovamente la password dopo la scadenza della conferma. Un Super Admin senza MFA confermata può configurarla, ma non accedere al centro operativo.

## Avvio di un rapporto

1. In **Clienti**, aggiungere azienda, identificativo URL, dati fiscali facoltativi, contatti e referente. Nessun campo fiscale è inventato dal software.
2. Aggiungere progetti, applicativi e servizi indicando la stessa azienda. I menu dipendenti filtrano le scelte; il server e il database verificano comunque l’associazione.
3. In **Contratti**, descrivere modello economico, date, canone, tariffa oraria, minuti inclusi, attività incluse/escluse e condizioni degli extra. Il contratto deve coprire l’intero mese da attivare.
4. Caricare il contratto firmato in **Documenti**, categoria Contratto, associandolo all’azienda e al contratto. La piattaforma non appone firme elettroniche.
5. In **Consuntivi**, scegliere il cliente e aprire «Approva un’attivazione mensile». Inserire esplicitamente prezzi e ore per quel mese, quindi confermare. Un riferimento al catalogo **non copia né impone** un prezzo: l’accordo effettivo è quello indicato nel modulo.
6. In **Referenti e accessi**, creare gli utenti. Ricevono il link per impostare la password via SMTP. Il ruolo amministratore cliente può consultare economia, decidere sulle approvazioni e creare utenti cliente. Il ruolo utente cliente vede le attività e può usare ticket/documenti. Si possono revocare singole capacità.

Il catalogo iniziale Light / Standard / Plus / Extended è dimostrativo e modificabile. Non esistono rinnovi o attivazioni automatiche. Per assistenza a consuntivo inserire canone e minuti inclusi a zero; per canone fisso impostare l’importo e gli eventuali minuti compresi. Le tariffe delle attivazioni approvate restano immutabili; cambiare il contratto o il catalogo influenza soltanto gli accordi successivi. Verificare prima di approvare.

## Registrazione del lavoro

Creare un intervento da dashboard o da Interventi. I campi essenziali sono cliente, data, titolo, categoria e stato. Si possono collegare progetto, applicativo e richiesta. Descrizione e note pubbliche sono condivise; note interne sono escluse dal portale cliente e dai report.

- **Manuale:** indicare inizio in Europe/Rome e durata intera in minuti, da 1 a 1440. Non sono accettati intervalli futuri o sovrapposti ad altri dello stesso operatore, anche per clienti diversi.
- **Timer:** avviare, interrompere e riprendere. Ogni pausa salva un segmento; il successivo avvio ne crea uno nuovo. Esiste un solo timer attivo per operatore. I timer molto lunghi vengono suddivisi in segmenti quando fermati; verificare e correggere eventuali dimenticanze prima della chiusura mensile.
- **Correzioni:** espandere la registrazione, indicare inizio, minuti e motivo. Un controllo di revisione impedisce che due modifiche concorrenti si sovrascrivano. Le registrazioni create da un altro operatore non sono correggibili da questa schermata.
- **Non fatturabile:** resta nello storico, ma non consuma pacchetto e non contribuisce agli importi.
- **Fuori pacchetto:** resta fatturabile ma necessita dell’autorizzazione extra; non consuma le ore incluse. La categoria Nuovo sviluppo viene sempre registrata fuori manutenzione.

Il filtro Data attività usa la data dell’intervento; i consuntivi e le esportazioni usano i segmenti temporali effettivi. I segmenti a cavallo del mese sono ripartiti ai confini di Europe/Rome. Gli orari inesistenti del passaggio all’ora legale vengono rifiutati; per l’ora ripetuta in autunno controllare l’intervallo risultante e preferire il timer in tempo reale.

## Ticket e autorizzazioni

I ticket non generano automaticamente ore. Impostare priorità e stato, rispondere pubblicamente o con nota interna. Collegare gli interventi tramite il loro modulo. Il cliente può confermare la risoluzione o richiedere riapertura con motivazione.

Per gli extra, creare un’approvazione con mese, minuti massimi, importo massimo e descrizione. Il cliente amministratore può approvare o rifiutare. Se l’autorizzazione arriva fuori dal portale, l’amministratore può registrarla ma deve indicare un riferimento. La decisione è definitiva e tracciata. Le approvazioni approvate di uno stesso mese formano un tetto cumulativo: valgono sia il limite temporale sia quello monetario, applicati alla tariffa storica. Non si fattura semplicemente il massimale approvato: si conteggia soltanto il tempo effettivamente registrato entro i limiti.

## Chiusura del mese

1. Fermare tutti i timer e controllare le registrazioni.
2. Risolvere ore senza autorizzazione: ottenere l’approvazione oppure classificare gli interventi non fatturabili se il lavoro è gratuito. Il software non forza la fatturazione degli extra.
3. Verificare l’accordo e il report. Le ore residue scadono salvo riporto contrattuale. Per riportarle, consolidare il mese precedente **prima** dell’attivazione del successivo.
4. Inserire eventuali voci separate con importo e motivazione verificati: non esistono aliquote o aumenti automatici.
5. A mese concluso, confermare **Consolida mese**. I dati economici e temporali interessati non sono più modificabili dalle schermate.
6. Scaricare PDF/CSV. Il PDF è un riepilogo, non una fattura. Può essere caricato nell’archivio per conservarne una copia.
7. Inserire manualmente numero e data della fattura emessa nel sistema esterno. Lo stato diventa Fatturato. Solo con una data di pagamento effettivo si può impostare Pagato.

## Documenti, accessi e storico

Gli upload sono privati; si accettano PDF, PNG/JPEG, TXT e CSV entro 10 MB. Per una nuova versione, scegliere il documento precedente. Il file precedente resta disponibile. Per il logo usare PNG/JPEG, categoria Logo, visibilità cliente attiva; appare sul dashboard aziendale.

Sospendere o archiviare un cliente impedisce l’accesso ai suoi referenti mantenendo lo storico. Disabilitare un utente revoca le sessioni memorizzate. L’archiviazione è la procedura ordinaria; la cancellazione definitiva è un’operazione CLI controllata, descritta nella guida privacy.

Il **Registro attività** mostra modifiche, autore e valori prima/dopo; nessun pulsante lo modifica. Le password, i segreti MFA e i percorsi dei file sono esclusi dai valori di audit. I dati liberi possono contenere informazioni personali: evitare contenuti non necessari.
