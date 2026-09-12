# ADR: Runtime and dependencies

- Status: Accepted
- Baseline: AssestMe specification 2.7
- Source commit: `d466ff1c0c3851fecee3d468cde7cb9359db5ae4`

## Context

Defines the framework, runtime capabilities, development environment, dependency authority, and prohibited alternate stacks.

## Decisions

| ID | Status | Decision |
|---|---|---|
| D-001 | APPROVED | Laravel 13 and Filament 5 Panel Builder |
| D-003 (v1) | SUPERSEDED | Ubuntu Server 24.04 LTS was the normative reference environment; superseded by D-003 on 2026-07-17 |
| D-003 | APPROVED | No host operating-system distribution is normative; runtime and bootstrap are capability-based |
| D-004 (v1) | SUPERSEDED | PHP 8.3 was required from the official Ubuntu 24.04 repositories; superseded by D-004 on 2026-07-17 |
| D-004 (v2) | SUPERSEDED | PHP 8.3 was required without prescribing an operating-system package source; superseded by D-004 on 2026-08-01 |
| D-004 | APPROVED | PHP web and CLI must be version 8.3.0 or newer, without a maximum version or prescribed operating-system package source |
| D-005 | SUPERSEDED | SQLite was the sole database with foreign keys, WAL, and a 5-second busy timeout; superseded by D-063 on 2026-07-31 |
| D-006 | APPROVED | File cache/session and synchronous queue; no Redis or worker |
| D-007 (v1) | SUPERSEDED | Docker was prohibited together with frontend build dependencies; superseded by D-007 on 2026-07-17 |
| D-007 (v2) | SUPERSEDED | No Node.js/npm/pnpm/Vite build or external runtime service; Docker was permitted for future packaging but was not required; superseded by D-007 on 2026-07-18 |
| D-007 | APPROVED | The application requires no Node.js frontend build; Docker Compose is the maintained and authoritative local development environment |
| D-030 | APPROVED | Composer lock is the authoritative exact dependency set |
| D-031 | APPROVED | Vendor code is never edited; a fork requires explicit approval |
| D-036 | APPROVED | Univer Sheet is not installed and never stores assessment findings |
| D-037 | APPROVED | Right Click and Advanced Table Export enhance standard resource tables only |
| D-042 (v1) | SUPERSEDED | The Docker development profile published the development HTTP port only on host loopback; superseded by D-042 on 2026-07-18 |
| D-042 | APPROVED | The Docker development profile is defined by `docker/compose.dev.yml`, a project-owned PHP 8.3 development image, bind-mounted source code, persistent default SQLite state, optional isolated Selenium browser testing, optional real MySQL/MariaDB compatibility-test services, and HTTP publication on all host IPv4 interfaces |
| D-081 | APPROVED | WeasyPrint 60.0 or newer is the supported PDF runtime; the installer verifies the version exclusively through `weasyprint --version` and separately proves operational capability by generating a minimal real PDF |

## Consequences

- The listed decisions remain normative with their recorded status.
- `SUPERSEDED` entries remain historical evidence and must not be reactivated implicitly.
- Detailed application contracts live in the reference pages linked from [the documentation index](../index.md); those pages may clarify implementation but may not contradict these decisions.
- A future change requires explicit approval and a recorded superseding decision, not a silent edit.
