# Installazione di AssestMe su CloudPanel

Questa è una procedura specifica per CloudPanel. Per requisiti, limiti di compatibilità e hosting tradizionale, leggere prima la [guida hosting generale](hosting-installation.md). L'archivio di release `assestme-<versione>.zip` contiene già le dipendenze PHP di produzione e non richiede Composer, Node.js o npm sul server. La compatibilità automatizzata è verificata in CI; una pubblicazione non equivale a una certificazione di una specifica istanza CloudPanel.

## Prima di iniziare

Preparare un dominio dedicato e scegliere uno dei database supportati per una nuova installazione: SQLite oppure MySQL / MariaDB. Per un database server, AssestMe stabilisce la connessione tramite PDO e rileva automaticamente se il prodotto è MySQL o MariaDB. L'installer non converte e non importa database AssestMe esistenti.

L'installer crea il primo e unico amministratore. Prima di rendere raggiungibile il dominio, abilitare obbligatoriamente in CloudPanel la **Basic Authentication** del sito oppure una restrizione temporanea per il proprio indirizzo IP. CSRF, rate limiting e lock dell'applicazione sono protezioni aggiuntive, non sostituiscono questo controllo perimetrale.

Scaricare soltanto l'archivio di release pubblicato. Il pacchetto contiene `RELEASE-MANIFEST.sha256`, già verificato dal workflow prima della pubblicazione, per controllare i file interni dopo l'estrazione.

## Creazione del sito dalla GUI

1. In CloudPanel aprire **Sites**, scegliere **Add Site** e quindi **Create a PHP Site**.
2. Selezionare il template Laravel 13 e PHP 8.3 o superiore, inserire il dominio e creare il sito.
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

## Runtime e pacchetti

AssestMe richiede PHP web e CLI 8.3 o superiore. Il wizard rileva automaticamente il PHP CLI coerente con il runtime web; non ne chiede il percorso. Richiede inoltre `pdo_sqlite` per SQLite oppure `pdo_mysql` per MySQL e MariaDB.

WeasyPrint è obbligatorio per i report PDF e viene rilevato automaticamente. Su Debian e Ubuntu installarlo prima del wizard:

```bash
sudo apt update
sudo apt install -y weasyprint
weasyprint --version
```

L'installer web non esegue `sudo`, non installa pacchetti e non modifica il sistema. Se non si dispone di accesso root o sudo, chiedere al provider hosting di installare WeasyPrint; poi premere **Verifica nuovamente**.

I client SQL non servono per connettersi, testare il database, eseguire migration, accedere o usare normalmente AssestMe. Servono soltanto per backup e restore MySQL/MariaDB. Possono essere installati prima o dopo il wizard; AssestMe li rileva alla successiva operazione.

MariaDB su Debian/Ubuntu:

```bash
sudo apt install -y mariadb-client
mariadb-dump --version
mariadb --version
```

MySQL su Ubuntu:

```bash
sudo apt install -y mysql-client
mysqldump --version
mysql --version
```

Su alcune distribuzioni `default-mysql-client` installa in realtà client MariaDB. AssestMe esegue `--version` e accetta il client soltanto quando il prodotto corrisponde al driver scelto.

## Installer web

1. Aprire `https://<dominio>/install` autenticandosi con la protezione temporanea CloudPanel.
2. Controllare dominio e versione nella schermata iniziale e confermare che il database sia nuovo.
3. Superare i controlli reali su PHP 8.3 o superiore, estensioni, directory, PHP CLI rilevato automaticamente e generazione PDF WeasyPrint.
4. Inserire URL HTTPS, timezone `Europe/Rome`, locale `it` e directory backup. Il nome applicazione resta sempre `AssestMe`; il wizard non chiede percorsi di binari.
5. Scegliere SQLite oppure MySQL / MariaDB. Per SQLite usare il percorso proposto sotto `storage/app/database`, fuori da `public`. Per un server database inserire i dati creati in CloudPanel e, se necessario, il socket Unix; AssestMe stabilisce la connessione tramite PDO e rileva automaticamente se il prodotto è MySQL o MariaDB.
6. Eseguire il test database. AssestMe verifica identità del prodotto, versione, charset, InnoDB, privilegi, schema, vincoli, transazioni e cleanup delle tabelle casuali di probe. Un database non vuoto o appartenente a un'altra applicazione viene bloccato senza offrire cancellazioni.
7. Creare l'unico amministratore con una password di 14–128 caratteri contenente maiuscole, minuscole, numeri e simboli.
8. Avviare la finalizzazione una sola volta. Il processo ripetibile esegue migration normali, seeder idempotenti, creazione amministratore, PDF e health check. SQLite esegue sempre il backup reale finale. Per MySQL/MariaDB, se il dump client è disponibile esegue e verifica il backup; se manca oppure il dump fallisce, i due controlli restano `pending` con il dettaglio operativo e l'installazione può completarsi. Il comando manuale di backup continua invece a fallire e a registrare l’errore. Non usa `migrate:fresh`.
9. Conservare la pagina finale fino a quando il cron è configurato. Dopo il lock definitivo `/install` e tutte le sue sotto-route rispondono `404`; non esiste reset web.

