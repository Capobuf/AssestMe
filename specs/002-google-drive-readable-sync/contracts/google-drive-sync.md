# Contracts: Google Drive Readable Sync

## Authenticated web routes

All routes use the existing authenticated administrator session and CSRF protection where mutating.
Unauthenticated requests redirect to `/admin/login`; no endpoint returns the refresh token.

### Save Google application configuration

- Surface: Filament Settings form at `/admin/settings/google-drive`.
- Input: client ID and optional replacement client secret only.
- Secrets are write-only: blank retains the existing stored value or optional environment default;
  no stored secret is hydrated into browser form state.
- The callback URI is calculated from the named callback route, displayed read-only, and copyable.
- Changing effective OAuth client ID or secret clears the prior account credential and root and
  disables sync.
- Success: encrypted settings are persisted and the connect action becomes available when complete.
- Failure: field-level Italian validation; existing configuration and connection remain unchanged.

### Start OAuth

`GET /admin/settings/google-drive/connect`

- Preconditions: effective Google application config complete from Settings or optional environment defaults.
- Result: stateful Socialite redirect with exactly `drive.file`, offline access, and consent prompt.
- Failure: redirect back to settings with an actionable Italian notification.

### OAuth callback

`GET /admin/settings/google-drive/callback`

- Input: provider authorization response with valid session state.
- Success: encrypted refresh token and verified account email replace connection identity; AssestMe
  creates a new `AssestMe` folder directly in My Drive, stores its returned ID/name, and redirects to
  Settings. It does not search for or adopt a pre-existing same-name folder.
- Root-creation failure: keep the account explicitly connected without a root, keep synchronization
  disabled, show a sanitized actionable error, and expose a protected retry action.
- Failure: existing valid settings remain unchanged; sanitized actionable notification.

## Filament page actions

`GoogleDriveSettingsPage` exposes actions based on state:

- follow a native embedded six-step Google Cloud configuration guide;
- configure or replace only the client ID/secret, with a write-only secret and calculated copyable callback URI;
- connect account;
- retry automatic managed-root creation when the connected-without-root state is present;
- toggle automatic synchronization;
- verify connection (refresh token plus root inspection);
- synchronize now (forced, application lock protected);
- disconnect (local clear always explicit; remote revocation result reported separately).

The guide remains reachable after configuration as a collapsible section. Status contains account,
managed root, enabled flag, aggregate last success time, actionable latest error, and
running/synchronized/pending/error wording. All labels/messages are Italian translations.

## CLI contract

```text
php artisan assestme:google-drive-sync [--force]
```

Exit codes:

- `0`: disabled/incomplete no-op, unchanged no-op, or every attempted assessment succeeded;
- `1`: at least one assessment failed or the integration-level connection/root validation failed;
- `2`: another synchronization holds the application lock.

Output reports counts for evaluated, skipped, synchronized, and failed assessments. It never prints
tokens or raw provider responses. `--force` bypasses only hash comparison.

Scheduler contract:

- scheduled hourly in `Europe/Rome`;
- `withoutOverlapping()`;
- same application lock as manual/CLI execution;
- no Google call when disabled/incomplete or all hashes match.

## GoogleWorkspaceClient boundary

Concrete application boundary operations:

- create the application-owned root directly in My Drive and return its Google identity;
- list direct children matching an exact managed prefix/name;
- create and rename folders;
- create native spreadsheet in a specified parent;
- ensure required tabs without deleting other tabs;
- clear/update only managed ranges and freeze table header rows;
- upload/replace a managed file from verified bytes and return a web-view link;
- revoke a refresh token where supported.

Every lookup is parent-scoped. Duplicate managed matches throw a domain exception. No operation
deletes a remote object. Creation sends the exact parent ID to the public Drive API; parent IDs are
never passed through display-path resolution.

## Remote Sheet contract

Required tabs and shapes:

- `Assessment`: rows `[field label, value]`, no JSON cells;
- `Findings`: one header row plus one row per current Finding;
- `Soluzioni`: one header row plus one row per current FindingSolution;
- `Evidenze`: one header row plus one row per current Evidence.

Every sync clears the managed range on those four tabs and batch-writes current local rows. Table
headers are frozen. User-added tabs are preserved. Remote edits in managed ranges are overwritten.
Null or absent cells are normalized to empty cells before the values request so column positions
remain dense and the API never receives a sparse numeric-key JSON object.

## Failure contract

On any assessment failure:

1. stop that assessment projection;
2. do not advance its successful hash/time;
3. persist sanitized `last_error` and UTC `last_error_at`;
4. continue later assessments when isolation is safe;
5. return/report failure and retry next schedule;
6. never call or alter Workspace save/report generation behavior.
