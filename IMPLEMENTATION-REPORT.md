# VP Developer Hub — correzione accesso e pagina bianca

Data: 2 novembre 2026. Implementazione locale; nessun deploy, commit, push, modifica DNS o accesso ai database reali. Nessuna password esistente modificata. Nessuna nuova dipendenza applicativa.

## Diagnosi verificata

Il commit di produzione indicato, `e85ad57a77f17273224952353c41b14613976403`, contiene una riga vuota **prima di `<?php`** in `bootstrap/app.php:1`. PHP emette quindi un byte prima che Laravel possa inviare gli header. Con output buffering disabilitato, Symfony tenta di impostare lo status HTTP in `Response::sendHeaders()`; Laravel converte l'avviso `Cannot modify header information - headers already sent` in eccezione. Anche la risposta di errore non riesce a inviare gli header. Con `display_errors=Off` il risultato è una pagina apparentemente vuota, HTTP 200, corpo `\n`.

Evidenze:

- Richieste GET pubbliche, senza login, a `https://hub.valeriopersiani.it/` e `/login`: entrambe HTTP 200, corpo di **1 byte (`0a`)**, assenza degli header CSP e sessione applicativi. Cloudflare riporta `DYNAMIC`.
- Riproduzione locale con il bootstrap estratto da quel commit, PHP 8.4, `output_buffering=0`, `display_errors=0`, sessione/cache in memoria: stesso HTTP 200 e stesso byte; log con origine dell'output alla riga 1 del bootstrap.
- Stessa riproduzione con il bootstrap senza byte iniziale: HTTP 302, `Location` valido e CSP presente.
- La rimozione della riga iniziale era **già presente nelle modifiche locali iniziali**: è stata conservata e coperta da un test in un processo PHP separato. I normali test HTTP Laravel possono non rilevare questo problema perché non inviano realmente gli header al browser.

Non è una pagina nascosta da CSS o JavaScript: in produzione non arrivava alcun documento HTML. Il progetto usa **Fortify + Blade**, senza Breeze, Jetstream, Livewire o Inertia. Il layout `resources/views/components/layout.blade.php` stampa il contenuto tramite `$slot` e carica asset locali con `@vite`. Il Dockerfile compila Vite e copia `public/build` nell'immagine finale; Apache serve `public/`. Non è stato necessario cambiare questi meccanismi o allargare la CSP.

I log Laravel locali contenevano errori storici di ottobre relativi a `$errors`, un template e una query SQLite; non dimostravano un errore corrente di produzione. I log stderr del container Coolify non sono accessibili in questa sessione: il messaggio relativo agli header è stato verificato nella riproduzione locale, non letto dal server reale.

## Modifiche funzionali

