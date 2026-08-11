# Tasks: Google Drive Readable Sync

**Input**: Design documents from `specs/002-google-drive-readable-sync/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/`

**Tests**: Required by the feature brief. Within every story phase, focused tests are written and
observed failing before implementation, then rerun before broader checks.

**Organization**: Eleven vertical phases preserve the requested delivery order. Story labels maintain
traceability even where one story spans two adjacent vertical phases.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: May run in parallel because it targets independent files after prerequisites.
- **[US1]**: Connect/configure Google Drive.
- **[US2]**: Produce the complete readable assessment copy.
- **[US3]**: Synchronize changes automatically and safely.

## Phase 1: Configuration, dependencies, and settings foundation

**Purpose**: Install approved packages and establish optional configuration plus minimal local state.

- [X] T001 Add approved Socialite, Drive Storage v5, and Google Sheets dependencies through Composer in `composer.json` and `composer.lock`
- [X] T002 [P] Add optional OAuth client ID/secret defaults to `.env.example` and typed service configuration to `config/services.php`
- [X] T003 [P] [US1] Add encrypted dedicated settings class and settings migration in `app/Settings/GoogleDriveSettings.php` and `database/settings/2026_08_11_000022_create_google_drive_settings.php`
- [X] T004 [P] [US3] Add portable assessment sync-state migration/model/relation in `database/migrations/2026_08_11_000020_create_assessment_google_drive_syncs_table.php`, `app/Models/AssessmentGoogleDriveSync.php`, and `app/Models/Assessment.php`
- [X] T005 [P] Add Google Drive Italian translation keys to `resources/lang/it/assestme.php`
- [X] T006 Add configuration/settings/migration success and failure tests in `tests/Feature/GoogleDrive/GoogleDriveSettingsTest.php` and make them pass

**Checkpoint**: Fresh SQLite/MySQL/MariaDB-compatible installations resolve optional settings with encrypted secrets; Google remains inactive while incomplete and automatic synchronization becomes active after managed-root creation succeeds.

---

## Phase 2: OAuth Google and credential lifecycle

**Goal**: Deliver authenticated, stateful, minimum-scope connection and refresh-token lifecycle.

**Independent Test**: An authenticated administrator receives the exact stateful offline redirect;
callback success stores encrypted credentials while all callback failures preserve valid prior state.

- [X] T007 [P] [US1] Write OAuth redirect/callback and secret non-disclosure tests in `tests/Feature/GoogleDrive/GoogleDriveOAuthTest.php`
- [X] T008 [P] [US1] Write token refresh/revocation failure tests in `tests/Feature/GoogleDrive/GoogleDriveTokenServiceTest.php`
- [X] T009 [US1] Implement temporary access-token and revocation behavior in `app/Services/GoogleDrive/GoogleDriveTokenService.php`
- [X] T010 [US1] Implement stateful Socialite redirect/callback lifecycle in `app/Http/Controllers/GoogleDrive/GoogleDriveOAuthController.php`
- [X] T011 [US1] Add authenticated OAuth routes in `routes/web.php`
- [X] T012 [US1] Run focused OAuth/token tests and close redirect, callback, log/output, and preservation failure paths

**Checkpoint**: US1 connection lifecycle works independently without a root or sync operation.

---

## Phase 3: Managed root creation and Settings UI

**Goal**: Complete US1 with a native Settings page and application-owned root creation.

**Independent Test**: Incomplete/disconnected/connected-without-root/configured states render; OAuth
success creates a new My Drive root by public API, and root failure remains visible and retryable.

- [X] T013 [P] [US1] Write automatic root success/failure/retry tests in `tests/Feature/GoogleDrive/GoogleDriveManagedRootTest.php` and OAuth callback coverage
- [X] T014 [P] [US1] Write Filament guide/configuration/state/action tests in `tests/Feature/GoogleDrive/GoogleDriveSettingsPageTest.php`
- [X] T015 [US1] Implement application-owned My Drive root creation through public Google APIs in `app/Services/GoogleDrive/GoogleWorkspaceClient.php`
- [X] T016 [US1] Create the managed root after OAuth callback and preserve explicit connected-without-root failure state in `app/Http/Controllers/GoogleDrive/GoogleDriveOAuthController.php`
- [X] T017 [US1] Remove browser-token/root-selection routes and controllers from `routes/web.php` and `app/Http/Controllers/GoogleDrive/`
- [X] T018 [US1] Implement incomplete/disconnected/connected-without-root/configured states and retry action in `app/Filament/Pages/GoogleDriveSettingsPage.php`
- [X] T019 [US1] Remove all selector JavaScript and render the usage-oriented status view in `resources/views/filament/pages/google-drive-settings-page.blade.php`
- [X] T020 [US1] Implement managed-root verification, automatic toggle, and disconnect state transitions in `app/Filament/Pages/GoogleDriveSettingsPage.php`
- [X] T021 [US1] Run focused root/UI tests and close root-creation, retry, disconnect, and secret-disclosure paths

