# Tasks: Assessment Integrity and MSP Baseline

**Input**: `spec.md`, `plan.md`, `research.md`, `data-model.md`, contract, and quickstart

**Tests**: Required. Every User Story starts with focused success/failure tests and ends with an
independently verifiable checkpoint.

**Organization**: Three sequential vertical User Story phases followed by cross-cutting polish.
Tasks sharing application files are intentionally sequential.

## Phase 1: Workspace mutation integrity and Finding/Evidence save (US1)

**Goal**: No protected action can overtake dirty state; one Finding/Evidence operation is fully
validated, idempotent, compensated, and versioned once; reorder uses the same request contract.

**Independent Test**: Dirty state is persisted before every dependent action, failures stop that
action, PDF/XLSX/completion observe the persisted edit, multi-Evidence save/retry is all-or-nothing,
and reorder replay/mismatch/conflict has no double mutation.

### Workspace autosave + action ordering

- [x] T001 [US1] Add failing Livewire tests for dirty Finding save before select/previous/next/close/tab/new/template/duplicate/delete in tests/Feature/WorkspacePageTest.php
- [x] T002 [US1] Add failing Livewire/report tests for dirty Finding save before completion/PDF/XLSX and stopped actions on save failure in tests/Feature/WorkspacePageTest.php and tests/Feature/ReportGenerationTest.php
- [x] T003 [US1] Separate Finding and assessment dirty/error state and implement one explicit pre-action persistence result in app/Filament/Resources/Assessments/Pages/WorkspaceAssessment.php
- [x] T004 [US1] Route every protected Workspace public method and Filament action through the server-side pre-action gate in app/Filament/Resources/Assessments/Pages/WorkspaceAssessment.php and app/Filament/Resources/Assessments/Tables/AssessmentFindingsTable.php
- [x] T005 [US1] Preserve silent automatic-save feedback and current-context failures in app/Filament/Resources/Assessments/Pages/WorkspaceAssessment.php, resources/views/filament/resources/assessments/pages/workspace-assessment.blade.php, and resources/lang/it/assestme.php

### Finding/Evidence authoritative save

- [x] T006 [US1] Add failing aggregate Evidence tests for two files/one version, invalid second file rollback, within-request duplicate, replay, mismatch, pending-file retention, and filesystem failure in tests/Feature/EvidenceTest.php and tests/Feature/WorkspacePageTest.php
- [x] T007 [US1] Add typed pending/prepared Evidence request data and include normalized files and URL Evidence in the payload hash in app/Data/Assessments/FindingSaveData.php and app/Data/Evidence/
- [x] T008 [US1] Extract reusable prevalidation/private-file preparation and explicit compensation from app/Actions/Evidence/StoreEvidence.php into app/Actions/Evidence/ without changing standalone Evidence behavior
- [x] T009 [US1] Persist Finding, solutions, associations, Evidence rows, replay response, and one version increment inside the existing lock/transaction in app/Actions/Assessments/SaveFindingDetails.php and app/Models/WorkspaceSaveRequest.php
- [x] T010 [US1] Hand the full upload/URL request to the aggregate action and remove premature pending cleanup and per-Evidence version increments in app/Filament/Resources/Assessments/Pages/WorkspaceAssessment.php

### Reorder idempotency

- [x] T011 [US1] Add failing reorder tests for +1 version, identical replay, payload mismatch, stale version, and invalid ownership/order in tests/Feature/WorkspacePageTest.php
- [x] T012 [US1] Add a stable pre-delivery reorder request DTO/property and implement lock/version/hash/replay behavior in app/Data/Assessments/, app/Actions/Assessments/ReorderFindings.php, and app/Filament/Resources/Assessments/Pages/WorkspaceAssessment.php

### Focused tests and checkpoint

- [x] T013 [US1] Replace the old dirty-selection warning browser expectation with a real edit-autosave-next-action journey in tests/Browser/WorkspaceResponsiveTest.php
- [x] T014 [US1] Run focused Workspace/Evidence/report feature tests and the one affected Dusk class; record exact Phase 1 results in docs/_meta/progress.md

**CHECKPOINT**: US1 independently prevents stale or partial Workspace mutations and outputs.

---

## Phase 2: Active risk profile and template interchange v2 (US2)

**Goal**: One enabled operational profile drives new classification/import, historical identities
remain intact, urgency is profile-relative, v1 imports and v2 custom-code round trips.

**Independent Test**: A second active profile with unrelated codes drives editors/import/urgency;
historical saves do not remap IDs; v1 imports, v2 imports/exports, and semantic failures are precise.

### Active risk profile resolver/validation

- [x] T015 [US2] Add failing custom-profile tests for resolver validity, editor/template options, cross-profile save, active-profile disable, historical preservation, and reclassification in tests/Feature/RiskEffortFoundationTest.php, tests/Feature/WorkspacePageTest.php, and tests/Feature/FindingTemplateCrudTest.php
- [x] T016 [US2] Implement the single-source enabled profile resolver in app/Services/Risk/ActiveRiskProfileResolver.php and validate active-profile disabling in app/Actions/Risk/SaveRiskProfileConfiguration.php
- [x] T017 [US2] Restrict new options while retaining historical values and enforce deliberate active-profile classification in app/Filament/Resources/Assessments/Schemas/FindingEditorSchema.php, app/Actions/Assessments/SaveFindingDetails.php, app/Filament/Resources/FindingTemplates/Schemas/FindingTemplateForm.php, and app/Actions/Templates/SaveFindingTemplate.php

