# Deployment dedicato con Coolify

## Stato della consegna

Sono inclusi Dockerfile multi-stage, Compose, health check e configurazione CI. Nessun deploy o modifica DNS è stato eseguito. Docker non è installato nell’ambiente di sviluppo: la build dell’immagine deve essere eseguita nel runner CI o in staging prima della pubblicazione. Il collaudo dell’applicazione viene eseguito direttamente con PHP, PostgreSQL e Chromium.

## Risorse isolate

Creare un nuovo progetto/ambiente Coolify per VP Developer Hub. Non riutilizzare database, volumi, utenti DB o variabili dei gestionali dei clienti. Per più stack sullo stesso host assegnare nomi Compose distinti (per esempio `COMPOSE_PROJECT_NAME=vp-hub-staging` e `vp-hub-production`); Coolify deve mantenere identificativi separati. Il Compose definisce `web`, `worker`, `scheduler`, `database`, volume `hub-storage` e volume `hub-postgres`; Docker Compose prefissa i nomi in base al progetto. Nessuna porta del database è pubblicata.

Fonti operative: [Coolify Docker Compose](https://coolify.io/docs/applications/builds/docker-compose), [storage persistente](https://coolify.io/docs/applications/configuration/persistent-storage), [deploy Laravel](https://laravel.com/framework/docs/deployment). Per applicazioni Compose, configurazione e health check dei servizi risiedono nel file Compose. I volumi sono locali al server: questo stack presuppone un singolo server, non storage distribuito.

## Variabili

| Variabile | Utilizzo / valore |
|---|---|
| `APP_KEY` | Obbligatoria, permanente; generare con `php artisan key:generate --show` e custodire in secret store |
| `APP_URL` | `https://hub.valeriopersiani.it` quando il dominio è configurato |
| `APP_ENV`, `APP_DEBUG` | `production`, `false` |
| `DB_DATABASE`, `DB_USERNAME` | Database/utente dedicati, default `vp_hub` |
| `DB_PASSWORD` | Segreto casuale lungo, obbligatorio |
| `DB_HOST`, `DB_PORT` | Compose imposta `database`, `5432` |
| `TRUSTED_PROXIES` | IP/CIDR reali del reverse proxy Coolify; non usare `*` senza una rete d’ingresso rigorosamente isolata |
| `SESSION_SECURE_COOKIE` | `true` in produzione; `false` solo in locale HTTP |
| `SESSION_ENCRYPT` | `true` |
| `MAIL_MAILER` | `smtp` per utenti reali; `log` solo per sviluppo |
| `MAIL_SCHEME` | `smtp` per STARTTLS oppure `smtps` secondo il provider |
| `MAIL_HOST`, `MAIL_PORT` | Server SMTP e porta del provider |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | Credenziali SMTP, nei segreti Coolify |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Mittente verificato e nome del servizio |
| `HUB_EMAIL_NOTIFICATIONS` | Abilita notifiche ticket; anche il singolo cliente può disabilitarle |
| `HUB_UPLOAD_MAX_KB` | 10240; allineare limiti PHP/Apache/proxy per cambiarlo |
| `HUB_AUDIT_RETENTION_DAYS` | 730 iniziale, da adattare alla policy documentata |
| `HUB_SESSION_RETENTION_DAYS` | 7 per eliminazione delle righe scadute |
| `RUN_MIGRATIONS` | `false` normalmente; solo web lo considera se esplicitamente impostato `true` |

In Compose sessioni, cache e coda usano il DB. Il worker usa timeout 90 secondi e il job retry_after configurato in Laravel deve essere superiore (configurato a 120 secondi). Non richiede Redis. File privati su disco locale condiviso fra i container dello stack; nessun `storage:link` necessario.

## Primo rilascio, da autorizzare

1. Creare un repository **privato** dedicato e caricare i sorgenti. Collegarlo come nuova applicazione Git-based con build pack Docker Compose e `compose.yaml`.
2. Impostare tutte le variabili e controllare i volumi. Conservare `APP_KEY` e password DB fuori dal repository.
3. Eseguire la build in staging. Per una prima inizializzazione si può abilitare temporaneamente `RUN_MIGRATIONS=true` soltanto sul servizio web. In alternativa avviare il database ed eseguire `docker compose run --rm web php artisan migrate --force` come passaggio esplicito di rilascio.
4. Eseguire `php artisan db:seed --force` nel container web: solo catalogo e categorie. **Non** usare `hub:demo` in produzione.
5. Eseguire interattivamente `php artisan hub:create-admin`. Configurare MFA al primo accesso e conservare i codici di recupero.
6. Configurare dominio del servizio web sulla porta interna 80. DNS e HTTPS richiedono un intervento autorizzato: record del sottodominio verso la VPS, certificato valido in Coolify, Cloudflare TLS **Full (strict)** se il proxy Cloudflare è usato. Non modificare record di altri servizi.
7. Controllare che Laravel riconosca HTTPS attraverso il proxy; verificare cookie `Secure`, header HSTS, assenza di redirect a HTTP e link delle email con dominio corretto.
8. Verificare `/health`: controlla disponibilità database e presenza dello schema applicativo richiesto, senza esporre dettagli. Solo il servizio web è instradato dal proxy; worker e scheduler non devono avere un dominio.
9. Inviare un invito a un account di collaudo, verificare email SMTP e reset password; controllare esecuzione worker e scheduler. Le notifiche di ticket sono asincrone; gli inviti/reset usano il broker password.
10. Creare i clienti reali, testare due tenant diversi e completare un backup/ripristino isolato prima di aprire gli accessi.

## Rilasci successivi

Eseguire CI, backup e prova in staging. Applicare migrazioni con `php artisan migrate --force --isolated` usando un solo processo. Riavviare web/worker/scheduler sulla stessa versione; non mischiare release durante modifiche di schema incompatibili. L’entrypoint aggiorna cache di configurazione, route e viste e non modifica DNS o crea utenti. `RUN_MIGRATIONS` torna a `false` dopo il rilascio.

Mantenere un solo scheduler. La manutenzione giornaliera esegue pulizia dei link di reset e dei job falliti; la retention audit/sessioni produce di default un’anteprima, senza cancellare automaticamente eventi audit. Applicarla esplicitamente secondo la procedura privacy.

Non usare `migrate:fresh`, `docker compose down -v` o cancellazioni volumi in produzione. Per rollback scegliere la precedente immagine solo se compatibile con lo schema; altrimenti ripristinare il backup in un ambiente separato e verificato.

## Monitoraggio

Monitorare `/health`, spazio volumi, scadenza certificati, esiti dei backup, `php artisan queue:failed`, restart dei container e log stderr. I worker non hanno un falso health HTTP: controllare uptime/processo, coda in attesa e fallimenti dal sistema di monitoraggio. Nessun servizio di monitoraggio esterno è stato connesso automaticamente.