| File | Modifica |
|---|---|
| `bootstrap/app.php` | Bootstrap senza output iniziale; configura esclusivamente gli header proxy consentiti. |
| `config/hub.php` | Normalizza gli IP/CIDR configurati. L'allowlist passa da `config('hub.trusted_proxies')` al provider esistente, senza doppia lettura `env()` nel bootstrap; funziona con `config:cache`. |
| `routes/web.php` | `/` manda direttamente i guest a `/login` e gli autenticati a `/dashboard`; rimossa `/security`. |
| `config/fortify.php` | Identificativo di login `login`; resta attivo il recupero password; eliminata la feature e il limiter del secondo fattore. |
| `app/Providers/FortifyServiceProvider.php` | Ricerca per email o username normalizzati, controlli account/tenant attivi invariati, rate limiting aggiornato, rimosso challenge. Input malformati non causano errori del limiter. |
| `app/Http/Responses/LoginResponse.php` | Login riuscito porta alla dashboard; elimina destinazioni e stato di challenge residui dalle vecchie sessioni. |
| `app/Http/Middleware/HubAccess.php` | Eliminato l'obbligo di configurazione amministratore; restano revoca sessione e controlli account/tenant attivi. |
| `app/Models/User.php` | Rimosso il trait del secondo fattore; aggiunti username normalizzato e generazione automatica per nuovi utenti senza scelta esplicita. |
| `database/migrations/2026_11_02_000100_add_username_to_users.php` | Colonna username, popolamento degli utenti esistenti e indice univoco case-insensitive. |
| `app/Support/Username.php` | Regole comuni per username: 3–64 caratteri ASCII, lettere/numeri/punti/trattini/underscore, iniziale alfanumerica; niente `@` o spazi. |
| `app/Console/Commands/CreateAdmin.php` | Opzione `--username`, stampa dello username assegnato, eliminati messaggi di configurazione Authenticator. |
| `app/Console/Commands/SetUsername.php` | Comando amministrativo locale `hub:set-username`, validazione, transazione e audit; nessun cambio di password, ruolo o tenant. |
| `app/Console/Commands/Demo.php` | Rimosso il messaggio sul secondo fattore dalle istruzioni demo. |
| `app/Http/Controllers/TeamController.php`, `resources/views/hub/team.blade.php` | Username facoltativo nell'invito e visibile nell'elenco. Autorizzazioni e assegnazione server del tenant invariate. |
| `resources/views/auth/form.blade.php` | Solo identificativo email/username, password, pulsante e link recupero nel login; nessun “Ricordami” o challenge. |
| `resources/views/components/layout.blade.php` | Eliminato il link a Sicurezza; scheda account con username. Logout mantenuto. |
| `resources/views/hub/security.blade.php` | Eliminata la schermata di configurazione, QR, codici e recovery. |
| `resources/css/app.css`, `lang/it.json` | Rimossi stili e traduzioni della configurazione precedente. |
| `tests/Feature/AuthenticationTest.php` | Regressioni su bootstrap, HTML, email/username, sessioni, proxy, limiti, endpoint eliminati, migrazione e autorizzazioni inviti. |
| `tests/Feature/AccessTest.php`, `HubTestCase.php`, `OperationsTest.php` | Aggiornati login e fixture; test per account con vecchia configurazione che accede direttamente. |
| `tests/browser/hub.spec.js` | Eliminato enrollment automatico; aggiunti root, asset, CSP, username, logout e login senza JavaScript; screenshot/tracce solo in storage escluso da Git. |
| `README.md`, `docs/ADMIN.md`, `ARCHITECTURE.md`, `BACKUP.md`, `CLIENT.md`, `DELIVERY.md`, `DEPLOYMENT.md`, `SECURITY-PRIVACY.md`, `TEST-REPORT.md` | Istruzioni attuali aggiornate; vecchio report dei test esplicitamente storico. |

Pint ha corretto anche soli spazi preesistenti in `BillingController.php`, `DocumentController.php`, `ResourceController.php`, `TicketController.php`, `DataLifecycleTest.php` e `TicketDocumentTest.php`. Nessuna modifica funzionale a questi file. Le modifiche iniziali ai permessi eseguibili di `artisan`, `docker/entrypoint.sh`, `scripts/backup.sh` e `scripts/restore.sh` sono state conservate.

### Dati e riferimenti storici al secondo fattore

Nessuna route, impostazione, interfaccia, traduzione o flusso applicativo attiva il secondo fattore. Tutti gli endpoint `/two-factor-challenge` e `/user/*two-factor*`, oltre a `/security`, sono assenti e restituiscono 404 anche per utenti autenticati.

Le vecchie colonne cifrate rimangono nel database, **inerti e senza cancellazioni**. Restano volutamente nella lista dei campi nascosti di `User` e nel filtro di `Audit`, per non esporre segreti storici. Restano anche le migrazioni già distribuite e i riferimenti nei test di regressione e nella documentazione storica. Fortify conserva le sue dipendenze transitive nel lock Composer, ma la feature non è abilitata. Rimuovere questi filtri solo per eliminare ogni occorrenza testuale esporrebbe dati riservati.

## Migrazione e compatibilità

`2026_11_02_000100_add_username_to_users.php` è **additiva e sicura sui dati esistenti** nel percorso `up()`:

1. Aggiunge `users.username`, nullable, massimo 64 caratteri.
2. Assegna `user-<id>` a ogni utente già presente, indipendentemente dal nome visualizzato e senza collisioni tra tenant.
3. Crea l'indice globale univoco `users_username_unique` su `LOWER(username)` (PostgreSQL e SQLite).

Non modifica ID, email, nome, hash password, ruoli, tenant, permessi, timestamp o vecchi dati cifrati. I test confrontano tutti questi valori prima e dopo la migrazione. L'email esistente continua a funzionare. La colonna nullable mantiene compatibilità con eventuali scritture della versione precedente durante il rilascio; il nuovo modello assegna sempre uno username ai nuovi utenti. Se non scelto, usa `user-<ULID minuscolo>`.

La migrazione aggiorna le righe degli utenti e crea un indice: eseguirla in finestra di manutenzione, con un solo processo di rilascio. Il `down()` elimina solo il nuovo campo/indice e quindi perderebbe gli username scelti: **non eseguire rollback della migrazione in produzione**. Per un rollback applicativo lasciare la colonna aggiuntiva, compatibile con il vecchio codice.

