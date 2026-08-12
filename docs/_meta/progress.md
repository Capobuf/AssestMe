# Progress

## Current state

- 2026-08-12: Started the focused `cloudpanel-release` installer CI stabilization on `develop` at
  `8bd1829409d1bec67694cc4f46853193f5e36fc8`. Scope is limited to path-based Dusk synchronization
  and failure diagnostics, failure-only safe artifact retention, server-side reporting of sanitized
  installer exceptions, and disabling Artisan development-server reload while the extracted release
  writes `.env`. The existing SQLite probe and installer business flow remain unchanged.
- 2026-08-12: Completed the focused `cloudpanel-release` installer CI stabilization. The database
  submit now waits for a real reload, identifies success only by `/install/administrator`, and emits
  URL, installer error, validation errors, screenshot, DOM, and available console evidence for a
  same-page failure or possible hang. A deliberately invalid symlinked SQLite path in an extracted
  release proved the diagnostic failure message and artifacts; a clean extracted-release run passed
  the database redirect and finalization before a later login mismatch specific to the remote
  Compose adaptation. The workflow now starts Artisan with `--no-reload` and uploads an allowlisted
  failure-only `cloudpanel-installer-diagnostics` artifact. Focused Pint and PHPStan passed, and 46
  focused PHP tests passed with 597 assertions. The aggregate quality portion passed 615 tests with
  5,396 assertions plus Canary, diagnostics, benchmark, PDF/XLSX, backup/restore, and storage audit;
  `CloudPanelInstallationTest` passed in both complete-gate attempts. The complete gate is not green:
  both 22-test Dusk runs ended with the unrelated existing five-second notification-removal timeout
  at `FilamentNavigationTest.php:191`, while its immediate isolated rerun passed with 70 assertions.
- 2026-08-11: Investigated Quality run `31506605494` for commit `5b520aa`.
  All 543 feature tests passed with 4,869 assertions; the sole quality-job failure
  was the stale `FilamentNavigationTest` expectation that `selected_assets` remained
  required and an empty selection produced a save error. The accepted D-045 v2 rule
  instead requires a visible non-required selector and a successful empty save, so
  the browser regression and revision evidence are being aligned with that explicit
  change of decision.
- 2026-08-11: Corrected the stale navigation regression to assert a visible optional
  asset selector, successful save, persisted `selected_assets` scope, and zero asset
  associations; the success notification is closed explicitly before logout. Focused
  Pint passed and isolated `FilamentNavigationTest` passed 1 Dusk test with 65
  assertions. No application fallback or behavior change was required.
- 2026-08-11: Started the explicitly approved Finding-scope revision on `develop`:
  asset association becomes optional for every Finding scope, including Findings
  copied from imported templates, and the requested Finding labels adopt `Stato e
  Report` / `Spiegazione del Problema` capitalization. Application, canonical
  contract, ADR, and focused success/failure coverage are in scope.
- 2026-08-11: Completed the optional Finding-asset revision across the Filament field,
  signed save validation, completeness/completion, incomplete filtering, report
  snapshots, imported-template copies, canonical contracts, and superseding ADR
  entries. Focused Pint passed; Workspace persistence, domain, template import/export,
  and Workspace page suites passed 71 tests before the broader command reached an
  unrelated local PDF-driver assertion (`dompdf` configured instead of required
  WeasyPrint); the two affected PDF scenarios then passed 2 tests with 7 assertions,
  focused PHPStan reported no errors, and isolated `WorkspaceResponsiveTest` passed
  5 Dusk tests with 193 assertions.
- 2026-08-11: Removed the stale local-only `LARAVEL_PDF_DRIVER=dompdf` override by
  restoring the required `weasyprint` value in the ignored `.env`; no Dompdf package
  is installed or required by Composer. After clearing configuration cache, the
  renderer contract test passed 1 test with 5 assertions. This host still has no
  executable `/usr/bin/weasyprint`, so real PDF generation was not re-claimed here.
- 2026-08-11: Started the GitHub Actions Node.js 20 deprecation cleanup on `develop`
  at `706c12ecc8cf0b8041d6455c31b4891e0249708a`. The failing `main` run was traced
  to newly published Composer advisories in the locked dependency graph, while the
  two concurrent `develop` runs were monitored separately for their application and
  installer failures. The workflow action runtime refresh is limited to the first
  Node.js 24 majors: checkout v5, upload-artifact v6, and download-artifact v7. The
  follow-up quality cleanup creates a private empty `.env` only for a clean isolated
  checkout and removes only the unchanged placeholder owned by that gate, preventing
  PHPUnit from repeating the same missing-environment-file warning for every test.
