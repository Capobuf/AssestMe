@extends('installation.layout', ['title' => 'Database', 'step' => 4])

@section('content')
    <p class="installer-eyebrow">Verifica capacità</p>
    <h1>Configura {{ $database->driver->label() }}</h1>
    <p>La verifica crea due tabelle casuali di probe, prova schema, vincoli, CRUD e rollback, quindi le elimina sempre. Un database non vuoto non verrà cancellato.</p>

    <form method="post" action="{{ route('installation.database.store') }}" class="installer-form" data-database-form>
        @csrf
        <input type="hidden" name="database_driver" value="{{ $database->driver->value }}">

        @if ($database->driver->value === 'sqlite')
            <label>Percorso file SQLite assoluto
                <input name="sqlite_path" required value="{{ old('sqlite_path', $database->database) }}">
                <small>Deve essere fuori da <code>public</code>; file e directory non possono essere symlink.</small>
            </label>
        @else
            <div class="installer-grid">
                <label>Host<input name="database_host" required value="{{ old('database_host', $database->host) }}"></label>
                <label>Porta<input name="database_port" required type="number" min="1" max="65535" value="{{ old('database_port', $database->port) }}"></label>
            </div>
            <label>Nome database<input name="database_name" required value="{{ old('database_name', $database->database) }}"></label>
            <label>Utente<input name="database_username" required value="{{ old('database_username', $database->username) }}"></label>
            <label>Password
                <input name="database_password" type="password" autocomplete="new-password">
                <small>{{ $database->password !== '' ? 'Una password cifrata è già presente nello stato di ripresa. Lascia vuoto per conservarla.' : 'La password non verrà mostrata né salvata in chiaro.' }}</small>
            </label>
            <label>Socket Unix opzionale<input name="database_socket" value="{{ old('database_socket', $database->socket) }}"></label>
            <div class="installer-grid">
                <label>Charset<input name="database_charset" readonly value="utf8mb4"></label>
                <label>Collation<input name="database_collation" readonly value="utf8mb4_unicode_ci"></label>
            </div>
            <label>Utility dump {{ $database->driver->label() }}<input name="dump_binary" required value="{{ old('dump_binary', $database->dumpBinary) }}"></label>
            <label>Client restore {{ $database->driver->label() }}<input name="restore_binary" required value="{{ old('restore_binary', $database->restoreBinary) }}"></label>
        @endif

        <button class="installer-button" type="submit">Testa realmente il database</button>
    </form>
@endsection
