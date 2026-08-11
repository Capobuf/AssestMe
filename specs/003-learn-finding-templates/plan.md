# Implementation Plan: Learn from Assessment Findings

**Branch**: `develop` | **Date**: 2026-08-11 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/003-learn-finding-templates/spec.md`

## Summary

Add an explicit Finding-to-template learning workflow to the existing Workspace row-action menu.
A forward-only nullable fingerprint column records the reusable source content known by each
Finding. One focused content projector canonicalizes Finding and FindingTemplate state for lineage
fingerprints, semantic exact comparison, solution mapping, and compact diffs; one lexical matcher
finds exact and conservative similar candidates. Typed actions create/link a template or replace an
authorized source inside the existing assessment cache lock, database transaction, optimistic
version increment, and authoritative `SaveFindingTemplate` path. Filament only persists the current
Finding, renders previews, dispatches actions, and reports localized outcomes.

## Technical Context

**Language/Version**: PHP 8.3.0+ with strict types

**Primary Dependencies**: Laravel 13.19, Filament 5.6, Livewire 4.3, Eloquent, existing PHP Intl
Normalizer, and existing `SaveFindingTemplate` / risk services; no new dependency

**Storage**: Existing SQLite/MySQL/MariaDB database; one nullable portable `char(64)` Finding column

**Testing**: Pest 4 Feature and Livewire/Filament tests, focused Pint and PHPStan, existing final Dusk gate

**Target Platform**: Existing Laravel web and CLI runtime; no frontend build or external service

**Project Type**: Conventional Laravel monolith with Filament administration UI

**Performance Goals**: Compare the current small template library in PHP, return at most three
similar candidates, and keep all exact/create/update mutations bounded to one Finding, one source
template, and at most three solutions

**Constraints**: `SaveFindingTemplate` remains authoritative; one assessment lock/version system;
no revision/history table, merge, force overwrite, AI, embeddings, new search service, repository,
Node/npm, dependency, import change, or baseline JSON modification

**Scale/Scope**: One administrator; bundled baseline of 221 templates; one current source lineage
per Finding; one to three active solutions under existing editorial limits

## Constitution Check

*GATE: Passed before research and re-checked after design.*

- **I Repository Authority — PASS**: accepted detached-snapshot, full-replacement, active-risk,
  read-only, dependent-save, optimistic-locking, and import contracts are preserved. The new durable
  fingerprint decision is added as the next available D-073 in the thematic Workspace ADR.
- **II Deliberate Simplicity — PASS**: two focused services, two aggregate actions, and small typed
  results cover projection/detection and mutation. No repository, CQRS, versioning subsystem,
  external search, or speculative abstraction is introduced.
- **III Fixed Application Stack — PASS**: the implementation uses only the locked PHP/Laravel/
  Filament/Livewire stack and portable schema primitives; SQLite/MySQL/MariaDB remain supported.
- **IV Authoritative Persistence and Explicit Failure — PASS**: current Workspace state saves first;
  template persistence stays authoritative; source fingerprint comparison occurs under lock; every
  related mutation rolls back together and failures remain explicit.
- **V Vertical Delivery and Proportional Evidence — PASS**: tasks connect migration/model, content
  projection, create/link/update actions, Workspace menu/modals, tests, docs, focused checks, and one
  final complete gate.

Post-design re-check: **PASS**. The model adds only the requested nullable fingerprint. Contracts
keep template import/export unchanged, keep disabled state outside the hash, and add no alternate
concurrency or identity mechanism.

## Project Structure

### Documentation (this feature)

```text
specs/003-learn-finding-templates/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── workspace-template-learning.md
├── checklists/
│   └── requirements.md
└── tasks.md
```

### Source Code (repository root)

```text
app/
├── Actions/Assessments/
│   ├── CopyTemplateToAssessment.php
│   ├── SaveFindingAsTemplate.php
│   └── UpdateTemplateFromFinding.php
├── Actions/Templates/SaveFindingTemplate.php
├── Data/Templates/
│   ├── FindingTemplateSyncResult.php
│   └── SavedFindingTemplate.php
├── Filament/Resources/Assessments/
│   ├── Pages/WorkspaceAssessment.php
│   └── Tables/AssessmentFindingsTable.php
├── Models/Finding.php
└── Services/Templates/
    ├── FindingTemplateContent.php
    └── FindRelatedFindingTemplates.php

database/migrations/*_add_source_template_fingerprint_to_findings_table.php
resources/views/filament/resources/assessments/template-learning-preview.blade.php
resources/lang/it/assestme.php
tests/Feature/FindingTemplateLearningTest.php
tests/Feature/WorkspacePageTest.php
```

**Structure Decision**: Extend the existing monolith. The Workspace page/table only orchestrate
save-before-action, previews, localized modals, and notifications. Concrete typed application
services own reusable-content representation and candidate detection; aggregate actions own locks,
transactions, authoritative template persistence, lineage, key realignment, and version increments.
`SaveFindingTemplate` gains a result-returning entry point while its existing `handle()` contract
remains intact, keeping external-ID generation in one source of truth.

## Complexity Tracking

No constitution violation requires justification.
