@extends('installation.layout', ['title' => 'Requisiti', 'step' => 2])

@section('content')
    <p class="installer-eyebrow">Pre-flight reale</p>
    <h1>Requisiti runtime e filesystem</h1>
    <p>Ogni controllo è eseguito dal processo PHP web. WeasyPrint genera un PDF minimo reale; il PHP CLI viene avviato come processo separato.</p>

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
