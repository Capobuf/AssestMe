# AssestMe

AssestMe is a single-user Laravel application for structured IT assessments of small and medium-sized organizations.

This repository uses a small documentation set instead of the former monolithic `plan.md`. Start from [docs/index.md](docs/index.md).

## Current baseline

- Documentation baseline: specification 2.7.
- Source branch: `develop`.
- Source commit inspected: `d466ff1c0c3851fecee3d468cde7cb9359db5ae4`.
- Original `plan.md` Git blob: `c201bf64ec39519e1f26eba5f18e63886f3ae8ae`.
- PHP contract: PHP web and CLI 8.3.0 or newer.
- Application stack: Laravel 13, Filament 5, Livewire 4.
- Databases for fresh installations: SQLite, MySQL, MariaDB.
- PDF renderer: WeasyPrint through Spatie Laravel PDF.
- Supported production model: prebuilt release ZIP plus web installer on verified CloudPanel or traditional PHP hosting capabilities.

## Development

The maintained local development entry point is:

```bash
docker compose -f docker/compose.dev.yml up --build
```

The primary local URL is:

```text
http://127.0.0.1:8000/admin
```

Operational commands, environment requirements, and verification gates are documented in [docs/how-to/development-and-verification.md](docs/how-to/development-and-verification.md).

## Documentation map

- Product scope: [docs/explanation/product-and-scope.md](docs/explanation/product-and-scope.md)
- Architecture: [docs/explanation/architecture.md](docs/explanation/architecture.md)
- Accepted decisions: [docs/adr/README.md](docs/adr/README.md)
- Runtime and dependencies: [docs/reference/runtime-and-dependencies.md](docs/reference/runtime-and-dependencies.md)
- Domain and application contracts: [docs/reference/domain-and-application-contracts.md](docs/reference/domain-and-application-contracts.md)
- UI, persistence, import, and export: [docs/reference/ui-persistence-import-export.md](docs/reference/ui-persistence-import-export.md)
- Reports, evidence, and private files: [docs/reference/reports-evidence-and-files.md](docs/reference/reports-evidence-and-files.md)
- Testing, security, and acceptance: [docs/reference/testing-security-and-acceptance.md](docs/reference/testing-security-and-acceptance.md)
- Hosting: [docs/how-to/hosting-installation.md](docs/how-to/hosting-installation.md)
- Documentation baseline and traceability: [docs/_meta/source-map.md](docs/_meta/source-map.md)

## Authority

The authority order is defined in [AGENTS.md](AGENTS.md) and [docs/index.md](docs/index.md). The root `plan.md` is now a compatibility index only; it is not a second source of truth.