- 2026-08-11: Started the Filament-native main-navigation move from the desktop sidebar
  to top navigation on `develop` at `63052396b0e7271f572346040d2847f498bb8b77`;
  the existing dirty assessment-integrity and Google Drive sync work is preserved.
- 2026-08-11: Enabled native `Panel::topNavigation()` without sidebar options, custom
  shell code, CSS, JavaScript, or plugins. Focused navigation tests passed (15 tests,
  69 assertions), the navigation Dusk journey passed (1 test, 63 assertions), and
  isolated responsive geometry and scroll tests passed (2 tests, 168 assertions)
  across 2560/1920/1440/1280/1024/390-pixel viewports.
- 2026-08-11: Started the optional one-way Google Drive readable-sync vertical slice on
  `develop` at `63052396b0e7271f572346040d2847f498bb8b77`. Preflight confirmed the
  existing dirty assessment-integrity work is preserved, Spec Kit 0.16.1 is available,
  and the new feature uses `specs/002-google-drive-readable-sync`.
- 2026-08-11: Completed the initial Google Drive readable-sync application slice through OAuth,
  encrypted settings, remote-root handling, deterministic four-tab snapshot, verified evidence and
  generated-report copies, non-destructive ID-prefix reconciliation, per-assessment success/error
  state, 50-record chunks, file-cache locking, hourly Europe/Rome scheduling, CLI `--force`, and
  aggregate Filament status. `php artisan test tests/Feature/GoogleDrive` passed 36 tests and 194
  assertions with the one real-PDF independence scenario skipped because this host has no
  executable `/usr/bin/weasyprint`; focused Pint passed and focused application PHPStan reported no
  errors. This root/configuration design was superseded by the later addendum. Real Google OAuth,
  Drive, and Sheets remain NOT VERIFIED.
- 2026-08-11: Reopened Google Drive US1 after frontend review showed that absent environment values
  ended at an unavailable message. Added the convergence tasks for a UI-managed application setup;
  implementation added a native Filament configuration form, encrypted write-only values, optional
  environment defaults, safe reconnection on OAuth configuration changes, and a route-derived
  return to the real Filament page. This interim configuration was superseded by the addendum below.
- 2026-08-11: Applied the Google Drive addendum and replaced the earlier selector design. Spec Kit
  artifacts passed a read-only consistency analysis after removing selector tasks. The Settings page
  now embeds the six-step Google Cloud guide, persists only OAuth client ID/encrypted secret, derives
  a read-only copyable callback, creates a new `My Drive/AssestMe` root after OAuth, and exposes a
  connected-without-root retry state. Selector scripts/routes/controllers and key/project settings
  were removed. `php artisan test tests/Feature/GoogleDrive --stop-on-failure` passed 49 tests with
  290 assertions; the host-only real-PDF scenario skipped for the documented WeasyPrint prerequisite.
  Long/final verification and real Google OAuth/Drive/Sheets acceptance remain pending.
- 2026-08-11: Corrected real-provider findings after an administrator connected Google. Opaque parent
  IDs are now sent directly to the public Drive API for folders, native Sheets, and files instead of
  being interpreted as display paths; Sheet null cells are normalized to dense rows; successful root
  creation enables automatic sync by default; and the connected Settings state uses native Filament
  information/action components. A real forced run against the connected account completed with
  `Valutati: 3; Saltati: 0; Sincronizzati: 3; Non riusciti: 0`. The earlier ID-named folder and any
  orphaned remote objects were deliberately not deleted because the non-deletion contract remains in force.
- 2026-08-11: Completed the Google Drive correction on branch `develop` at HEAD
  `4ff675f944d484e291844628dd5b7e3ac1e2c1a9` while preserving the pre-existing shared dirty worktree.
  Focused Google feature coverage passed 53 tests with 306 assertions; the isolated connected and
  disconnected Settings browser journeys passed 2 tests with 22 assertions; focused Pint, application
  PHPStan, Composer validation, vendor-integrity, and diff-whitespace checks passed. The single final
  Docker `scripts/verify.sh` invocation passed: Pint 461 files, PHPStan 358 files, 540 application tests
  with 4,845 assertions, 36 strict Canary routes, the 50-Finding benchmark, storage audit with zero
  anomalies, diagnostics, Composer audit with no advisories, and 21 Dusk tests with 726 assertions.
  Real Drive/Sheets evidence remains bounded to the connected-account forced run (3 of 3 assessments);
  the administrator reported OAuth connection success, but the callback journey and complete manual
  provider lifecycle were not independently observed and remain `NOT VERIFIED`.
