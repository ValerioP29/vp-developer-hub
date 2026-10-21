# Consegna locale

## Applicazione

Workspace: `/home/persi/projects/Valerio/vp_developer_hub`.

L’applicazione è stata avviata su **http://127.0.0.1:8080**. PostgreSQL dedicato al collaudo usa la porta **55432**, dati in `/tmp/vp-hub-pg`, database `vp_hub`. Le credenziali demo casuali si trovano nel file locale privato:

`storage/app/demo-credentials.txt`

Account amministratore: `admin@vp-hub.test`. Account cliente amministratore: `cliente@vp-hub.test`. Account utente cliente: `utente@vp-hub.test`.

**Al primo accesso amministratore va configurata MFA.** La configurazione usata dal collaudo browser è stata revocata, con invalidazione delle sessioni del solo admin demo. Il cliente può accedere con la propria password ed eventualmente configurare MFA in Sicurezza.

Il server di collaudo è locale e il database è temporaneo, con autenticazione trust limitata all’istanza dimostrativa in loopback. Non è una configurazione per dati reali. Dopo riavvio/rimozione dei file temporanei, ripetere il setup con un database dedicato persistente e autenticazione password come descritto nel README. La configurazione di produzione Compose usa password e volumi persistenti.

Se i processi locali vengono fermati e i file temporanei esistono ancora:

```bash
/usr/lib/postgresql/16/bin/pg_ctl -D /tmp/vp-hub-pg -l /tmp/vp-hub-pg.log -o '-p 55432 -k /tmp -h 127.0.0.1' start
php artisan serve --host=127.0.0.1 --port=8080
```

## Repository e archivio

La directory `.git` del workspace è gestita dall’ambiente e non scrivibile. È stato creato un repository Git locale con metadati separati in `/tmp/vp-developer-hub-source.git`, esportato in:

- `artifacts/vp-developer-hub.bundle`: repository con commit e ramo `main`.
- `artifacts/vp-developer-hub-source.tar.gz`: sorgenti della stessa revisione.
- `artifacts/SHA256SUMS`: checksum dei due artefatti.

Per ottenere un checkout ordinario:

```bash
git clone /percorso/vp-developer-hub.bundle vp-developer-hub
cd vp-developer-hub
```

Né bundle né archivio contengono `.env`, password demo, database, upload, log, segreti MFA, dipendenze installate o tracce browser. I lock file e gli screenshot senza segreti sono inclusi. Per installare dipendenze e compilare asset seguire il README.

## GitHub e infrastruttura

Il collegamento GitHub è stato consultato e non è emerso un repository dedicato con il nome cercato. Gli strumenti disponibili non espongono la creazione di repository e la CLI `gh` non era disponibile/autenticata: **nessun repository remoto è stato creato o modificato**.

Dopo avere clonato il bundle, con GitHub CLI installata e autenticata in un ambiente autorizzato, il passaggio restante è:

```bash
gh repo create vp-developer-hub --private --source=. --remote=origin --push
```

Nessun collegamento Coolify utilizzabile è disponibile nella sessione. La pubblicazione richiede repository remoto privato, configurazione del nuovo stack, segreti e SMTP, verifica Docker/staging e autorizzazione al rilascio/DNS. Istruzioni in `docs/DEPLOYMENT.md`.

## Cosa resta fuori dalla prima versione

Nessuna fatturazione elettronica, pagamento integrato, firma digitale o integrazione automatica con software cliente. Un solo accordo economico per azienda/mese; più applicativi condividono il monte ore. Nessun servizio antivirus, monitoraggio esterno, backup remoto o provider email viene configurato senza accessi e decisioni esplicite. Il dettaglio del collaudo e delle verifiche ancora richieste in staging è in `docs/TEST-REPORT.md`.