Personalizzazione facoltativa dopo il rilascio, nel container web:

```bash
php artisan hub:set-username 'email-esistente@azienda.it' 'nome.scelto'
```

Il comando non crea utenti e non modifica password. Gli username sono globali perché il login non richiede un tenant. Conoscere uno username non concede alcun accesso senza password.

## Sicurezza mantenuta

- Fortify conserva autenticazione con password, CSRF, rigenerazione sessione al login e invalidazione sessione/token CSRF al logout.
- Login: 5 tentativi/minuto per identificativo normalizzato + IP, massimo 30/minuto per IP; errori generici per password errata, account inesistente/disabilitato e tenant sospeso.
- Nessuna registrazione pubblica o bypass; recupero password esistente conservato.
- Cookie di produzione database/encrypted, Secure, HttpOnly e SameSite=Lax; Compose già configurato correttamente.
- Proxy limitati agli IP/CIDR autorizzati. Test per `10.0.2.2` e rifiuto degli header inoltrati da client non fidati.
- CSP invariata (`script-src 'self'`), HSTS per richieste riconosciute HTTPS e asset locali.
- Scope fail-closed, chiavi esterne composte, controlli di ruolo e servizi transazionali economici/tempo invariati.

## Verifiche eseguite

| Verifica | Esito |
|---|---|
| Baseline prima delle modifiche, SQLite | 80 test / 266 asserzioni superati. |
| Suite finale SQLite in memoria | **98 test / 422 asserzioni superati**. |
| Suite finale PostgreSQL 16 dedicato `vp_hub_auth_test` | **98 test / 422 asserzioni superati**. |
| Concorrenza PostgreSQL, processi indipendenti | **PASS** timer, registrazione sovrapposta, correzione concorrente: una operazione accettata e una rifiutata per scenario. |
| `vendor/bin/pint --test` | **PASS**. |
| `npm run build` | **PASS**, Vite 8.3.4; manifest, CSS, JS e 7 font locali generati. |
| `git diff --check` | **PASS**. |
| Cache configurazione, route e viste | **PASS**, in percorsi temporanei dedicati; 46 route, nessuna route MFA. |
| Riproduzione HTTP prima/dopo senza output buffering | **PASS**, da 200/1 byte a redirect e header corretti. |
| Browser Chromium / Playwright | **9 test superati** (16,4 s): desktop/mobile, CRUD, allegati, PDF, accessibilità light/dark, CSRF HTTP reale, HTML/asset/CSP, username/logout e JavaScript disabilitato. |

I database di test sono stati creati in un'istanza PostgreSQL 16 temporanea in `/tmp`, senza porta TCP esposta, distinta da ogni database esistente. Il browser usa il database separato `vp_hub_auth_browser_test`, fixture demo e credenziali casuali private in `/tmp`. Le sessioni browser usano il driver database e la CSP reale; cache di configurazione, route e Blade attive. Nessuna traccia browser, log o credenziale è stata aggiunta al repository. I primi tentativi browser erano bloccati dal runtime mancante: Chromium e la sola libreria `libasound.so.2` sono stati scaricati/estratti in `/tmp`, senza installazioni di sistema; il collaudo completo è poi riuscito.

Comandi principali eseguiti (credenziali di collaudo e percorsi temporanei esclusi):

```bash
php artisan test --compact
vendor/bin/pint --test
npm run build
git diff --check
APP_ENV=testing DB_CONNECTION=pgsql DB_DATABASE=vp_hub_auth_test php artisan test --compact
APP_ENV=testing DB_CONNECTION=pgsql DB_DATABASE=vp_hub_auth_test php scripts/concurrency.php
npx playwright test
```

Per i comandi PostgreSQL sono stati impostati anche host socket, porta e utente dell'istanza temporanea; non usare le credenziali di produzione. Per i test browser i valori opzionali `HUB_TEST_CREDENTIALS` e `HUB_TEST_URL` selezionano ambiente/credenziali di collaudo. `HUB_TEST_PROXY_CLIENTS=true` assegna IP simulati distinti ai test quando **il solo server locale di test** considera fidato `127.0.0.1`, così i test indipendenti non condividono i contatori rate limit. Non disabilita i limiti. `PLAYWRIGHT_BROWSERS_PATH` e `LD_LIBRARY_PATH` sono stati usati solo per il runtime Chromium temporaneo.

