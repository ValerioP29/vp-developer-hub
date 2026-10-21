# VP Developer Hub

Portale privato multi-cliente di **Valerio Persiani Developer**. Applicazione Laravel con frontend Blade personalizzato, PostgreSQL, autenticazione Fortify e MFA TOTP. Nessuna connessione ai gestionali dei clienti.

## Cosa contiene

- Workspace amministratore e portale cliente responsive, tema chiaro/scuro, font locali e wordmark tipografico.
- Clienti, referenti, autorizzazioni per ruolo con revoche granulari, progetti, applicativi e servizi.
- Contratti con attività incluse/escluse; catalogo pacchetti modificabile; attivazioni mensili approvate e storicizzate.
- Pacchetti a ore, assistenza a consuntivo e canone fisso. Denaro in centesimi, tempo in secondi, riporto opzionale dopo consolidamento.
- Interventi, note pubbliche/interne, registrazione manuale, timer pausa/ripresa, correzioni con revisione e audit. Nessuna sovrapposizione per operatore.
- Ticket, commenti, priorità, cronologia, riapertura e allegati privati. Le richieste non consumano ore.
- Approvazioni esplicite degli extra con tetto di tempo e importo; ore non autorizzate escluse dai compensi.
- Documenti versionati, download autorizzati e logo aziendale privato.
- Riepiloghi mensili, PDF/CSV, consolidamento, riferimenti fattura e data pagamento effettivo. Voci manuali separate dai compensi, senza aliquote predefinite.
- Email configurabili in coda, scheduler, audit, esportazione amministrativa e cancellazione controllata del tenant.
- Dockerfile, Compose per Coolify, CI, script di backup/ripristino e test automatici.

## Prerequisiti

PHP **8.4+** (il lock corrente richiede PHP 8.4), Composer 2, PostgreSQL 16+, Node 24 LTS, npm. Estensioni PHP: `pdo_pgsql`, `mbstring`, `xml`, `dom`, `fileinfo`, `intl`, `gd`, `zip`, `bcmath`, `openssl`, `ctype`, `tokenizer`; `pcntl` per worker Linux. SQLite serve soltanto alla suite rapida. Produzione e test di concorrenza richiedono PostgreSQL.

## Installazione locale

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Creare un database **dedicato**, impostare `DB_*` in `.env` e mantenere la connessione separata da qualunque altro gestionale.

```bash
php artisan migrate
php artisan db:seed
npm run build
php artisan hub:create-admin
php artisan serve --host=127.0.0.1 --port=8080
```

Aprire `http://127.0.0.1:8080`. La password amministratore viene richiesta senza mostrarla nel terminale. Al primo accesso è obbligatoria la configurazione MFA, con conferma del codice TOTP. Conservare i codici di recupero. Non esiste registrazione pubblica.

Per lo sviluppo, in terminali separati:

```bash
php artisan queue:work --tries=3 --timeout=90
php artisan schedule:work
```

Usare `npm run build` dopo le modifiche frontend. La CSP è restrittiva e questa configurazione di collaudo serve gli asset compilati; non espone il server Vite.

## Demo fittizia

```bash
php artisan hub:demo --credentials-file=storage/app/demo-credentials.txt
```

Solo `APP_ENV=local` o `testing`. Crea tre aziende fittizie, i cinque applicativi GED richiesti come **anagrafiche dimostrative**, contratti, sei mesi di attività e richieste. Le attivazioni demo sono esplicitamente create dal comando demo; il seed ordinario installa soltanto categorie e catalogo e non applica canoni a clienti reali.

Account: `admin@vp-hub.test`, `cliente@vp-hub.test`, `utente@vp-hub.test`. Le password sono casuali, diverse per account e scritte nel file privato con permessi `0600`, escluso dal repository. Non sono nel README. Se la demo esiste, il comando si interrompe senza sovrascriverla.

I test browser configurano realmente la MFA dell’amministratore demo: dopo una nuova esecuzione il segreto di collaudo rimane in `storage/app/browser-mfa-secret.txt` (privato, escluso da Git). Usarlo nell’app TOTP esclusivamente per l’account demo; accedendo a `/security` si possono conservare i codici di recupero o riconfigurare MFA. Per questa consegna la configurazione MFA di collaudo è stata revocata: al primo accesso amministratore configura la tua app. Non usare account o segreti demo in produzione.

## Test

```bash
php artisan test
vendor/bin/pint --test
npm run build
composer audit
npm audit --audit-level=high
```

La suite standard usa un database SQLite in memoria. Per verificare lo stesso codice su PostgreSQL, **usare un database vuoto dedicato con suffisso `_test`**: `RefreshDatabase` modifica il database di test.

```bash
DB_CONNECTION=pgsql DB_DATABASE=vp_hub_test php artisan test
APP_ENV=testing DB_DATABASE=vp_hub_test php scripts/concurrency.php
```

Il secondo comando avvia processi PHP indipendenti: due avvii timer, due registrazioni sovrapposte e due correzioni della stessa revisione. Richiede PostgreSQL e rifiuta nomi database senza suffisso `_test`.

Browser, con app locale già avviata e demo presente:

```bash
npx playwright install --with-deps chromium
npx playwright test
```

I test browser aggiungono dati fittizi e configurano MFA all’account demo. Report in `storage/app/playwright-report`, screenshot di consegna in `docs/screenshots`. Non usare l’URL di produzione come `HUB_TEST_URL`.

Esiti effettivamente rilevati e limiti di verifica: [TEST-REPORT.md](docs/TEST-REPORT.md).

## Documentazione

- [Guida amministratore](docs/ADMIN.md)
- [Guida cliente](docs/CLIENT.md)
- [Architettura e schema](docs/ARCHITECTURE.md)
- [Setup Coolify, variabili e rilascio](docs/DEPLOYMENT.md)
- [Backup e ripristino](docs/BACKUP.md)
- [Sicurezza, trattamento dati e retention](docs/SECURITY-PRIVACY.md)
- [Report dei test](docs/TEST-REPORT.md)
- [Consegna e ambiente locale](docs/DELIVERY.md)

## Confini della prima versione

Non comprende fatturazione elettronica, pagamenti, firma elettronica, integrazioni con applicativi cliente, SLA impliciti, consulenza fiscale o attestazione di conformità GDPR. Le voci fiscali sono importi manuali separati e non vengono determinate dal software. L’amministratore globale è fidato e può accedere a tutti i tenant; il database non usa RLS, l’isolamento applicativo combina scope, controlli server e chiavi esterne composte.

Il deploy reale richiede SMTP, segreti, accesso Coolify, dominio HTTPS e verifica dei backup. Il codice non crea DNS e non pubblica automaticamente in produzione. I contratti firmati restano documenti esterni caricati nel portale.
