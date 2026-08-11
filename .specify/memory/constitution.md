<!--
Sync Impact Report
- Version change: template -> 1.0.0
- Added principles: Repository Authority; Deliberate Simplicity; Fixed Application Stack;
  Authoritative Persistence and Explicit Failure; Vertical Delivery and Proportional Evidence
- Added sections: Product and Architecture Boundaries; Delivery Workflow
- Removed sections: none (template placeholders replaced)
- Follow-up TODOs: none
-->
# AssestMe Constitution

## Core Principles

### I. Repository Authority

`AGENTS.md` and `docs/index.md` retain the authority order defined by the project. Accepted ADRs
and canonical reference pages MUST be followed; assumptions, Spec Kit artifacts, plans, and task
files MUST NOT replace or weaken repository contracts. A contradiction that makes an approved
decision impossible MUST be reported with reproducible evidence instead of silently bypassed.

### II. Deliberate Simplicity

Changes MUST use the smallest application-specific design that satisfies approved behavior. New
microservices, generic repository abstractions, Redis, queue workers, Node-based frontend builds,
and speculative framework layers are prohibited. Complexity MUST be justified by a current,
demonstrable product requirement.

### III. Fixed Application Stack

Laravel 13, Filament 5, Livewire 4, and PHP 8.3 or newer remain mandatory. Fresh installations
MUST continue to support SQLite, MySQL, and MariaDB. Implementations MUST preserve the PDF,
spreadsheet, schema-validation, cache, session, queue, and browser-test technologies selected by
the accepted repository contracts.

### IV. Authoritative Persistence and Explicit Failure

Server persistence remains authoritative. Saves and dependent mutations MUST preserve validation,
optimistic locking, idempotency, and explicit state reporting. Real failures MUST stop dependent
work and remain visible; fake success, silent omission, empty generated artifacts, and automatic
fallbacks are prohibited.

### V. Vertical Delivery and Proportional Evidence

Work MUST be organized as coherent vertical slices that connect user behavior, application logic,
persistence, interface behavior, and applicable success and failure-path tests. Tests and checks
MUST be proportional to the change surface. `scripts/verify.sh` MUST run once, at the conclusion of
the complete coherent change set, unless the user explicitly requests another execution.

## Product and Architecture Boundaries

The application remains a single-user Laravel product with no multi-tenancy, public API, role
system, or infrastructure expansion unless an approved requirement explicitly changes that scope.
Eloquent remains the direct persistence model; business behavior belongs in typed actions and
services while Filament resources and pages remain thin. SQLite/MySQL/MariaDB portability and the
accepted private-file, report, import/export, and workspace contracts MUST be preserved.

## Delivery Workflow

Before implementation, contributors MUST inspect the active branch, runtime, dependency lock,
working tree, and only the canonical documents and code relevant to the change. Each User Story
MUST reach an independently testable checkpoint before the next begins. Discoveries record facts,
not new requirements. Completion requires the focused checks, the single final complete gate, and
the concrete runtime evidence required by `AGENTS.md`; unexecuted acceptance remains `NOT VERIFIED`.

## Governance

This constitution governs Spec Kit artifacts but does not supersede the repository authority chain.
Amendments require explicit user approval, a documented reason, semantic versioning, and an updated
Sync Impact Report. Every specification, plan, task list, implementation, and review MUST check
compliance. Any justified exception MUST be documented in the implementation plan and must not
contradict an accepted ADR.

**Version**: 1.0.0 | **Ratified**: 2026-08-11 | **Last Amended**: 2026-08-11
