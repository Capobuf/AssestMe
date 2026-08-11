# Data Model: Google Drive Readable Sync

## GoogleDriveSettings

Singleton settings group `google_drive`.

| Property | Type | Rules |
|---|---|---|
| `client_id` | nullable string | UI-managed OAuth client identifier; falls back to optional environment config |
| `encrypted_client_secret` | nullable string | Encrypted by Spatie Settings; never hydrated back into the form |
| `sync_enabled` | boolean | Defaults false; effective only with installation config, refresh token, and root |
| `google_account_email` | nullable string | Valid email, max 254; cleared on disconnect |
| `encrypted_refresh_token` | nullable string | Encrypted by Spatie Settings; never serialized to browser/logs |
| `root_folder_id` | nullable string | Google object ID returned when AssestMe creates its root |
| `root_folder_name` | nullable string | Expected `AssestMe`; display-only while the returned ID remains authoritative |

Effective states:

1. `installation_incomplete`: application credentials incomplete; the Settings form remains editable and local operation remains available.
2. `disconnected`: no refresh token/account.
3. `connected`: account exists, root absent.
4. `configured-disabled`: account/root exist, sync toggle false.
5. `configured-enabled`: account/root exist, sync toggle true.
6. `error`: connection/sync state contains an actionable current failure.

The callback URI is calculated from the named application route and is never persisted. Disconnect
clears account/token/root and sets `sync_enabled=false`; remote content remains untouched. The root
ID participates in every snapshot hash.

Changing the effective OAuth client ID or client secret transitions to `disconnected`, disables
synchronization, and clears account/token/root. A successful subsequent OAuth callback creates a new
managed root; AssestMe never searches for or adopts a same-name folder.

## AssessmentGoogleDriveSync

One row per Assessment, table `assessment_google_drive_syncs`.

| Column | Type | Rules |
|---|---|---|
| `assessment_id` | foreign primary/unique key | References assessments; cascade on local assessment deletion |
| `last_content_hash` | nullable char(64) | SHA-256 of last fully successful snapshot |
| `last_synced_at` | nullable UTC timestamp | Advances only with full success |
| `last_error` | nullable text | Sanitized actionable message; no token, stack, or raw provider payload |
| `last_error_at` | nullable UTC timestamp | Set with `last_error` |
| timestamps | UTC timestamps | Portable Laravel timestamps |

State transitions:

```text
never synced ──success──> synchronized
never synced ──failure──> pending/error
synchronized ──unchanged──> synchronized (no write/no Google call)
synchronized ──failure──> pending/error (successful hash/time retained)
pending/error ──success──> synchronized (error fields cleared)
connected/no root ──root creation success──> configured-disabled
```

## GoogleDriveAssessmentSnapshot

Immutable typed data built per Assessment with deterministic ordering.

- root: ID only in canonical hash; display name is UI metadata;
- client: stable ID plus displayed anagraphic fields used by the projection;
- assessment: stable ID, title/date/status/scope/narratives/locale and selected sites;
- findings: all current non-deleted Findings ordered by `sort_order,id`, including category, scope
  sites/assets, risk labels/override, chosen solution identities, resolution, report flag;
- solutions: current solutions ordered within Finding by `sort_order,id`, with all Sheet fields;
- evidences: current evidence ordered within Finding by `sort_order,id`, with local file metadata or
  URL and report flag;
- generated reports: all immutable records ordered by `format,version,id`, including path, name,
  byte size, file hash, and generated time;
- `content_hash`: SHA-256 over canonical JSON excluding volatile sync time and remote links.

The `Ultima sincronizzazione` Sheet value is supplied at execution time and excluded from the hash
to avoid perpetual changes.

## Managed remote identity

| Local object | Parent | Managed prefix/name |
|---|---|---|
| Client | AssestMe-managed root | `C-000001 - {sanitized display name}` |
| Assessment | Client folder | `A-000012 - YYYY-MM-DD - {sanitized title}` |
| Sheet | Assessment folder | `Findings - A-000012` |
| Document folder | Assessment folder | exact `Documenti` |
| Evidence folder | Assessment folder | exact `Evidenze` |
| GeneratedReport | Documenti | `R-000041 - {format label} v{version}.{extension}` |
| Evidence file | Evidenze | `F-000101 - E-000221 - {sanitized title}.{extension}` |

For every managed prefix lookup: zero creates, one reuses and may rename, two or more fail. Exact
container names are resolved only under the already-ID-addressed Assessment parent; duplicates also
fail rather than selecting silently.

## Relationship changes

- `Assessment` gains `googleDriveSync(): HasOne`.
- No observer or dirty flag is added to Client/Assessment/Finding/Solution/Evidence/GeneratedReport.
- No remote ID is added to domain models; remote objects are rediscovered by parent plus stable
  prefix to keep local state minimal.
