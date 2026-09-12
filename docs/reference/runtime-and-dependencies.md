# Runtime and dependencies

## Runtime contract

- PHP web and CLI: 8.3.0 or newer.
- WeasyPrint: 60.0 or newer.
- No maximum PHP version is declared by documentation alone; the installer and dependency set validate the actual runtime.
- No operating-system distribution or CPU architecture is normative.
- Timestamps are stored in UTC and presented in `Europe/Rome`.

Required PHP extensions:

```text
bcmath ctype curl dom fileinfo filter gd iconv intl libxml mbstring
openssl pdo phar session simplexml tokenizer xml xmlreader xmlwriter
zip zlib
```

Database-specific extension:

- SQLite: `pdo_sqlite`;
- MySQL/MariaDB: `pdo_mysql`.

## Application stack

- Laravel 13.
- Filament 5 Panel Builder.
- Livewire 4.
- File cache and sessions.
- Synchronous queue.
- SQLite, MySQL, and MariaDB for fresh installations.
- WeasyPrint 60.0 or newer as the sole PDF renderer through Spatie Laravel PDF. Its version is
  read only from `weasyprint --version`; a separate minimal real-PDF probe verifies that the runtime
  is operational.
- PhpSpreadsheet as the direct XLSX dependency.
- Opis JSON Schema for template validation.
- Laravel Dusk for browser tests.

Optional Google Drive readable synchronization retains the locked versions of Laravel Socialite,
Yaza Laravel Google Drive Storage v5, Revolution Laravel Google Sheets, and Google PHP Client intact.
AssestMe uses the public Google Drive client directly for exact-parent-ID mutations and Revolution's
public API for Sheet values. The administrator
configures only the OAuth client ID and encrypted write-only client secret from the native Google
Drive Settings page; AssestMe calculates and displays the callback URI read-only. Optional
`GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` values are defaults only and are not installation requirements.
The integration is inactive unless effective application configuration, one connected user account,
and the application-created My Drive root are present. It adds no browser selector, API key, project
number, Node.js, Redis, queue-worker, service-account,
or production cloud dependency to installations that do not enable it.

`composer.lock` is the authoritative exact dependency inventory. Vendor and transitive dependency
code is never edited, patched, monkey-patched, copied for modification, or replaced by a fork.
Application integration uses public package APIs through AssestMe-owned services; no runtime logic
depends on a dependency's display-path implementation.

## Prohibited runtime requirements

Normal application operation must not require:

- Node.js, npm, pnpm, Vite, or an application frontend build;
- Redis, Horizon, or a queue worker;
- Chromium or Chrome for report generation;
- an external PDF, storage, or cloud service;
- Docker in production;
- Composer on the production host.

The optional Selenium service is isolated Dusk infrastructure only.

The external-cloud prohibition applies to required normal operation. The explicitly optional
Google Drive readable copy is an output integration: local saves, PDF/XLSX generation, private
storage, backup, restore, and source-of-truth behavior remain fully local and independent.

## Development environment

The maintained development command is:

```bash
docker compose -f docker/compose.dev.yml up --build
```

The `app` service:

- works at `/workspace`;
- bind-mounts the repository;
- runs Laravel's server on `0.0.0.0:8000` with `--no-reload`;
- publishes port 8000 on host IPv4 interfaces;
- uses the host repository UID/GID for writes;
- preserves `.env`, `vendor`, database state, storage, reports, evidence, and backups in the bind-mounted repository;
- contains no Nginx, PHP-FPM, Node.js, Redis, database server, Supervisor, systemd, Horizon, Chromium, or ChromeDriver.
- is based on Debian Trixie so its APT-provided WeasyPrint satisfies the 60.0 minimum.

Optional Compose profiles provide Selenium and isolated real MySQL/MariaDB compatibility services. They are not mandatory dependencies of normal `up`.

## Database contract

- Fresh installations support SQLite, MySQL, and MariaDB.
- Migrations must remain portable.
- No in-application migration or conversion between database drivers is provided.
- SQLite keeps its foreign-key and approved concurrency settings.
- Server databases are checked for product identity, version, charset, engine, privileges, schema suitability, constraints, transactions, and probe cleanup.
- Compatibility claims are limited to versions actually exercised in CI or real acceptance evidence.

## Production runtime

The installer verifies PHP, required extensions, writable secure private directories, PHP CLI, WeasyPrint, database capability, and scheduler instructions. It does not install packages, run `sudo`, alter control-panel configuration, or claim compatibility from a panel name.
