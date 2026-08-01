# Installazione di AssestMe su CloudPanel

Questa procedura usa l'archivio di release `assestme-<versione>.zip`: contiene già le dipendenze PHP di produzione e non richiede Composer, Node.js o npm sul server. La compatibilità automatizzata è verificata in CI; una pubblicazione non equivale a una certificazione di una specifica istanza CloudPanel.

## Prima di iniziare

Preparare un dominio dedicato e scegliere uno dei database supportati per una nuova installazione: SQLite, MySQL oppure MariaDB. L'installer non converte e non importa database AssestMe esistenti.

L'installer crea il primo e unico amministratore. Prima di rendere raggiungibile il dominio, abilitare obbligatoriamente in CloudPanel la **Basic Authentication** del sito oppure una restrizione temporanea per il proprio indirizzo IP. CSRF, rate limiting e lock dell'applicazione sono protezioni aggiuntive, non sostituiscono questo controllo perimetrale.

Scaricare soltanto l'archivio di release pubblicato. Il pacchetto contiene `RELEASE-MANIFEST.sha256`, già verificato dal workflow prima della pubblicazione, per controllare i file interni dopo l'estrazione.

## Creazione del sito dalla GUI

1. In CloudPanel aprire **Sites**, scegliere **Add Site** e quindi **Create a PHP Site**.
2. Selezionare il template Laravel 13 e PHP 8.3, inserire il dominio e creare il sito.
3. Abilitare SSL/TLS dalla sezione del dominio e verificare che HTTPS sia operativo.
4. Abilitare temporaneamente **Basic Authentication** nella sezione Security del sito, oppure limitare l'accesso al proprio IP.
5. Impostare la document root sulla directory `public` dell'applicazione, mai sulla root del progetto. Esempio: `/home/<site-user>/htdocs/<dominio>/public`.
6. Nel File Manager caricare l'archivio CloudPanel, estrarlo nella directory del progetto e verificare che `artisan`, `vendor/autoload.php` e `public/index.php` siano allo stesso livello previsto.
7. Verificare che file e directory appartengano al site user. `storage` e `bootstrap/cache` devono essere scrivibili dal processo PHP; non applicare permessi ricorsivi `0777`.
8. Nelle impostazioni PHP del sito configurare:

   ```ini
   memory_limit=512M
   upload_max_filesize=25M
   post_max_size=30M
   max_file_uploads=20
   max_execution_time=120
   ```

9. Per MySQL o MariaDB, aprire **Databases**, creare dalla GUI un database vuoto e un utente dedicato, annotando host, porta, nome database e utente. Per SQLite questo passaggio non serve.

## Installer web

1. Aprire `https://<dominio>/install` autenticandosi con la protezione temporanea CloudPanel.
2. Controllare dominio e versione nella schermata iniziale e confermare che il database sia nuovo.
3. Superare i controlli reali su PHP 8.3, estensioni, directory, PHP CLI e generazione PDF WeasyPrint.
4. Inserire URL HTTPS, timezone `Europe/Rome`, locale `it`, directory backup, percorsi di WeasyPrint e PHP CLI.
5. Scegliere separatamente SQLite, MySQL o MariaDB. Per SQLite usare il percorso proposto sotto `storage/app/database`, fuori da `public`. Per un server database inserire i dati creati in CloudPanel e, se necessario, il socket Unix.
6. Eseguire il test database. AssestMe verifica identità del prodotto, versione, charset, InnoDB, privilegi, schema, vincoli, transazioni e cleanup delle tabelle casuali di probe. Un database non vuoto o appartenente a un'altra applicazione viene bloccato senza offrire cancellazioni.
7. Creare l'unico amministratore con una password di 14–128 caratteri contenente maiuscole, minuscole, numeri e simboli.
8. Avviare la finalizzazione una sola volta. Il processo ripetibile esegue migration normali, seeder idempotenti, creazione amministratore, PDF, backup, verifica e health check. Non usa `migrate:fresh`.
9. Conservare la pagina finale fino a quando il cron è configurato. Dopo il lock definitivo `/install` e tutte le sue sotto-route rispondono `404`; non esiste reset web.