- 2026-08-11: A post-gate non-secret status check found the development database disconnected from
  Google (`account`, refresh credential, and root absent) while its OAuth client configuration,
  3 local assessments, and 3 successful per-assessment sync states remain present. No secret was
  logged or recoverable, so connection was not fabricated or bypassed: the administrator must use
  the normal interactive `Collega account Google` action once more. The successful callback will
  create the corrected managed root and enable automatic synchronization by default.
- 2026-08-11: Started the dedicated `codex/fix-p0-p1-assessment-integrity` change set
  from baseline `63052396b0e7271f572346040d2847f498bb8b77`; initialized official Spec Kit
  0.16.1 with Codex skills and one vertical three-story feature at
  `specs/001-assessment-integrity`. No application code has been changed yet.
- 2026-08-11: Phase 1 implemented one server-side pre-action persistence gate, atomic/idempotent Finding plus Evidence saves with compensation, and signed idempotent reorder. Focused Workspace/Persistence/Evidence/Architecture tests passed: 99 tests, 1,379 assertions.
- 2026-08-11: Phase 2 made the enabled `active_risk_profile_id` authoritative for new classifications and v1/v2 imports, retained historical IDs, replaced urgency code hardcodes with per-profile top-two ordering, preserved schema v1, and made schema v2 canonical for export. Custom-profile, schema, import/export, template, risk, dashboard, Workspace, and persistence focused suites passed after their test fixtures seeded the required active profile.
- 2026-08-11: Phase 3 replaced the eight-template demo with a schema-v2 MSP library of 221 templates across 17 existing categories. `SeedDataTest` passed 3 tests and 26 assertions, including two seeds and byte-identical deterministic export; the eight distributed external IDs remain present.
- 2026-08-11: Docker/Selenium Dusk proof `test_dirty_selection_autosave_and_version_conflict_use_the_signed_workspace_protocol` passed 1 test and 7 assertions, demonstrating edit → row click → server save → next selection plus conflict behavior.
- 2026-08-11: Final affected-suite preflight passed Pint, PHPStan across 336 files, 157 host tests with 1,728 assertions apart from the expected host-only non-executable WeasyPrint prerequisite, and 67 Docker report tests with 787 assertions using the production renderer.
- 2026-08-11: Docker acceptance passed a real credential-form administrator login and native Workspace journey (1 Dusk test, 61 assertions), the isolated 50-Finding application benchmark generated PDF/XLSX within every limit, and an isolated valid single-administrator installation completed backup creation, verification, restore diagnostics, and before/after marker verification.
- 2026-08-11: The one permitted `scripts/verify.sh` invocation passed Composer validation, Pint, PHPStan and 484 tests with 4,465 assertions before stopping on two failures: the Finding-template Canary fixture lacked seeded active-risk configuration, and an unrelated uncommitted Google Drive settings migration in the shared workspace was not rerunnable. The affected Canary test passed after seeding canonical domain configuration; the Google Drive slice is excluded from this change set and was not modified here.
- Documentation migration package prepared from AssestMe specification 2.7.
- Product, architecture, runtime, domain, UI/persistence, reporting/files, testing/security, hosting, and accepted decisions are mapped to canonical pages.
- D-001 through D-070 are represented exactly once across thematic ADRs.
- Existing general hosting, cPanel, CloudPanel, and CloudPanel acceptance documentation is included.
- The former plan remains recoverable exactly from the verified commit/blob recorded in `source-baseline.md`.
- 2026-08-02: CloudPanel GitHub Actions deployment integration is configured on `develop`: `Quality` has the gated `deploy_cloudpanel` job, the restricted server command and all five repository secrets are installed, and a direct forced-command deployment activated a release that passed `artisan about`. The GitHub Actions deployment job itself remains NOT VERIFIED because its queued run was cancelled to avoid a duplicate deployment while removing the obsolete release Git metadata check.
- 2026-08-02: Started a Dusk-only determinism repair on `develop` for `MilestoneZeroTest` and `WorkspaceResponsiveTest`: synchronize the Finding report toggle with Livewire state and applied version, and explicitly close the last-row action menu before the delete-menu flow. Production code is not in scope absent deterministic evidence.
- 2026-08-02: The isolated focused browser commands `scripts/dusk-isolated.sh "$PWD" --filter=MilestoneZeroTest` and `scripts/dusk-isolated.sh "$PWD" --filter=WorkspaceResponsiveTest` completed successfully; Pint passed for both changed test files. The isolated complete browser command started but cannot complete in this host because `ApprovedUxQaTest` requires an executable `/usr/bin/weasyprint`.
- 2026-08-02: Reproduced both reported failures in `docker/compose.dev.yml` with its Selenium service. The Milestone Zero callback had valid false state but timed out because Dusk only accepts callbacks beginning with `return`; its callback now begins with an IIFE return. The responsive action-menu Escape event is sent through the focused WebDriver target, avoiding Chrome's non-interactable element check after a flipped panel. The two focused browser commands and the complete Dusk suite completed in the compose runtime, where `/usr/bin/weasyprint` is executable.

