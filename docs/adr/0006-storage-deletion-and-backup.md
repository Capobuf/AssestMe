# ADR: Storage, deletion, and backup

- Status: Accepted
- Baseline: AssestMe specification 2.7
- Source commit: `d466ff1c0c3851fecee3d468cde7cb9359db5ae4`

## Context

Defines explicit failure, policy-aware deletion, staged recovery, multi-driver backup, and CLI-only restore.

## Decisions

| ID | Status | Decision |
|---|---|---|
| D-017 | APPROVED | Archive/permanent-delete behavior follows one global setting |
| D-018 | APPROVED | Fail loudly; no silent fallback, omission, or placeholder success |
| D-023 | APPROVED | File deletion uses recoverable staging, not a false claim of SQL/filesystem atomicity |
| D-029 | SUPERSEDED | The SQLite-only local backup contract was implemented and tested; superseded by the multi-driver D-066 contract on 2026-07-31 |
| D-061 | SUPERSEDED | The native Settings-cluster backup page and CLI-only restore boundary remain, but its SQLite-only archive contract is superseded by D-066 on 2026-07-31 |
| D-066 (v1) | SUPERSEDED | Server database dump and restore executables were configured during installation; superseded by D-066 on 2026-08-01 |
| D-066 | APPROVED | Backups use a driver-aware schema-v2 manifest and operation-time automatic client discovery; SQLite backup remains mandatory, while missing MySQL/MariaDB clients do not block installation but make SQL backup/restore unavailable with actionable errors; restore remains maintenance-mode CLI-only with a required safety backup and best-effort compensation |

## Consequences

- The listed decisions remain normative with their recorded status.
- `SUPERSEDED` entries remain historical evidence and must not be reactivated implicitly.
- Detailed application contracts live in the reference pages linked from [the documentation index](../index.md); those pages may clarify implementation but may not contradict these decisions.
- A future change requires explicit approval and a recorded superseding decision, not a silent edit.
