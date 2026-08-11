# Data Model: Assessment Integrity and MSP Baseline

## WorkspaceSaveRequest (existing, response expanded)

- Identity: request UUID primary key.
- Ownership: one assessment.
- Contract: expected version, applied version, deterministic payload hash, operation discriminator,
  and replayable result identifiers.
- Operations in scope: `save_finding`, `save_assessment`, and `reorder_findings`.
- State transition: absent → applied once; identical replay → original result; same UUID with any
  operation/assessment/payload difference → mismatch.

## FindingSaveData (expanded typed request)

- Stable request UUID and tab UUID.
- Expected assessment version.
- Finding/solution/association payload.
- Ordered pending file Evidence manifests and optional URL Evidence manifest.
- SHA-256 over one normalized payload containing all of the above.

Each file manifest contains the pending private path or supplied upload identity, original name,
detected size/MIME/extension, content SHA-256, title/caption/internal-note/report fields, and target
ordering data needed for deterministic validation. Raw file bytes are not stored in the request
row.

## Prepared Evidence

- Temporary/pending private source remains until authoritative success.
- Generated final path is written before the database transaction.
- Evidence row stores generated path, metadata, size, and SHA-256.
- Database failure triggers final-path compensation.
- Compensation failure is logged with assessment, Finding, request, and path for cleanup/audit.
- Validation failure does not remove the pending source.

## Assessment version transition

- Every successful Finding aggregate save: `expected → expected + 1` exactly once.
- Every successful reorder: `expected → expected + 1` exactly once.
- Identical replay: no transition.
- Stale expected version: explicit conflict and no mutation.

## RiskProfile

- Multiple profiles may exist; `is_default` retains seed/default semantics only.
- Operational identity is `GeneralSettings.active_risk_profile_id`.
- Active profile invariant: referenced profile exists and `is_enabled = true`.
- Disabling transition is prohibited while the profile is operationally active.
- Consequence/Likelihood/Priority/Matrix children remain profile-owned and ordered.

## Historical risk classification

- Persisted Finding/template level IDs remain authoritative while unchanged.
- An unrelated save does not resolve or remap them through the current active profile.
- A changed classification must use enabled levels from the active profile and a matching matrix
  cell; templates do not define a separate override mechanism.

## Template document

- Top-level schema version routes structural validation.
- V1: legacy fixed-code structure remains import-only.
- V2: consequence, likelihood, and priority are null or stable technical codes; export-only target.
- Semantic identity: template and solution external IDs remain stable and unique.
- Full replacement remains transactional by template external ID.

## MSP baseline

- One v2 document at `templates/base-findings.it.json`.
- Existing eight external IDs are stable.
- Every entry has one category identity, coherent active-profile risk codes/matrix result, and one to
  three solutions with exactly one recommended.
- Repeated replacement import is idempotent; normalized export is deterministic.
