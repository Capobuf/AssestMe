# Quickstart Validation: Google Drive Readable Sync

## Prerequisites

- PHP 8.3+ and repository dependencies installed.
- A disposable test database/storage environment.
- For manual provider acceptance only: Google OAuth Web application client ID/secret and a normal
  user My Drive account. Never use production credentials in automated tests or evidence.

## Automated focused validation

```bash
php artisan test tests/Feature/GoogleDrive
vendor/bin/pint --test app/Console/Commands/GoogleDriveSyncCommand.php app/Data/GoogleDrive app/Filament/Pages/GoogleDriveSettingsPage.php app/Http/Controllers/GoogleDrive app/Models/AssessmentGoogleDriveSync.php app/Services/GoogleDrive tests/Feature/GoogleDrive
vendor/bin/phpstan analyse app/Console/Commands/GoogleDriveSyncCommand.php app/Data/GoogleDrive app/Filament/Pages/GoogleDriveSettingsPage.php app/Http/Controllers/GoogleDrive app/Models/AssessmentGoogleDriveSync.php app/Services/GoogleDrive --memory-limit=1G
composer validate --strict
composer audit --locked
```

Expected: all Google tests use fakes/mocks, no real provider call occurs, and no token appears in
captured output or logs.

## Required automated scenarios

1. Missing installation config renders the editable Google application form and local saves/reports pass.
2. Saving valid values with no `GOOGLE_*` environment values enables connection while stored secrets remain absent from browser output.
3. OAuth redirect is authenticated, stateful, offline, and `drive.file`-only.
4. Callback success encrypts the refresh token; denial/state/provider/missing-token cases preserve
   prior valid state and disclose no secret.
5. Callback success creates a new `My Drive/AssestMe` root and persists only the Google-returned ID;
   simulated creation failure remains visible and retryable without enabling sync.
6. No Picker script, token/root endpoint, API key, project number, or arbitrary folder selection is present.
7. A realistic dataset creates the hierarchy, one native four-tab Sheet, report copies, one file
   evidence copy, and one URL reference.
8. Local and simulated remote edits are followed by forced sync; managed rows equal local values.
9. Unchanged hash causes zero Google calls; changed local content causes sync.
10. Provider/local-file failure retains prior successful hash and records error; retry clears error.
11. Unrelated remote files and user-added tabs remain present.
12. Manual/scheduler overlap acquires only one lock owner.

## Complete repository gate

Use the current canonical application path after the coherent feature and documentation are complete:

```bash
scripts/test-app.sh
```

## Manual real-Google acceptance

1. On a disposable HTTPS installation, open Google Drive Settings, follow the embedded guide, copy
   the calculated callback URI into an OAuth Web application client, then enter only its client ID
   and secret. Optional environment defaults may provide those two values but are not required.
2. Save, confirm the connect action appears without the secret being rendered, and connect a
   disposable Google account from the same Settings page.
3. Confirm the consent request uses only `drive.file` and callback success creates a new
   `My Drive/AssestMe` folder without any folder selector. When root creation succeeds, automatic
   synchronization is active by default and can be disabled from the native status/action panel.
4. Synchronize the realistic dataset and inspect names, hierarchy, Sheet tabs/rows, links, PDF/XLSX
   hashes, and evidence files.
5. Edit a managed Sheet cell and a local Finding; synchronize and confirm local content wins.
6. Add an unrelated file and extra tab; synchronize and confirm both remain.
7. Revoke authorization; confirm actionable error, local editing/report operation, then reconnect/retry.
8. Disconnect and confirm remote content remains.

Record each exercised boundary precisely. The 2026-08-11 connected-environment correction provides
the following bounded evidence, not the complete eight-step manual procedure:

```text
Google OAuth VERIFIED: USER-REPORTED connection succeeded; callback journey not independently observed
real Google Drive VERIFIED: forced synchronization completed for 3/3 assessments
real Google Sheets VERIFIED: forced synchronization completed for 3/3 assessments after dense-row correction
complete manual lifecycle VERIFIED: NOT VERIFIED
```