### Verifiche ancora richieste su staging/produzione

- Build dell'immagine Docker e startup tramite entrypoint Apache: Docker non è installato qui. Verificata la build Vite reale e letto il Dockerfile, non eseguita una build container.
- Catena HTTPS completa Cloudflare → Traefik → Apache, IP effettivo del proxy e cookie su browser reale del dominio dopo il rilascio. Il bootstrap/proxy è coperto dai test, ma non è stato modificato o riavviato il server di produzione.
- Invio e ricezione reali SMTP e consegna del reset password: testato il broker locale con notifiche simulate, non credenziali SMTP reali.
- Worker/scheduler della nuova immagine e log stderr Coolify.

## Variabili d'ambiente

**Nessuna nuova variabile obbligatoria di produzione.** Conservare:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hub.valeriopersiani.it
TRUSTED_PROXIES=10.0.2.2
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

`10.0.2.2` deve essere l'IP che raggiunge realmente Apache, come indicato nell'ambiente attuale. Non sostituirlo con `*`. Conservare `APP_KEY`, credenziali DB e SMTP. I nomi `HUB_TEST_CREDENTIALS` e `HUB_TEST_PROXY_CLIENTS` sono soltanto opzioni dei test Playwright, non impostazioni da inserire in Coolify.

## Istruzioni esatte per il rilascio Coolify — da eseguire solo dopo autorizzazione

Le modifiche sono locali, non committate né inviate al remoto. Prima del rilascio occorre approvare e pubblicare una revisione su `main`, verificandola in staging. Un semplice restart dell'immagine vecchia non contiene la correzione; serve una nuova build.

1. Eseguire il backup PostgreSQL/file secondo `docs/BACKUP.md`, conservando `APP_KEY`. Non ricreare utenti, non eseguire seed demo o reset password.
2. Verificare le variabili sopra. Nel progetto Coolify **esistente** impostare temporaneamente `RUN_MIGRATIONS=true` per il servizio web; il Compose già impedisce al worker e scheduler di eseguire migrazioni.
3. In Coolify distribuire la revisione approvata di `main`, ricompilando l'immagine. Il Dockerfile esegue `npm ci` e `npm run build` nello stage asset. L'entrypoint ricostruisce le cache ed esegue `php artisan migrate --force` **prima** di avviare Apache. Usare un solo web/processo di migrazione per questo rilascio; riavviare web, worker e scheduler sulla stessa revisione. La migrazione deve terminare prima di aprire il traffico al nuovo web.
4. Nel terminale Coolify del container **web della nuova revisione**, eseguire questi comandi per verificare migrazione e cache (la migrazione è idempotente se già applicata dall'entrypoint):

```bash
cd /var/www/html
gosu www-data php artisan migrate --force --isolated
gosu www-data php artisan migrate:status
gosu www-data php artisan config:cache
gosu www-data php artisan route:cache
gosu www-data php artisan view:cache
gosu www-data php artisan route:list --path=login
gosu www-data php artisan route:list --path=two-factor
curl -fsS http://localhost/health
```

Se il terminale è già eseguito come `www-data`, omettere `gosu www-data`. L'assenza di route `two-factor` è l'esito previsto. Non eseguire `key:generate`, `migrate:fresh`, `db:wipe`, `down -v` o `migrate:rollback`.

5. Riportare `RUN_MIGRATIONS=false` nelle variabili Coolify dopo il rilascio. Verificare che tutti i servizi usino la stessa revisione e che esista un solo scheduler.
6. Da una macchina con accesso HTTPS verificare:

```bash
curl -sS -o /dev/null -D - https://hub.valeriopersiani.it/
curl -sS -L -o /tmp/vp-hub-login.html -w 'HTTP %{http_code}, bytes %{size_download}\n' https://hub.valeriopersiani.it/
curl -fsS https://hub.valeriopersiani.it/health
```

Atteso: `/` guest → 302 verso `/login`, risposta finale 200 con HTML completo e form email/username, `/health` → `{"status":"ok"}`. In browser verificare asset 200, CSP senza violazioni, cookie Secure e login sia email sia username → dashboard senza passaggi intermedi; provare logout, recupero password e un utente di ciascuno di due tenant di collaudo. Verificare `APP_URL` nei link email e nessun redirect HTTP.

In caso di esito anomalo consultare stdout/stderr del servizio web in Coolify: con `LOG_CHANNEL=stderr` non basta cercare `storage/logs/laravel.log`. Non rigenerare chiavi né cancellare sessioni/dati per mascherare errori.
