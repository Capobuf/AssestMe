@extends('installation.layout', ['title' => 'Installazione completata', 'step' => 6])

@section('content')
    <p class="installer-eyebrow" data-dusk="installation-complete">Installazione completata</p>
    <h1>AssestMe è pronto</h1>
    <div class="installer-alert installer-alert-warning">
        Mantieni attiva una protezione temporanea del sito, come Basic Authentication, protezione directory o restrizione IP, finché non hai verificato il primo login.
    </div>

    <div class="installer-requirements">
        @foreach ($checks as $check)
            <div @class(['requirement', 'is-passed' => $check['status'] === 'passed', 'is-failed' => $check['status'] === 'failed'])>
                <span aria-hidden="true">{{ $check['status'] === 'passed' ? '✓' : ($check['status'] === 'pending' ? '…' : '!') }}</span>
                <div><strong>{{ $check['label'] }}</strong><small>{{ $check['detail'] }}</small></div>
                <b>{{ ['passed' => 'Superato', 'failed' => 'Non superato', 'pending' => 'Da configurare'][$check['status']] }}</b>
            </div>
        @endforeach
    </div>

    <section class="installer-cron" data-scheduler-panel>
        <h2>Scheduler</h2>
        <p>Configura un cron job dal pannello hosting usando il PHP CLI rilevato.</p>
        <p><strong>Frequenza:</strong></p>
        <code>* * * * *</code>
        <p><strong>Comando:</strong></p>
        <code>{{ $cronCommand }}</code>
        <details>
            <summary>CloudPanel</summary>
            <p><strong>Sites → dominio → Cron Jobs → Add Cron Job</strong></p>
            <p>Imposta separatamente frequenza e comando.</p>
        </details>
        <details>
            <summary>cPanel</summary>
            <p><strong>Advanced → Cron Jobs → Add New Cron Job</strong></p>
            <p>Minute: *<br>Hour: *<br>Day: *<br>Month: *<br>Weekday: *<br>Command: <code>{{ $cronCommand }}</code></p>
        </details>
        <details>
            <summary>Plesk</summary>
            <p><strong>Websites &amp; Domains → Scheduled Tasks → Add Task</strong></p>
            <p>Usa “Run a command” e inserisci il comando generato.</p>
            <p>Su Plesk il task può essere eseguito in un ambiente chroot. Se il percorso PHP rilevato non è raggiungibile dal task pianificato, usa l’opzione “Run a PHP script” o chiedi al provider il percorso corretto.</p>
        </details>
        <details>
            <summary data-dusk="scheduler-shell">Shell o pannello generico</summary>
            <code>crontab -e</code>
            <code>* * * * * {{ $cronCommand }}</code>
        </details>
        <p data-scheduler-status>Scheduler {{ $scheduler->isRecent() ? 'verificato' : 'non ancora verificato' }}.</p>
        <button class="installer-button installer-button-secondary" type="button" data-recheck-health>Verifica nuovamente</button>
    </section>

    <p><a class="installer-button" href="/admin/login">Accedi ad AssestMe</a></p>
@endsection
