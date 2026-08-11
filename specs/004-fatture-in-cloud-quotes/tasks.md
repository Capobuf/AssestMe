# Tasks: Fatture in Cloud Quotes

**Input**: Design documents from `specs/004-fatture-in-cloud-quotes/`

**Tests**: Required by the feature brief. Keep them compact, dataset-driven, and inside the existing Pest/Livewire/Dusk infrastructure; never call the live provider.

**Organization**: US1–US6 are the implementation slices V1–V6. Do not start a later story until the current story has connected persistence, provider behavior, UI, success/failure tests, and focused green evidence. The last phase is convergence V7, not deferred feature testing.

## Phase 1: Scoped preflight

**Purpose**: Preserve concurrent work and freeze the actual implementation/verification surface without creating horizontal feature infrastructure.

- [X] T001 Re-read `.github/workflows/quality.yml`, inspect current diffs and locked dependencies, confirm the official FIC contract still matches `specs/004-fatture-in-cloud-quotes/contracts/fatture-in-cloud-v2.md`, and record only changed facts in `docs/_meta/progress.md` / `docs/_meta/discoveries.md`

---

## Phase 2: Shared foundation

No horizontal foundation is created. Each shared class, migration, route, translation subtree, and fake response is introduced by the first user story that consumes it, preventing unused services, disconnected UI, and test deferral.

---

## Phase 3: User Story 1 — Connect one provider company (V1, Priority P1) 🎯 MVP

**Goal**: The administrator can securely configure OAuth, connect exactly one FIC company, select a live default VAT, verify/reconnect, and disconnect locally from a usable Italian Settings page.

**Independent Test**: `tests/Feature/FattureInCloud/FattureInCloudConnectionTest.php` proves secret handling, OAuth state/code/token lifecycle, company cardinality, VAT default, reconnect/disconnect, and bounded failures entirely with `Http::fake`.

- [X] T002 [US1] Add the compact success/failure provider datasets and connection-page assertions in `tests/Feature/FattureInCloud/FattureInCloudConnectionTest.php`
- [X] T003 [P] [US1] Add idempotent encrypted optional settings keys in `database/settings/2026_08_11_000025_create_fatture_in_cloud_settings.php` and typed properties/state helpers in `app/Settings/FattureInCloudSettings.php`
- [X] T004 [US1] Implement exact callback configuration, OAuth state/code exchange, encrypted access/refresh lifecycle, one controlled refresh, official JSON requests, pagination, bounded exceptions, company discovery, and VAT listing in `app/Services/FattureInCloud/` and `app/Data/FattureInCloud/`
- [X] T005 [US1] Add authenticated connect/callback routes in `routes/web.php` and thin state-validating callbacks in `app/Http/Controllers/FattureInCloud/FattureInCloudOAuthController.php`
- [X] T006 [US1] Build the complete native Settings experience in `app/Filament/Pages/FattureInCloudSettingsPage.php`, `resources/views/filament/pages/fatture-in-cloud-settings-page.blade.php`, and the `fatture_in_cloud` subtree of `resources/lang/it/assestme.php`
- [X] T007 [US1] Record the approved narrow remote-quote/VAT-boundary supersession and exact security/provider contract in `docs/adr/0001-product-boundary-and-language.md`, `docs/explanation/product-and-scope.md`, `docs/reference/domain-and-application-contracts.md`, and `docs/reference/testing-security-and-acceptance.md`
- [X] T008 [US1] Run focused Pint, PHPStan, and `tests/Feature/FattureInCloud/FattureInCloudConnectionTest.php`; fix failures and record exact V1 evidence in `docs/_meta/progress.md`

**Checkpoint**: The connection flow is usable from UI; no standalone service or disconnected settings migration remains.

---

## Phase 4: User Story 2 — Enter composer and resolve client (V2, Priority P2)

**Goal**: `Crea preventivo` passes the authoritative Workspace save guard, opens only for editable Assessments, and reaches a composer with one live exact FIC client mapping.

**Independent Test**: Component/feature tests prove dirty success and failure/conflict/read-only guards plus valid mapping, exact fiscal lookup, no fuzzy name match, explicit duplicate choice, and provider client creation.

