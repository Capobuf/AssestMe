<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'AssestMe',
    ],
    'admin' => [
        'created' => 'Amministratore AssestMe configurato correttamente.',
        'prompts' => [
            'name' => 'Nome amministratore',
            'email' => 'Email amministratore',
            'password' => 'Password amministratore',
            'replace_confirmation' => 'Confermi la sostituzione esplicita delle credenziali dell’amministratore?',
        ],
        'errors' => [
            'already_exists' => 'Esiste già un amministratore. Usa --replace per una sostituzione esplicita.',
            'current_password' => 'La password amministratore corrente non è corretta.',
            'replace_confirmation' => 'In modalità non interattiva --replace richiede --current-password.',
        ],
    ],
    'assessments' => [
        'navigation' => 'Assessment',
        'singular' => 'assessment',
        'plural' => 'assessment',
        'group' => 'Assessment',
        'status' => [
            'draft' => 'Bozza',
            'completed' => 'Completato',
            'archived' => 'Archiviato',
        ],
        'fields' => [
            'title' => 'Titolo',
            'date' => 'Data assessment',
            'status' => 'Stato',
            'findings' => 'Finding',
        ],
    ],
    'workspace' => [
        'title' => 'Workspace assessment',
        'open' => 'Apri workspace',
        'assessment_section' => 'Dettagli assessment',
        'findings' => 'Finding',
        'add_finding' => 'Aggiungi finding',
        'save_state' => 'Stato salvataggio',
        'saved_notification' => 'Assessment salvato',
        'explicit_save' => 'Salva assessment',
        'download_pdf' => 'Scarica PDF di prova',
        'download_xlsx' => 'Scarica XLSX di prova',
        'status' => [
            'saved' => 'Salvato',
            'unsaved' => 'Modifiche non salvate',
            'saving' => 'Salvataggio…',
            'error' => 'Errore di salvataggio',
            'conflict' => 'Conflitto: ricaricare',
            'offline' => 'Offline',
        ],
        'errors' => [
            'invalid_request' => 'La richiesta di salvataggio non è valida.',
            'payload_hash' => 'Il contenuto della richiesta non corrisponde alla firma inviata.',
            'assessment_missing' => 'L’assessment non esiste più.',
            'finding_ownership' => 'Uno o più finding non appartengono a questo assessment.',
            'conflict' => 'L’assessment è stato modificato in un’altra scheda. Ricarica la pagina prima di continuare.',
            'validation' => 'Correggi i campi indicati. Le modifiche sono rimaste nella pagina.',
            'persistence' => 'Il salvataggio non è riuscito. Le modifiche sono rimaste nella pagina.',
        ],
    ],
    'findings' => [
        'fields' => [
            'title' => 'Titolo',
            'problem' => 'Problema',
            'entrepreneur_notes' => 'Note per l’imprenditore',
            'recommended_solution' => 'Soluzione raccomandata',
            'priority' => 'Priorità',
            'effort' => 'Impegno',
            'estimate_type' => 'Tipo stima',
            'estimate_notes' => 'Note stima',
            'status' => 'Stato',
            'include' => 'Nel report',
        ],
        'priority' => [
            'low' => 'Bassa',
            'moderate' => 'Moderata',
            'high' => 'Alta',
            'critical' => 'Critica',
        ],
        'effort' => [
            'low' => 'Basso',
            'moderate' => 'Moderato',
            'high' => 'Alto',
            'very_high' => 'Molto alto',
        ],
        'estimate_type' => [
            'exact' => 'Esatta',
            'range' => 'Intervallo',
            'bundled' => 'Inclusa in altre attività',
            'requires_quote' => 'Richiede preventivo',
            'requires_analysis' => 'Richiede approfondimento',
            'variable' => 'Variabile',
            'not_applicable' => 'Non applicabile',
        ],
        'status' => [
            'open' => 'Aperto',
            'planned' => 'Pianificato',
            'in_progress' => 'In corso',
            'resolved' => 'Risolto',
            'accepted' => 'Accettato',
            'not_applicable' => 'Non applicabile',
        ],
    ],
];
