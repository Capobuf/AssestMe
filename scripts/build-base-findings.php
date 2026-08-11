<?php

declare(strict_types=1);

use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

$path = dirname(__DIR__).'/templates/base-findings.it.json';
$document = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
$legacyIds = [
    'nas.notifications.missing',
    'infrastructure.nas.on-ups',
    'security.rdp.public',
    'network.cabling.unlabelled',
    'network.dhcp.invalid-dns',
    'backup.restore-test.missing',
    'infrastructure.rack.unsuitable-location',
    'ups.monitoring.missing',
];
$templates = array_values(array_filter(
    $document['templates'],
    static fn (array $template): bool => in_array($template['external_id'], $legacyIds, true),
));

/** @var array<string, list<string>> $catalog */
$catalog = [
    'Governance IT' => [
        'Responsabilità operative IT non definite',
        'Revisione periodica della sicurezza non pianificata',
        'Piano di risposta agli incidenti assente',
        'Business continuity e disaster recovery non documentati',
        'Fornitori IT critici non censiti',
        'Fornitori IT critici non riesaminati periodicamente',
        'Processo di aggiornamento e manutenzione non definito',
        'Modifiche infrastrutturali non registrate',
        'Configurazioni critiche escluse dalle copie di sicurezza',
        'Ownership dei servizi essenziali non assegnata',
    ],
    'Sicurezza' => [
        'Firewall perimetrale assente o inadeguato al contesto',
        'Firewall o router non più supportato dal produttore',
        'Credenziali amministrative perimetrali predefinite o deboli',
        'Interfaccia amministrativa del firewall esposta a Internet',
        'Servizi amministrativi non necessari pubblicati su Internet',
        'Port forwarding obsoleti o privi di giustificazione',
        'Accesso remoto privo di MFA nonostante la disponibilità',
        'Protocolli di amministrazione non cifrati ancora attivi',
        'Regole firewall eccessivamente permissive',
        'Gestione firewall raggiungibile dalle reti utente',
        'Configurazione firewall non sottoposta a backup',
    ],
    'Rete' => [
        'Unica connessione WAN in una sede con requisiti di continuità',
        'Seconda WAN presente ma failover non configurato',
        'Failover WAN configurato ma mai verificato',
        'Connessione WAN di backup dipendente dallo stesso punto di guasto',
        'Stato delle connessioni WAN non monitorato',
        'Rete completamente flat nonostante asset con esigenze di separazione',
        'Rete guest priva di VLAN dedicata',
        'Dispositivi IoT privi di VLAN dedicata',
        'Server e servizi critici privi di segmentazione',
        'Telefonia VoIP priva di segmentazione quando necessaria',
        'Traffico inter-VLAN non limitato',
        'Rete di management non separata',
        'Switch unmanaged usato dove sono richiesti controllo e diagnostica',
        'Switch core non più supportato dal produttore',
        'Protezione dai loop assente su una topologia ridondata',
        'Configurazioni degli switch non archiviate',
        'NTP incoerente sugli apparati di rete',
        'Indirizzi di management non documentati',
        'Gestione degli indirizzi IP incoerente o soggetta a conflitti',
        'Apparati core non sottoposti a monitoraggio',
        'Cifratura Wi-Fi obsoleta',
        'WPS abilitato senza necessità operativa',
        'Rete Wi-Fi guest non isolata dalla LAN',
        'Client IoT e sistemi aziendali condividono la stessa rete Wi-Fi',
        'Access point consumer non coordinati in un impianto multi-AP',
        'Configurazioni incoerenti tra access point dello stesso impianto',
        'Gestione centralizzata Wi-Fi assente in un impianto multi-AP complesso',
        'Posizionamento degli access point inadeguato agli ambienti serviti',
        'Copertura Wi-Fi insufficiente nelle aree operative',
        'Interferenza radio evidente non analizzata',
        'Pianificazione dei canali 2,4 GHz incoerente',
        'Ampiezza di canale Wi-Fi inappropriata al contesto',
        'Configurazione delle bande 2,4 e 5 GHz incoerente',
        'Potenza trasmissiva sbilanciata rispetto alla topologia',
        'Roaming compromesso da SSID o sicurezza incoerenti',
        'Credenziali amministrative degli access point predefinite',
        'Firmware degli access point non più supportato',
    ],
    'Cablaggio e Infrastruttura Fisica' => [
        'Porte del patch panel non documentate',
        'Patch cord danneggiati o palesemente inadeguati',
        'Apparati installati senza supporto meccanico idoneo',
        'Ventilazione del rack insufficiente',
        'Rack accessibile senza controllo fisico adeguato',
        'Cablaggio del rack ostacola manutenzione e ventilazione',
        'Alimentazioni improvvisate o concatenate in modo pericoloso',
        'Apparati critici privi di UPS nonostante il rischio di interruzione',
        'UPS sottodimensionato rispetto al carico collegato',
        'Batterie UPS degradate senza piano di sostituzione',
    ],
    'Server' => [
        'Sistema operativo server non più supportato',
        'Hypervisor non più supportato',
        'Firmware critico dei server obsoleto',
        'Patching dei server non gestito',
        'Server critico escluso dal backup',
        'Macchine virtuali critiche escluse dal backup',
        'Ripristino delle macchine virtuali mai verificato',
        'Storage server privo della ridondanza richiesta',
        'RAID server degradato o non monitorato',
        'Alert hardware dei server non configurati',
        'Accessi amministrativi ai server non adeguatamente protetti',
        'Account amministrativi usati per attività quotidiane sui server',
        'Spegnimento controllato dei server su evento UPS non configurato',
        'Single point of failure server non documentato o mitigato',
    ],
    'NAS e Storage' => [
        'NAS privo di una copia di backup indipendente',
        'RAID del NAS considerato erroneamente un backup',
        'Snapshot del NAS assenti nonostante siano disponibili e utili',
        'Snapshot del NAS modificabili dallo stesso account della produzione',
        'Copia off-site dei dati NAS assente',
        'Protezione offline o immutabile assente per dati esposti a ransomware',
        'Disco USB sempre collegato usato come unica copia aggiuntiva',
        'Ripristino dal NAS mai verificato',
        'NAS pubblicato direttamente su Internet',
        'Firmware del NAS non più supportato',
        'Protocollo SMB1 attivo senza necessità documentata',
        'Condivisioni guest o anonime attive sul NAS',
        'Permessi delle condivisioni NAS eccessivi',
        'Account amministrativi NAS predefiniti o deboli',
        'MFA amministrativa NAS assente nonostante il supporto',
        'Capacità e spazio disponibile del NAS non monitorati',
    ],
    'Backup' => [
        'Backup dei dati e sistemi importanti completamente assente',
        'Dati o sistemi importanti esclusi dalla policy di backup',
        'Copia di backup off-site assente',
        'Protezione offline o immutabile dei backup assente',
        'Backup accessibile con le stesse credenziali della produzione',
        'Repository di backup modificabile direttamente dai sistemi protetti',
        'Retention dei backup non definita o non verificata',
        'Fallimenti dei job di backup privi di alert',
        'Ultimo test di ripristino troppo remoto rispetto al rischio',
        'Configurazioni di rete firewall o hypervisor escluse dai backup',
        'Servizi SaaS critici privi di strategia di protezione dati',
        'RPO e RTO non definiti per i sistemi critici',
    ],
    'Endpoint' => [
        'Postazioni Windows con versione non più supportata',
        'Postazioni Windows prive di password',
        'Account Windows condivisi tra più persone',
        'Utenti Windows con privilegi amministrativi non necessari',
        'Cifratura BitLocker assente su dispositivi esposti a rischio dati',
        'Antivirus o EDR Windows assente o disabilitato',
        'Aggiornamenti Windows disabilitati',
        'Patch management assente su una flotta che richiede controllo',
        'Firewall locale Windows disabilitato senza giustificazione',
        'Blocco schermo Windows non configurato',
        'Software endpoint non più supportato',
        'Gestione centralizzata Windows assente su una flotta non controllabile manualmente',
        'Dispositivi macOS con versione non più supportata',
        'FileVault assente su Mac esposti a rischio dati',
        'Utenti macOS con privilegi amministrativi non necessari',
        'Aggiornamenti macOS non gestiti',
        'Protezione endpoint macOS assente quando prevista',
        'Blocco schermo macOS non configurato',
        'Gestione MDM assente su una flotta Mac non controllabile manualmente',
    ],
    'Identità e Accessi' => [
        'Credenziali condivise tra più persone',
        'Password annotate su supporti cartacei non protetti',
        'Password conservate in fogli di calcolo o documenti non protetti',
        'Password manager assente nonostante riuso e condivisione delle credenziali',
        'Stesse credenziali amministrative riutilizzate su sistemi diversi',
        'Account di ex dipendenti ancora attivi',
        'Account inutilizzati non sottoposti a revisione',
        'MFA assente sugli account amministrativi',
        'MFA assente sui servizi cloud critici',
        'Account privilegiato usato per le attività quotidiane',
        'Account amministrativo separato assente',
        'Privilegi assegnati oltre le necessità operative',
        'Account generici privi di tracciabilità individuale',
    ],
    'Cloud e Microsoft 365' => [
        'MFA assente per gli amministratori Microsoft 365',
        'MFA utenti cloud non applicata nonostante il rischio',
        'Autenticazione legacy ancora consentita',
        'Numero eccessivo di Global Administrator',
        'Account amministrativi cloud usati come mailbox ordinarie',
        'Condivisione anonima cloud eccessivamente permissiva',
        'Link pubblici a documenti cloud non controllati',
        'Inoltri esterni cloud non sottoposti a verifica',
        'Audit e logging cloud insufficienti rispetto alle capacità disponibili',
        'Dati SaaS critici privi di strategia di backup o recovery',
        'Configurazioni di sicurezza cloud non riesaminate',
    ],
    'Posta Elettronica' => [
        'Record SPF assente per un dominio di invio',
        'Record SPF palesemente errato',
        'Firma DKIM assente nonostante il supporto del provider',
        'Policy DMARC assente',
        'Configurazione DMARC incoerente con i flussi di posta',
        'MFA assente sulle mailbox aziendali esposte',
        'Inoltro esterno sospetto o non governato',
        'Account condivisi usati impropriamente come identità personali',
        'Mailbox critiche prive di protezioni adeguate',
        'Domini di invio non monitorati',
    ],
    'VoIP' => [
        'PBX amministrabile da Internet senza protezione adeguata',
        'Servizio SIP esposto a Internet senza necessità',
        'Password delle estensioni VoIP deboli o predefinite',
        'Firmware del PBX non più supportato',
        'Telefoni VoIP non più supportati',
        'Configurazione del PBX non sottoposta a backup',
        'Accessi remoti VoIP non adeguatamente protetti',
        'Trasporto sicuro VoIP assente quando richiesto dall’architettura',
        'VoIP non segmentato nonostante un rischio concreto',
        'PBX critico privo di UPS',
    ],
    'Videosorveglianza' => [
        'NVR o telecamere con credenziali predefinite',
        'NVR o telecamere pubblicati direttamente su Internet',
        'Firmware della videosorveglianza non più supportato',
        'Videosorveglianza non segmentata dalla LAN aziendale',
        'Accessi remoti alla videosorveglianza non protetti',
        'Ora di NVR e telecamere non sincronizzata',
        'Storage NVR degradato o non monitorato',
        'Configurazione NVR non sottoposta a backup',
    ],
    'Continuità Operativa' => [
        'Spegnimento automatico su evento UPS non configurato',
        'Single point of failure sul firewall critico',
        'Single point of failure sul core switch critico',
        'Single point of failure sullo storage critico',
        'Failover dei servizi critici non testato',
        'Procedura di recovery operativa assente',
        'Tempo di ripristino mai misurato',
        'Credenziali e procedure di recovery non disponibili in emergenza',
    ],
    'Monitoraggio' => [
        'Sistemi critici privi di monitoraggio',
        'Fault hardware privi di alert',
        'Fallimenti backup non inclusi nel monitoraggio',
        'Spazio disco e capacità privi di alert',
        'Log critici non centralizzati nonostante la necessità',
        'Retention dei log non definita',
        'Orologi dei sistemi non sincronizzati',
        'Eventi di sicurezza mai sottoposti a revisione',
        'Indisponibilità dei servizi essenziali non rilevata automaticamente',
    ],
    'Documentazione' => [
        'Diagramma di rete assente',
        'Piano IP e VLAN assente',
        'Inventario degli asset insufficiente',
        'Configurazioni degli apparati non archiviate',
        'Documentazione delle procedure di backup assente',
        'Documentazione delle procedure di recovery assente',
        'Dipendenze dei servizi critici non documentate',
        'Ownership e processo delle credenziali non definiti',
        'Documentazione non allineata all’infrastruttura reale',
    ],
    'Licenze e Conformità' => [
        'Software non più supportato ancora in uso',
        'Software privo di licenza valida quando comprovabile',
        'Inventario delle licenze assente nonostante la necessità',
        'Dati personali sensibili non protetti in modo proporzionato al rischio',
        'Capacità di recupero dei dati personali non verificata',
        'Misure tecniche e organizzative mai sottoposte a verifica periodica',
    ],
];