- [X] T009 [US2] Add portable nullable opaque mapping columns/constraint in `database/migrations/2026_08_11_000022_add_fatture_in_cloud_mapping_to_clients_table.php`, expose typed model fields in `app/Models/Client.php`, and add migration/mapping coverage in `tests/Feature/FattureInCloud/FattureInCloudClientResolutionTest.php`
- [X] T010 [US2] Implement fiscal normalization, exact paginated lookup, live mapping validation, explicit candidate result, client creation payload, and atomic mapping in `app/Actions/FattureInCloud/ResolveFattureInCloudClient.php`, `app/Actions/FattureInCloud/MapFattureInCloudClient.php`, and supporting `app/Data/FattureInCloud/` DTOs
- [X] T011 [US2] Register the Assessment composer page in `app/Filament/Resources/Assessments/AssessmentResource.php` and create its minimal ready/client-resolution UI in `app/Filament/Resources/Assessments/Pages/CreateFattureInCloudQuote.php` and `resources/views/filament/resources/assessments/pages/create-fatture-in-cloud-quote.blade.php`
- [X] T012 [US2] Add visible editable-only `Crea preventivo` action to `app/Filament/Resources/Assessments/Pages/WorkspaceAssessment.php` using the existing pre-action persistence method and Italian states in `resources/lang/it/assestme.php`
- [X] T013 [US2] Complete compact client resolution/create and Workspace guard coverage in `tests/Feature/FattureInCloud/FattureInCloudClientResolutionTest.php` and `tests/Feature/FattureInCloud/FattureInCloudWorkspaceGuardTest.php`
- [X] T014 [US2] Run focused migration portability, Pint, PHPStan, client-resolution, composer-page, and Workspace-guard tests; fix failures and record exact V2 evidence in `docs/_meta/progress.md`

**Checkpoint**: Dirty Workspace → saved authoritative state → composer → live exact FIC client is demonstrable end to end.

---

## Phase 5: User Story 3 — Compose rows manually (V3, Priority P3)

**Goal**: The administrator manually builds transient groups/free rows, can place one Finding in multiple groups, and receives only compatible exact-price suggestions.

**Independent Test**: Dataset and component tests prove reference/marker generation, all assignment cardinalities, no `bundled` grouping, price rules, one group-to-row mapping, editable fields, and zero commercial persistence.

- [X] T015 [P] [US3] Add compact parametrized pure-logic coverage in `tests/Unit/FattureInCloud/FattureInCloudQuoteLogicTest.php` for `F-000042`, exact markers/versions, compatible sums, non-exact estimates, row mapping, and generated references
- [X] T016 [US3] Implement exact references/markers, compatible estimate suggestions, transient group/free-row DTO validation, and group-to-row projection in `app/Actions/FattureInCloud/` and `app/Data/FattureInCloud/`
- [X] T017 [US3] Extend the real composer in `app/Filament/Resources/Assessments/Pages/CreateFattureInCloudQuote.php` and its Blade/Filament schema to show Problem/solutions, add/remove/reorder manual groups/free rows, assign/remove/move Findings, permit repeated cross-group assignment, and edit title/description/price/quantity/measure/discount
- [X] T018 [US3] Add compact Livewire behavior and no-persistence assertions in `tests/Feature/FattureInCloud/FattureInCloudQuoteComposerTest.php`, run focused Pint/PHPStan/unit/component tests, fix failures, and record exact V3 evidence in `docs/_meta/progress.md`

**Checkpoint**: The composer is genuinely usable without POST and contains no automatic grouping or local commercial model.

---

## Phase 6: User Story 4 — Enrich rows from FIC catalogs (V4, Priority P4)

**Goal**: Rows optionally use live read-only products and current VAT types while every commercial value remains editable and AssestMe performs no tax calculation.

**Independent Test**: Feature/component tests prove paginated product search, product identity/code preservation, suggestions, default/override VAT, unavailable selection rejection, and zero provider/local catalog mutation.

