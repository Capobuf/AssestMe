# ADR: Testing and verification

- Status: Accepted
- Baseline: AssestMe specification 2.7
- Source commit: `8de2790898b7181b0c1094d08e68e77adfdc67a6`

## Context

Defines Dusk, isolated autonomous verification, the canonical MariaDB path, and bounded database compatibility smoke coverage.

## Decisions

| ID | Status | Decision |
|---|---|---|
| D-027 | APPROVED | Laravel Dusk is used for browser behavior tests without Node.js |
| D-041 | APPROVED | Development verification is autonomous and isolated; manual cross-browser/device QA is deferred until a reference installation and devices exist |
| D-067 | SUPERSEDED | CI proved common behavior through a multi-version SQLite/MySQL/MariaDB matrix; superseded by D-076 and D-077 on 2026-09-12 |
| D-074 (v1) | SUPERSEDED | The complete `scripts/verify.sh` gate ran only inside the explicitly marked `app` service of `docker/compose.dev.yml`; superseded by D-074 on 2026-08-13 |
| D-074 | SUPERSEDED | Verification used recursive core/complete gates, receipt reuse, a database matrix, and a full Dusk pass; superseded by D-078 through D-080 on 2026-09-12 |
| D-076 | APPROVED | MariaDB 12.3.3 is the pinned canonical CI database and runs the complete Feature suite once plus the real server backup/restore round trip |
| D-077 | APPROVED | SQLite and pinned MySQL 8.4.11 retain only migration/seed, capability, and fundamental diagnostic compatibility smoke coverage; neither runs the complete Feature suite, Dusk, or real backup/restore |
| D-078 | APPROVED | `scripts/check.sh` is the mandatory browser-free pre-commit gate and runs strict Composer validation, Pint, PHPStan, and the Unit suite once against disposable SQLite; dependency audit remains CI-only |
| D-079 | APPROVED | The single primary CI workflow separates `check`, canonical `application`, extracted-release `installer`, and bounded compatibility-smoke responsibilities without nested complete gates or repeated full suites |
| D-080 | APPROVED | Automated Dusk coverage is limited to one application smoke plus browser-specific local-draft, risk-matrix, and report-preview behavior, with the release installer journey executed separately against MariaDB |

## Consequences

- The listed decisions remain normative with their recorded status.
- `SUPERSEDED` entries remain historical evidence and must not be reactivated implicitly.
- Detailed application contracts live in the reference pages linked from [the documentation index](../index.md); those pages may clarify implementation but may not contradict these decisions.
- A future change requires explicit approval and a recorded superseding decision, not a silent edit.
