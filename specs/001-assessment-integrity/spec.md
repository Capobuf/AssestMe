# Feature Specification: Assessment Integrity and MSP Baseline

**Feature Branch**: `codex/fix-p0-p1-assessment-integrity`

**Created**: 2026-08-11

**Status**: Ready for planning

**Input**: P0/P1 reliability hardening, active risk profile, template interchange v2, and a
comprehensive Italian MSP Finding baseline.

## User Scenarios & Testing

### User Story 1 - Workspace mutation integrity and Finding/Evidence save (Priority: P1)

As the administrator editing an assessment, I can request navigation, mutation, completion, or
file generation without manually saving first, knowing that the current authoritative form is
saved before the requested action and that any save failure stops that action.

**Why this priority**: Current action ordering can create records or generate output from stale
data, and a multi-part Finding/Evidence save can persist only part of one user operation.

**Independent Test**: Edit an existing Finding without explicitly saving, invoke each protected
action, and verify that successful actions use the newly persisted state while validation,
conflict, storage, or server failure leaves the user in the current context with no dependent
mutation or generated output.

**Acceptance Scenarios**:

1. **Given** a dirty Finding, **When** the administrator selects another Finding, navigates,
   closes the editor, changes workspace context, creates, copies, duplicates, deletes, reorders,
   completes, or generates output, **Then** the current form is authoritatively saved first.
2. **Given** a dirty Finding whose save fails, **When** a protected action is requested, **Then**
   that action does not execute and the current validation, conflict, storage, or server error is
   shown in the current context.
3. **Given** a dirty Finding, **When** PDF, XLSX, or completion is requested, **Then** the snapshot
   or completion validation uses exactly the state confirmed by the preceding save.
4. **Given** multiple new Evidence items in one Finding save, **When** every item is valid, **Then**
   the Finding, solutions, associations, Evidence, one save request, and one version increment are
   committed as one authoritative user operation.
5. **Given** any invalid or duplicate Evidence in that operation, **When** save is requested,
   **Then** neither the Finding changes nor any new final Evidence or final file remains.
6. **Given** the same request identifier and identical payload is delivered again, **When** the
   save or reorder is retried, **Then** the original result is replayed without duplicate Evidence,
   files, order changes, or version increments.
7. **Given** the same request identifier with a different payload, **When** it is delivered, **Then**
   an idempotency mismatch is reported and no data changes.

---

### User Story 2 - Active risk profile and template interchange v2 (Priority: P2)

As the administrator configuring or applying risk classification, I use one enabled operational
profile while retaining readable historical classifications, and I can exchange templates with
custom risk codes through a versioned contract.

**Why this priority**: Existing screens, import, and dashboard behavior disagree about the active
profile and assume the codes of the seeded default profile.

**Independent Test**: Configure a second enabled profile with entirely different technical codes,
make it operational, create and edit Findings/templates, import/export both supported document
versions, and verify historical data and urgency without relying on default codes.

**Acceptance Scenarios**:

1. **Given** multiple profiles, **When** an operational classification is created, **Then** all new
   selected levels belong to the single enabled active profile.
2. **Given** a historical Finding or template classified by another profile, **When** unrelated
   content is edited, **Then** its persisted risk identities remain readable and unchanged.
3. **Given** a historical record is deliberately reclassified, **When** it is saved, **Then** the
   complete new combination belongs to the active profile and follows its matrix.
4. **Given** a profile is operationally active, **When** disabling it is requested, **Then** the
   request fails until another enabled profile is selected.
5. **Given** priorities with arbitrary codes, **When** urgency is calculated, **Then** the highest
   two ordered levels of each Finding's own profile are urgent, or the only level when only one
   exists.
6. **Given** a valid legacy v1 template document, **When** it is previewed or imported, **Then** it
   remains supported.
7. **Given** a valid v2 document with active-profile custom codes, **When** it is previewed,
   imported, exported, and imported again, **Then** the result is deterministic and preserves
   stable external identities.
8. **Given** an unknown, disabled, cross-profile, or matrix-inconsistent risk code combination,
   **When** preview or import is requested, **Then** it fails with template index, external ID,
   field, and offending code without silently converting a supplied value to null.

---

### User Story 3 - Comprehensive MSP Finding baseline (Priority: P3)

As an MSP administrator starting a fresh installation, I receive a substantial Italian Finding
library whose entries describe independent, observable, actionable conditions across the real
technology and operational domains encountered in micro and small/medium organizations.

