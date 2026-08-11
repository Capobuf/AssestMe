# Validation Quickstart: Fatture in Cloud Quotes

## Prerequisites

- Run inside the normative `app` service from `docker/compose.dev.yml`.
- Do not configure real FIC credentials; automated checks use Laravel HTTP fakes.
- Keep the integration optional: a fresh install must work with all FIC settings null.

## Focused vertical checks

After each slice, format only touched PHP files and run its compact tests:

```bash
docker compose -f docker/compose.dev.yml exec -T app vendor/bin/pint --test <touched-php-paths>
docker compose -f docker/compose.dev.yml exec -T app php artisan test tests/Unit/FattureInCloud tests/Feature/FattureInCloud --stop-on-failure
docker compose -f docker/compose.dev.yml exec -T app vendor/bin/phpstan analyse --memory-limit=1G app/Actions/FattureInCloud app/Data/FattureInCloud app/Services/FattureInCloud app/Settings/FattureInCloudSettings.php app/Filament/Pages/FattureInCloudSettingsPage.php app/Filament/Resources/Assessments/Pages/CreateFattureInCloudQuote.php
```

Expected outcome: every focused test passes; no real network request occurs; no secret appears in output.

## Required behavior evidence

1. Configuration tests prove write-only encrypted secrets, exact-one company acceptance/rejection, token refresh, and default VAT persistence.
2. Client tests prove valid mapping reuse, exact VAT/tax-code lookup without name fallback, explicit ambiguity, and create/map behavior.
3. Pure/composer datasets prove `42 -> F-000042`, exact marker parsing, manual many-to-many assignments, no `bundled` auto-grouping, compatible exact sums only, one group-to-row mapping, and generated references.
4. Version tests prove exact latest selection, detail loading, current-Assessment reference reconciliation, and no fuzzy/legacy guessing.
5. Create tests prove success, ordinary error, `Retry-After`, auth refresh where applicable, and ambiguous POST reconciliation without blind repeat.
6. Workspace component tests prove the composer action does not bypass dirty save, conflict, error, or read-only guards.
7. Persistence assertions prove settings/mapping are the only new local state and no commercial rows/history exist after abandon or success.

## One isolated browser journey

```bash
docker compose -f docker/compose.dev.yml --profile browser exec -T \
  -e ASSESTME_TEST_ROOT=/tmp/assestme-fic-dusk \
  -e ASSESTME_TEST_ISOLATED=1 \
  app scripts/dusk-isolated.sh --filter=FattureInCloudQuoteTest
```

Expected journey: dirty Workspace -> `Crea preventivo` -> confirmed save -> composer -> resolved fake client -> select Finding -> manual group -> edit row -> fake submit -> real-shaped success confirmation.

## Final CI equivalence

Re-read `.github/workflows/quality.yml` immediately before running. As of planning, execute in this order:

1. Working-tree review, Pint, PHPStan, focused FIC tests.
2. `docker compose -f docker/compose.dev.yml --profile browser exec -T -e RUN_DUSK=0 app scripts/verify.sh`.
3. `scripts/dusk-isolated.sh` in the workflow's isolated browser root.
4. Database matrix equivalents for MySQL 8.0.46, MySQL 8.4.11, MariaDB 11.8.6, MariaDB 12.3.2, including migration/capability, configured full suite, integrity, diagnostics, and real dump/restore round trip.
5. Build/validate/extract/install the CloudPanel release, run installer Dusk if still configured, `schedule:run`, and diagnostics.
6. Run `scripts/bootstrap-local.sh` from a disposable clean checkout and verify canonical seed counts/diagnostics.
7. Statically verify GitHub-only publish/deploy steps and run applicable underlying local scripts; do not publish a GitHub release or claim real CloudPanel deployment.

Record exact commands, exit status, test/assertion counts, failures/root causes/fixes, and `NOT_RUN` GitHub-specific steps. Never convert unexecuted work into PASS.

## Optional real-provider acceptance

Real OAuth/FIC acceptance requires administrator credentials and provider-side changes, so it is `NOT VERIFIED` unless explicitly performed. If later authorized, verify HTTPS callback equality, exact-one company, client create/map, live catalogs, one quote creation, returned link, and provider-side authorization removal without recording secrets.