## Scheduler CloudPanel

Nella pagina finale copiare esattamente il comando rilevato, composto dal percorso assoluto del PHP CLI 8.3 e dal percorso assoluto di `artisan`. In **Cron Jobs** del sito CloudPanel creare un job con:

```text
Frequenza: * * * * *
Comando: <php-cli-assoluto> <artisan-assoluto> schedule:run
```

Non aggiungere la frequenza nel campo comando. Attendere almeno un minuto e scegliere **Verifica nuovamente** nell'installer, oppure eseguire:

```bash
php artisan assestme:diagnose
php artisan assestme:diagnose --json
```

Lo scheduler è operativo quando l'heartbeat risulta recente. Esso mantiene anche backup alle 02:30, integrità database alle 03:30 e pulizia delle richieste di salvataggio scadute, usando `Europe/Rome`.

## Verifica e apertura del sito

1. Visitare `/up` e verificare `status: healthy`.
2. Visitare `/admin/login`, accedere e aprire la dashboard e la diagnostica amministrativa.
3. Generare un PDF reale e verificare un backup applicativo.
4. Disabilitare Basic Authentication o la restrizione IP soltanto dopo un login riuscito e dopo avere verificato il lock `storage/app/private/installed.lock`.

Da shell del site user è disponibile anche lo smoke test non distruttivo per la configurazione:

```bash
scripts/cloudpanel-smoke.sh /home/<site-user>/htdocs/<dominio> <dominio>
```

Lo script crea e verifica un backup applicativo; non installa pacchetti e non stampa credenziali.

## Aggiornamento manuale

Prima di aggiornare, eseguire e verificare un backup applicativo e un backup CloudPanel. Il backup CloudPanel protegge l'infrastruttura secondo le funzioni del pannello; il backup AssestMe contiene manifest SHA-256, database e storage privato coerenti con l'applicazione. Sono complementari.

Mantenere persistenti tra le release:

- `.env` con permessi `0600`;
- `storage/app/private`, `storage/app/generated`, `storage/app/database` e `storage/backups`;
- `storage/app/private/installed.lock` e heartbeat;
- il database server quando si usa MySQL o MariaDB.

Procedura sintetica:

1. attivare la maintenance mode e creare un backup AssestMe verificato;
2. estrarre la nuova release in una directory nuova;
3. collegare o copiare in modo controllato i soli dati persistenti sopra elencati;
4. conservare la nuova `vendor/` fornita dalla release, senza riusare quella precedente;
5. eseguire `php artisan migrate --force --isolated`, poi `php artisan optimize`;
6. eseguire `php artisan assestme:diagnose`, disattivare maintenance e verificare login, PDF e backup.

Per un rollback applicativo, riattivare maintenance, ripristinare la directory della release precedente e il relativo `.env`, quindi ripristinare il database soltanto se la release ha applicato migration incompatibili. Verificare sempre l'archivio prima del restore.

Il restore è esclusivamente CLI e richiede maintenance mode. Per MySQL e MariaDB crea prima un safety backup e tenta una compensazione se l'import fallisce; l'import SQL non è atomicamente garantito dal server. Se falliscono sia import sia compensazione, AssestMe resta in maintenance e conserva gli artefatti diagnostici. Sono necessari client coerenti con il prodotto (`mysql`/`mysqldump` oppure `mariadb`/`mariadb-dump`), configurabili con `ASSESTME_DB_RESTORE_BINARY` e `ASSESTME_DB_DUMP_BINARY`.

AssestMe non converte dati tra SQLite, MySQL e MariaDB. Un cambio di driver richiede una nuova installazione vuota e una procedura dati esterna esplicitamente progettata e validata.
