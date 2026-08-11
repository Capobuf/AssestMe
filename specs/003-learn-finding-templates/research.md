# Research: Learn from Assessment Findings

## Reusable content projection and lineage fingerprint

**Decision**: Use one `FindingTemplateContent` service with explicit ordered arrays for the reusable
Finding/template fields and solutions. Fingerprints use SHA-256 over JSON with unescaped Unicode and
preserved zero fractions after NFC normalization, LF line endings, explicit nulls, enum values,
fixed two-decimal monetary strings, and deterministic solution ordering.

**Rationale**: Explicit projections prevent assessment-only fields from leaking into templates and
avoid implementation-dependent PHP serialization. Template fingerprints include solution external
IDs, while semantic exact projections deliberately omit operational identities.

**Alternatives considered**: Model serialization was rejected because casts, relation order, and
metadata can vary. A version column or revision table was explicitly excluded. Reusing import JSON
was rejected because it uses names/codes for interchange and includes enabled state while lineage
requires persisted internal identities and excludes enabled state.

## Exact duplicate and solution identity mapping

**Decision**: Compute an exact semantic SHA-256 signature over normalized complete reusable content.
Semantic text uses Unicode compatibility normalization, lowercase, punctuation-to-space, collapsed
whitespace, explicit nulls, and fixed decimals. It omits all database IDs, external template IDs,
solution external IDs/manual keys, timestamps, and enabled state. Solutions retain complete content,
recommendation state, and order. Exact-link mapping groups solutions by that complete semantic row
and pairs stable Finding IDs with stable template external IDs for indistinguishable ties.

**Rationale**: This detects certain duplicates independently of incidental identity and aligns every
solution without relying on title or position alone. Stable tie-breaking resolves truly identical
rows without inventing distinctions.

**Alternatives considered**: Title-only, description-only, row-position-only, and external-key-only
matching were rejected because manual Findings and legitimate title collisions make them unsafe.

## Authoritative external-ID mapping

**Decision**: Preserve the existing `SaveFindingTemplate::handle()` API and add a result-returning
entry point that exposes the solution external IDs assigned to each submitted row. Both entry points
run the same validation, ID-generation, full-replacement, and persistence implementation. Aggregate
actions submit Finding solutions in deterministic order and realign keys from this authoritative
result; existing lineage keys are translated to persisted solution IDs before update.

**Rationale**: New solution IDs are generated exactly once by the existing authority. The caller can
align Finding snapshots without duplicating slug/collision behavior or guessing from titles.

**Alternatives considered**: Client-side ID generation and a second slug service were prohibited.
Post-save title lookup was rejected as ambiguous. Changing the existing public return type would
create unnecessary regression risk.

## Similarity algorithm and baseline characterization

**Decision**: Normalize title/problem lexically with Unicode compatibility normalization,
lowercasing, punctuation removal, and collapsed whitespace. For templates in the same category,
score `70%` normalized-title `similar_text` ratio plus `30%` Jaccard similarity of unique title and
problem tokens of at least three characters; warn at `0.86`. Across categories, warn only when the
normalized title ratio is at least `0.96`. Sort by score, then title and stable external ID, and
return at most three. Exact signatures are removed from the similar set.

**Rationale**: The algorithm is deterministic, inspectable, package-free, and deliberately biased
toward few false positives. Same-category problem vocabulary distinguishes superficially similar
titles; the very high cross-category title gate catches practical classification mistakes.

**Baseline characterization**: All 221 bundled templates were compared on 2026-08-11 without
changing the source file. The same-category `0.86` threshold produced 5 candidate pairs: Windows/
macOS unnecessary administrator privileges, Windows/macOS screen lock, backup/recovery procedure
documentation, and two firewall/core-switch/storage single-point-of-failure pairs. Manual review
found 0 clearly anomalous false-positive pairs. The cross-category `0.96` title gate produced 0
pairs. Lower same-category thresholds produced 6 pairs at `0.84`, 8 at `0.82`, and 12 at `0.72`, so
`0.86` was retained conservatively.

**Alternatives considered**: Edit-distance-only scoring was noisier for long Italian descriptions.
Embeddings, AI, vector storage, full-text engines, a package, and a configurable threshold were
explicitly excluded.

## Atomic mutation and optimistic guard

**Decision**: Each create/link/update action receives the expected assessment version, acquires the
existing assessment file-cache lock, opens one outer database transaction, reloads the draft
assessment/Finding, and increments the existing assessment version once. Update also loads the
source template with soft-deleted rows under database lock and compares its current fingerprint
inside that transaction before calling authoritative persistence.

**Rationale**: The existing `IncrementAssessmentVersion` conditional update protects the aggregate,
the cache lock serializes local saves, and row locking closes the fingerprint check/write TOCTOU
window. Nested Laravel transactions keep `SaveFindingTemplate` under the same outer rollback.

**Alternatives considered**: A new lock/version system, compare-before-transaction, merge, and force
overwrite were rejected by approved contracts.

## Collision-free Finding solution key realignment

**Decision**: Within the outer transaction, first assign every affected active Finding solution a
unique bounded temporary key containing a per-operation token and its persisted ID, then apply final
template external IDs from the authoritative result or exact semantic map.

**Rationale**: The two-phase rewrite respects the existing `(finding_id, external_key)` unique index
even when final keys swap or collide with stale lineage values.

**Alternatives considered**: Nulling keys was rejected because null does not preserve operation
identity and weakens failure diagnosis. Sequential direct replacement can violate the unique index.

## Risk override and historical classification

**Decision**: For new templates, validate the Finding consequence/likelihood against the active
selectable profile and derive priority through `CalculateFindingPriority`; ignore manual priority
and customer-specific override rationale. For source updates, construct the same reusable desired
state but let `SaveFindingTemplate` retain its current historical-classification contract: unchanged
persisted historical identities remain legal, while a deliberate changed classification must be
active/selectable and matrix coherent.

**Rationale**: New library defaults cannot inherit assessment-specific overrides or illegal historic
choices, while an unrelated source update must not remap valid persisted history.

**Alternatives considered**: Copying the manual override, storing an override flag on templates,
and silently mapping historical levels into the active profile were explicitly prohibited.

## Workspace interaction

**Decision**: Add create/new-lineage and update actions to the existing Finding ellipsis group. A
small read-only Blade modal view renders exact/similar candidates or update summaries with ordinary
links to template edit pages. The page persists current state before action execution, and server
actions independently enforce draft status.

**Rationale**: This keeps the dense editor unchanged, provides accessible open-template paths, and
uses Filament's modal/action lifecycle rather than a parallel editor.

**Alternatives considered**: Permanent editor buttons, custom JavaScript layouts, and a template
editing form inside Workspace were rejected.

## Import boundary

**Decision**: Do not change JSON schemas, baseline data, import/seeder behavior, or conflict modes.
Document that an explicit replace import can still replace locally improved baseline templates.

**Rationale**: Import ownership metadata and replace protection are a separate product decision.

**Alternatives considered**: Origin flags and bundled/user-owned template metadata were explicitly
outside scope.
