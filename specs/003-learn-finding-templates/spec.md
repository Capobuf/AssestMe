# Feature Specification: Learn from Assessment Findings

**Feature Branch**: `develop`

**Created**: 2026-08-11

**Status**: Draft

**Input**: Let the administrator progressively improve the reusable Finding Template library from real assessment Findings by creating, linking, and deliberately updating templates without silent regressions or automatic propagation.

## User Scenarios & Testing

### User Story 1 - Save a field Finding as a reusable template (Priority: P1)

As the administrator working in a draft assessment, I can save a complete Finding as a new reusable template while keeping customer-specific state out of the library.

**Why this priority**: Learning from Findings created during real work is the core value of the feature and immediately expands the reusable library.

**Independent Test**: Starting from a manual Finding with reusable content, assessment-only state, and solutions, saving it as a template creates one reusable template, links the Finding to it, preserves the Finding's assessment state, and makes the new template available for future assessments.

**Acceptance Scenarios**:

1. **Given** a complete manual Finding has no source template and no matching library entry, **When** the administrator confirms `Salva come template`, **Then** one template is created from only its reusable content and the Finding records the new lineage.
2. **Given** a Finding already has a valid source template, **When** the administrator chooses `Salva come nuovo template`, **Then** a new template is created and becomes the Finding's sole current lineage without changing the previous template.
3. **Given** a Finding has a manually overridden priority, **When** it is saved as a template, **Then** the template default priority is derived from consequence and likelihood and the customer-specific override reason is not promoted globally.
4. **Given** a Finding uses historical risk values that cannot form a selectable classification in the active profile, **When** creation is attempted, **Then** no template or partial lineage change is created and the administrator is asked to reclassify the Finding.
5. **Given** authoritative template persistence fails, **When** creation is attempted, **Then** the template, lineage, solution identities, fingerprint, and assessment version all remain unchanged.

---

### User Story 2 - Reuse an identical template and review similar candidates (Priority: P1)

As the administrator, I am warned before creating redundant templates and can link an identical Finding to the existing template while retaining control when a candidate is merely similar.

**Why this priority**: A growing library remains useful only when certain duplicates are prevented without blocking legitimate variants.

**Independent Test**: A semantically identical Finding is classified as an exact duplicate and can only be linked; a near match shows at most three candidates but may still be saved as new; a clearly different baseline Finding shows no noisy warning.

**Acceptance Scenarios**:

1. **Given** an active or disabled non-deleted template has the same complete reusable meaning as a Finding, **When** the administrator starts template creation, **Then** no second template can be created and `Collega al template esistente` is offered.
2. **Given** the administrator confirms an exact link, **When** the operation succeeds, **Then** the template remains unchanged while the Finding lineage, known fingerprint, and solution identities are aligned atomically.
3. **Given** up to three conservative lexical candidates are similar but not identical, **When** the administrator reviews them, **Then** each candidate can be opened and `Salva comunque come nuovo` remains available.
4. **Given** a clearly different Finding, **When** template creation starts, **Then** no similarity warning is shown.
5. **Given** an exact template is disabled, **When** it is detected and linked, **Then** its disabled state is disclosed and remains unchanged.
6. **Given** an otherwise exact template is soft-deleted, **When** duplicate detection runs, **Then** it does not block creation or participate as a candidate.

---

### User Story 3 - Improve the source template deliberately (Priority: P1)

As the administrator, I can review a compact reusable-content difference summary and replace the source template from an improved derived Finding without changing Findings already present in other assessments.

**Why this priority**: The library must learn from real improvements while preserving detached assessment snapshots and stable solution lineage.

**Independent Test**: With an unchanged known source template, editing a derived Finding and confirming the update replaces all reusable template content, preserves identities for existing solutions, creates identities for new solutions, removes missing solutions according to library policy, refreshes lineage, and leaves another existing Finding untouched.

**Acceptance Scenarios**:

1. **Given** a derived Finding's known template fingerprint still matches the current template, **When** the administrator opens update, **Then** a compact summary identifies changed fields and added, changed, and removed solutions before confirmation.
2. **Given** the administrator confirms a valid update, **When** it succeeds, **Then** the template receives a full replacement of reusable content, existing solution identities remain stable, new solution identities are created authoritatively, removed solutions follow normal soft-delete policy, and the Finding is realigned atomically.
3. **Given** another Finding already derives from the same template, **When** the template is updated, **Then** that other Finding remains an unchanged detached snapshot.
4. **Given** reusable content is already aligned, **When** update is requested, **Then** the template is not rewritten and only safe lineage initialization or refresh occurs when needed.
5. **Given** the source template is disabled, **When** an otherwise valid update succeeds, **Then** it remains disabled.

---

### User Story 4 - Prevent stale or unverifiable template updates (Priority: P1)

As the administrator, I am prevented from silently overwriting a template when my Finding does not prove knowledge of the current source content.

**Why this priority**: A stale Finding must never regress improvements already made to the library.

**Independent Test**: Two Findings begin from the same source; the first updates it, and the second is then refused with no template mutation. Legacy Findings initialize lineage only when already semantically aligned.

**Acceptance Scenarios**:

1. **Given** two Findings know the same source state and the first updates the template, **When** the second attempts an update, **Then** the operation reports a conflict and the newer template remains unchanged.
2. **Given** a legacy Finding has a source but no known fingerprint and is semantically identical to the current template, **When** update is requested, **Then** lineage is safely initialized and `Template già allineato` is reported without rewriting the template.
3. **Given** a legacy Finding has a source but no known fingerprint and differs from the current template, **When** update is requested, **Then** update is blocked as unverifiable and saving as a new template remains available.
4. **Given** the source template is soft-deleted, **When** update is requested, **Then** no restore or update occurs and saving as a new template remains available.
5. **Given** an assessment is completed or archived, **When** any create, link, or update operation is attempted directly or through the Workspace, **Then** it is unavailable in the interface and rejected by the server until reopening.

### Edge Cases

- A source template is hard-deleted and the Finding source reference has been cleared while an old fingerprint remains.
- Multiple template solutions are semantically indistinguishable and require stable tie-breaking during exact linking.
- Solution identity realignment would temporarily collide with another key in the same Finding.
- A manually added solution carries its normal `manual-<id>` identity rather than a missing key.
- Reusable content includes Unicode variants, different line endings, whitespace, punctuation, or equivalent decimal representations.
- A candidate title is practically identical but classified under a different category.
- A template is changed after the update modal opens but before confirmation.
- The current Workspace form cannot be persisted before the dependent template action.
- A Finding is duplicated and both copies retain the same known source lineage.

## Requirements

### Functional Requirements

