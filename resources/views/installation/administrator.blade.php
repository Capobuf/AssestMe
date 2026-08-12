@extends('installation.layout', ['title' => 'Amministratore', 'step' => 5])

@section('content')
    <p class="installer-eyebrow">Ultimo passaggio</p>
    <h1>Crea l’unico amministratore</h1>
    <p>La password non viene mai inserita nello stato di avanzamento, nei log o nei messaggi. L’invio avvia migration riprendibili, seed, test finali e chiusura irreversibile dell’installer.</p>

    <form method="post" action="{{ route('installation.finalize') }}" class="installer-form" data-dusk="administrator-step">
        @csrf
        <label>Nome<input name="name" required maxlength="120" value="{{ old('name') }}" autocomplete="name"></label>
        <label>Email<input name="email" required type="email" maxlength="254" value="{{ old('email') }}" autocomplete="email"></label>
        <label>Password<input name="password" required type="password" minlength="14" maxlength="128" autocomplete="new-password"></label>
        <label>Conferma password<input name="password_confirmation" required type="password" minlength="14" maxlength="128" autocomplete="new-password"></label>
        <small>Da 14 a 128 caratteri, con maiuscole, minuscole, numeri e simboli.</small>
        <div class="installer-actions">
            <a class="installer-button installer-button-secondary" href="{{ route('installation.database') }}">Indietro</a>
            <button class="installer-button" type="submit">Installa e chiudi l’installer</button>
        </div>
    </form>
@endsection