### Editor/template/dashboard

- [x] T018 [US2] Add failing urgency tests with watch/attention/urgent_now/emergency codes for widgets and assessment filter in tests/Feature/DashboardTest.php and tests/Feature/AssessmentLifecycleTest.php
- [x] T019 [US2] Implement per-profile top-two ordered priority resolution and replace every code hardcode in app/Services/Risk/UrgentPriorityLevelIds.php, app/Filament/Widgets/AssessmentStatsOverview.php, app/Filament/Widgets/UrgentFindings.php, and app/Filament/Resources/Assessments/Tables/AssessmentsTable.php

### Schema v2, v1 compatibility, import/export

- [x] T020 [US2] Add failing v1/v2/custom-code/unknown-code/matrix-mismatch/deterministic-round-trip/tags tests and fixtures in tests/Feature/TemplateImportExportTest.php and fixtures/imports/
- [x] T021 [US2] Preserve v1 and define canonical v2 structural contracts in schemas/finding-template-v1.schema.json and schemas/finding-template.schema.json
- [x] T022 [US2] Route v1/v2 structural validation and apply one active-profile contextual semantic validator to preview/import in app/Actions/Templates/ImportFindingTemplates.php and app/Services/Templates/ValidateTemplateRiskCodes.php
- [x] T023 [US2] Emit deterministic v2 output in app/Actions/Templates/ExportFindingTemplates.php and convert templates/base-findings.it.json to schema version 2 without changing its eight existing external IDs
- [x] T024 [US2] Run focused risk/editor/template/dashboard/import-export tests; record exact Phase 2 results in docs/_meta/progress.md

**CHECKPOINT**: US2 independently supports custom operational risk codes and v1/v2 interchange.

---

## Phase 3: Comprehensive MSP Finding baseline (US3)

**Goal**: Replace the eight-entry demo with a substantial, independent, observable, actionable,
Italian MSP assessment library across every approved domain.

**Independent Test**: The v2 library validates structurally and semantically, seeds twice without
duplication, round trips deterministically, preserves eight IDs, and passes editorial/category/risk
and recommendation constraints.

### Comprehensive Finding library

- [x] T025 [US3] Add failing baseline tests for thematic coverage, stable/unique IDs, category spelling, editorial limits, risk matrix, one recommendation, deterministic round trip, and repeated seed in tests/Feature/SeedDataTest.php and tests/Feature/TemplateImportExportTest.php
- [x] T026 [US3] Expand governance/perimeter/WAN/LAN/Wi-Fi/physical/server/storage/backup baseline Findings vertically through text, risk, solution, and category in templates/base-findings.it.json
- [x] T027 [US3] Expand Windows/macOS/identity/cloud/email/VoIP/video/continuity/monitoring/documentation/licensing baseline Findings vertically through text, risk, solution, and category in templates/base-findings.it.json
- [x] T028 [US3] Review duplicate conditions, category identity, matrix coherence, editorial budgets, solution realism, and any primary-source normative wording in templates/base-findings.it.json

### Schema, semantic, seed, and focused-test checkpoint

- [x] T029 [US3] Run structural and semantic import validation, two seed runs, export/re-import comparison, and focused seed/template tests; record final template count and Phase 3 results in docs/_meta/progress.md

**CHECKPOINT**: US3 independently provides a production-useful deterministic MSP baseline.

---

## Polish: Canonical docs and final evidence

- [x] T030 Update D-012 history/active-risk decisions and pre-action persistence decisions in docs/adr/0003-domain-data-and-import.md and docs/adr/0004-workspace-ui-and-persistence.md
- [x] T031 Update changed contracts only in docs/reference/domain-and-application-contracts.md, docs/reference/ui-persistence-import-export.md, docs/reference/reports-evidence-and-files.md, and docs/reference/repository-layout.md
- [x] T032 Record factual discoveries and final focused evidence in docs/_meta/discoveries.md and docs/_meta/progress.md
- [x] T033 Run Pint, PHPStan, and all affected tests; fix and rerun only failing focused commands
- [x] T034 Start docker/compose.dev.yml and verify real login, real PDF/XLSX generation/validation, and backup/restore without printing credentials
- [x] T035 Run scripts/verify.sh exactly once, record the exact result, and complete all Spec Kit task checkboxes in specs/001-assessment-integrity/tasks.md
- [x] T036 Review the final diff, commit the complete change set on codex/fix-p0-p1-assessment-integrity, and report the final commit without merging main

## Dependencies and execution order

```text
US1 (T001–T014)
  ↓ checkpoint
US2 (T015–T024)
  ↓ checkpoint
US3 (T025–T029)
  ↓ checkpoint
Polish (T030–T036)
```

- No User Story is implemented in parallel with another.
- Within each story, failing contract tests precede the corresponding implementation.
- The two baseline writing tasks may be edited in separate passes but are sequential because they
  share one JSON file.
- `scripts/verify.sh` appears only in T035 and runs once.

## Implementation strategy

US1 is the reliability MVP. Each completed checkpoint leaves a coherent, independently testable
increment. The feature is complete only after all three stories and final evidence tasks pass.
