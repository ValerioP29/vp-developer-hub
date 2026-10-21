# Backup e ripristino

## Oggetti da proteggere

1. PostgreSQL: schema, dati, autorizzazioni applicative, audit, password cifrate/hash e segreti MFA cifrati.
2. `storage/app/private`: documenti e versioni, logo compreso.
3. `APP_KEY` permanente e configurazione dei servizi, custodite separatamente in secret store cifrato. Perdere `APP_KEY` impedisce di decifrare MFA e sessioni. Rigenerarla durante un ripristino non risolve il problema.
4. Sorgenti, `composer.lock`, `package-lock.json`, versione dell’immagine e configurazione dello stack.

Cache, viste compilate, sessioni scadute e log non sostituiscono un backup applicativo. I backup contengono dati riservati: cifrare e limitare accessi e retention anche delle copie esterne.

## Script Compose

Eseguire dalla directory della **sola installazione Hub** dopo avere verificato `docker compose ps` e nomi dei volumi:

```bash
BACKUP_DIR=/percorso/privato/backup ./scripts/backup.sh
```

Lo script ferma temporaneamente web, worker e scheduler per rendere coerenti database e file; esegue `pg_dump` in formato custom e archivio dei documenti, calcola SHA-256 e riavvia i servizi con un trap anche in caso di errore. La manutenzione provoca un breve periodo di indisponibilità: pianificarlo. Non copia segreti in chiaro nell’archivio. Richiede disco libero e permessi sullo stack.

Conservare, ad esempio, backup giornalieri e copie settimanali secondo una policy scelta dal titolare; nessuna cancellazione automatica dei backup è inclusa. Copiare fuori dalla VPS e cifrare con una soluzione gestita da te (per esempio restic o un sistema di backup già autorizzato). Non sono stati configurati bucket, account o servizi esterni.

## Ripristino isolato

Creare uno stack di staging dedicato, senza email in uscita e senza DNS pubblico, con stessa versione applicativa e `APP_KEY` originale. Ripristinare prima lì:

```bash
./scripts/restore.sh /percorso/backup --confirm-replace-hub
```

**Il comando sostituisce i dati nel target Compose corrente.** La conferma esplicita è obbligatoria. Prima, salvare il target e verificare di non essere nello stack di altri software. Il comando controlla i checksum, ferma gli scrittori, ripristina PostgreSQL e documenti, esegue le migrazioni necessarie e riavvia i servizi. In caso di errore si ferma mantenendo l’app offline: leggere il log e correggere prima di riavviare.

Verificare health, accesso/MFA, due aziende separate, download di documenti di versioni diverse, riepiloghi, importi e numero di registrazioni. Se il ripristino usa una release diversa verificare la compatibilità delle migrazioni; il backup non autorizza automaticamente un downgrade di schema.

## Prova senza Docker

Un dump PostgreSQL custom può essere controllato con `pg_restore --list`, poi caricato in un **nuovo database isolato** con `pg_restore --exit-on-error --no-owner --no-acl -d database_restore_test file.dump`. Verificare tabelle, conteggi e dati rilevanti. Il report di consegna distingue questa prova dall’esecuzione degli script container: il solo controllo di sintassi shell non dimostra un ripristino Docker.

Programmare prove periodiche di ripristino, registrando data, versione, durata, conteggi ed esito. Backup mai ripristinati non costituiscono evidenza di recuperabilità.
