# Progress

## Current state

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
