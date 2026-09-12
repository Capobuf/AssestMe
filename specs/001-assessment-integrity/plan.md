# Implementation Plan: Assessment Integrity and MSP Baseline

**Branch**: `codex/fix-p0-p1-assessment-integrity` | **Date**: 2026-08-11 |
**Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/001-assessment-integrity/spec.md`

## Summary

Deliver three sequential vertical slices. First, every dependent Workspace action persists the
active form through one server-authoritative gate; Finding, solutions, associations, and pending
Evidence share one idempotent transaction and one assessment version, while private-file writes
use explicit compensation. Reorder joins the same request protocol. Second, one resolver makes
`GeneralSettings::active_risk_profile_id` the operational source for editors and template
interchange; urgency becomes profile-relative, v1 remains importable, and export becomes v2.
Third, the Italian seed document becomes a substantial, deterministic MSP library validated by
the same v2 structural and semantic path used in production.

## Technical Context

**Language/Version**: PHP 8.3.6 with strict types for every new PHP file

**Primary Dependencies**: Laravel 13, Filament 5, Livewire 4, Opis JSON Schema 2.6,
Spatie Laravel PDF/WeasyPrint, PhpSpreadsheet 5

**Storage**: Portable Eloquent persistence for SQLite, MySQL, and MariaDB; private local filesystem
for pending/final Evidence and generated files; file cache/session; synchronous queue

**Testing**: Pest/PHPUnit feature and Livewire tests, Laravel Dusk for one real protected-action
journey, Pint, PHPStan, existing diagnostics and complete verification script

**Target Platform**: Existing PHP web/CLI deployment and `docker/compose.dev.yml`; no Node build or
new infrastructure

**Project Type**: Single Laravel web application

**Performance Goals**: Preserve the existing 10/25/50 Finding navigator evidence and current
workspace query budget; one user action produces at most one assessment version increment

**Constraints**: Server persistence authoritative; explicit optimistic-lock/idempotency failure;
no SQL/filesystem atomicity claim; no PWA/IndexedDB redesign; no new package, fallback, service,
queue worker, public API, or generic repository

**Scale/Scope**: One administrator, assessments with at least 50 Findings, up to 20 pending files
per form and configured aggregate Evidence limits, a substantial multi-domain seed library

## Constitution Check

*GATE before research: PASS. Re-checked after design: PASS.*

- Repository authority: PASS. The plan follows the task, accepted ADRs, and canonical references;
  Spec Kit artifacts remain implementation aids.
- Deliberate simplicity: PASS. New code is limited to Workspace-specific orchestration and small
  application services/DTOs with no single-implementation interfaces or infrastructure changes.
- Fixed stack: PASS. No dependency or database-support change is planned.
- Authoritative persistence/failure: PASS. Save ordering, idempotency, compensation, and explicit
  failures are the central design.
- Vertical delivery/evidence: PASS. Phase order is US1 then US2 then US3; each reaches focused
  success/failure tests before the next. The then-current complete gate runs once in final polish.

## Phase 0 Research Decisions

The resolved decisions and rejected alternatives are recorded in [research.md](research.md). No
`NEEDS CLARIFICATION` remains.

## Phase 1 Design

- [data-model.md](data-model.md) defines request, Evidence preparation, risk-profile, template
  document, and baseline identities/state transitions.
- [contracts/template-interchange-v2.md](contracts/template-interchange-v2.md) defines v1/v2
  routing and v2 semantic validation without duplicating the canonical schema file.
- [quickstart.md](quickstart.md) defines independently runnable end-to-end validation scenarios.

## Vertical Implementation Sequence

### Phase 1 — User Story 1

1. Add focused Livewire/action tests for save-before-navigation/mutation/output and stopped actions.
2. Refactor `WorkspaceAssessment` around one internal pre-action persistence result, with silent
   autosave and separate Finding/assessment dirty state.
3. Include normalized pending file and URL Evidence in `FindingSaveData` hashing and save replay.
4. Validate all pending Evidence first, prepare final private files, then persist the whole Finding
   aggregate, Evidence rows, request row, and one version increment in one database transaction;
   compensate prepared files on failure and retain pending uploads on validation failure.
5. Add a stable reorder request prepared before delivery, persist/replay it through
   `WorkspaceSaveRequest`, and expose conflict/mismatch without double increments.
6. Run the focused US1 feature tests and one Dusk protected-action journey; record checkpoint.

### Phase 2 — User Story 2

1. Add custom-profile tests for active resolution, editor/template options, historical saves,
   disabling, import, urgency, and schema compatibility.
2. Add one `ActiveRiskProfileResolver` and use it from active-profile operations; preserve
   unchanged historical IDs and require the active matrix for deliberate reclassification.
3. Replace urgency code checks in widgets and assessment filtering with per-profile top ordered
   priority IDs.
4. Preserve `schemas/finding-template-v1.schema.json`, make the canonical schema v2, route preview
   and import by declared version, validate v2 codes/matrix with contextual errors, and export v2.
5. Run focused US2 tests and record checkpoint.

### Phase 3 — User Story 3

1. Expand `templates/base-findings.it.json` as v2 across every approved MSP domain while retaining
   the eight distributed external IDs and avoiding duplicate/vendor-only Findings.
2. Add structural, semantic, editorial, category, external-ID, recommendation, deterministic
   round-trip, and repeated-seed tests.
3. Run focused US3 tests and record checkpoint.

### Polish

1. Update ADR 0003/0004 and only the affected canonical reference/repository-layout contracts.
2. Run Pint, PHPStan, all affected tests, then start `docker/compose.dev.yml` and gather the real
   login, PDF/XLSX, and backup/restore evidence required by `AGENTS.md`.
3. Run the then-current complete gate exactly once at the end, record factual outcomes, and create the final
   commit without merging `main`.

## Project Structure

### Documentation (this feature)

```text
specs/001-assessment-integrity/
├── spec.md
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/template-interchange-v2.md
├── checklists/requirements.md
└── tasks.md
```

### Source Code (affected areas)

```text
app/
├── Actions/Assessments/       # authoritative Finding save, reorder, completion
├── Actions/Evidence/          # validation/preparation reused by aggregate save
├── Actions/Risk/              # active-profile configuration validation
├── Actions/Templates/         # v1/v2 semantic import and v2 export
├── Data/Assessments/          # typed aggregate save/reorder inputs and results
├── Filament/Resources/        # Workspace and active-profile form options
├── Filament/Widgets/          # profile-relative urgent queries
├── Models/                    # existing request/risk relationships only
└── Services/                  # small active-profile/urgent-level services

schemas/
├── finding-template.schema.json
└── finding-template-v1.schema.json

templates/base-findings.it.json
database/seeders/
resources/lang/it/assestme.php
resources/views/filament/resources/assessments/pages/workspace-assessment.blade.php
tests/Feature/
tests/Browser/
docs/adr/
docs/reference/
```

**Structure Decision**: Extend the existing Laravel structure by behavior area. Do not introduce
modules, repositories, a frontend project, or a parallel persistence layer.

## Complexity Tracking

No constitution violations or exceptions are required.
