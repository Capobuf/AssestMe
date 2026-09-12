# Tasks: Learn from Assessment Findings

**Input**: Design documents from `specs/003-learn-finding-templates/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/workspace-template-learning.md, quickstart.md

**Tests**: Focused Pest Feature and Livewire tests are required by the approved specification; write or extend them before each implementation slice.

**Organization**: Tasks are grouped by user story and retain one coherent Workspace-to-persistence vertical slice.

## Phase 1: Setup and scoped evidence

**Purpose**: Preserve repository state and establish the approved design boundary.

- [x] T001 Record scoped preflight, initial HEAD, runtime, existing working-tree changes, and feature start in docs/_meta/progress.md
- [x] T002 Characterize the 221-template baseline and record the deterministic similarity decision in specs/003-learn-finding-templates/research.md

---

## Phase 2: Foundational lineage and authoritative projection

**Purpose**: Add the shared data contract and reusable representation that block all four stories.

- [x] T003 Add failing migration/model/copy/duplicate lineage tests in tests/Feature/FindingTemplateLearningTest.php and tests/Feature/AssessmentDomainTest.php
- [x] T004 Add the portable source fingerprint migration and Finding model field in database/migrations/*_add_source_template_fingerprint_to_findings_table.php and app/Models/Finding.php
- [x] T005 Add canonical fingerprint and semantic-normalization tests in tests/Feature/FindingTemplateLearningTest.php
- [x] T006 Implement reusable Finding/template projection, fingerprint, semantic signature, solution row matching, and compact diff in app/Services/Templates/FindingTemplateContent.php
- [x] T007 Extend authoritative save result coverage in tests/Feature/FindingTemplateCrudTest.php
- [x] T008 Preserve SaveFindingTemplate::handle() while exposing authoritative ordered solution IDs through app/Data/Templates/SavedFindingTemplate.php and app/Actions/Templates/SaveFindingTemplate.php
- [x] T009 Set source fingerprints during template copy while preserving them on duplicate in app/Actions/Assessments/CopyTemplateToAssessment.php and app/Actions/Assessments/DuplicateFinding.php

**Checkpoint**: Copied and duplicated Findings carry a deterministic known-source guard, and one authoritative template save exposes generated solution IDs without duplicating generation logic.

---

## Phase 3: User Story 1 - Save a field Finding as a reusable template (Priority: P1) MVP

**Goal**: Create a new template/new lineage from a draft Finding using only reusable content and one atomic assessment mutation.

**Independent Test**: A manual Finding creates one valid template, excludes assessment-only data, aligns source/fingerprint/solution keys, derives override priority, increments once, and fully rolls back on failure.

- [x] T010 [US1] Add create/new-lineage, excluded-data, priority-override, invalid-risk, version, key-alignment, and rollback tests in tests/Feature/FindingTemplateLearningTest.php
- [x] T011 [US1] Add the typed aggregate outcome in app/Data/Templates/FindingTemplateSyncResult.php
- [x] T012 [US1] Implement atomic create/new-lineage, active-risk validation, two-phase key realignment, and one version increment in app/Actions/Assessments/SaveFindingAsTemplate.php
- [x] T013 [US1] Add create/new-lineage Workspace methods and localized notifications in app/Filament/Resources/Assessments/Pages/WorkspaceAssessment.php and resources/lang/it/assestme.php
- [x] T014 [US1] Add save/create-new actions to the Finding ellipsis menu with save-before-action behavior in app/Filament/Resources/Assessments/Tables/AssessmentFindingsTable.php

**Checkpoint**: User Story 1 is independently usable from the Workspace and proven through real database transactions.

---

## Phase 4: User Story 2 - Reuse identical templates and review similar candidates (Priority: P1)

**Goal**: Block exact duplicate creation, link exact templates, and show only conservative non-blocking candidates for variants.

**Independent Test**: Exact content links with zero creation; disabled exact templates remain disabled; deleted exact templates are ignored; near variants return at most three candidates and clearly distinct templates return none.

- [x] T015 [US2] Add exact, disabled, soft-deleted, similar, distinct, and deterministic duplicate-solution mapping tests in tests/Feature/FindingTemplateLearningTest.php
- [x] T016 [US2] Implement exact and thresholded similar candidate discovery in app/Services/Templates/FindRelatedFindingTemplates.php
- [x] T017 [US2] Implement locked exact-link revalidation and atomic lineage/key alignment in app/Actions/Assessments/SaveFindingAsTemplate.php
- [x] T018 [US2] Add create preview modes and candidate URLs in app/Filament/Resources/Assessments/Pages/WorkspaceAssessment.php
- [x] T019 [US2] Render compact exact/similar candidate content with accessible template links in resources/views/filament/resources/assessments/template-learning-preview.blade.php and resources/lang/it/assestme.php
- [x] T020 [US2] Wire exact-link versus save-anyway submit behavior in app/Filament/Resources/Assessments/Tables/AssessmentFindingsTable.php

**Checkpoint**: Exact duplicates cannot be created, while legitimate similar variants remain explicitly creatable.

---

## Phase 5: User Story 3 - Improve the source template deliberately (Priority: P1)

**Goal**: Preview and perform authorized full replacement while preserving existing solution identity and detached Findings.

**Independent Test**: A valid source update shows a compact diff, preserves existing external IDs, creates authoritative IDs for new solutions, soft-deletes omissions, leaves another Finding untouched, retains disabled state, and refreshes lineage.

- [x] T021 [US3] Add full-replacement, stable/new/removed solution, disabled-state, no-diff, and non-propagation tests in tests/Feature/FindingTemplateLearningTest.php
- [x] T022 [US3] Implement locked fingerprint guard, no-diff outcome, authoritative full replacement, key realignment, and lineage refresh in app/Actions/Assessments/UpdateTemplateFromFinding.php
- [x] T023 [US3] Add update-diff preview and outcome handling in app/Filament/Resources/Assessments/Pages/WorkspaceAssessment.php
- [x] T024 [US3] Add compact diff and detached-Finding warning rendering in resources/views/filament/resources/assessments/template-learning-preview.blade.php and resources/lang/it/assestme.php
- [x] T025 [US3] Wire the update-source modal action in app/Filament/Resources/Assessments/Tables/AssessmentFindingsTable.php

**Checkpoint**: A deliberate update changes only the source template and current lineage; pre-existing Findings remain detached.

---

## Phase 6: User Story 4 - Prevent stale or unverifiable updates (Priority: P1)

**Goal**: Refuse stale, legacy-different, deleted-source, and read-only mutations without partial state.

**Independent Test**: Two sibling Findings reproduce mandatory regression prevention; legacy-identical initializes safely, legacy-different and deleted source refuse, and completed/archived state is enforced in both action and UI.

- [x] T026 [US4] Add stale sibling, legacy-null, soft-deleted/hard-cleared source, and direct read-only action tests in tests/Feature/FindingTemplateLearningTest.php
- [x] T027 [US4] Complete legacy-identical initialization and localized mismatch/deleted/read-only refusal paths in app/Actions/Assessments/UpdateTemplateFromFinding.php and resources/lang/it/assestme.php
- [x] T028 [US4] Add Livewire action visibility, save-before-action failure, conflict, and read-only tests in tests/Feature/WorkspacePageTest.php
- [x] T029 [US4] Enforce source lifecycle/action visibility and safe template-open/save-as-new alternatives in app/Filament/Resources/Assessments/Pages/WorkspaceAssessment.php and app/Filament/Resources/Assessments/Tables/AssessmentFindingsTable.php

**Checkpoint**: Mandatory stale-regression Scenario E and all legacy/deletion/read-only refusals are demonstrated with zero forbidden mutation.

---

## Phase 7: Documentation and verification

**Purpose**: Record the durable contract and execute proportional then complete evidence.

- [x] T030 Add D-073 to docs/adr/0004-workspace-ui-and-persistence.md and update ADR coverage/index in docs/adr/README.md
- [x] T031 [P] Document reusable fields, fingerprint/legacy/exact/update/import boundaries in docs/reference/domain-and-application-contracts.md and docs/reference/ui-persistence-import-export.md
- [x] T032 Record implementation facts and any discoveries in docs/_meta/progress.md and docs/_meta/discoveries.md
- [x] T033 Run the focused Feature/Livewire tests from specs/003-learn-finding-templates/quickstart.md and resolve failures
- [x] T034 Run focused Pint and PHPStan over the actual change surface and resolve failures
- [x] T037 Add fail-closed normative-runtime enforcement for the then-current complete gate, update Compose/CI/agent contracts, and cover host refusal in tests/Feature/DeploymentConfigurationTest.php
- [x] T038 Record the then-current verification decision and invocation in canonical testing documentation
- [x] T035 Run the then-current authoritative complete gate and record exact results in docs/_meta/progress.md
- [x] T036 Audit the final diff, confirm all acceptance scenarios/evidence and mark every completed task in specs/003-learn-finding-templates/tasks.md

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Complete.
- **Foundational (Phase 2)**: Blocks every user story.
- **US1 (Phase 3)**: Depends on Phase 2 and establishes create/new-lineage mutation and UI hooks.
- **US2 (Phase 4)**: Depends on Phase 2 and reuses the US1 aggregate action/UI preview.
- **US3 (Phase 5)**: Depends on Phase 2 and authoritative result mapping; independent from similarity scoring.
- **US4 (Phase 6)**: Depends on US3 update action and validates all guard states.
- **Documentation/verification (Phase 7)**: Depends on all four stories.

### User Story Dependencies

- **US1**: Independently proves learning a new template from a Finding.
- **US2**: Reuses US1's action surface but independently proves exact-link and similarity behavior.
- **US3**: Independently proves valid source improvement and detached-snapshot behavior.
- **US4**: Extends US3 with mandatory concurrency, legacy, deletion, and read-only refusal evidence.

### Parallel Opportunities

- T031 may be prepared independently after behavior stabilizes, but it must not overwrite the user's pre-existing edit in `docs/reference/ui-persistence-import-export.md`.
- Focused formatting/static analysis can evaluate disjoint file groups, but implementation changes to shared actions and Workspace files remain sequential.
- No parallel agent work is required; task markers describe dependency safety, not authorization to fork.

## Implementation Strategy

1. Finish the shared lineage/projection foundation and make copy/duplicate tests pass.
2. Deliver and validate US1 creation as the MVP.
3. Add US2 exact/similar review without weakening US1 creation.
4. Add US3 full replacement, then US4 stale/legacy/deletion guards.
5. Update canonical docs/ADR, run focused checks, then the single final complete gate.

## Format Validation

All 38 tasks use the required checkbox, task ID, optional `[P]`, story label where
applicable, concrete description, and exact repository path.