## Scheduler CloudPanel

Nella pagina finale copiare esattamente il comando rilevato, composto dal percorso assoluto del PHP CLI e dal percorso assoluto di `artisan`.

Aprire **CloudPanel → Sites → assestme → Cron Jobs → Add Cron Job** e creare il job come site user:

```text
Frequenza: * * * * *
Comando: <php-cli-assoluto> <artisan-assoluto> schedule:run
```

Non aggiungere la frequenza nel campo comando.

L'alternativa SSH, sempre come site user, è:

```bash
crontab -e
```

e aggiungere la riga completa:

```text
* * * * * <php-cli-assoluto> <artisan-assoluto> schedule:run
```

AssestMe non crea né modifica automaticamente il crontab. Attendere almeno un minuto e scegliere **Verifica nuovamente** nell'installer, oppure eseguire:

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
scripts/shared-hosting-smoke.sh /home/<site-user>/htdocs/<dominio> <dominio>
```

Lo script crea e verifica un backup applicativo; per MySQL/MariaDB richiede quindi il dump client coerente. Non installa pacchetti e non stampa credenziali.

## Hosting PHP tradizionale

Lo stesso ZIP e wizard sono utilizzabili su un hosting PHP tradizionale che soddisfa la guida hosting generale. Il wizard non usa API CloudPanel e non installa pacchetti. Per cPanel con `public_html` fisso usare il template split-root documentato nella guida, senza caricare l’intero progetto nella document root.

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

Il restore è esclusivamente CLI e richiede maintenance mode. Per MySQL e MariaDB crea prima un safety backup e tenta una compensazione se l'import fallisce; l'import SQL non è atomicamente garantito dal server. Se falliscono sia import sia compensazione, AssestMe resta in maintenance e conserva gli artefatti diagnostici. Sono necessari client coerenti con il prodotto (`mysql`/`mysqldump` oppure `mariadb`/`mariadb-dump`), rilevati a ogni operazione. `ASSESTME_DB_RESTORE_BINARY` e `ASSESTME_DB_DUMP_BINARY` restano override avanzati per percorsi non standard; `ASSESTME_PHP_BINARY` e `LARAVEL_PDF_WEASYPRINT_BINARY` hanno lo stesso ruolo per PHP CLI e WeasyPrint.

AssestMe non converte dati tra SQLite, MySQL e MariaDB. Un cambio di driver richiede una nuova installazione vuota e una procedura dati esterna esplicitamente progettata e validata.

## Aggiornamento automatico da GitHub Actions

L'istanza CI di AssestMe usa il workflow unico `CI` del repository `Capobuf/AssestMe`. Su un push a
`main`, il job `publish-main-release` richiede `check`, `application`, `installer` e
`compatibility-smoke`; `deploy_cloudpanel` si esegue soltanto dopo la pubblicazione riuscita. Le pull
request non ricevono i secret di deploy.

Il job apre una connessione SSH non interattiva con una chiave dedicata e un file `known_hosts` verificato. La chiave è limitata sul server a un comando forzato che esegue `dploy deploy main`; il comando crea la release, usa lo storage condiviso e l'overlay `.env`, esegue le operazioni dploy e aggiorna `current`. Non modificare questa procedura per cambiare document root, `.env`, database, storage condiviso o configurazione dploy.

### Installare lo script server-side

Dal checkout che contiene questa versione del repository, validare lo script e trasferirlo al server con un canale amministrativo già autorizzato. Sul server, come operatore che può impostare ownership del site user, impostare `DEPLOY_SCRIPT_SOURCE` al percorso assoluto effettivamente trasferito; non assumere un percorso del checkout del repository sul server.

```bash
DEPLOY_SCRIPT_SOURCE=/percorso/assoluto/verificato/deploy-assestme

