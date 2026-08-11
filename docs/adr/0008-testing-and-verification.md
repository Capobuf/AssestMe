# ADR: Testing and verification

- Status: Accepted
- Baseline: AssestMe specification 2.7
- Source commit: `d466ff1c0c3851fecee3d468cde7cb9359db5ae4`

## Context

Defines Dusk, isolated autonomous verification, and the real database CI matrix.

## Decisions

| ID | Status | Decision |
|---|---|---|
| D-027 | APPROVED | Laravel Dusk is used for browser behavior tests without Node.js |
| D-041 | APPROVED | Development verification is autonomous and isolated; manual cross-browser/device QA is deferred until a reference installation and devices exist |
| D-067 | APPROVED | CI proves common behavior against real SQLite, MySQL, and MariaDB databases at explicitly pinned tested versions; no unexecuted server version or real CloudPanel environment is described as compatible or certified |
| D-074 | APPROVED | The complete `scripts/verify.sh` gate runs only inside the explicitly marked `app` service of `docker/compose.dev.yml`; direct host or unmarked-container execution fails before any verification mutation, and CI uses the same normative runtime |

## Consequences

- The listed decisions remain normative with their recorded status.
- `SUPERSEDED` entries remain historical evidence and must not be reactivated implicitly.
- Detailed application contracts live in the reference pages linked from [the documentation index](../index.md); those pages may clarify implementation but may not contradict these decisions.
- A future change requires explicit approval and a recorded superseding decision, not a silent edit.