**Checkpoint**: User Story 1 is complete and independently testable.

---

## Phase 4: Deterministic Assessment snapshot and Drive naming

**Goal**: Build the complete stable local projection and safe ID-addressed hierarchy inputs.

**Independent Test**: The realistic dataset serializes every required row/file record in stable order;
child changes and the managed root identity alter the hash while sync time does not; unsafe names retain stable prefixes.

- [X] T022 [P] [US2] Write naming/sanitization and duplicate-prefix contract tests in `tests/Feature/GoogleDrive/GoogleDriveNameTest.php`
- [X] T023 [P] [US2] Write canonical realistic-dataset/hash tests in `tests/Feature/GoogleDrive/GoogleDriveSnapshotTest.php`
- [X] T024 [US2] Implement stable readable naming in `app/Services/GoogleDrive/GoogleDriveName.php`
- [X] T025 [US2] Add typed snapshot DTOs in `app/Data/GoogleDrive/GoogleDriveAssessmentSnapshot.php`
- [X] T026 [US2] Implement bounded Eloquent loading and deterministic projection in `app/Services/GoogleDrive/BuildGoogleDriveAssessmentSnapshot.php`
- [X] T027 [US2] Run focused naming/snapshot tests and prove complete relation/report/evidence hash coverage

**Checkpoint**: US2 has a complete local projection with no Google calls.

---

## Phase 5: Google Sheet, documents, and evidence projection

**Goal**: Create/update the complete non-destructive readable remote copy for one Assessment.

**Independent Test**: A fake boundary records the required hierarchy, exactly one four-tab native
Sheet, verified immutable document copies, file evidence copy, URL reference, and no delete calls.

- [X] T028 [P] [US2] Write complete remote hierarchy/Sheet projection test in `tests/Feature/GoogleDrive/GoogleDriveAssessmentSyncTest.php`
- [X] T029 [P] [US2] Write local-file missing/hash/size failure tests in `tests/Feature/GoogleDrive/GoogleDriveFileValidationTest.php`
- [X] T030 [P] [US2] Write remote overwrite, extra-tab/file preservation, and duplicate-managed-object tests in `tests/Feature/GoogleDrive/GoogleDriveNonDestructiveTest.php`
- [X] T031 [US2] Complete parent-scoped create/find/rename/upload/Sheet operations in `app/Services/GoogleDrive/GoogleWorkspaceClient.php`
- [X] T032 [US2] Implement one-Assessment orchestration for folders and Sheet replacement in `app/Services/GoogleDrive/GoogleDriveSyncService.php`
- [X] T033 [US2] Implement verified GeneratedReport and Evidence file copying plus remote links in `app/Services/GoogleDrive/GoogleDriveSyncService.php`
- [X] T034 [US2] Run focused remote-projection tests and close overwrite, non-destruction, duplicate, and file failure paths

**Checkpoint**: User Story 2 is complete and independently testable for one Assessment.

---

## Phase 6: Sync state, hashing, scheduler, and Sync now

**Goal**: Deliver bounded automatic/manual orchestration with deterministic skip and overlap safety.

**Independent Test**: Disabled/incomplete and unchanged runs make zero Google calls; changed/forced
runs synchronize in chunks, update state, and concurrent execution reports the lock owner.

- [X] T035 [P] [US3] Write command no-op/change/force/chunk tests in `tests/Feature/GoogleDrive/GoogleDriveSyncCommandTest.php`
- [X] T036 [P] [US3] Write lock and scheduler registration tests in `tests/Feature/GoogleDrive/GoogleDriveSchedulerTest.php`
- [X] T037 [US3] Add aggregate typed result in `app/Data/GoogleDrive/GoogleDriveSyncResult.php`
- [X] T038 [US3] Implement bounded all-assessment orchestration, hash skip, and file-cache lock in `app/Services/GoogleDrive/GoogleDriveSyncService.php`
- [X] T039 [US3] Implement `assestme:google-drive-sync --force` in `app/Console/Commands/GoogleDriveSyncCommand.php`
- [X] T040 [US3] Register hourly overlap-protected schedule in `routes/console.php`
- [X] T041 [US3] Wire “Sincronizza ora” and aggregate status into `app/Filament/Pages/GoogleDriveSettingsPage.php`
- [X] T042 [US3] Run focused command/scheduler/Settings tests and prove zero-call skips and lock behavior

