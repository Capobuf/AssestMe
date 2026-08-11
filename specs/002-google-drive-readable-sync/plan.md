# Implementation Plan: Google Drive Readable Sync

**Branch**: `develop` | **Date**: 2026-08-11 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/002-google-drive-readable-sync/spec.md`

## Summary

Add an optional, one-way readable projection of AssestMe assessment data below an automatically
created `My Drive/AssestMe` folder. A dedicated settings page owns the embedded Google Cloud guide,
encrypted two-field OAuth application configuration, calculated callback, connection, managed-root
creation/retry, status, manual sync, and disconnect. Typed application services
resolve UI settings with optional environment defaults, build deterministic assessment snapshots,
skip unchanged assessments, create/update the ID-addressed Drive hierarchy and one four-tab native
Sheet per Assessment, copy verified immutable reports and file evidence, and persist retryable
per-assessment status. A scheduled command uses the existing synchronous/file-cache runtime and
never enters the Workspace save path. Successful root creation enables automatic synchronization by
default, and the connected state is rendered with native Filament information and action components.

## Technical Context

**Language/Version**: PHP 8.3.0+ with strict types

**Primary Dependencies**: Laravel 13.19, Filament 5.6, Livewire 4.3,
`laravel/socialite:^5.29`, `yaza/laravel-google-drive-storage:^5.0`,
`revolution/laravel-google-sheets:^7.2`, existing Spatie Laravel Settings 3.9 and Google PHP client

**Storage**: Existing SQLite/MySQL/MariaDB database, private local filesystem, file cache; Google
Drive and native Google Sheets are optional output-only destinations

**Testing**: Pest 4 feature/unit tests, Socialite facade fakes/mocks, one concrete Google boundary
substituted through the container, focused Livewire/Filament tests, existing Dusk gate

**Target Platform**: Existing Laravel web/CLI runtime and scheduler; no new daemon or frontend build

**Project Type**: Conventional Laravel monolith with Filament administration UI

**Performance Goals**: Skip unchanged assessments before any Google call; process at most 50
assessments per database chunk; avoid unbounded relation loading; one remote transaction sequence
per changed assessment

**Constraints**: `drive.file` only, an application-created normal My Drive root, exact Google parent IDs for every
created object, dense Sheet value rows, offline refresh credential encrypted
at rest, no `stateless()`, no autosave Google call, no remote deletion/import, no fake success,
synchronous queue unchanged, no Google Picker/key/project-number configuration, no Node/npm/Redis/worker,
and no edits, forks, patches, copied internals, or monkey-patches for Composer dependencies

**Scale/Scope**: One administrator, one connected Google account and root per installation, one sync
state and native Sheet per Assessment, all current Findings/Solutions/Evidences/GeneratedReports

## Constitution Check

*GATE: Passed before research and re-checked after design.*

- **I Repository Authority — PASS**: the plan preserves accepted ADRs and canonical local
  persistence/report/private-file contracts; the new durable decision uses D-072 because D-071 is
  already assigned in the current repository state.
- **II Deliberate Simplicity — PASS**: two focused orchestration services plus concrete snapshot and
  Google boundary classes; no repository, CQRS, generic cloud provider, worker, custom command bus,
  or dependency modification.
- **III Fixed Application Stack — PASS**: Laravel/Filament/Livewire/PHP and all existing database,
  cache, session, queue, PDF, XLSX, and Dusk choices remain unchanged. The approved packages declare
  Laravel 13/PHP 8.3 compatibility.
- **IV Authoritative Persistence and Explicit Failure — PASS**: local data stays authoritative;
  failures never advance successful state or block local behavior; files are hash-verified and never
  omitted or replaced by placeholders.
- **V Vertical Delivery and Proportional Evidence — PASS**: tasks run through settings → OAuth →
  root → snapshot → remote projection → scheduler/retry → tests/docs, then the single final gate.

Post-design re-check: **PASS**. The data model adds only one per-assessment state table and one
dedicated settings group. Contracts keep remote state output-only and make all mutations explicit.

## Project Structure

### Documentation (this feature)

```text
specs/002-google-drive-readable-sync/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── google-drive-sync.md
├── checklists/
│   ├── requirements.md
│   └── integration.md
└── tasks.md
```

### Source Code (repository root)

```text
app/
├── Console/Commands/GoogleDriveSyncCommand.php
├── Data/GoogleDrive/
│   ├── GoogleDriveAssessmentSnapshot.php
│   └── GoogleDriveSyncResult.php
├── Filament/Pages/GoogleDriveSettingsPage.php
├── Http/Controllers/GoogleDrive/
│   └── GoogleDriveOAuthController.php
├── Models/AssessmentGoogleDriveSync.php
├── Services/GoogleDrive/
│   ├── BuildGoogleDriveAssessmentSnapshot.php
│   ├── GoogleDriveConfiguration.php
│   ├── GoogleDriveName.php
│   ├── GoogleDriveSyncService.php
│   ├── GoogleDriveTokenService.php
│   └── GoogleWorkspaceClient.php
└── Settings/GoogleDriveSettings.php

database/
├── migrations/*_create_assessment_google_drive_syncs_table.php
└── settings/*_create_google_drive_settings.php

resources/views/filament/pages/google-drive-settings-page.blade.php
resources/lang/it/assestme.php
routes/web.php
routes/console.php
tests/Feature/GoogleDrive/
```

**Structure Decision**: Extend the existing monolith. Controllers/pages only validate and dispatch;
typed concrete services own token, snapshot, naming, synchronization, and remote boundary behavior.
The Google boundary is concrete and container-substitutable in tests, avoiding a one-implementation
interface while keeping CI independent of Google.

## Complexity Tracking

No constitution violation requires justification.