- [X] T019 [US4] Add product/VAT response and validation cases to `tests/Feature/FattureInCloud/FattureInCloudQuoteComposerTest.php` using only `Http::fake`
- [X] T020 [US4] Add paginated read-only product search/detail validation and live enabled VAT lookup to the concrete services/DTOs in `app/Services/FattureInCloud/` and `app/Data/FattureInCloud/`
- [X] T021 [US4] Wire on-demand optional product selection, exact product ID/code retention, editable suggestions, configured default VAT, and per-row override into `app/Filament/Resources/Assessments/Pages/CreateFattureInCloudQuote.php` and its Italian UI/view
- [X] T022 [US4] Run focused Pint/PHPStan/composer tests, fix failures, prove no product/VAT writes or local tax totals, and record exact V4 evidence in `docs/_meta/progress.md`

**Checkpoint**: Products and VAT are live optional enrichments, not a new module or local tax engine.

---

## Phase 7: User Story 5 — Reuse previous remote version (V5, Priority P5)

**Goal**: Offer only the latest exact remote AssestMe quote version and reconstruct its rows transiently with exact current-Assessment Finding links.

**Independent Test**: One compact feature file proves full marker grammar, pagination/latest choice, detail load, valid `F-xxxxxx` membership, unmatched row preservation, and rejection of malformed/foreign/legacy guesses.

- [X] T023 [US5] Add exact marker/version/list/detail/reconciliation datasets in `tests/Feature/FattureInCloud/FattureInCloudPreviousVersionTest.php`
- [X] T024 [US5] Implement paginated quote-only discovery, anchored parsing/latest selection, detailed load, transient row reconstruction, and exact current-Assessment reference reconciliation in `app/Actions/FattureInCloud/FindPreviousFattureInCloudQuote.php` and supporting services/DTOs
- [X] T025 [US5] Add the Italian previous-version banner, `Carica precedente`, `Parti da zero`, unmatched-row state, and loading/error behavior to the composer page/view and `resources/lang/it/assestme.php`
- [X] T026 [US5] Run focused Pint/PHPStan/previous-version/component tests, fix failures, and record exact V5 evidence in `docs/_meta/progress.md`

**Checkpoint**: Remote history can seed the composer without any local version record or fuzzy/legacy relationship.

---

## Phase 8: User Story 6 — Create one reliable remote quote (V6, Priority P6)

**Goal**: Validate and create one FIC quote with reliable success, ordinary failure, and exact-marker ambiguous-outcome reconciliation.

**Independent Test**: Fake-provider tests prove exact payload/success ID, ordinary error, 401 refresh, 429 delay, timeout reconciliation, zero/multiple ambiguity, single-flight behavior, and zero commercial persistence; one Dusk happy path proves the full UI journey.

- [X] T027 [US6] Add compact final-create success/error/auth/rate/ambiguous datasets and payload assertions in `tests/Feature/FattureInCloud/FattureInCloudQuoteCreationTest.php`
- [X] T028 [US6] Implement final live validation, one-row-per-group mapping, `quote` POST, single-flight outcome DTOs, bounded status behavior, and exact-marker post-POST reconciliation without blind retry in `app/Actions/FattureInCloud/CreateFattureInCloudQuote.php` and concrete FIC services/DTOs
- [X] T029 [US6] Wire submit/disabled/success/error/ambiguous states and real provider ID/link display into `app/Filament/Resources/Assessments/Pages/CreateFattureInCloudQuote.php`, its Blade view, and `resources/lang/it/assestme.php`, retaining transient inputs on failure and storing no success history
- [X] T030 [US6] Complete and run focused creation/component/persistence tests, fix failures, and record exact V6 feature evidence in `docs/_meta/progress.md`
- [X] T031 [US6] Add the single fake-provider browser journey in `tests/Browser/FattureInCloudQuoteTest.php`, run it through `scripts/dusk-isolated.sh`, fix UI regressions, and record exact assertion evidence

**Checkpoint**: The real-shaped provider flow completes reliably from Workspace through confirmation with no local commercial persistence.

---

## Phase 9: Convergence and regression (V7)

**Purpose**: Remove regressions and prove current CI equivalence; this phase does not recover tests deliberately omitted from V1–V6.

