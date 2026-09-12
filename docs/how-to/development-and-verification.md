# Development and verification

## Prerequisites

The supported workstation provides Docker, Docker Compose, Git, and the repository. The project-owned image provides PHP, Composer, extensions, WeasyPrint, and project utilities.

## Start

```bash
docker compose -f docker/compose.dev.yml up --build
```

Open:

```text
http://127.0.0.1:8000/admin
```

An authorized local-network client may use the development host IPv4 address on port 8000.

## Bootstrap behavior

The Docker entrypoint calls the environment-neutral bootstrap. It may create only missing local environment/state, install locked Composer dependencies when required, generate only a missing application key, apply pending migrations and idempotent domain seeds, publish required assets, preserve an existing singleton administrator, clear obsolete caches, and run preflight/diagnostics.

It must not run `migrate:fresh`, replace an application key, change existing credentials, create a second user, delete normal storage/backups, or hide errors.

## Focused verification

Select tests from the behavior changed. Examples:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test --filter=<relevant-test>
php artisan dusk --filter=<relevant-browser-test>
```

Rerun the failed test or command before running a broader gate.

## Mandatory pre-commit check

```bash
docker compose -f docker/compose.dev.yml exec -T app scripts/check.sh
```

Run this browser-free gate before every commit. It executes strict Composer validation, Pint,
PHPStan, and the Unit suite once with disposable SQLite. Composer audit remains a CI-only step.

## CI verification

The primary `.github/workflows/ci.yml` workflow runs on pull requests to and pushes on `main`, plus
manual dispatch. Its `application` job runs `scripts/test-app.sh` with MariaDB 12.3.3;
its `installer` job validates one extracted release ZIP with MariaDB 12.3.3. The SQLite/MySQL
compatibility smoke runs only on branch pushes and manual dispatch. No job nests another complete
gate.

## Production release

Production uses the maintained prebuilt ZIP. Do not run Composer or Node.js on the production server. Follow the general hosting guide and the applicable panel guide.
