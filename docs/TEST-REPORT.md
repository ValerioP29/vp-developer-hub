# Report di collaudo — 20 ottobre 2026

Applicazione eseguita realmente nell’ambiente locale con PHP 8.4.25, Laravel 13.37.0, PostgreSQL 16.15 e Node 24.18.0. Dati esclusivamente fittizi. Nessun test eseguito contro servizi o database dei clienti.

## Esiti

| Verifica eseguita | Esito | Evidenza |
|---|---|---|
| PHPUnit, SQLite in memoria | **80 test, 266 asserzioni, tutti superati** | [sqlite.json](test-results/sqlite.json) |
| Stessa suite, PostgreSQL dedicato | **80 test, 266 asserzioni, tutti superati** | [postgresql.json](test-results/postgresql.json) |
| Processi PHP indipendenti su PostgreSQL | **3 scenari concorrenti superati** | [concurrency.txt](test-results/concurrency.txt) |
| Browser Chromium / Playwright | **6 test superati**, desktop e mobile | [browser.txt](test-results/browser.txt) |
| Accessibilità automatizzata axe | Nessuna violazione serious/critical nelle pagine login e dashboard chiara/scura verificate | Inclusa nella suite browser |
| CSRF reale via HTTP | Richiesta senza token respinta con **419** | Suite browser |
| Build Vite/Tailwind, font locali | **Riuscita** | [frontend-build.txt](test-results/frontend-build.txt) |
| Laravel Pint | **Superato** | [style.txt](test-results/style.txt) |
| Validazione Composer e requisiti PHP | **Superati** | [composer-validation.txt](test-results/composer-validation.txt), [php-platform.txt](test-results/php-platform.txt) |
| Audit dipendenze Composer | **0 advisory**, 0 dipendenze abbandonate segnalate | [composer-audit.json](test-results/composer-audit.json) |
| Audit dipendenze npm | **0 vulnerabilità segnalate** | [npm-audit.json](test-results/npm-audit.json) |
| Dump PostgreSQL e ripristino isolato | **Riuscito**, conteggi corrispondenti su 10 tabelle principali | [restore.json](test-results/restore.json) |
| Archivio file privati e ripristino separato | **Riuscito**, SHA-256 corrispondente per tutti i file della prova | [restore.json](test-results/restore.json) |
| Cache configurazione, route e viste | **Compilate**, suite browser eseguita anche con cache attiva | Comandi Artisan eseguiti localmente |
| Health finale | **HTTP 200**, `{"status":"ok"}` | Richiesta HTTP locale dopo le migrazioni finali |
| Compose e script operativi | YAML analizzato; dipendenze servizi controllate; sintassi Bash/sh valida | Verifica statica locale |

Gli audit delle dipendenze sono una rilevazione alla data della prova, non una garanzia futura. I test automatici di accessibilità non sostituiscono una revisione manuale completa con tecnologie assistive.

## Copertura funzionale

- Login/password errata/logout, registrazione pubblica assente, recupero password, password forte, MFA obbligatoria admin, challenge MFA, codice di recupero monouso, rate limiting, cliente sospeso.
- Scope fail-closed per guest, IDOR su interventi, ticket, documenti, report e slug; permessi per ruoli e revoche granulari; impossibilità di creare amministratori globali dal team cliente.
- Associazioni cross-tenant respinte dal controller e dalle chiavi esterne composte; impossibilità di trasferire una risorsa a un altro tenant.
- Rendering schermate amministratore/moduli, CRUD cliente/progetto/applicativo, archiviazione con conservazione storico, filtri, dettagli contrattuali e escaping XSS.
- Pacchetto a ore, canone fisso, consuntivo, attivazione approvata, importi in centesimi, date di validità, duplicati mensili, storicizzazione prezzi, scadenza e riporto. Anche i secondi residui dei timer vengono conservati senza perdita nel riporto.
- Extra senza autorizzazione, approvazioni pendenti/rifiutate, limiti monetari e temporali, decisione cliente amministratore e impossibilità di decidere due volte o su altro tenant.
- Non fatturabile e fuori pacchetto, nuovi sviluppi separati, confini del mese in Europe/Rome, passaggio all’ora legale e rifiuto degli orari inesistenti.
- Sovrapposizioni, registrazioni adiacenti, tempi futuri, pausa/ripresa e retry, un solo timer, correzione con audit e revisione, blocco dei mesi consolidati.
- Fatturato distinto da Pagato, data pagamento obbligatoria, voci manuali separate e nessuna aliquota automatica.
- Ticket senza consumo ore, commenti pubblici/interni, conferma/riapertura, allegati, file eseguibili respinti, download privati, nuove versioni.
- PDF realmente generato (`%PDF-`), CSV con neutralizzazione formule, note interne escluse; notifiche configurabili e implementate come notifiche in coda.
- Export ZIP dati/documenti senza segreti di autenticazione, permessi del file; cancellazione controllata con archivio e frase esatta, conservazione degli altri tenant.
- Riepiloghi aggregati di 22 tenant ottenuti con massimo 7 query, senza una query aggiuntiva per ogni cliente.

## Concorrenza

Lo script non simula il lock in memoria: avvia due processi PHP separati e connessioni PostgreSQL indipendenti per ciascun caso. Verifica:

1. Due avvii simultanei del timer → uno accettato, uno respinto; una sola riga timer.
2. Due registrazioni simultanee dello stesso intervallo → una accettata, una respinta.
3. Due correzioni della stessa revisione → una accettata, una respinta; revisione incrementata una sola volta e durata attesa.

## Browser e ispezione visuale

Schermate realmente acquisite e ispezionate:

- [Dashboard amministratore desktop](screenshots/admin-desktop.png)
- [Dashboard cliente desktop](screenshots/client-desktop.png)
- [Dashboard cliente mobile](screenshots/client-mobile.png)
- [Area cliente mobile scura](screenshots/client-mobile-dark.png)

Viewport desktop 1440×1050, mobile 390×844; verificata assenza di overflow orizzontale nel dashboard mobile. Testati navigazione mobile, tema, PDF scaricato dal browser, creazione richiesta/commento/allegato e CRUD progetto amministratore dopo MFA reale. La configurazione MFA del solo account demo è stata revocata dopo il collaudo per consentire la configurazione personale al primo accesso di consegna.

## Problemi individuati e corretti durante il collaudo

Valori predefiniti dei modelli appena creati; confronto date su SQLite; fuso orario implicito PostgreSQL; validità del contratto al primo giorno del mese; calcolo durante cambio ora; precisione dei secondi riportati; compilazione di direttive Blade adiacenti; filtro ricerca portabile; contrasto testi; bootstrap dello script di concorrenza. I test di regressione pertinenti sono inclusi.

## Non eseguito / da completare in staging

- Build e avvio Docker/Compose: Docker non è disponibile nell’ambiente. Il job CI container è incluso ma non è stato eseguito su GitHub.
- Deployment Coolify, certificato HTTPS reale, DNS/Cloudflare e proxy di produzione: nessuna modifica effettuata.
- Invio email verso un server SMTP reale: non sono state fornite credenziali. Flussi applicativi verificati con notifiche simulate/configurazione locale; recapito e configurazione del provider richiedono prova staging.
- Script container di backup/ripristino end-to-end: verificata la sintassi. Sono state eseguite separatamente una prova reale PostgreSQL e una prova archivio file, non un ripristino dello stack Docker completo.
- Load test di produzione, pentest indipendente, audit legale/GDPR e verifica completa con screen reader.

Questi punti sono passaggi di rilascio espliciti. La consegna è collaudabile localmente; non è una dichiarazione di produzione già validata o pubblicata.