- **FR-001**: The Finding action menu MUST offer `Salva come template` for an unlinked Finding and MUST offer both `Aggiorna template di origine` and `Salva come nuovo template` for a Finding with a valid source; existing duplicate and delete actions MUST remain available.
- **FR-002**: Template actions MUST be secondary menu actions, MUST use compact native confirmation surfaces, and MUST NOT add permanent editor buttons or a second template editor.
- **FR-003**: Creating or updating a template MUST include title, category, problem, entrepreneur notes, technical notes, default scope, consequence, likelihood, matrix-coherent priority, reusable rationale when applicable, and every solution's reusable content, recommendation state, and order.
- **FR-004**: Creating or updating a template MUST exclude assessment, customer, selected sites/assets, evidence, attachments, evidence URLs, Finding status, report inclusion, implemented solution, resolution data, and other customer-specific information.
- **FR-005**: A manually overridden Finding priority MUST NOT become a template override or default; the default MUST be derived from consequence and likelihood and customer-specific override rationale MUST be excluded, with a concise explanation in the interface.
- **FR-006**: Creating a template MUST reject a historical or otherwise non-selectable classification that cannot legally become a new active-profile classification, without automatic remapping.
- **FR-007**: `source_template_id` MUST remain the only operational source lineage reference; saving as new MUST replace it with the new template rather than add another lineage field.
- **FR-008**: A linked Finding MUST store a nullable 64-character SHA-256 fingerprint of the known reusable source-template content, with no version counter, revision table, or template history.
- **FR-009**: The known fingerprint MUST be set when a template is copied, created from a Finding, linked as an exact duplicate, or successfully updated from a Finding.
- **FR-010**: Fingerprinting MUST include stable category identity, all reusable Finding-level fields, risk-level identities, reusable rationale, and every active solution including its template external identity, complete reusable content, recommendation state, and order.
- **FR-011**: Fingerprinting MUST exclude template database identity, timestamps, deletion metadata, and enabled state; disabling a template MUST NOT invalidate known content while soft deletion MUST block Workspace update.
- **FR-012**: Fingerprint input MUST be deterministic across equivalent Unicode, line endings, nulls, enum values, decimal values, key order, and solution order and MUST NOT depend on implementation-specific object serialization.
- **FR-013**: Before update, the current template MUST be loaded authoritatively and locked in the same atomic operation that compares its current fingerprint with the Finding's known fingerprint and applies any approved replacement.
- **FR-014**: A fingerprint mismatch MUST block overwrite and merge, reveal no technical hash, provide cancellation and template-opening paths, and leave `Salva come nuovo template` separately available; no force overwrite MAY be offered.
- **FR-015**: A legacy linked Finding with no fingerprint MAY initialize lineage only when its reusable content is semantically identical to the current source; otherwise update MUST be blocked as unverifiable.
- **FR-016**: A valid update MUST use full replacement for all reusable fields and active solutions, never field-level merge, automatic conflict resolution, or propagation to existing assessment Findings.
- **FR-017**: Existing template solutions MUST retain their external IDs by mapping from Finding solution external keys; manual Finding solutions MUST be treated as new and receive IDs only from authoritative template persistence.
- **FR-018**: After create, exact link, or update, every Finding solution key MUST be realigned to the actually persisted template-solution external ID using deterministic mapping that never relies on title alone and avoids transient uniqueness collisions.
- **FR-019**: Exact duplicate detection MUST compare the complete normalized reusable meaning, including all solutions, while ignoring record IDs, template and solution external IDs, manual keys, timestamps, and enabled state.
- **FR-020**: An exact duplicate MUST prevent creation and offer linking; soft-deleted templates MUST be ignored, while disabled templates MAY be detected and linked without being re-enabled.
- **FR-021**: Exact solution mapping MUST use complete reusable solution content, recommendation state, and order with a deterministic tie-breaker for indistinguishable solutions.
- **FR-022**: Similarity detection MUST use a small deterministic lexical comparison over the real library, prefer the same category, return no more than three conservative candidates, and remain a non-blocking warning.
- **FR-023**: A merely similar candidate MUST offer open, cancel, and save-as-new choices but MUST NOT offer automatic linking or updating.
- **FR-024**: Update confirmation MUST summarize reusable Finding-level changes and solution additions, modifications, and removals and MUST state that existing assessment Findings will not change.
- **FR-025**: When no reusable difference exists, update MUST avoid rewriting the template and report `Template già allineato`, changing only safe lineage state if necessary.
- **FR-026**: A disabled source template MAY be updated and MUST remain disabled; a soft-deleted source MUST NOT be restored or updated.
- **FR-027**: A duplicated Finding MUST retain its source template and known fingerprint so normal conflict and diff safeguards continue to apply.
- **FR-028**: Completed and archived assessments MUST reject create, link, and update operations server-side and MUST hide them in the Workspace until explicit reopening.
- **FR-029**: Before every Workspace template operation, dirty current state MUST be persisted by the existing authoritative save protocol; failed persistence MUST stop the dependent operation.
- **FR-030**: Template creation or replacement, source lineage change, fingerprint change, solution-key realignment, and exactly one coherent assessment-version increment MUST be atomic and use the existing assessment concurrency mechanism.
- **FR-031**: An error MUST leave no partial template, lineage, fingerprint, solution-key mapping, or assessment-version change and MUST never be reported as success.
- **FR-032**: Authoritative template persistence MUST remain responsible for validation, risk semantics, external ID generation, solution limits, full replacement, and transaction behavior.
- **FR-033**: User-visible labels, warnings, conflicts, and validation failures MUST be localized in Italian and MUST provide visible accessible alternatives to any link or menu action.
- **FR-034**: The template interchange schema, bundled baseline file, import behavior, baseline seeding, and replace conflict mode MUST remain unchanged; explicit replace import MAY still overwrite locally improved baseline content according to its existing contract.
- **FR-035**: No artificial intelligence, embeddings, vector service, new search engine, template version table, audit history, generic repository, continuous synchronization, automatic merge, or new user setting MAY be introduced.
- **FR-036**: Automated tests MUST demonstrate creation, exact linking, conservative similarity, full replacement, stable/new/removed solution identity behavior, stale regression prevention, safe legacy initialization, risk override handling, disabled/deleted sources, read-only enforcement, atomic rollback, and non-propagation to existing Findings.
- **FR-037**: The complete verification gate MUST fail before any verification work or mutation unless it runs inside the explicitly marked `app` service from `docker/compose.dev.yml`; agent instructions and CI MUST use that same normative invocation.

