# Data Model: Fatture in Cloud Quotes

## Persistence boundary

Only the two sections marked **persisted** are stored locally. The composer and every remote catalog/document projection are explicitly transient.

## FattureInCloudSettings — persisted encrypted/settings group

Group: `fatture_in_cloud`

| Field | Type | Secret | Rule |
|---|---|---:|---|
| `client_id` | nullable string | no | Required before connect; max 512 |
| `encrypted_client_secret` | nullable string | yes | Required before connect; blank edit retains effective value |
| `encrypted_access_token` | nullable string | yes | Never exposed; cleared on disconnect/reconfigure |
| `access_token_expires_at` | nullable UTC datetime string | no | Parsed strictly; cleared with token |
| `encrypted_refresh_token` | nullable string | yes | Never exposed; replacement persisted on every token exchange/refresh |
| `company_id` | nullable string | no | Set only after exact-one discovery |
| `company_name` | nullable string | no | Provider display value for status only |
| `default_vat_type_id` | nullable string | no | Must resolve to a current enabled VAT type before ready |
| `default_vat_type_label` | nullable string | no | Last confirmed display label; never treated as identity |

### Derived states

- `installation_configured`: client ID and effective secret exist.
- `connected`: refresh token and exactly one company identity exist.
- `ready`: connected and configured default VAT resolves live and enabled.
- `reauthorization_required`: credentials exist but token refresh/401 indicates revoked or expired authorization.

### Transitions

```text
unconfigured -> configured -> authorizing -> connected_not_ready -> ready
configured/connected/ready -> configured (local disconnect)
connected/ready -> reauthorization_required (refresh/auth failure)
any configured state -> configured (effective OAuth credential change clears connection)
```

OAuth `state` is single-use authenticated-session state, not a settings property.

## Client FIC mapping — persisted columns on `clients`

| Field | Type | Rule |
|---|---|---|
| `fic_company_id` | nullable string(64) | Opaque provider company identity; both mapping columns null or both non-null |
| `fic_client_id` | nullable string(64) | Opaque provider client identity |

Constraints:

- Composite unique `(fic_company_id, fic_client_id)` prevents two AssestMe Clients mapping to one remote client.
- A mapping is usable only when `fic_company_id` equals current settings `company_id` and a live exact-ID lookup succeeds.
- A foreign-company or unavailable mapping remains historical inert data until replaced; it is never silently used.
- Updating a mapping writes both columns atomically through a typed action.
- Soft-deleted local Clients do not participate in composer selection but retain their exact mapping with the row.

## QuoteComposerState — transient Livewire state

| Field | Type | Rule |
|---|---|---|
| `assessment_id` | positive integer | Bound record; never client-selectable |
| `fic_company_id` | non-empty string | Snapshot of ready current connection; revalidated before submit |
| `fic_client_id` | nullable string | Must be resolved and live before rows can submit |
| `client_candidates` | list of provider client summaries | Exact fiscal matches only; no fuzzy/name candidates |
| `groups` | ordered list of `QuoteComposerRow` | Manual groups only |
| `free_rows` | ordered list of `QuoteComposerRow` | No Finding assignment |
| `vat_types` | list of live VAT summaries | Display/validation projection only |
| `previous_version` | nullable remote summary | Exact marker result only |
| `previous_choice` | `pending`, `loaded`, `fresh` | Banner decision state |
| `submitting` | boolean | Single-flight guard |
| `created_document` | nullable remote result | Current-page confirmation only |
| `provider_error` | nullable bounded code/message | Italian display only; no raw provider body |

The state is discarded when the component is left or remounted. It is not written to database, settings, cache, filesystem, session draft, or IndexedDB.

## QuoteComposerRow — transient

| Field | Type | Validation |
|---|---|---|
| `key` | UUID string | Component identity only |
| `kind` | `group` or `free` | Immutable after creation |
| `finding_ids` | list of unique positive integers | Group: zero or more while editing, at least one for a sourced group at submit; each belongs to current Assessment |
| `product_id` | nullable string | Must resolve live before submit |
| `product_code` | nullable string | Preserved/suggested; editable |
| `title` | string | Required, trimmed, provider-bounded length |
| `description` | string | Commercial text plus generated sorted unique Finding references |
| `net_price` | decimal string | Required at submit, finite, >= 0; never inferred from incompatible estimates |
| `quantity` | decimal string | Required, finite, > 0 |
| `measure` | nullable string | Provider-bounded length |
| `discount` | decimal string | Finite, 0–100 |
| `vat_type_id` | string | Required, live, enabled |

The final description is derived at mapping time so a user edit cannot accidentally remove required Finding references. Free rows add none.

## Provider projections — transient typed DTOs

- `FattureInCloudToken`: access token, refresh token, expiry seconds.
- `FattureInCloudCompany`: opaque ID and display name.
- `FattureInCloudVatType`: opaque ID, numeric display value, description, disabled/default flags.
- `FattureInCloudClient`: opaque ID, name, VAT number, tax code, address fields.
- `FattureInCloudProduct`: opaque ID, code, name, description, measure, net price, default VAT, disabled/accessibility state where available.
- `FattureInCloudQuoteSummary`: opaque ID, type, subject, visible subject, URL.
- `FattureInCloudQuoteDetail`: summary plus provider item list.
- `CreatedFattureInCloudQuote`: required opaque document ID and nullable provider URL.

Every DTO validates required response shape at the boundary; malformed provider responses fail explicitly.

## Exact identities and grammars

### Finding reference

- Generate: `F-` + zero-padded six-digit local Finding ID (`42` -> `F-000042`).
- Reconcile: case-sensitive token `F-[0-9]{6}` followed by current-Assessment membership check.

### Assessment/version marker

- Format: `[ASSESTME assessment=<positive local Assessment ID> version=<positive integer>]`.
- Parse: full-string, case-sensitive, no spaces other than the two literal separators.
- Latest: maximum parsed version for the current Assessment among type `quote` only.

### Fiscal identifiers

- Trim, uppercase, remove spaces and `.`, `-`, `/` punctuation.
- For VAT comparison only, remove one leading `IT` from either side.
- Empty normalized values do not participate.
- Equality only; never prefix, substring, phonetic, or name similarity.
