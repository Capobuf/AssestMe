@extends('installation.layout', ['title' => 'Configurazione', 'step' => 3])

@section('content')
    <p class="installer-eyebrow">Configurazione applicazione</p>
    <h1>Impostazioni di produzione</h1>

    <form method="post" action="{{ route('installation.configuration.store') }}" class="installer-form">
        @csrf
        <label>Nome applicazione
            <input name="application_name" required maxlength="120" value="{{ old('application_name', $application?->name ?? 'AssestMe') }}">
        </label>
        <label>APP_URL
            <input name="application_url" required type="url" value="{{ old('application_url', $application?->url ?? $detectedUrl) }}">
            <small>In produzione deve usare HTTPS e corrispondere al dominio corrente, senza percorsi aggiuntivi.</small>
        </label>
        <div class="installer-grid">
            <label>Fuso orario
                <input name="timezone" required value="{{ old('timezone', $application?->timezone ?? 'Europe/Rome') }}">
            </label>
            <label>Lingua
                <select name="locale"><option value="it" selected>Italiano</option></select>
            </label>
        </div>
        <label>Directory backup assoluta
            <input name="backup_root" required value="{{ old('backup_root', $application?->backupRoot ?? storage_path('backups')) }}">
        </label>
        <label>Binario WeasyPrint
            <input name="weasyprint_binary" required value="{{ old('weasyprint_binary', $application?->weasyPrintBinary ?? $inspection->weasyPrintBinary) }}">
        </label>
        <label>Binario PHP CLI 8.3
            <input name="php_binary" required value="{{ old('php_binary', $application?->phpBinary ?? $inspection->phpBinary) }}">
        </label>
        <fieldset>
            <legend>Database nuovo</legend>
            @foreach (['sqlite' => 'SQLite', 'mysql' => 'MySQL', 'mariadb' => 'MariaDB'] as $value => $label)
                <label class="installer-choice"><input type="radio" name="database_driver" value="{{ $value }}" @checked(old('database_driver', $database?->driver->value ?? 'sqlite') === $value)> <span><strong>{{ $label }}</strong></span></label>
            @endforeach
        </fieldset>
        <button class="installer-button" type="submit">Continua al database</button>
    </form>
@endsection