## Implementation state inherited from specification 2.7

- D-063–D-070 release, installer, and multi-database scope: implemented and passed the complete local automated gate recorded by the source plan.
- Real CloudPanel acceptance: NOT VERIFIED.
- Physical Edge, Firefox, iOS Safari, and Android Chrome checklist: NOT VERIFIED.
- Global final product acceptance: open.

- 2026-08-11: Completed the Finding contextual-properties default-state adjustment. The five lateral
  sections remain collapsible but now render open initially. Focused Pint passed, and the isolated
  `FilamentNavigationTest` browser journey passed 1 test with 66 assertions, including the rendered
  expanded state of all five sections.

- 2026-08-11: Started Spec Kit feature `003-learn-finding-templates` on `develop` from HEAD
  `1ba329470f151cb0adf7a0a12c84be1c3487dc15`. The scoped preflight confirmed PHP 8.3.6,
  Laravel 13.19.0, Filament 5.6.8, Livewire 4.3.3, and preserved the pre-existing uncommitted
  contextual-properties changes while beginning the Finding-to-template learning vertical slice.

- 2026-08-11: Implemented the Finding-template learning domain and Workspace slice through a
  forward-only lineage fingerprint migration, canonical/semantic projection, exact and conservative
  similar detection, authoritative external-ID mapping, atomic create/link/full-replacement actions,
  stale/legacy/deleted/read-only guards, localized compact Filament previews, and D-073. Focused
  dedicated coverage passed 14 tests with 71 assertions; the two focused Workspace action tests
  passed with 30 assertions. Full focused suites and the authoritative final gate remain pending.

- 2026-08-11: Hardened the complete verification entry point after a direct host invocation exposed
  the host's missing executable `/usr/bin/weasyprint`. `scripts/verify.sh` now rejects host and
  unmarked-container execution before Composer or application work; `docker/compose.dev.yml`, CI,
  AGENTS.md, canonical verification docs, and D-074 use the same marked `app` service invocation.
  The host-refusal proof returned exit 1 in 0.1 seconds, and the focused Docker deployment contract
  passed 19 tests with 350 assertions.

- 2026-08-11: The single authoritative final invocation
  `docker compose -f docker/compose.dev.yml exec -T app scripts/verify.sh` completed with exit 0.
  Composer validation and audit, Pint over 469 files, PHPStan over 365 files, 566 application tests
  with 5,030 assertions, 36 strict Canary routes, diagnostics, the isolated 50-Finding benchmark,
  and storage audit with zero anomalies passed. The same gate also completed a real credential-form
  administrator login and browser acceptance through 21 Dusk tests with 729 assertions.

- 2026-08-11: Final feature audit completed all 38 Spec Kit tasks. The clean whitespace check,
  acceptance-scenario review, migration/lineage guard coverage, and CI/runtime contract review found
  no remaining local defect. The authorized publication scope also includes the inherited
  contextual-properties default-open implementation and its browser regression coverage.

## Update rule

Record only factual work performed, the exact affected area, commands run, and results. Progress entries do not create or modify product requirements.

- 2026-08-12: Started the approved feature 004 composer UI refinement on `develop` at HEAD
  `d02539c4acd7beb258d5177af434070704d016ad`. Preflight confirmed PHP 8.3.6, Composer
  2.7.1, the locked Laravel/Filament/Livewire stack, and pre-existing unrelated branding changes in
  the working tree. Relevant Accepted ADRs and the canonical UI/transient-composer contract were
  compatible with the requested three-area workbench; the minimal Spec Kit delta was recorded in
  the existing `specs/004-fatture-in-cloud-quotes/` artifacts. Focused implementation evidence is
  pending.

- 2026-08-12: Completed feature 004 composer UI refinement without provider-payload or commercial
  persistence changes. The page now uses the responsive three-area Finding/composer/summary
  workbench, `Riga da finding`/`Riga libera` terminology, compact client/previous-version context,
  transient Finding search and linked counts, product-first editable rows, collapsible Problem/
  solution context and Finding assignments, one final create CTA, and a pure display-only
  VAT-excluded net-row total. Browser feedback exposed a lower legacy CSS rule that kept the summary
  below the editor and numeric-string checkbox hydration that left the derived linked count stale;
  both were corrected and covered. Focused Pint passed on five files, PHPStan passed on both changed
  application classes, all FIC unit/feature tests passed 46 tests with 221 assertions, and the
  isolated FIC Dusk journey passed 1 test with 15 assertions across desktop three-column geometry,
  transient total/count updates, light/dark variables, confirmed provider ID/link, and 390 px no-
  overflow behavior. The final normative `scripts/verify.sh` invocation exited 0: Composer validation
  and audit, Pint on 509 files, PHPStan on 395 files, 615 application tests with 5,359 assertions,
  strict Canary on 38 routes, diagnostics, the 50-Finding benchmark, real PDF/XLSX, backup/restore,
  zero-anomaly storage audit, and 22 Dusk tests with 748 assertions all passed.

- 2026-08-12: Started application-branding integration from the two supplied root SVG variants.
  Preflight confirmed the SVGs are valid two-color vector artwork: the Black variant is suitable for
  light surfaces and the White variant for dark surfaces. The affected surfaces are the Filament
  panel/login and the dark installer header; focused contrast, asset, and rendering checks are pending.
- 2026-08-12: Completed contrast-aware application branding and centered desktop top navigation
  without vendor changes. The responsive SVG assets use cropped view boxes, Filament switches the
  Black/White variants for light/dark themes, the installer uses the White variant on its dark header,
  and the release-root proof includes both files. Focused Pint and PHPStan passed; 35 PHP tests passed
  with 255 assertions; light/dark browser inspection confirmed the expected visible 56.27 × 36 px
  logo with no page errors; and the isolated navigation Dusk test passed on its required rerun with
  70 assertions after the live UI review exposed and removed Filament's residual 8 px block margin,
  including exact horizontal and vertical centering plus unchanged responsive navigation. The
  complete gate is pending.

- 2026-08-11: Started Spec Kit feature `004-fatture-in-cloud-quotes` on `develop` from HEAD
  `653be43f743ac483ce047e0e18aeb0dc4388daa6`. Preflight found a clean working tree, PHP
  8.3.6, and healthy normative Compose services. The provider contract was checked against the
  official Fatture in Cloud API v2 documentation and OpenAPI repository at
  `2be805afd6667b00b4f1d09b6833472e8eb09d7c`; specification iteration 1 passed all 16 quality
  checks with no unresolved clarification.
- 2026-08-11: Provider research corrected the disconnect contract before planning: the current FIC
  OAuth and OpenAPI sources expose authorization-code exchange and refresh at `/oauth/token` but no
  remote revocation endpoint. Disconnect will therefore erase all local authorization and explain
  provider-side revocation instead of inventing an API call.
- 2026-08-11: V1 completed the optional authenticated Fatture in Cloud Settings slice: encrypted
  write-only OAuth credentials and token rotation, exact state/callback/scopes, exactly-one-company
  adoption, live enabled VAT default, verification/reconnect, and local disconnect. D-014 v2,
  D-039 v2, and D-075 record the narrow transient remote-quote boundary. Focused Pint passed on 13
  files, PHPStan passed on 10 application paths, and
  `FattureInCloudConnectionTest` passed 10 tests with 77 assertions in the normative app container.
- 2026-08-11: V2 added the portable opaque Client mapping, live mapping validation, paginated exact
  VAT-number/tax-code resolution without name matching, explicit duplicate choice, provider client
  creation, draft-only composer route, and the Workspace pre-action persistence guard. Focused Pint
  and application PHPStan passed; all FIC suites passed 17 tests with 99 assertions.
- 2026-08-11: V3 completed the transient manual composer, exact Finding references/markers,
  repeated cross-group assignments, free rows, row ordering, and compatible exact-price suggestions.
  V4 added paginated read-only products, editable product suggestions, and enabled live VAT choices.
  Their focused logic/component checks passed 6 tests with 24 assertions; PHPStan passed.
- 2026-08-11: V5 completed paginated exact-marker discovery, greatest-version selection, typed detail
  reconstruction, exact current-Assessment `F-xxxxxx` reconciliation, and unmatched-row UI. Its
  focused suite passed 3 tests with 12 assertions and PHPStan passed across 21 FIC application files.
- 2026-08-11: V6 completed live client/Finding/product/VAT validation, one-item-per-row quote
  creation, single-flight UI, bounded 401/429/ordinary errors, and exact-marker reconciliation after
  ambiguous POST transport failure without blind retry. Creation/component coverage passed 10 tests
  with 31 assertions. The first focused Dusk run exposed a selector mismatch and the second exposed
  the wrong Filament Section slot; after both focused fixes, the isolated fake-provider journey
  passed 1 browser test with 6 assertions, including dirty Finding persistence and real-shaped remote
  document ID/link confirmation.
- 2026-08-11: Real-provider diagnosis for LP Distribuzione replaced invalid free-text and rejected
  compound client filters with separate exact `vat_number`/`tax_code` queries, added the required
  read scopes and an authorization-scope version guard, and proved one unique live VAT match without
  creating or editing provider data. The focused FIC suite passed 42 tests with 193 assertions.
- 2026-08-11: Repaired the quote-composer presentation after browser inspection proved that custom
  utility classes were absent from the shipped Filament stylesheet and raw `fi-input` elements had
  no supported wrapper. The composer now uses application-owned responsive layout classes and native
  Filament input, select, checkbox, and icon-button components. Desktop light/dark and 390 px browser
  checks showed structured grids, no horizontal overflow, and no browser errors; the isolated FIC
  Dusk journey passed 1 test with 8 assertions, including computed-layout regression assertions.
- 2026-08-11: Diagnosed the first live quote rejection without retrying the creating POST; an exact
  marker lookup confirmed no remote document was created. Quote creation now repeats the live
  client's name and available fiscal identifiers beside its provider ID, as required by the provider
  document contract. The corrected payload passed the provider's non-creating totals validation with
  HTTP 200 and no invalid fields. Ordinary 4xx rejections now expose a corrective local message and
  log only bounded status/code/field-path metadata. Focused Pint and PHPStan passed, and all FIC
  feature tests passed 39 tests with 186 assertions.
- 2026-08-12: Started the second focused refinement of the feature 004 Fatture in Cloud composer
  from browser annotations on `develop` at `d02539c4acd7beb258d5177af434070704d016ad`. Scope is
  limited to transient composer copy/layout, resolved-client presentation, provider-derived editable
  measure suggestions, and deterministic single-Finding title/description prefilling; no provider
  write contract, persistence, migration, or dependency change is authorized.
- 2026-08-12: Completed feature 004 annotated composer refinement T033. The page now uses the
  approved title/breadcrumb/section/summary/action copy, a prominent resolved-client icon, accessible
  placeholder-only Finding search, a proportional five-control commercial row at 1440 px, `U.M.`/
  `€` suffixes, and editable measure suggestions derived from the existing detailed product list.
  Selecting exactly one Finding prefills editable title and Problem plus its exact visible reference;
  multiple Findings leave the title blank and retain sorted references, while removing all Findings
  clears generated content. Focused Pint passed on 5 files; focused PHPStan passed on 2 application
  files; all FIC unit/feature tests passed 46 tests with 238 assertions; isolated FIC Dusk passed 1
  test with 25 assertions, including 3-column workspace, 5-column commercial row, title/description
  prefill, prominent client icon, accessible search, light/dark, and 390 px no-overflow behavior.
  The final normative `scripts/verify.sh` exited 0: Composer validation/audit, Pint on 509 files,
  PHPStan on 395 files, 615 application tests with 5,376 assertions, strict Canary on 38 routes,
  diagnostics, 50-Finding benchmark, real PDF/XLSX generation, backup/restore, storage audit, and 22
  Dusk tests with 758 assertions all passed. Resource and published CSS are byte-identical; no
  migration, dependency lock, commercial persistence, or provider-write payload changed.
- 2026-08-12: Started a focused settings sub-navigation refinement from browser feedback on
  `develop` at `72a50a6b30cc008f5ec2e6dc5fd563d2fc9a9504`. The current Filament tabs were verified at
  1280 px with a 1,216 px viewport box, 1,345 px scrollable content, one row, and native
  `overflow-x: auto`; scope is limited to eliminating that horizontal scrollbar without changing
  settings routes or mobile navigation.
- 2026-08-12: Completed the settings sub-navigation refinement. At 1,200 px and wider, compact tabs
  remain on one line with no horizontal overflow; between the Filament mobile breakpoint and 1,200
  px, the existing native settings selector is shown instead of squeezing or scrolling the tabs.
  Browser inspection confirmed one row and zero overflow at 1,280 px in light/dark themes, the
  native selector and zero page overflow at 1,024 px and 390 px, and no page error overlay. Focused
  Pint passed, and isolated `FilamentNavigationTest` passed with 79 assertions. The complete gate is
  pending.
- 2026-08-12: Started the annotated navigation hierarchy refinement requested after the single-row
  settings review. Google Drive and Fatture in Cloud are moving from the flat Settings list into an
  `Integrazioni` Filament cluster nested under `Impostazioni`, with start-positioned native
  sub-navigation and settings content on the right. Per
  explicit instruction, only focused automated checks will run before handoff; the complete gate
  must wait for the user's visual approval and explicit go-ahead.
- 2026-08-12: Completed the focused implementation for visual review. `Integrazioni` is a native
  Filament cluster nested under the Settings tab bar, its Fatture in Cloud and Google Drive pages
  keep that parent bar visible, and their native child navigation remains at the left of the
  settings content. Application-owned route composition and parent component de-duplication contain
  Filament's nested-discovery behavior without vendor changes. The general Evidence form now shows
  `Dimensione Massima File` with a fixed `Mb` suffix; a conditional settings migration changes only
  the legacy 25/250 defaults to 5/100 and preserves customized limits. Focused Pint and PHPStan
  passed; 43 navigation/integration feature tests passed with 259 assertions; 8 settings tests
  passed with 122 assertions; and isolated `FilamentNavigationTest` passed with 87 assertions,
  including the retained parent tabs, active `Integrazioni` state, and left/content positioning.
  The migration was applied to the development application. Visual acceptance and the complete
  gate remain pending the user's review and explicit approval.
- 2026-08-12: Follow-up visual feedback aligned the assessment evidence limit with the single-file
  control: its label is now `Dimensione Massima Assessment` and its fixed field suffix is `Mb`.
  The configured 100 default and existing validation contract are unchanged; the complete gate
  remains deferred pending explicit approval.
- 2026-08-12: Started a focused Diagnostics presentation repair after browser review showed that its
  uncompiled utility classes left runtime data and the complete check table visually unstructured.
  Scope is limited to application-owned responsive layout and native Filament presentation; no
  diagnostic check, result, or execution behavior changes. The complete gate remains deferred.
- 2026-08-12: Completed the focused Diagnostics presentation repair for visual review. Runtime and
  database information now use a native Filament section with three responsive summary cards; all
  diagnostic checks remain present in a structured table with native status badges and an internal
  horizontal scroll area on narrow screens. Application-owned light/dark styles replace the
  previously unshipped utility classes. Focused Pint and diff checks passed; the normative-container
  diagnostics suite passed 7 tests with 82 assertions; and isolated desktop/mobile Dusk coverage
  passed 1 test with 16 assertions, including responsive summary columns, complete status badges,
  internal table containment, and no document-level horizontal overflow. Resource and published CSS
  are byte-identical. Visual acceptance and the complete gate remain pending the user's review and
  explicit approval.
- 2026-08-12: Started the approved dashboard replacement on `develop`. Scope is limited to a
  Filament operational homepage with resume-work context, four launchers, recent companies, latest
  assessments, real attention counts, and compact existing system health. The implementation adds
  no domain state, analytics, score, chart, dependency, migration, or workspace/report change.
- 2026-08-12: Started the requested focused dashboard visual revision after real desktop/mobile
  review. Scope is limited to localizing the hero glow behind a masked neutral logo, removing
  dashboard attention/system presentation, and replacing it with real counts and links for the
  existing company, site, asset, and Finding-template archive entities. Operational services and
  their dedicated product pages remain unchanged.
- 2026-08-12: Completed the focused operational-dashboard replacement and visual revision. The
  homepage now provides deterministic resume context, four primary launchers, recent companies,
  the five latest assessments, and a four-entry archive backed by real Client, Site, Asset, and
  FindingTemplate counts and index routes. The hero uses an anthracite base, a localized low-alpha
  lime glow, and the real neutral logo at 0.045 desktop / 0.035 mobile opacity with intersecting
  edge masks; the former attention and system-health dashboard sections are absent, while their
  dedicated product behavior remains untouched. Focused Pint and PHPStan passed; the dashboard and
  risk-profile feature tests passed 16 tests with 74 assertions; isolated dashboard Dusk passed 1
  test with 27 assertions. Authenticated browser verification at 1440x900 and 390x844 confirmed
  four/one archive columns, no document overflow or application error overlay, no console errors,
  and zero scoped Axe violations. Resource and published dashboard CSS are byte-identical. The
  normative complete gate failed in the application suite after 619 passed tests and 5,412
  assertions because the pre-existing in-progress Integrations navigation route
  `filament.admin.integrations` redirected the authorized Canary request with HTTP 302; the later
  diagnostics, benchmark, storage audit, route/about, and complete Dusk phases were therefore not
  run. That unrelated navigation work was not modified under the dashboard-only scope.
- 2026-08-12: Before publishing the complete queued worktree, replaced the redirect-only
  Integrations cluster landing route with a direct link to its first real settings page. This keeps
  both integration pages inside the nested Filament navigation while removing a synthetic HTTP 302
  target from strict authorization sweeps. Focused Pint and PHPStan passed, and the combined strict
  Canary plus navigation suite passed 18 tests with 90 assertions. The complete exact-fingerprint
  verification and the GitHub workflow-equivalent browser run follow this repair before publication.
- 2026-08-12: Started the annotated dashboard copy refinement. Scope is limited to the seven
  selected hero, launcher, panel-heading, and latest-assessment link labels; dashboard behavior,
  data, routes, and layout remain unchanged.
- 2026-08-12: Completed the seven annotated dashboard copy changes. Authenticated browser
  inspection confirmed `Riprendi il Lavoro`, `Apri Workspace`, `Nuovo Assessment`, both requested
  `Ultimi Assessment` labels, `Aziende Recenti`, and `Altro` only on the latest-assessment panel;
  the company-panel link remains `Vedi tutto`. Focused Pint passed, dashboard feature coverage
  passed 10 tests with 46 assertions, and isolated responsive dashboard Dusk passed 1 test with 27
  assertions. The 1280 px browser viewport had no document-level horizontal overflow.
- 2026-08-12: Started the extracted-release installer finalization investigation. Scope is limited
  to sanitized phase markers, deterministic release-server lifecycle diagnostics, equivalent HTTP
  and Dusk runs against separate fresh ZIP extractions, and a structural final-submit assertion;
  application finalization behavior remains unchanged until the failing phase is demonstrated.
- 2026-08-12: Added sanitized finalization and Artisan phase markers, throwable reporting, a
  structural Dusk final-submit outcome classifier, deterministic PID/port/state diagnostics, and
  independent HTTP/Dusk extraction paths in the CloudPanel release job. A fresh extracted ZIP
  passed the maintained SQLite HTTP smoke with the same `--no-reload` startup. A separate fresh
  Dusk extraction reached `installer.finalize.complete` with the server alive and listening, and
  the complete browser journey passed after removing Compose DB/backup overrides from the release
  server environment. The final same-ZIP matrix passed the HTTP smoke, one Dusk test with 28
  assertions including real login, `schedule:run`, and `assestme:diagnose --json`; the focused
  installer/action/workflow suite passed 22 tests with 195 assertions, Pint passed, PHPStan passed,
  workflow YAML parsed, and the shell script passed `bash -n`.
  The earlier GitHub listener loss was not reproduced, so its root cause remains undetermined
  pending evidence from the instrumented workflow.
- 2026-08-12: Started the requested hard-termination investigation for the extracted-release
  installer. Scope is limited to a same-run historical A/B between `c8bfab8` and current `develop`,
  exact `artisan serve` supervisor/listener ownership and exit evidence, sanitized runtime facts,
  and independent fresh release state. No installer, database-probe, migration, finalization,
  timeout, retry, or restart behavior will change without a demonstrated root cause.
- 2026-08-12: Completed the neutral same-run release diagnostic harness. The CloudPanel job now
  builds `c8bfab8` and current `develop` with the same runner/toolchain, validates their installer
  route/form contract, installs independent fresh ZIP extractions, and conditionally builds
  `d02539` only when the known-good/current boundary requires it. The real socket owner is resolved
  with `ss`; `strace` records separate Artisan supervisor and PHP listener exit/signal evidence;
  sanitized process trees, runtime facts, state files, last marker, and a GitHub summary table are
  emitted. A passing current HTTP run is repeated only twice, for three total fresh executions.
  No application installer behavior, timeout, retry, restart, probe, migration, or finalization
  logic changed. Focused container checks passed 26 tests with 432 assertions. The complete
  normative gate passed 622 application tests with 5,456 assertions, 38/38 strict Canary routes,
  the 50-Finding benchmark, diagnostics, audit and storage checks, and 23 Dusk tests with 785
  assertions. The historical GitHub-run evidence remains pending; root cause is not yet determined.
- 2026-08-12: The first published historical harness run stopped before either HTTP smoke because
  the Ubuntu runner did not provide `rg`, which the form-contract preflight had used without
  declaring it. Replaced that preflight-only search with portable recursive extended `grep` and
  added a workflow contract assertion. This was a harness correction only; the run produced no
  application termination evidence and did not change installer behavior.
- 2026-08-12: A container reproduction of the published historical harness completed installation
  but exposed a cleanup-only wait: after the Artisan supervisor stopped, its still-listening PHP
  child was reparented to PID 1, so the stale-parent safety check did not terminate it and `strace`
  could not finish. Cleanup now accepts the exact PID still returned as owner of the requested port,
  preserving the PID safety boundary without adding a timeout, retry, or server restart.
