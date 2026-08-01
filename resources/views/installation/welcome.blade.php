@extends('installation.layout', ['title' => 'Benvenuto', 'step' => 1])

@section('content')
    <p class="installer-eyebrow">Prima configurazione</p>
    <h1>Installa AssestMe su questo dominio</h1>
    <p>Dominio rilevato: <strong>{{ $domain }}</strong><br>URL rilevato: <strong>{{ $detectedUrl }}</strong></p>

    <div class="installer-alert installer-alert-warning">
        <strong>Protezione obbligatoria prima di continuare.</strong>
        Mantieni attiva una protezione temporanea del sito, come Basic Authentication, protezione directory o restrizione IP, finché non hai completato l’installazione e verificato il primo accesso.
    </div>

    <ul class="installer-checklist">
        <li>Il database scelto deve essere nuovo e privo di tabelle applicative.</li>
        <li>Sono disponibili SQLite e MySQL / MariaDB; il prodotto server viene rilevato automaticamente.</li>
        <li>Non vengono convertiti né importati database di installazioni precedenti.</li>
        <li>Composer e Node.js non sono necessari sul server se usi il release ZIP.</li>
    </ul>

    <p><a href="{{ route('installation.documentation.hosting') }}" target="_blank" rel="noopener">Apri la guida hosting inclusa nella release</a></p>

    <form method="post" action="{{ route('installation.welcome.continue') }}">
        @csrf
        <button class="installer-button" type="submit">Avvia le verifiche</button>
    </form>
@endsection
