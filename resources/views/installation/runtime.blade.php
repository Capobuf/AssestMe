@extends('installation.layout', ['title' => 'Requisiti', 'step' => 2])

@section('content')
    <p class="installer-eyebrow">Pre-flight reale</p>
    <h1>Requisiti runtime e filesystem</h1>
    <p>Ogni controllo è eseguito dal processo PHP web. WeasyPrint genera un PDF minimo reale; il PHP CLI viene avviato come processo separato.</p>

    @if (! $inspection->requirement('runtime.weasyprint')?->passed)
        <div class="installer-alert installer-alert-error" role="alert">
            <strong>WeasyPrint è obbligatorio per generare i report PDF.</strong>
            <p>Su Debian e Ubuntu esegui:</p>
            <pre><code>sudo apt update
sudo apt install -y weasyprint
weasyprint --version</code></pre>
            <p>Se non disponi di accesso root o sudo, chiedi al provider hosting di installare WeasyPrint.</p>
            <p>Dopo l’installazione premi “Verifica nuovamente”.</p>
            <small>Dettaglio tecnico: {{ $inspection->requirement('runtime.weasyprint')?->actual }}</small>
        </div>
    @endif

    <div class="installer-requirements">
        @foreach ($inspection->requirements() as $requirement)
            <div @class(['requirement', 'is-passed' => $requirement->passed, 'is-failed' => ! $requirement->passed])>
                <span aria-hidden="true">{{ $requirement->passed ? '✓' : '!' }}</span>
                <div>
                    <strong>{{ $requirement->key }}</strong>
                    <small>Atteso: {{ $requirement->expected }} · Rilevato: {{ $requirement->actual }}</small>
                </div>
                <b>{{ $requirement->passed ? 'Superato' : 'Non superato' }}</b>
            </div>
        @endforeach
    </div>

    <form method="post" action="{{ route('installation.runtime.continue') }}" class="installer-actions">
        @csrf
        <button class="installer-button installer-button-secondary" type="submit" name="refresh" value="1">Verifica nuovamente</button>
        <button class="installer-button" type="submit" @disabled(! $inspection->passed())>Continua</button>
    </form>
@endsection