### Key Entities

- **Finding**: An assessment-specific detached snapshot that may point to one current source template and retain the fingerprint of source reusable content it last knew.
- **Finding Template**: Reusable library content with stable external identity, risk defaults, enabled/deleted lifecycle, and active remediation solutions.
- **Finding Solution**: Assessment-specific remediation snapshot whose external key carries lineage to a template solution after copying, creation, linking, or update.
- **Known Source Fingerprint**: A deterministic SHA-256 guard describing the reusable source-template content known by one Finding; it is not a version or audit record.
- **Exact Duplicate**: A non-deleted template whose normalized reusable meaning is identical to the Finding independently of operational identities and state.
- **Similar Candidate**: A non-deleted template with conservatively high lexical similarity that is shown only for human review.

## Success Criteria

### Measurable Outcomes

- **SC-001**: Each of the six primary acceptance journeys—new template, exact link, similar variant, valid source update, stale-source refusal, and deleted-source refusal—can be completed or refused with the specified result and zero partial mutations.
- **SC-002**: An exact duplicate attempt creates zero new templates, while an exact link aligns 100% of solution identities and increments the assessment version exactly once.
- **SC-003**: A valid full replacement preserves 100% of existing solution external identities, assigns an authoritative identity to every new solution, soft-deletes every omitted template solution, and changes zero pre-existing Findings in other assessments.
- **SC-004**: After one Finding updates a shared template, every stale sibling Finding is refused on update and the newer template remains byte-for-byte unchanged in reusable meaning.
- **SC-005**: Similarity review returns at most three candidates; characterization of all 221 baseline templates yields a documented conservative threshold and a bounded count of anomalous candidate pairs.
- **SC-006**: Clearly similar and clearly distinct test cases are classified as expected, while exact matches are classified only as exact duplicates.
- **SC-007**: Every completed or archived assessment template action is both absent from the Workspace and refused when invoked server-side.
- **SC-008**: The complete repository verification gate passes after implementation; real browser/device/hosting acceptance remains explicitly unverified unless executed.
- **SC-009**: A direct host invocation of `scripts/verify.sh` is rejected before Composer or application commands, while the same gate is executable from the marked Compose app service.

## Assumptions

- The application continues to have one authenticated administrator and no roles or multi-tenancy.
- Findings and template solutions remain limited by existing authoritative editorial and solution-count rules.
- The bundled 221-template Italian library is representative enough to choose a conservative lexical threshold without adding a user setting.
- Template pages already provide the accessible destination used by `Apri template`.
- Explicit template import in replace mode remains capable of replacing locally improved baseline template content and is intentionally outside this slice.
- No cross-database data migration is required; only fresh-install and forward migration portability across SQLite, MySQL, and MariaDB is in scope.