$categoryContext = [
    'Governance IT' => ['organization', 'ruoli, processi, responsabili ed evidenze di riesame', 'decisioni non coordinate e tempi di risposta più lunghi'],
    'Sicurezza' => ['network', 'configurazioni perimetrali, esposizioni, log e accessi amministrativi', 'accessi non autorizzati e compromissione dei sistemi'],
    'Rete' => ['network', 'topologia, configurazioni, misure radio, VLAN e percorsi di traffico', 'interruzioni, propagazione degli incidenti e diagnosi più difficili'],
    'Cablaggio e Infrastruttura Fisica' => ['selected_sites', 'installazione fisica, alimentazione, ventilazione e tracciabilità dei collegamenti', 'guasti fisici e manutenzioni più rischiose'],
    'Server' => ['selected_assets', 'versioni, supporto, patch, log hardware, privilegi e copertura backup', 'indisponibilità dei servizi e perdita di dati'],
    'NAS e Storage' => ['selected_assets', 'stato volumi, accessi, protocolli, snapshot, copie e prove di restore', 'perdita o indisponibilità dei dati condivisi'],
    'Backup' => ['organization', 'job, repository, credenziali, retention, copie e risultati di ripristino', 'impossibilità di recuperare dati e servizi nei tempi attesi'],
    'Endpoint' => ['selected_assets', 'versioni, patch, privilegi, cifratura, protezione e criteri locali', 'compromissione degli endpoint e accesso ai dati aziendali'],
    'Identità e Accessi' => ['organization', 'account, assegnazioni, privilegi, autenticazione e processo di disattivazione', 'accessi non attribuibili o non autorizzati'],
    'Cloud e Microsoft 365' => ['organization', 'ruoli, metodi di autenticazione, condivisioni, audit e impostazioni del tenant', 'compromissione degli account e diffusione non controllata dei dati'],
    'Posta Elettronica' => ['organization', 'DNS del dominio, configurazione del provider, autenticazione e inoltri', 'abuso del dominio, frodi e compromissione delle comunicazioni'],
    'VoIP' => ['selected_assets', 'esposizioni, firmware, credenziali, segmentazione e copie di configurazione', 'interruzione o uso abusivo del servizio telefonico'],
    'Videosorveglianza' => ['selected_assets', 'esposizioni, firmware, credenziali, segmentazione, orari e stato storage', 'accesso non autorizzato alle immagini o perdita delle registrazioni'],
    'Continuità Operativa' => ['organization', 'dipendenze, ridondanze, procedure, prove e accessi di emergenza', 'fermi prolungati e ripristini non governati'],
    'Monitoraggio' => ['organization', 'copertura, soglie, destinatari, retention e prove di consegna degli alert', 'guasti rilevati tardi e tempi di indisponibilità maggiori'],
    'Documentazione' => ['organization', 'documenti disponibili, data di revisione, ownership e corrispondenza con lo stato reale', 'interventi più lenti e maggiore rischio di errore'],
    'Licenze e Conformità' => ['organization', 'inventari, evidenze, stato del supporto e valutazione del rischio sui dati', 'rischi operativi, contrattuali e di protezione dei dati'],
];

