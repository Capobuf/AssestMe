# Research: Assessment Integrity and MSP Baseline

## Workspace pre-action persistence

**Decision**: Keep orchestration inside `WorkspaceAssessment` with one internal method that
identifies the active authoritative form, skips synchronized state, invokes the existing signed
save path silently, and returns an explicit success/failure result used by every protected public
action.

**Rationale**: Direct Livewire calls must be safe without relying on browser JavaScript. Filament
action lifecycle hooks may call the same page method, while server-side gating stays authoritative.

**Alternatives considered**: A JavaScript action queue was rejected as non-authoritative and too
large; repeated per-action dirty checks were rejected because they would drift.

## Finding and Evidence transaction boundary

**Decision**: Extend the Finding request payload with normalized Evidence manifests. Validate all
Finding/Evidence input and aggregate limits before final writes, prepare generated private files,
then persist the complete database aggregate plus request and one version inside the existing
assessment lock/transaction. Delete prepared files on database failure and delete pending files
only after success or explicit removal.

**Rationale**: This preserves one user operation without falsely claiming filesystem transactions.
The stored payload hash covers every relevant part and makes replay/mismatch deterministic.

**Alternatives considered**: Sequential `StoreEvidence` calls were rejected because they create
partial success and multiple versions; distributed transactions and a queue were rejected as
unsupported complexity.

## Reorder idempotency

**Decision**: Give reorder a typed request containing a request UUID prepared before the first
delivery, expected version, and exact ordered IDs. Record it in `WorkspaceSaveRequest` under an
operation discriminator and replay the stored applied version.

**Rationale**: The existing request table and assessment lock already provide the required
protocol. A pending UUID retained until success is stable across a retry.

**Alternatives considered**: Generating a UUID inside each action delivery was rejected because it
cannot identify retries; adding a package was unnecessary.

## Operational risk profile and history

**Decision**: Resolve the operational profile only through
`GeneralSettings::active_risk_profile_id`, require the referenced profile to exist and be enabled,
and use its enabled levels for new choices. Allow unchanged historical IDs to survive unrelated
saves; require a complete active-profile matrix combination once risk classification changes.

**Rationale**: This enforces one operational source without remapping detached history or confusing
`is_default` seed semantics with runtime behavior.

**Alternatives considered**: A second active flag and bulk remapping were explicitly rejected.

## Urgency semantics

**Decision**: Resolve urgent priority IDs as the last two enabled levels by each profile's
persisted `sort_order` (or its only level), then filter Findings by those IDs.

**Rationale**: Priority has no score. Profile-relative order preserves the current default behavior
and supports historical custom profiles on all three query surfaces.

**Alternatives considered**: Code comparisons and invented scores were rejected.

## Template schema version routing

**Decision**: Preserve the current schema byte-for-byte as an explicit v1 file, make the canonical
path v2, route validation by top-level `schema_version`, and apply the same active-profile semantic
validator in preview and import. V2 risk codes use the existing stable technical-code grammar.

**Rationale**: Structural validation cannot know database profiles, while semantic validation must
never silently coerce a supplied unknown code to null. Exporting only v2 establishes one new
canonical output without breaking legacy input.

**Alternatives considered**: Mutating v1 in place or dropping v1 import was rejected as a
compatibility break; embedding all profile values as JSON enums was rejected as non-customizable.

## MSP baseline references

**Decision**: Prefer concise operational text without citations. Include a framework or normative
reference in `technical_notes` only when verified against a primary source and directly relevant;
describe GDPR Article 32 and framework guidance as risk-based rather than universal technology
mandates.

**Rationale**: Omission is safer than an invented or overstated claim and does not reduce the
technical actionability of a Finding.

**Alternatives considered**: Blog-derived citations, blanket compliance claims, extra source/tag
fields, and arbitrary record quotas were rejected.