**Checkpoint**: Normal scheduled/manual synchronization is complete.

---

## Phase 7: Failure, retry, disconnect, and managed-root closure

**Goal**: Make every required external/local failure explicit, sanitized, retryable, and locally isolated.

**Independent Test**: Each required failure retains prior success state, stores no secret, leaves local
save/report paths working, and clears on retry; disconnect never deletes remote objects.

- [X] T043 [P] [US3] Write provider failure/retry matrix tests in `tests/Feature/GoogleDrive/GoogleDriveFailureRetryTest.php`
- [X] T044 [P] [US3] Write local Workspace/PDF/XLSX independence test in `tests/Feature/GoogleDrive/GoogleDriveLocalIndependenceTest.php`
- [X] T045 [US3] Add sanitized domain exceptions and provider error mapping in `app/Services/GoogleDrive/GoogleWorkspaceClient.php` and `app/Services/GoogleDrive/GoogleDriveTokenService.php`
- [X] T046 [US3] Complete per-assessment error persistence/retry clearing in `app/Services/GoogleDrive/GoogleDriveSyncService.php`
- [X] T047 [US1] Complete safe disconnect/revocation and non-destructive managed-root messaging in `app/Filament/Pages/GoogleDriveSettingsPage.php`
- [X] T048 [US3] Run focused failure/retry/local-independence tests and verify no successful-hash advance on failure

**Checkpoint**: User Story 3 is complete and independently testable.

---

## Phase 8: Focused integration and browser coverage

**Purpose**: Consolidate the exact requested test set without live Google infrastructure.

- [X] T049 Add end-to-end fake-boundary realistic dataset/update/conflict/non-destruction scenario in `tests/Feature/GoogleDrive/GoogleDriveReadableSyncTest.php`
- [X] T050 [P] Add focused Dusk rendering/state/buttons/console test in `tests/Browser/GoogleDriveSettingsTest.php` only if compatible with existing fake browser infrastructure
- [X] T051 Run all `tests/Feature/GoogleDrive` tests and focused Dusk test when present, rerunning any failure before broader checks
- [X] T052 Run focused Pint and PHPStan over the complete Google Drive change surface

---

## Phase 9: Canonical documentation and ADR

**Purpose**: Record the durable output-only decision without duplicating Spec Kit artifacts.

- [X] T053 [P] Update dependency/runtime and external references in `docs/reference/runtime-and-dependencies.md` and `docs/reference/external-references.md`
- [X] T054 [P] Update readable-copy, file validation, security, failure, and acceptance contracts in `docs/reference/reports-evidence-and-files.md` and `docs/reference/testing-security-and-acceptance.md`
- [X] T055 Record approved optional one-way non-destructive Drive output as D-072 in `docs/adr/0006-storage-deletion-and-backup.md` after rechecking the highest decision ID
- [X] T056 Record factual implementation discoveries/progress in `docs/_meta/discoveries.md` and `docs/_meta/progress.md`

---

## Phase 10: Verification and convergence

**Purpose**: Prove dependency integrity, the coherent change set, and artifact/code convergence.

- [X] T057 Run `composer validate --strict` and `composer audit --locked`
- [X] T058 Run the focused commands from `specs/002-google-drive-readable-sync/quickstart.md`
- [X] T059 Run the single final `scripts/verify.sh` complete gate
- [X] T060 Run `$speckit-converge`; implement and test any appended tasks until the feature converges
- [X] T061 Record exact final branch/HEAD, commands, results, and real-Google `NOT VERIFIED` boundaries in `docs/_meta/progress.md`

---

## Dependencies & Execution Order

### Phase Dependencies

- Phase 1 establishes packages, configuration, settings, and schema.
- Phase 2 depends on Phase 1 token/settings support.
- Phase 3 depends on Phase 2 connection lifecycle and completes US1.
- Phase 4 depends only on Phase 1 domain state and builds US2 locally.
- Phase 5 depends on Phases 3–4 and completes US2 remotely.
- Phase 6 depends on Phases 4–5 and establishes US3 orchestration.
- Phase 7 depends on Phases 2–6 and closes all recovery paths.
- Phase 8 depends on all user stories.
- Phase 9 may begin after behavior stabilizes; D-072 is written only after final ID recheck.
- Phase 10 depends on the coherent implementation and documentation.