$existingIds = array_fill_keys(array_column($templates, 'external_id'), true);
foreach ($catalog as $category => $titles) {
    [$scope, $technicalCheck, $businessImpact] = $categoryContext[$category];
    foreach ($titles as $title) {
        $slug = Str::limit(Str::slug(Str::lower($title)), 140, '');
        $externalId = Str::slug(Str::lower($category)).'.'.$slug;
        if (isset($existingIds[$externalId])) {
            throw new RuntimeException("Duplicate generated external_id: {$externalId}");
        }
        $existingIds[$externalId] = true;

        $critical = str_contains($title, 'pubblicat')
            || str_contains($title, 'esposta a Internet')
            || str_contains($title, 'completamente assente')
            || str_contains($title, 'predefinite');
        $moderate = $category === 'Documentazione'
            || str_contains($title, 'non pianificata')
            || str_contains($title, 'non censiti')
            || str_contains($title, 'non registrate');
        [$consequence, $likelihood, $priority] = $critical
            ? ['critical', 'likely', 'critical']
            : ($moderate ? ['significant', 'likely', 'moderate'] : ['serious', 'possible', 'high']);
        $action = preg_replace('/^(Assenza di |Assente |Mancanza di )/u', '', $title) ?: $title;

        $templates[] = [
            'external_id' => $externalId,
            'title' => $title,
            'category' => $category,
            'problem' => "Durante l'assessment è stata osservata questa condizione: {$title}. L'evidenza deve essere confermata sul perimetro indicato e distinta da eccezioni temporanee o documentate.",
            'entrepreneur_notes' => "La condizione può causare {$businessImpact}. La priorità proposta va confermata considerando dipendenze, dati trattati e continuità richiesta dall'azienda.",
            'technical_notes' => "Verificare {$technicalCheck}. Conservare evidenze dello stato rilevato e delle eventuali eccezioni approvate.",
            'default_scope_type' => $scope,
            'default_scope_description' => null,
            'consequence' => $consequence,
            'likelihood' => $likelihood,
            'priority' => $priority,
            'priority_rationale' => "La condizione può produrre {$businessImpact}; probabilità e impatto devono essere confermati con le evidenze raccolte.",
            'active' => true,
            'solutions' => [[
                'external_id' => 'remediation.'.Str::limit($slug, 145, ''),
                'title' => 'Correggere e verificare la condizione rilevata',
                'description' => "Definire il requisito operativo, correggere la condizione «{$action}», verificare il risultato con una prova ripetibile e aggiornare la documentazione tecnica pertinente.",
                'comparison_notes' => null,
                'effort' => $critical ? 'high' : 'moderate',
                'effort_notes' => 'L’impegno dipende dal numero di sistemi coinvolti, dalle dipendenze e dalle finestre di intervento disponibili.',
                'estimate_type' => 'requires_analysis',
                'amount_min' => null,
                'amount_max' => null,
                'currency_code' => null,
                'billing_frequency' => 'one_off',
                'custom_billing_frequency' => null,
                'estimate_notes' => 'La stima richiede conferma del perimetro e delle condizioni tecniche rilevate.',
                'recommended' => true,
                'sort_order' => 0,
            ]],
        ];
    }
}

$output = json_encode(
    ['schema_version' => 2, 'templates' => $templates],
    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
)."\n";

if (file_put_contents($path, $output) === false) {
    throw new RuntimeException("Unable to write {$path}.");
}

fwrite(STDOUT, sprintf("Generated %d finding templates.\n", count($templates)));
