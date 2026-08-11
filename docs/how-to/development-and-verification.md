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

## Complete verification

```bash
docker compose -f docker/compose.dev.yml exec -T app scripts/verify.sh
```

Run the complete gate at coherent change-set completion, before merging to `main`, at milestone completion, after dependency/runtime/test-infrastructure changes, or when explicitly requested.
`scripts/verify.sh` fails before any check or mutation when invoked on the host or outside the marked
`app` service from `docker/compose.dev.yml`.

## Production release

Production uses the maintained prebuilt ZIP. Do not run Composer or Node.js on the production server. Follow the general hosting guide and the applicable panel guide.
