# Quickstart Validation: Assessment Integrity and MSP Baseline

Run each story as an independent checkpoint from the repository root. Use the current PHP/Composer
runtime and existing isolated test helpers; reserve the canonical application path for final polish.

## User Story 1 checkpoint

1. Run focused Workspace, Evidence, report snapshot, and reorder feature tests.
2. Demonstrate dirty Finding → another Finding/new Finding: the title is persisted before context
   changes.
3. Demonstrate invalid Finding → protected mutation/report/completion: no dependent row, file, or
   lifecycle change occurs.
4. Save two Evidence files under one request and verify one version increment; replay the request
   and verify no duplicate rows/files.
5. Run the focused Dusk protected-action scenario.

## User Story 2 checkpoint

1. Create a second enabled profile using `watch`, `attention`, `urgent_now`, and `emergency` priority
   codes, make it active, and leave the default profile's `is_default` unchanged.
2. Run focused risk/editor/template/dashboard tests and confirm old Findings retain old IDs.
3. Import a v1 fixture, import a v2 custom-code fixture, export v2 twice, and compare normalized
   documents.
4. Verify unknown code and matrix mismatch errors contain item context.

## User Story 3 checkpoint

1. Validate the baseline against v2 and the semantic importer.
2. Seed twice and compare template/solution counts and external IDs.
3. Export and re-import; compare normalized output.
4. Run editorial/category/risk/recommendation coverage tests and report the final template count.

## Final evidence

1. Run Pint, PHPStan, and all affected tests.
2. Start `docker/compose.dev.yml`; verify a real login and real PDF/XLSX generation and validation.
3. Execute and verify backup/restore according to the existing operational guide.
4. Run the current canonical application path exactly once and record its exact result.
