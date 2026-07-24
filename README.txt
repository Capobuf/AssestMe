AssestMe deterministic planning package — specification 2.1

Primary files:
- plan.md: authoritative executable product, architecture, deployment, data, UI, failure, test, and acceptance specification.
- AGENTS.md: mandatory repository-level instructions for coding agents.
- .gitignore: Laravel, SQLite, private-storage, browser-test, and local-agent exclusions.
- .env.example: local SQLite, WeasyPrint, security, administrator-bootstrap, and deployment variables.

Contracts and initial data:
- schemas/finding-template.schema.json: JSON Schema Draft 2020-12 contract.
- templates/base-findings.it.json: initial Italian finding library.
- fixtures/imports/: valid and invalid import cases, including all estimate branches and update semantics.
- fixtures/reports/assessment-50-findings.json: long report/performance fixture.
- fixtures/evidence/: valid and corrupt file fixtures.

Implementation stubs:
- resources/views/reports/assessment.blade.php: shared WeasyPrint and browser-preview report structure.
- stubs/config/database.php.fragment: deterministic SQLite connection settings.
- stubs/config/laravel-pdf.php.fragment: verified Spatie WeasyPrint settings.
- stubs/nginx/assestme.conf: production Nginx virtual host.
- stubs/php/assestme.ini: production PHP limits and security defaults.
- stubs/cron/assestme: scheduler cron entry.
- stubs/ci/quality.yml.stub: PHP 8.3 GitHub Actions quality and Dusk workflow.

Executable shell scripts:
- scripts/preflight.sh: verifies Ubuntu, architecture, PHP, extensions, Composer, Git, and SQLite.
- scripts/bootstrap-local.sh: initializes an implemented AssestMe checkout and creates local credentials.
- scripts/verify.sh: runs audit, formatting, static analysis, tests, Canary, and optional Dusk.
- scripts/deploy-production.sh: release-based production deployment using shared SQLite/storage and pre-migration backup.

Copy the package contents into the root of a new AssestMe repository. Read AGENTS.md and plan.md completely before implementation. The native Filament 5 workspace and production WeasyPrint report pipeline are authoritative. No third-party assessment-grid package is used in the first implementation.