### User Story Dependencies

- **US1 (P1)**: Independently demonstrable after Phase 3; does not require an Assessment.
- **US2 (P1)**: Local projection is independent after Phase 4; remote copy needs configured US1.
- **US3 (P2)**: Uses US2 one-assessment sync but its skip/lock/state behavior is independently fake-testable.

### Parallel Opportunities

- In Phase 1, config, settings, schema, and translations touch separate files after Composer resolves.
- OAuth and token tests in Phase 2 can be authored independently.
- Managed-root and Filament guide tests in Phase 3 can be authored independently.
- Naming and snapshot tests in Phase 4 are independent.
- Remote projection/file/non-destruction tests in Phase 5 are independent.
- Command and scheduler tests in Phase 6 are independent.
- Provider retry and local-independence tests in Phase 7 are independent.
- Canonical reference-page updates in Phase 9 are independent until the ADR/progress pass.

## Parallel Examples

```text
Phase 4:
- T022 GoogleDriveNameTest.php
- T023 GoogleDriveSnapshotTest.php

Phase 5:
- T028 GoogleDriveAssessmentSyncTest.php
- T029 GoogleDriveFileValidationTest.php
- T030 GoogleDriveNonDestructiveTest.php
```

## Implementation Strategy

1. Complete configuration and US1 connection/root settings, then validate its focused tests.
2. Build the deterministic local US2 snapshot before any remote orchestration.
3. Complete one-Assessment remote copy and its non-destructive/failure tests.
4. Add US3 bounded command/scheduler/manual orchestration and recovery.
5. Consolidate focused coverage, update canonical docs/D-072, run the gate once, and converge.

## Notes

- Every task has an exact file path and all story tasks have `[US#]` labels.
- No task authorizes real Google calls in CI, remote deletion, import, Node/npm, Redis, or workers.
- Real OAuth/Drive/Sheets acceptance remains `NOT VERIFIED` without manual evidence.

---

## Phase 11: Addendum convergence — two-field setup guide and managed root

**Finding**: `[US1/AC1-7, FR-006–FR-017, FR-047–FR-054, SC-001, SC-006]` requires replacement of
the earlier selector design: configuration must be executable from the UI with an embedded guide,
two stored OAuth fields, calculated callback, and automatic application-owned root.

- [X] T062 [US1] Replace every incompatible selector-related requirement/task/check with the addendum requirements across `spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/google-drive-sync.md`, `quickstart.md`, `tasks.md`, and `checklists/integration.md`
- [X] T063 [US1] Verify the embedded-guide content against current official Google enablement, OAuth, scope, Branding, Audience, Data Access, and client documentation and record the sources in `research.md`
- [X] T064 Run `$speckit-analyze` and remediate any artifact inconsistency before application implementation
- [X] T065 [US1] Update canonical runtime/security/readable-copy/external-reference documentation and D-072 consequences for the addendum
- [X] T066 [US1] Add/update focused feature and Dusk coverage, then run focused tests, Pint, and PHPStan without starting the long complete gate
- [X] T067 Prove no dependency source was changed with repository/package integrity checks
- [X] T068 Run `$speckit-converge` and implement/test any remaining artifact/code gap
- [X] T069 Execute T059 once as the final complete gate, then record exact evidence and real-Google `NOT VERIFIED` boundaries in T061

---

## Phase 12: Real-provider correction and connected-state completion

- [X] T070 [US1] Enable automatic synchronization by default after successful initial or retried managed-root creation and present the connected state with native Filament components in `app/Filament/Pages/GoogleDriveSettingsPage.php`
- [X] T071 [US2] Create folders, native Sheets, evidence, and reports under the exact managed parent ID through the public Drive API in `app/Services/GoogleDrive/GoogleWorkspaceClient.php`
- [X] T072 [US2] Normalize nullable Sheet cells to dense rows before values writes in `app/Services/GoogleDrive/GoogleWorkspaceClient.php`
- [X] T073 [US1] [US2] Add exact-parent, dense-row, connected-page, and default-enabled regression coverage in `tests/Feature/GoogleDrive` and `tests/Browser/GoogleDriveSettingsTest.php`
- [X] T074 Record the sanitized real-provider result and the intentionally preserved orphan boundary in canonical documentation
- [X] T075 Run focused browser/static checks and then execute T059 once as the final complete gate