**Why this priority**: The current eight-item library demonstrates import but is not sufficient for
a practical assessment.

**Independent Test**: Validate, import, seed twice, export, and re-import the complete baseline;
verify thematic coverage, stable identities, editorial limits, risk semantics, solution rules,
category consistency, and idempotency.

**Acceptance Scenarios**:

1. **Given** a fresh installation, **When** baseline data is seeded, **Then** the library covers
   governance, perimeter, WAN, LAN, Wi-Fi, physical infrastructure, server/virtualization, storage,
   backup, Windows, macOS, identity, cloud, email, VoIP, video surveillance, continuity,
   monitoring/logging, documentation, licensing, and risk-based data protection.
2. **Given** the eight previously distributed templates, **When** the new baseline is loaded,
   **Then** their external IDs are unchanged.
3. **Given** any baseline entry, **When** reviewed, **Then** it has a diagnostic title, observed
   problem, entrepreneur explanation, technical notes, coherent scope/risk/rationale, and at least
   one concrete solution with realistic effort and estimate type.
4. **Given** a normative or framework reference in technical notes, **When** reviewed, **Then** it
   is concise, directly pertinent, supported by a primary source, and does not turn guidance or a
   risk-based obligation into a universal technology mandate.

### Edge Cases

- A protected action is requested when no Finding form is active or when the form is already
  synchronized; no unnecessary save is performed.
- A pre-action save succeeds but the subsequent action fails; the confirmed save remains valid and
  the second failure is reported without fake success.
- A pending upload fails Finding validation; its temporary reference remains usable for correction
  rather than being deleted prematurely.
- Private file preparation succeeds but the database operation fails; prepared files are
  compensated, and cleanup failure is recorded with enough information for audit/retry.
- A stale expected version or invalid reorder ownership/order changes no order and no version.
- The active-profile setting references a missing or disabled profile; operational classification
  fails explicitly rather than selecting a default profile.
- A historical profile has one enabled priority level; that level is the urgent tier for its
  historical Findings.
- A v2 document contains null risk values; structural nullability is accepted only where the
  application contract permits the incomplete template state.
- Similar MSP conditions are consolidated instead of duplicated by vendor or product name.

## Requirements

### Functional Requirements

- **FR-001**: The workspace MUST use one authoritative pre-action persistence decision for every
  action that changes editor context, mutates dependent data, completes an assessment, or generates
  output.
- **FR-002**: The pre-action decision MUST skip saving when there is no authoritative active form or
  the active form is already synchronized.
- **FR-003**: A failed pre-action save MUST prevent the originally requested action and preserve the
  current form context and error state.
- **FR-004**: Successful automatic pre-action saves MUST not emit repetitive explicit-save success
  notifications; explicit saves MAY retain their existing feedback.
- **FR-005**: Assessment metadata persistence MUST NOT mark an independently dirty Finding form as
  synchronized.
- **FR-006**: PDF, XLSX, and completion MUST use the state confirmed by the immediately preceding
  authoritative save.
- **FR-007**: One Finding save MUST validate the Finding and every pending Evidence item before its
  authoritative mutation begins, including aggregate size and within-request duplicates.
- **FR-008**: One Finding save MUST commit Finding content, solutions, associations, Evidence
  metadata, one idempotent request, and one assessment version increment as one logical operation.
- **FR-009**: Private Evidence files MUST be prepared before the database operation and compensated
  if that operation fails; compensation failure MUST be diagnosed without reporting success.
- **FR-010**: A failed validation MUST NOT prematurely remove temporary uploads still referenced by
  the active form.
- **FR-011**: The Finding save request identity MUST cover Finding content, solutions,
  associations, new file Evidence, and new URL Evidence.
- **FR-012**: Identical Finding save retries MUST replay the original result without duplicated data,
  files, or version increments; payload changes under the same identity MUST fail as mismatches.
- **FR-013**: Reorder requests MUST carry stable request identity, expected version, and exact order;
  identical retry, mismatch, stale-version, ownership, and invalid-order behavior MUST be explicit
  and non-mutating where rejected.
- **FR-014**: Exactly one enabled operational risk profile MUST be selected globally; default/seed
  status MUST NOT select the operational profile.
- **FR-015**: New Finding and template classifications MUST use only levels belonging to the active
  profile and MUST reject cross-profile combinations.
- **FR-016**: Historical risk identities MUST remain readable and unchanged until the administrator
  explicitly reclassifies the record; changing the active profile MUST NOT remap historical data.
