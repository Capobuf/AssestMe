# Installazione AssestMe su hosting PHP

Questa è la guida generale per CloudPanel, cPanel, Plesk e hosting Linux che espongono le capability richieste. La compatibilità non è universale: il wizard verifica il sito reale e non installa pacchetti, non modifica cron e non rileva automaticamente il pannello.

## Requisiti obbligatori

- PHP web e PHP CLI 8.3 o superiore, con le estensioni richieste dall’installer.
- PDO coerente: `pdo_sqlite` per SQLite oppure `pdo_mysql` per MySQL/MariaDB.
- WeasyPrint 60.0 o superiore, verificabile con `weasyprint --version` e con il PDF minimo del wizard.
- Document root sicura e storage privato fuori dalla document root.
- Un cron o task pianificato configurabile ogni minuto.
- Filesystem scrivibile dal medesimo site user del processo PHP: deve poter creare e mantenere `storage/framework/installer` e `storage/app/private` a `0700`, e file privati a `0600`.

Le estensioni PHP richieste sono: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd`, `iconv`, `intl`, `libxml`, `mbstring`, `openssl`, `pdo`, `phar`, `session`, `simplexml`, `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, `zip` e `zlib`, oltre al PDO del database scelto.

Non usare `0777`. Se il pre-flight segnala `private_directory_permissions_not_securable_by_web_process`, allineare la proprietà dei file al site user; il processo web non esegue `chown`.

## Opzionale ma raccomandato

- `mysqldump` o `mariadb-dump` e il corrispondente client restore.
- SSH come site user.
- Backup del provider.

AssestMe può essere installato senza client SQL. Per MySQL/MariaDB, senza client o con dump non funzionante, il backup applicativo server resta “da configurare” nella pagina finale: non viene nascosto e non è dichiarato superato. Il backup del provider è utile ma non sostituisce necessariamente un backup applicativo verificato.

SQLite esegue sempre un backup reale finale e un suo errore blocca il setup. MySQL/MariaDB usano invece il client disponibile come verifica di capability: client assente o dump fallito lasciano backup e verifica `pending`, mentre il comando manuale `php artisan assestme:backup` continua a terminare con errore quando non riesce.

## Layout preferito

Per CloudPanel, Plesk, VPS e pannelli che consentono una document root personalizzata, estrarre la release in `/project` e impostare la document root a `/project/public`:

```text
/project
├── app
├── bootstrap
├── storage
├── vendor
└── public
```

`.env`, `storage`, `vendor` e `bootstrap` non devono mai essere sotto la document root.

## Shared hosting con document root fissa

Se cPanel o il piano hosting obbliga `public_html`, mantenere l’applicazione privata, per esempio:

```text
/home/utente/assestme/       ← applicazione privata
/home/utente/public_html/    ← soli file pubblici
```

1. Estrarre la release in `/home/utente/assestme`.
2. Copiare in `public_html` soltanto il contenuto di `assestme/public/`.
3. Copiare `deploy/shared-hosting/index.php.dist` in `public_html/index.php`.
4. Modificare unicamente `$appRoot` nel template con `/home/utente/assestme`.
5. Aprire `/install` e completare i controlli reali.

Il template non legge il percorso da query string, cookie o POST e non mostra percorsi assoluti in caso di errore.

## Scheduler

Il wizard fornisce un comando già verificato nel formato:

```text
<php-cli-assoluto> <artisan-assoluto> schedule:run
```

La frequenza è sempre `* * * * *`. Configurare il task dal pannello o, con shell, usare `crontab -e` e la riga completa `* * * * * <comando-generato>`.

### Plesk

Usare **Websites & Domains → Hosting Settings → Document root** per impostare `public`, **Websites & Domains → PHP** per PHP 8.3+, e **Websites & Domains → Scheduled Tasks** per il cron. Un percorso CLI tipico è `/opt/plesk/php/<versione>/bin/php`. Il task può girare in chroot: se quel percorso non è disponibile, usare “Run a PHP script” o chiedere il percorso al provider.

## WeasyPrint

Su VPS Debian/Ubuntu con accesso amministrativo:

```bash
sudo apt update
sudo apt install -y weasyprint
weasyprint --version
```

Il pacchetto della distribuzione è compatibile soltanto se il comando restituisce la versione 60.0
o superiore. L'installazione APT da sola non garantisce il requisito: verificare sempre la versione
prima di aprire il wizard.

Su hosting condiviso, cPanel o Plesk senza privilegi amministrativi, chiedere al provider WeasyPrint
60.0 o superiore e le sue dipendenze. Se il binario è in una posizione non standard, impostare
`LARAVEL_PDF_WEASYPRINT_BINARY` in `.env`. Non eseguire installazioni dal processo web e non assumere
che `pip install` sia sufficiente.

## Hosting non compatibile

Un piano non è compatibile se non offre PHP 8.3+, cron/task pianificati, WeasyPrint 60.0 o superiore,
un layout con file privati fuori dalla document root, oppure la possibilità per il processo PHP di
scrivere e mettere in sicurezza storage e `.env`.
