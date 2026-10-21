# Sicurezza e gestione dei dati

## Controlli implementati

- Nessuna registrazione pubblica. Login e reset password con Laravel Fortify; password robuste al reset/creazione admin; TOTP e codici di recupero, obbligatori per Super Admin.
- Sessioni su database, cifratura della sessione, rotazione login/logout, cookie HttpOnly e SameSite. Cookie Secure in produzione; revoca sessioni dopo reset o disabilitazione da gestione utenti.
- CSRF per richieste browser e rate limiting per login, MFA, upload, reset e operazioni applicative.
- Scope tenant fail-closed: senza identità autenticata non si leggono modelli tenant. Solo il Super Admin opera globalmente. I comandi di sistema usano bypass espliciti e non sono endpoint pubblici.
- Verifiche per capacità e ruolo su ogni controller, verifica proprietario per registrazioni e referenti, vincoli composti per relazioni fra risorse dello stesso tenant. Lo slug è un riferimento, non un’autorizzazione.
- Input validato, output Blade escapato, CSV neutralizzato contro formule, PDF senza PHP/JavaScript o risorse remote.
- File in archivio privato, nome fisico casuale, tipi e dimensioni verificati, download autorizzato come allegato; no SVG/HTML/eseguibili. Logo solo PNG/JPEG. Non è incluso un motore antivirus o CDR: valutarlo prima di ampliare i tipi ammessi o accettare file da canali pubblici.
- Note interne escluse dal portale e dai report. Audit disponibile solo al Super Admin; password, segreti e percorsi fisici non vengono serializzati nell’audit.
- `noindex`, CSP, anti-framing, `nosniff`, no-store; HSTS su HTTPS. Nessun font remoto, analytics o CDN a runtime.
- Lock transazionali, revisioni e vincoli DB per tempo, relazioni e duplicati mensili. Segreti soltanto da ambiente, esclusi da Git e Docker context.

L’applicazione non implementa PostgreSQL RLS. Un accesso SQL amministrativo vede i dati di tutti i clienti. Il ruolo DB di runtime deve essere dedicato allo Hub, con permessi sul solo database. I controlli non equivalgono a un penetration test indipendente.

## Responsabilità privacy da definire

Questo documento descrive strumenti e decisioni operative, non certifica conformità legale.

Occorre definire per ogni trattamento chi stabilisce finalità e mezzi, quali dati vengono trattati e con quale base. Per anagrafica clienti, gestione del rapporto professionale e amministrazione, Valerio Persiani può avere un ruolo autonomo. Per dati inseriti per conto delle aziende o contenuti di assistenza relativi ai loro interessati, il ruolo e le istruzioni dipendono dagli accordi effettivi. Non assumere automaticamente che ogni cliente sia sempre titolare e il fornitore sempre responsabile, o viceversa.

Prima dell’apertura reale, formalizzare informativa pertinente, eventuali accordi per trattamenti per conto terzi, fornitori/subfornitori (VPS, email, backup), finalità, basi, tempi di conservazione, gestione degli accessi, canale per esercizio diritti e procedura incidenti. Verificare trasferimenti e localizzazione dei dati rispetto ai fornitori scelti. I contenuti contrattuali firmati si conservano come documenti; il portale non attribuisce valore di firma alle approvazioni operative.

## Minimizzazione

Usare descrizioni tecniche comprensibili senza password, token, chiavi, dati dei dipendenti non necessari o dump di altri sistemi. Gli upload non devono diventare un archivio indiscriminato. Le email dei ticket contengono un aggiornamento generico e rimandano al portale autenticato.

I log operativi e i fallimenti delle code possono contenere identificativi: limitarne accessi e retention. `APP_DEBUG=false` è obbligatorio in produzione. Il mailer `log` registra contenuti email e link di recupero per lo sviluppo: in produzione configurare SMTP e limitare comunque l’accesso ai log.

## Retention

`HUB_AUDIT_RETENTION_DAYS` e `HUB_SESSION_RETENTION_DAYS` sono configurabili. Il comando `php artisan hub:prune` produce un’anteprima. Solo `php artisan hub:prune --apply` elimina i record oltre soglia. Lo scheduler esegue l’anteprima; un eventuale automatismo con `--apply` deve essere aggiunto dopo una decisione esplicita sulla policy.

Reset scaduti e job falliti hanno pulizia pianificata. Contratti, attività, consuntivi e documenti non vengono eliminati automaticamente: archiviazione ordinaria e cancellazione controllata evitano la perdita involontaria dello storico. Definire separatamente conservazione per finalità amministrative, prove degli accordi, ticket e documenti; impostazioni tecniche e obblighi reali possono differire.

## Esportazione e cancellazione controllata

Da terminale autorizzato:

```bash
php artisan hub:tenant-data ID --export=/percorso/privato/cliente.zip
```

Esporta dati e documenti/versioni in ZIP con permessi restrittivi; non esporta password, sessioni, segreti MFA o percorsi dei file. **È un’esportazione amministrativa, include note interne e audit:** rivedere pertinenza e destinatario prima di consegnarla a terzi. L’app non invia automaticamente il file al cliente.

Per cancellare, verificare identità e autorità del richiedente, finalità residue, necessità di conservazione e backup. Archiviare prima il cliente. Eseguire l’anteprima (nessuna modifica) e poi, soltanto con decisione autorizzata:

```bash
php artisan hub:tenant-data ID
php artisan hub:tenant-data ID --purge --confirm=DELETE:slug-esatto
```

Il comando richiede stato archiviato e frase esatta, rifiuta timer attivi, elimina dati relazionali, sessioni, reset e file del solo tenant. In caso di errore filesystem verificare ed eliminare i residui prima di dichiarare completata la procedura. Le copie nei backup, i file esportati e le email inviate hanno cicli di vita separati: documentare esclusioni e tempi; ripristinando un backup occorre riapplicare le cancellazioni pertinenti.

## Incidenti e accessi

Disabilitare l’account coinvolto, conservare evidenze pertinenti in luogo protetto, esaminare audit/log e seguire la procedura organizzativa definita. Perdita del dispositivo TOTP: usare un codice di recupero e riconfigurare da Sicurezza. Se sono persi anche i codici, serve una procedura amministrativa identificata e tracciata sul server; non esiste una scorciatoia pubblica per bypassare MFA.
