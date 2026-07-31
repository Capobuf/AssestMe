# Checklist di collaudo CloudPanel

Compilare questa scheda su una vera istanza. Conservare il risultato con i riferimenti alla release, senza password, cookie o contenuti di `.env`.

## Ambiente

- Operatore e data/ora UTC:
- Versione AssestMe e SHA-256 release:
- Versione CloudPanel:
- Sistema operativo riportato dal pannello:
- Versione PHP web:
- Versione PHP CLI e percorso assoluto:
- Versione WeasyPrint e percorso assoluto:
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
- [ ] PDF minimo WeasyPrint superato.
- [ ] PHP CLI 8.3 rilevato e validato.
- [ ] Probe database completo e cleanup superati.
- [ ] Test con database non vuoto bloccato senza cancellazioni.
- [ ] Migration e dati iniziali completati.
- [ ] Unico amministratore creato e login riuscito.
- [ ] `/install` restituisce `404` dopo il lock.
- [ ] `/up` riporta `healthy` dopo l'installazione.

## Operatività

- [ ] Cron CloudPanel inserito con frequenza `* * * * *`.
- [ ] Comando cron identico a quello mostrato dall'installer.
- [ ] Heartbeat recente dopo almeno un minuto.
- [ ] `php artisan assestme:diagnose` completato; allegare output senza segreti.
- [ ] Diagnostica amministrativa visualizzata.
- [ ] PDF applicativo generato e aperto correttamente.
- [ ] Backup AssestMe creato e manifest verificato.
- [ ] Restore provato in maintenance su ambiente sacrificabile.
- [ ] Per server database, safety backup e risultato compensazione annotati.
- [ ] Basic Authentication/restrizione IP rimossa solo dopo login riuscito.

## Risultati

- Esito installer:
- Esito login:
- Esito PDF:
- Esito cron/heartbeat:
- Esito backup/verifica:
- Esito restore:
- Esito smoke script:
- Problemi osservati, messaggio e orario:
- Log o artefatti allegati (percorsi, senza segreti):
- Esito finale: superato / non superato
- Firma operatore:
