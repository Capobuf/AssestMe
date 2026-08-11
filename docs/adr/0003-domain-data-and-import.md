# ADR: Domain, data, and import

- Status: Accepted
- Baseline: AssestMe specification 2.7
- Source commit: `d466ff1c0c3851fecee3d468cde7cb9359db5ae4`

## Context

Defines stable domain identities, risk ownership, scope validation, import semantics, and database portability.

## Decisions

| ID | Status | Decision |
|---|---|---|
| D-012 (v1) | SUPERSEDED | JSON Schema v1 was the canonical template interchange contract; superseded by D-012 v2 on 2026-08-11 while remaining supported for import |
| D-012 (v2) | APPROVED | JSON Schema v2 is the canonical template interchange and export contract; v1 remains importable, while v2 accepts stable active-profile risk codes and delegates existence, enabled-state, ownership, and matrix coherence to application validation |
| D-013 | APPROVED | Priority = consequence × likelihood, with reasoned manual override |
| D-024 | APPROVED | Referenced finding solutions cannot be deleted until references are reassigned |
| D-025 | APPROVED | Template import is full replace by stable template/solution external IDs |
| D-032 | APPROVED | UTC storage and Europe/Rome presentation |
| D-033 | APPROVED | Integer database primary keys; no UUID/ULID migration complexity |
| D-038 | APPROVED | Risk profiles own consequence, likelihood, priority, and matrix records; effort levels are global |
| D-071 | APPROVED | `GeneralSettings::active_risk_profile_id`, not `is_default`, identifies the sole enabled operational risk profile for new classifications and template import; historical persisted classifications remain readable and are not remapped |
| D-045 | APPROVED | Finding asset association remains optional for every scope except `selected_assets`, which requires at least one same-company asset; conditional site/asset UI and completion/report validation use the same domain rule |
| D-048 | APPROVED | Manually created template and solution external IDs are generated deterministically from titles on first authoritative save, collision-suffixed, immutable thereafter, and preserved unchanged by JSON import/export |
| D-049 | APPROVED | Risk profile and level technical codes are generated on creation and immutable after first save; existing level identity is preserved and the sixteen entries are edited through a deterministic 4×4 consequence-by-likelihood matrix |
| D-053 | APPROVED | Risk-matrix editing uses an application-owned custom Filament Field with one independent select in each semantic 4×4 table cell; nested Livewire state is keyed by persisted level IDs, `risk_matrix_entries` remains authoritative, and no plugin, Node.js pipeline, or runtime dependency is added |
| D-063 | APPROVED | Fresh AssestMe installations support SQLite, MySQL, and MariaDB through portable Laravel migrations; no data conversion or migration between database drivers is provided |

## Consequences

- The listed decisions remain normative with their recorded status.
- `SUPERSEDED` entries remain historical evidence and must not be reactivated implicitly.
- Detailed application contracts live in the reference pages linked from [the documentation index](../index.md); those pages may clarify implementation but may not contradict these decisions.
- A future change requires explicit approval and a recorded superseding decision, not a silent edit.
