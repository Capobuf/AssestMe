AssestMe — specification 2.6

Primary files:
- plan.md: authoritative executable product, architecture, deployment, data, UI, failure, test, and acceptance specification.
- AGENTS.md: mandatory repository-level instructions for coding agents.
- .gitignore: Laravel, SQLite, private-storage, browser-test, and local-agent exclusions.
- .env.example: SQLite/MySQL/MariaDB, WeasyPrint, security, installer, and deployment variables.
- docs/cloudpanel-installation.md: Italian CloudPanel GUI installation, update, backup, and rollback procedure.
- docs/cloudpanel-acceptance-checklist.md: manual real-instance acceptance record.

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
- scripts/preflight.sh: verifies environment capabilities without making a Linux distribution normative.
- scripts/bootstrap-local.sh: initializes an implemented AssestMe checkout and creates local credentials.
- scripts/verify.sh: runs audit, formatting, static analysis, tests, Canary, and optional Dusk.
- scripts/build-cloudpanel-release.sh: creates a production-dependency CloudPanel ZIP and SHA-256 sidecar.
- scripts/cloudpanel-smoke.sh: verifies an installed CloudPanel site without installing operating-system packages.
- scripts/deploy-production.sh: release-based production deployment using shared SQLite/storage and pre-migration backup.

Development uses docker/compose.dev.yml. Production uses the versioned CloudPanel release ZIP, whose document root must be the included public directory. The native Filament 5 workspace and production WeasyPrint report pipeline remain authoritative.