test -f "$DEPLOY_SCRIPT_SOURCE"
bash -n "$DEPLOY_SCRIPT_SOURCE"

install -d \
  -o bydot-assestme \
  -g bydot-assestme \
  -m 750 \
  /home/bydot-assestme/bin

install \
  -o bydot-assestme \
  -g bydot-assestme \
  -m 750 \
  "$DEPLOY_SCRIPT_SOURCE" \
  /home/bydot-assestme/bin/deploy-assestme
```

Lo script installato è `/home/bydot-assestme/bin/deploy-assestme`. Usa il lock `/home/bydot-assestme/.dploy/github-actions-deploy.lock`, pertanto due deploy GitHub non possono sovrapporsi nemmeno oltre alla concorrenza del workflow. Dopo `dploy deploy main` controlla `current`, `artisan` e `public/index.php`, esegue `php8.3 artisan about` e stampa nei log SSH la release effettivamente pubblicata. Le release dploy non conservano `.git`; il deploy non ricava né dichiara un commit da quella directory.

### Creare e limitare la chiave SSH dedicata

Generare una sola volta la coppia di chiavi su una postazione amministrativa sicura:

```bash
ssh-keygen \
  -t ed25519 \
  -C "github-actions-assestme-deploy" \
  -f assestme-actions \
  -N ""
```

`assestme-actions` è la chiave privata da inserire nel secret `CLOUDPANEL_SSH_PRIVATE_KEY`; `assestme-actions.pub` è la chiave pubblica da installare sul server. Nessuno dei due file deve essere committato. Questa coppia è diversa dalla deploy key GitHub già usata da CloudPanel per clonare il repository e non deve riutilizzarla.

Aggiungere la chiave pubblica dedicata a `/home/bydot-assestme/.ssh/authorized_keys`, preceduta dal comando forzato e dalle restrizioni seguenti. Non usare `restrict` finché la versione OpenSSH del server e la compatibilità con il comando forzato non sono state verificate.

```text
command="/home/bydot-assestme/bin/deploy-assestme",no-agent-forwarding,no-port-forwarding,no-X11-forwarding,no-pty ssh-ed25519 CHIAVE_PUBBLICA github-actions-assestme-deploy
```

Il comando forzato è intenzionale: la riga SSH del workflow non trasmette un comando remoto arbitrario e propaga l'exit code dello script. Un errore SSH o dploy fa quindi fallire il job.

### Creare `CLOUDPANEL_KNOWN_HOSTS`

Non accettare una fingerprint alla cieca e non usare un hostname dietro un proxy Cloudflare per SSH ordinario. Prima leggere direttamente sul server la fingerprint ED25519:

```bash
ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub
```

Poi, dalla postazione amministrativa, acquisire la chiave dell'host reale e confrontarne la fingerprint con il valore letto sul server:

```bash
ssh-keyscan \
  -p PORTA_SSH \
  -t ed25519 \
  -H HOST_O_IP_SERVER \
  > cloudpanel-known-hosts

ssh-keygen -lf cloudpanel-known-hosts
```

Solo se le fingerprint coincidono, inserire l'intero contenuto di `cloudpanel-known-hosts` nel secret `CLOUDPANEL_KNOWN_HOSTS`.

### Configurare i repository secret

In GitHub aprire esattamente:

```text
Repository
→ Settings
→ Secrets and variables
→ Actions
→ New repository secret
```

Creare i cinque repository secret richiesti dal job:

```text
CLOUDPANEL_HOST
CLOUDPANEL_PORT
CLOUDPANEL_USER
CLOUDPANEL_SSH_PRIVATE_KEY
CLOUDPANEL_KNOWN_HOSTS
```

Il valore noto di `CLOUDPANEL_USER` è `bydot-assestme`, ma resta un secret per uniformità operativa e non è inserito nel workflow. Non pubblicare valori dei secret in issue, commit, workflow o log. Dopo l'installazione dello script, della chiave pubblica e dei secret, un vero push su `main` eseguirà il primo deploy automatico; conservarne il log come evidenza dell'istanza reale.