- **FR-017**: Deliberate reclassification MUST use a complete active-profile combination coherent
  with that profile's matrix.
- **FR-018**: The active profile MUST NOT be disabled before another enabled profile becomes active.
- **FR-019**: Urgency MUST be derived per Finding profile from the top two semantic priority levels
  by persisted order, using the sole level when a profile has one.
- **FR-020**: Template preview and import MUST support document versions v1 and v2; export MUST
  produce only v2.
- **FR-021**: V2 risk fields MUST accept null or stable technical codes without enumerating seeded
  default codes, and MUST continue to reject removed fields including tags.
- **FR-022**: Supplied v2 risk codes MUST be resolved against the active enabled profile and MUST
  fail contextually when missing, disabled, cross-profile, or matrix-inconsistent.
- **FR-023**: Template import MUST preserve stable template and solution external IDs and retain full
  replacement semantics.
- **FR-024**: V2 export and import MUST be deterministic and round-trippable.
- **FR-025**: The baseline MUST replace the eight-record demo with a substantial library covering
  every domain named in User Story 3 through independent, observable, actionable conditions.
- **FR-026**: The eight distributed baseline external IDs MUST remain unchanged and every external
  ID in the resulting library MUST be unique.
- **FR-027**: Every baseline template MUST satisfy editorial limits, category consistency,
  active-profile risk resolution, matrix consistency, and exactly one recommended solution.
- **FR-028**: The baseline MUST NOT add tags, source/standard/regulation/reference fields, invented
  standards claims, or artificial entries used only to inflate record count.
- **FR-029**: Baseline seeding MUST be idempotent and deterministic across validation, import,
  repeated seed, export, and re-import.
- **FR-030**: Existing detached assessment snapshots, completed classifications, primary keys, and
  template external IDs MUST NOT be migrated or rewritten by this feature.

### Key Entities

- **Workspace State**: The active assessment or Finding form, its synchronization/error state,
  expected version, and stable request identity.
- **Workspace Save Request**: The immutable identity and payload digest of one authoritative save or
  reorder plus the replayable result identifiers.
- **Finding**: Detached assessment content, classification, scope, solutions, associations, order,
  and Evidence relationships.
- **Evidence**: Private file or URL metadata associated with a Finding and included in the same save
  identity when newly created.
- **Risk Profile**: A customizable owner of ordered consequence, likelihood, priority, and matrix
  values; one enabled profile is globally operational.
- **Finding Template Document**: A versioned collection of templates and solutions identified by
  stable external IDs and semantically validated risk codes.
- **MSP Baseline**: The canonical Italian collection seeded into fresh installations.

## Success Criteria

### Measurable Outcomes

- **SC-001**: In every protected-action scenario, 100% of failed saves prevent the dependent action
  and 100% of successful actions observe the newly confirmed state.
- **SC-002**: A multi-Evidence Finding save increments the assessment version exactly once; any
  validation or database failure leaves zero new final Evidence records and zero unreferenced final
  files attributable to a reported success.
- **SC-003**: Replaying an identical Finding save or reorder 10 times produces the same result with
  no additional Evidence, files, ordering changes, or version increments.
- **SC-004**: With a custom active profile containing no seeded default codes, 100% of new
  classifications, imports, and urgent-Finding calculations produce the expected profile-relative
  results.
- **SC-005**: All valid legacy v1 fixtures import successfully; all exports identify v2; a v2 export
  imported and exported again is byte-for-byte deterministic after normalized formatting.
- **SC-006**: The baseline contains more than the original eight unique templates and covers all 21
  named assessment domains without duplicate external IDs or accidental category variants.
- **SC-007**: 100% of baseline templates pass structural schema validation, semantic risk/matrix
  validation, editorial limits, solution recommendation rules, and two consecutive idempotent seed
  runs.
- **SC-008**: One real browser journey demonstrates edit, protected action, silent automatic save,
  and successful subsequent context change without data loss.

## Assumptions

- The existing authenticated single-administrator model and current workspace remain in place.
- Server persistence remains authoritative; expansion of local/offline draft architecture and PWA
  behavior is outside this feature.
- Existing generated-file, backup/restore, installer, report renderer, spreadsheet generator, and
  private-storage contracts remain unchanged except for consuming newly confirmed workspace state.
- Primary-source references are included only when certainty and direct relevance are established;
  otherwise the baseline omits the citation.
- Completeness of the MSP library is evaluated by thematic and editorial quality, not an arbitrary
  target record count.
