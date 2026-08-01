@extends('installation.layout', ['title' => 'Installazione completata', 'step' => 6])

@section('content')
    <p class="installer-eyebrow">Installazione completata</p>
    <h1>AssestMe è pronto</h1>
    <div class="installer-alert installer-alert-warning">
        Mantieni la Basic Authentication di CloudPanel finché non hai effettuato con successo il primo login. Disattivala soltanto dopo la verifica.
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
        <h2>Scheduler CloudPanel</h2>
        <p>Apri <strong>CloudPanel → Sites → assestme → Cron Jobs → Add Cron Job</strong>.</p>
        <p>Esegui il cron come <strong>site user</strong> e inserisci i due valori separatamente:</p>
        <p><strong>Frequenza:</strong></p>
        <code>* * * * *</code>
        <p><strong>Comando:</strong></p>
        <code>{{ $cronCommand }}</code>
        <p>Alternativa SSH:</p>
        <code>crontab -e</code>
        <code>* * * * * {{ $cronCommand }}</code>
        <p data-scheduler-status>Scheduler {{ $scheduler->isRecent() ? 'verificato' : 'non ancora verificato' }}.</p>
        <button class="installer-button installer-button-secondary" type="button" data-recheck-health>Verifica nuovamente</button>
    </section>

    <p><a class="installer-button" href="/admin/login">Accedi ad AssestMe</a></p>
@endsection
