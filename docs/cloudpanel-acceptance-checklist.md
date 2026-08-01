# Checklist di collaudo CloudPanel

Compilare questa scheda su una vera istanza. Conservare il risultato con i riferimenti alla release, senza password, cookie o contenuti di `.env`.

## Ambiente

- Operatore e data/ora UTC:
- Versione AssestMe e SHA-256 release:
- Versione CloudPanel:
- Sistema operativo riportato dal pannello:
- Versione PHP web:
- Versione PHP CLI >= 8.3 e percorso assoluto rilevato:
- Versione WeasyPrint e percorso assoluto rilevato:
- Driver database: SQLite / MySQL / MariaDB
- Prodotto e versione database rilevati:
- Dominio:
- Document root (deve terminare in `/public`):
- Protezione iniziale: Basic Authentication / restrizione IP

## Installazione

- [ ] Archivio `.sha256` verificato.
- [ ] Archivio estratto senza `.env` e con `vendor/autoload.php`.
- [ ] Ownership del site user verificata; nessun `0777` ricorsivo.
- [ ] HTTPS operativo prima dell'inserimento di credenziali.
- [ ] `/` reindirizza a `/install` prima dell'installazione.
- [ ] `/admin` reindirizza a `/install` prima dell'installazione.
- [ ] `/up` riporta `not_installed` prima dell'installazione.
- [ ] Requisiti runtime e permessi superati dal processo web.
- [ ] PHP web 8.3 o superiore accettato; un runtime 8.2 viene rifiutato.
- [ ] PDF minimo WeasyPrint rilevato automaticamente e superato.
- [ ] PHP CLI 8.3 o superiore rilevato automaticamente e validato.
- [ ] Il wizard non richiede nome applicazione né percorsi PHP, WeasyPrint, dump o restore; `APP_NAME` è `AssestMe`.
- [ ] Il wizard mostra soltanto SQLite e MySQL / MariaDB; per un database server il prodotto viene rilevato automaticamente tramite PDO.
- [ ] Estensione `pdo_sqlite` oppure `pdo_mysql` coerente rilevata.
- [ ] Probe database completo e cleanup superati.
- [ ] Probe MySQL/MariaDB completato tramite PDO senza eseguire client dump/restore.
- [ ] Test con database non vuoto bloccato senza cancellazioni.
- [ ] Migration e dati iniziali completati.
- [ ] Unico amministratore creato e login riuscito.
- [ ] `/install` restituisce `404` dopo il lock.
- [ ] `/up` riporta `healthy` dopo l'installazione.

## Operatività

- [ ] Aperto **CloudPanel → Sites → assestme → Cron Jobs → Add Cron Job**.
- [ ] Cron CloudPanel inserito come site user con frequenza `* * * * *`.
- [ ] Comando cron identico a quello mostrato dall'installer.
- [ ] Heartbeat recente dopo almeno un minuto.
- [ ] `php artisan assestme:diagnose` completato; allegare output senza segreti.
- [ ] Diagnostica amministrativa visualizzata.
- [ ] PDF applicativo generato e aperto correttamente.
- [ ] SQLite: backup AssestMe reale creato e manifest verificato.
- [ ] MySQL/MariaDB con dump client: backup reale creato e manifest verificato.
- [ ] MySQL/MariaDB senza dump client: installer completato con `backup` e `backup_verification` pending e istruzione operativa visibile.
- [ ] Dopo l'installazione del client, AssestMe lo rileva senza rieseguire il setup.
- [ ] Restore provato in maintenance su ambiente sacrificabile.
- [ ] Per server database, safety backup e risultato compensazione annotati.
- [ ] Basic Authentication/restrizione IP rimossa solo dopo login riuscito.

## Risultati

- Esito installer:
- Esito login:
- Esito PDF:
- Esito cron/heartbeat:
- Esito backup/verifica:
- Client dump/restore rilevati e relativo prodotto:
- Esito restore:
- Esito smoke script:
- Problemi osservati, messaggio e orario:
- Log o artefatti allegati (percorsi, senza segreti):
- Esito finale: superato / non superato
- Firma operatore:
