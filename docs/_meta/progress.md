# Progress

## Current state

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

## Update rule

Record only factual work performed, the exact affected area, commands run, and results. Progress entries do not create or modify product requirements.
