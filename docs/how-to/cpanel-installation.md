# Installazione AssestMe su cPanel

Seguire prima la [guida hosting generale](hosting-installation.md). cPanel è supportato quando il piano espone le capability richieste, non automaticamente su ogni piano.

1. In **MultiPHP Manager** selezionare PHP 8.3 o superiore per il dominio e verificare le estensioni richieste, inclusi PDO e il driver database scelto.
2. Mantenere la release in una directory privata, ad esempio `/home/utente/assestme`, mai interamente in `public_html`.
3. Copiare in `public_html` solo il contenuto di `public/` e usare `deploy/shared-hosting/index.php.dist` come nuovo `public_html/index.php`, impostando `$appRoot` alla directory privata.
4. Il PHP CLI può essere `/usr/local/bin/php`. Su EasyApache `/usr/bin/php` può essere PHP CGI e viene rifiutato dal probe CLI. Sono supportati anche `/usr/local/bin/ea-php83` e `/usr/local/bin/ea-php84` quando il loro probe `--version` conferma PHP CLI 8.3+.
5. In **Advanced → Cron Jobs → Add New Cron Job** inserire ogni minuto e usare il comando assoluto mostrato dall’installer.

WeasyPrint spesso non è installabile dal singolo account cPanel: richiederlo al provider. I client SQL sono opzionali; senza di essi il backup MySQL/MariaDB resta da configurare, mentre login e uso ordinario continuano a funzionare.

File, storage e `.env` devono appartenere al medesimo account cPanel che esegue PHP. Non usare `0777`. Al termine verificare login, PDF, cron/heartbeat e backup applicativo.