- [ ] T032 Audit `app/`, `database/`, `resources/`, and `tests/` diffs for hidden commercial persistence, automatic grouping, invented endpoints, generic fake/provider infrastructure, untranslated strings, raw secret/error exposure, TODO/placeholders, and unconnected slice artifacts; correct each finding in its owning file
- [ ] T033 Consolidate only canonical decisions and evidence in `docs/explanation/product-and-scope.md`, `docs/reference/domain-and-application-contracts.md`, `docs/reference/ui-persistence-import-export.md`, `docs/reference/testing-security-and-acceptance.md`, `docs/_meta/discoveries.md`, and `docs/_meta/progress.md` without duplicating the feature spec
- [ ] T034 Run final working-tree review, `git diff --check`, touched-file Pint, application PHPStan, all focused FIC tests, and affected Workspace/settings/migration/deployment tests; fix and rerun failures
- [ ] T035 Re-read `.github/workflows/quality.yml` then reproduce `quality` with `RUN_DUSK=0 scripts/verify.sh` followed by the workflow's isolated complete Dusk command; fix and rerun the affected full job equivalent
- [ ] T036 Reproduce every current `database-compatibility` matrix entry and its configured migration/seed/capability/full-suite/integrity/diagnostics/dump-restore steps using disposable Docker services; fix and rerun affected entries
- [ ] T037 Reproduce `cloudpanel-release`: build archive, verify manifest/content/secrets, extract, complete SQLite installer/browser check, run scheduler and diagnostics, and verify optional FIC configuration never blocks install
- [ ] T038 Reproduce `clean-checkout-bootstrap` from a disposable clean checkout and verify diagnostics/singleton/canonical seed counts with no FIC credential/network requirement
- [ ] T039 Statically validate GitHub-only publish/deploy steps and execute applicable local release/deploy script gates without publishing a release or deploying; record GitHub-specific operations as `NOT_RUN locally`
- [ ] T040 Write the exact final preflight/spec/slice/provider/test/CI/failure/status evidence in `docs/_meta/progress.md`, `docs/_meta/discoveries.md`, and `docs/_meta/final-outcome.md`, marking every unexecuted real-provider/manual environment check `NOT VERIFIED`

---

## Dependencies and execution order

```text
T001 preflight
  -> US1/V1 connection
    -> US2/V2 guarded composer + client
      -> US3/V3 manual composition
        -> US4/V4 live product/VAT enrichment
          -> US5/V5 remote previous version
            -> US6/V6 create/reconcile
              -> V7 convergence/current CI equivalence
```

- US1 is the MVP and must converge before US2.
- US2 requires a usable US1 connection boundary.
- US3 requires the US2 composer/client context.
- US4 enriches the converged US3 row state.
- US5 reconstructs the same converged row state from remote detail.
- US6 consumes all earlier slices and cannot start while any prior checkpoint is incomplete.
- V7 begins only after all six story-specific focused suites are green.

## Parallel opportunities

Parallel work is deliberately narrow because the user requires slice convergence:

- In US1, T003 can proceed beside the initial T002 test fixture design; T004–T008 then converge sequentially.
- In US3, pure-logic tests T015 can be written independently before T016/T017 integration.
- Documentation research and non-overlapping Italian copy may be drafted in parallel only within the active slice, never by starting another story.
- Final CI matrix entries may run independently only after T035 passes and only if they use isolated databases/storage.

## Implementation strategy

### MVP first

Complete T001–T008. At that point the administrator has a fully usable, secure, exact-one-company FIC connection page even though quote creation is not yet exposed.

### Incremental vertical delivery

For each next story: write the compact decision-focused test surface, add minimum persistence only if the story needs it, implement typed behavior, connect the real UI, run focused success/failure checks, fix, record evidence, then advance.

### Definition of completion

- All T001–T040 are checked with factual evidence.
- Each story checkpoint is independently demonstrable.
- Current locally executable CI job equivalents are green.
- GitHub-only publication/deployment and real FIC/manual acceptance are accurately `NOT_RUN`/`NOT VERIFIED` unless evidence exists.

## Format validation

All 40 tasks use the required checkbox + sequential `Txxx` identifier format. Story-phase tasks carry `[US1]`–`[US6]`; `[P]` appears only where files and prerequisites permit work in parallel.
