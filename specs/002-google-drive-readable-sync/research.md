# Research: Google Drive Readable Sync

## Dependency compatibility

**Decision**: Use stable `laravel/socialite:^5.29`,
`yaza/laravel-google-drive-storage:^5.0`, and
`revolution/laravel-google-sheets:^7.2` and allow Composer to lock exact versions.

**Rationale**: Composer metadata inspected on 2026-08-11 shows Socialite 5.29 accepts Illuminate
13, Yaza 5.0 requires PHP 8.3 and accepts Illuminate 13, and Revolution 7.2 requires PHP 8.3 and
Illuminate 12/13. Revolution also supplies the approved transitive Google PHP client.

**Alternatives considered**: Manual REST calls were rejected because approved packages and their
Google client already expose the required operations. Forks and unstable package branches were
rejected by repository policy.

## OAuth and credential lifecycle

**Decision**: Use Socialite's stateful Google driver with only `drive.file`, `access_type=offline`,
and consent prompting needed to obtain a refresh token. Store only account email and a Spatie
Settings encrypted refresh token; generate access tokens through the Google PHP client when needed.

**Rationale**: This meets minimum scope, protected state, offline scheduler access, and secret
handling requirements without adding a proprietary OAuth service. Spatie Settings natively supports
encrypted properties and encrypted settings migrations.

**Alternatives considered**: `stateless()`, broad Drive scope, service accounts, and permanent
access-token storage were explicitly rejected by the approved feature boundary.

## Google client boundary

**Decision**: Keep one concrete `GoogleWorkspaceClient` around approved Drive/Sheets package and
Google-client operations. It accepts a temporary access token per operation and exposes typed
application-level methods for folder inspection, prefix lookup, folder/file/Sheet creation,
renaming, value replacement, and header freezing.

**Rationale**: A concrete boundary is container-substitutable in Pest while avoiding a generic cloud
provider or a one-implementation interface. Package-specific details stay out of Filament and sync
orchestration.

**Alternatives considered**: Mocking deep vendor classes throughout every test was rejected as
brittle. A generic filesystem/cloud abstraction was rejected as speculative.

## Remote identity and non-destruction

**Decision**: Resolve managed children only within the expected parent by stable ID prefix; zero
matches creates, one match reuses/renames, more than one fails. Never delete remote objects or
manual Sheet tabs.

**Rationale**: Drive permits duplicate display names and path-based adapters become ambiguous. The
local integer ID prefix is stable while titles are mutable. Explicit duplicate failure prevents
silent attachment to the wrong object.

**Alternatives considered**: Persisting every remote path/ID would create excessive mutable state.
Name-only lookup and first-match behavior were rejected as unsafe. Remote garbage collection was
explicitly excluded.

## Snapshot and change detection

**Decision**: Build a dedicated deterministic Google projection from Eloquent relations, borrowing
labels and normalization helpers from the existing report snapshot where semantically identical but
retaining Sheet-specific rows. Sort every collection and map by stable IDs, JSON-encode with stable
key order, and hash with SHA-256 including the root folder ID.

**Rationale**: The report snapshot validates only reportable/completable content and can exclude
Findings, whereas Drive must represent all current assessment content. A dedicated projection avoids
coupling the readable continuity copy to PDF layout while reusing domain labels and file contracts.

**Alternatives considered**: Dirty observers across child models were rejected as dispersed and
fragile. Reusing the report DTO unchanged was rejected because it omits excluded/soft-deleted state
semantics and lacks remote document identity.

## Failure, retry, and concurrency

**Decision**: Store last successful hash/time and latest sanitized error/time per Assessment. Only
advance success state after the full assessment projection succeeds. Use the existing file-cache
lock for manual/CLI overlap and scheduler `withoutOverlapping()`; continue to later assessments
after recording an individual failure and return a failing command status when any assessment fails.

**Rationale**: This provides deterministic retries and explicit operational truth while preventing
Google failures from entering or blocking local save/report paths.

**Alternatives considered**: Queue workers, Redis locks, retries inside autosave, and catch-and-report-
success behavior violate repository decisions.

## Managed root and embedded configuration guide

**Decision**: Do not use Google Picker. After a successful OAuth callback, create a new folder named
`AssestMe` directly in My Drive with the public Drive API and retain the returned ID as the only root
identity. Do not search for or adopt an existing same-name folder. Configure only the OAuth client ID
and encrypted secret in AssestMe; derive the callback URI from the named application route. Provide a
native Filament six-step guide based on current official Google documentation.

**Rationale**: `drive.file` permits AssestMe to create and manage its own files without broad Drive
access. Automatic ownership removes the API key, project number, browser token exposure, arbitrary
folder selection, and JavaScript integration from V1 while keeping setup executable from the UI.

**Alternatives considered**: Google Picker, a custom browser selector, broad Drive scopes, name-based
root adoption, and editable callback configuration were explicitly rejected by the addendum.

**Official sources checked 2026-08-11**:

- https://developers.google.com/workspace/guides/enable-apis for enabling Drive and Sheets APIs;
- https://developers.google.com/workspace/drive/api/guides/api-specific-auth for the non-sensitive
  `drive.file` scope;
- https://developers.google.com/identity/protocols/oauth2/web-server for exact redirect matching,
  protected state, and offline access;
- https://support.google.com/cloud/answer/15549049 for Branding;
- https://support.google.com/cloud/answer/15549945 for Audience, test users, and seven-day expiry;
- https://support.google.com/cloud/answer/15549135 for Data Access;
- https://support.google.com/cloud/answer/15549257 for Web application OAuth clients.

## Dependency integrity

**Decision**: Retain the approved Socialite, Yaza Drive storage, Revolution Sheets, and Google PHP
Client dependencies intact. Use Socialite, Revolution Sheets, and the public Google PHP Client APIs
through AssestMe-owned services; send Drive object creation directly through the public Drive client
so exact parent IDs cannot be interpreted as display paths.

**Rationale**: The feature must remain upgradeable and reproducible without altering dependency code.

**Alternatives considered**: `vendor/` edits, Composer patches, forks, monkey-patches, copied vendor
classes, and reliance on non-public implementation details are prohibited.

## Verification boundary

**Decision**: CI uses a substituted concrete Google boundary and mocked Socialite provider. Manual
OAuth/Drive/Sheets acceptance remains `NOT VERIFIED` unless executed with a real account.

**Rationale**: Deterministic CI must not depend on third-party credentials or quota and cannot be
misreported as real-provider acceptance.

**Alternatives considered**: Live Google CI was rejected as slow, stateful, secret-bearing, and
contrary to the brief.
