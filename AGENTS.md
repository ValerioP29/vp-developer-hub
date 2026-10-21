# VP Developer Hub — regole del progetto

Applicazione privata Laravel 13 / PHP 8.4 / PostgreSQL. Leggere README e docs/ARCHITECTURE.md prima di intervenire sui flussi economici o sulle autorizzazioni.

- Lavorare soltanto in questo progetto; nessun accesso a GED, CRM, WA_NODE, RAG o loro database.
- Nessun deploy, modifica DNS, cancellazione di dati reali o pubblicazione repository senza autorizzazione esplicita.
- Mantenere scope tenant fail-closed e autorizzazioni server. Non usare `withoutGlobalScopes` nei controller per aggirare un controllo di accesso.
- Relazioni tenant protette anche da chiavi esterne composte. Non fidarsi degli ID o dello slug inviati dal browser.
- Importi in centesimi e durata in secondi. Attivazioni approvate e mesi consolidati mantengono le condizioni storiche. Nessun extra senza autorizzazione.
- Le operazioni sul tempo usano i servizi transazionali e il lock dell'operatore; le operazioni economiche il lock tenant.
- Segreti, upload, log e tracce browser non entrano nel repository. `.env.example` resta senza credenziali.
- Usare Blade/componenti e asset locali; evitare nuove dipendenze senza un'esigenza concreta.
- Prima della consegna eseguire i test pertinenti, Pint e build. Per concorrenza usare PostgreSQL con database `_test`. Non eseguire test distruttivi su database reali.
- Documentare ciò che è stato verificato e ciò che richiede staging o credenziali. Non dichiarare eseguiti test non eseguiti.
