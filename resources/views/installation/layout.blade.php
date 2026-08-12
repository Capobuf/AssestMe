<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title>{{ $title }} — AssestMe</title>
    <link rel="icon" href="/images/brand/assestme-logo-black.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/css/assestme-installer.css">
</head>
<body>
    <header class="installer-header">
        <a class="installer-brand" href="/install" aria-label="AssestMe installer">
            <img src="/images/brand/assestme-logo-white.svg" alt="" aria-hidden="true">
            <strong>AssestMe</strong>
        </a>
        <span class="installer-version">Versione {{ config('assestme.version') }}</span>
    </header>

    <main class="installer-shell">
        <nav class="installer-steps" aria-label="Avanzamento installazione">
            @foreach (['Benvenuto', 'Requisiti', 'Applicazione', 'Database', 'Amministratore'] as $index => $label)
                <span @class(['is-current' => ($step ?? 1) === $index + 1, 'is-complete' => ($step ?? 1) > $index + 1])>
                    <b>{{ $index + 1 }}</b> {{ $label }}
                </span>
            @endforeach
        </nav>

        @if (session('installation_error'))
            <div class="installer-alert installer-alert-error" role="alert" data-dusk="installation-error">{{ session('installation_error') }}</div>
        @endif

        @if (session('installation_success'))
            <div class="installer-alert installer-alert-success" role="status">{{ session('installation_success') }}</div>
        @endif

        @if ($errors->any())
            <div class="installer-alert installer-alert-error" role="alert" data-dusk="validation-errors">
                <strong>Correggi i campi indicati.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <article class="installer-card">
            @yield('content')
        </article>
    </main>

    <footer class="installer-footer">Installer locale AssestMe · nessun asset o servizio esterno</footer>
    <script src="/js/assestme-installer.js" defer></script>
</body>
</html>
