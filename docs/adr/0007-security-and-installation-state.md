# ADR: Security and installation state

- Status: Accepted
- Baseline: AssestMe specification 2.7
- Source commit: `d466ff1c0c3851fecee3d468cde7cb9359db5ae4`

## Context

Defines optional MFA and the secure resumable/irreversible installer state boundary.

## Decisions

| ID | Status | Decision |
|---|---|---|
| D-028 | APPROVED | TOTP MFA is optional, with recovery codes and CLI reset |
| D-065 | APPROVED | The installer uses a stable bootstrap application key, encrypted resumable non-administrator state, an atomic allowlisted `.env` writer, filesystem finalization locking, real capability checks, and an irreversible private installed lock; installed or anomalous instances never reopen the web installer |

## Consequences

- The listed decisions remain normative with their recorded status.
- `SUPERSEDED` entries remain historical evidence and must not be reactivated implicitly.
- Detailed application contracts live in the reference pages linked from [the documentation index](../index.md); those pages may clarify implementation but may not contradict these decisions.
- A future change requires explicit approval and a recorded superseding decision, not a silent edit.
