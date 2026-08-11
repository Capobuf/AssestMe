# Feature Specification: Google Drive Readable Sync

**Feature Branch**: `develop`

**Created**: 2026-08-11

**Status**: Draft

**Input**: Optional, one-way AssestMe to Google Drive synchronization that keeps complete, readable assessment copies available without making Drive a database, backup, editing surface, or source of truth.

## User Scenarios & Testing

### User Story 1 - Connect and configure Google Drive (Priority: P1)

As the administrator, I can configure the Google OAuth application entirely from AssestMe, connect one Google account, let AssestMe create its dedicated Drive root, enable or disable automatic synchronization, verify the connection, and disconnect without affecting local operation.

**Why this priority**: No remote copy can be produced safely until the account, minimum authorization, root folder, and credential lifecycle are explicit and validated.

**Independent Test**: Starting with no Google environment variables, an authenticated administrator can follow the embedded setup guide, enter only the OAuth client identifier and secret, copy the calculated callback URI into Google Cloud, connect an account, see the automatically created `My Drive/AssestMe` root, and disconnect; no local assessment behavior depends on Google availability.

**Acceptance Scenarios**:

1. **Given** Google integration is not configured for the installation, **When** the administrator opens Drive settings, **Then** an embedded six-step setup guide and fields for only client identifier and client secret are shown while explaining that all local features remain available.
2. **Given** the administrator enters valid Google application values and saves them, **When** the page reloads, **Then** the integration is ready to connect, the secret is not rendered back to the browser, and the exact callback URI calculated by AssestMe is read-only and copyable.
3. **Given** installation credentials are configured from the UI or optional environment defaults, **When** the administrator starts connection, **Then** authorization requests only `drive.file` access for objects created or explicitly used by AssestMe and requests offline access using protected request state.
4. **Given** Google returns a valid account identity and refresh credential, **When** the callback completes, **Then** the account email is shown, the refresh credential is encrypted at rest, and the credential is absent from browser output, notifications, reports, and logs.
5. **Given** Google returns a valid account identity and refresh credential, **When** the first callback completes, **Then** AssestMe creates a new `AssestMe` folder in My Drive, stores the Google-returned folder ID as the authoritative root identity, and does not search for or adopt an existing same-name folder.
6. **Given** root creation fails after a valid OAuth callback, **When** the administrator returns to Settings, **Then** the account remains explicitly connected-without-root, synchronization stays disabled, an actionable error is shown, and a visible retry action is available.
7. **Given** installation configuration is complete, **When** the administrator returns to normal use, **Then** the embedded guide remains reachable in a collapsible section and the page emphasizes connection and synchronization state.
8. **Given** the integration is connected, **When** OAuth client configuration changes, **Then** automatic synchronization is disabled and the prior account credential and root are cleared so the administrator must reconnect explicitly.
9. **Given** the integration is connected, **When** the administrator disconnects, **Then** synchronization is disabled, local credentials and root settings are cleared, remote content is preserved, and any remote revocation problem is reported explicitly.
10. **Given** OAuth and managed-root creation succeed, **When** the administrator returns to Settings, **Then** automatic synchronization is already active by default and the connected state is rendered as a native Filament status/action panel rather than an unstructured text block.

---

### User Story 2 - Produce a complete readable assessment copy (Priority: P1)

As the administrator, I can synchronize an assessment so Drive contains an identifiable client folder, an identifiable assessment folder, one native Google Sheet with all current structured assessment data, copies of every valid generated PDF/XLSX, and copies of file evidence grouped by assessment.

**Why this priority**: This is the core user value: a complete and independently readable representation that remains consultable when AssestMe itself is unavailable.

**Independent Test**: Using a realistic local dataset with one client, one assessment, two findings, solutions, file and URL evidence, and generated PDF/XLSX records, synchronization produces the required hierarchy and four-tab spreadsheet; editing a local finding and synchronizing again replaces managed spreadsheet values with the local content.

**Acceptance Scenarios**:

1. **Given** a configured integration and a complete local assessment dataset, **When** synchronization runs, **Then** Drive contains client and assessment folders whose names begin with stable padded local IDs.
2. **Given** an assessment folder, **When** synchronization runs, **Then** exactly one managed native Sheet named `Findings - A-{id}` exists with `Assessment`, `Findings`, `Soluzioni`, and `Evidenze` tabs.
3. **Given** the managed Sheet already exists, **When** synchronization runs again, **Then** managed ranges are cleared and rewritten from current local data, removed local rows no longer appear in managed ranges, and manually added tabs remain untouched.
4. **Given** a local file evidence and a URL evidence, **When** synchronization runs, **Then** the file is copied into the assessment `Evidenze` folder and linked in the Sheet while the URL is written directly and no Drive file is created for it.
5. **Given** immutable generated PDF and XLSX records, **When** synchronization runs, **Then** their existing verified local files are copied unchanged into `Documenti` with deterministic names containing each generated-report ID.
6. **Given** a managed remote value was manually changed, **When** a forced synchronization runs, **Then** the local AssestMe value overwrites the managed remote value and nothing is imported locally.
7. **Given** an unrelated remote file or user-added Sheet tab, **When** synchronization runs, **Then** it remains present and unchanged.

---

### User Story 3 - Synchronize changes automatically and safely (Priority: P2)

As the administrator, I can rely on scheduled synchronization and use “Synchronize now”, while unchanged assessments are skipped, concurrent runs do not duplicate work, and Google failures remain visible and retryable without blocking any local save, evidence upload, PDF generation, or XLSX generation.

**Why this priority**: Durable usefulness requires predictable refresh, bounded work, and transparent recovery while preserving AssestMe availability.

**Independent Test**: A scheduled or manual run processes changed assessments in bounded chunks, skips unchanged content without Google calls, records success or failure per assessment, retries a failed assessment on the next run, and prevents overlapping execution for the same synchronization scope.

**Acceptance Scenarios**:

1. **Given** automatic sync is disabled or configuration is incomplete, **When** the scheduled command runs, **Then** it exits successfully without contacting Google.
2. **Given** an assessment content hash equals its last successful hash, **When** a normal run evaluates it, **Then** the assessment is skipped without contacting Google.
3. **Given** local client, assessment, finding, relation, solution, evidence, report, or root-folder data changes, **When** a normal run evaluates it, **Then** its canonical hash changes and the assessment is synchronized.
4. **Given** a synchronization succeeds, **When** local state is committed, **Then** the successful hash and timestamp advance and any prior error is cleared.
5. **Given** a Google or local-file failure, **When** synchronization stops for that assessment, **Then** its successful hash does not advance, an actionable sanitized error and time are stored, and a later run retries it.
6. **Given** a prior failure is removed, **When** the next synchronization succeeds, **Then** the error state clears and the success state advances.
7. **Given** scheduler and manual synchronization overlap, **When** both target the same scope, **Then** only one performs the remote mutation and the other reports that synchronization is already running.
8. **Given** `--force` or “Synchronize now” requests forced alignment, **When** it runs, **Then** changed-hash comparison is bypassed but all safety, validation, and non-deletion rules remain active.

### Edge Cases

- A refresh credential is revoked, missing, malformed, or cannot obtain an access token.
- The configured root was deleted, is no longer accessible, or resolves to a non-folder.
- Google returns quota, timeout, Drive API, or Sheets API errors.
- Two managed remote objects under the same parent have the same stable AssestMe ID prefix.
- A required local evidence or generated-report file is missing, unreadable, size-mismatched, or hash-mismatched.
- A title contains path separators, control characters, excessive whitespace, an empty sanitized value, or a name exceeding remote readability limits.
- An assessment contains no findings, no generated reports, no evidence, or soft-deleted children.
- A valid OAuth account is connected but creation of the managed root fails temporarily.
- Remote content has duplicate display names but only one or neither matches the expected stable local-ID prefix.
- The callback is denied, has invalid state, lacks an offline credential, or returns an identity without an email.
- Remote revocation fails during disconnect after local credentials have been securely cleared.
- A manually added Sheet tab has the same name as a required managed tab.
- A synchronization run encounters one failed assessment among multiple chunks.

## Requirements

### Functional Requirements

- **FR-001**: Google Drive synchronization MUST be optional and disabled unless the installation and application configuration are complete.
- **FR-002**: AssestMe local database and private storage MUST remain the sole source of truth.
- **FR-003**: The system MUST provide one-way `AssestMe → Google Drive` export only and MUST NOT import, merge, poll, webhook, or otherwise apply remote edits locally.
- **FR-004**: Google unavailability MUST NOT block local autosave, explicit save, finding edits, evidence uploads, PDF generation, XLSX generation, or other normal local behavior.
- **FR-005**: No Google request MAY execute inside the critical autosave or explicit workspace-save path.
- **FR-006**: The Settings area MUST allow the administrator to configure only the OAuth client identifier and client secret without installer or environment changes; optional environment values MAY supply defaults when no UI value exists. The callback URI MUST be calculated by AssestMe from the current installation route, rendered read-only, and expose a `Copia URI` action.
- **FR-007**: The Settings area MUST expose installation-incomplete, disconnected, connected-without-root, configured, syncing/synchronized, disabled, and actionable error states in Italian, and installation-incomplete MUST include the editable configuration form plus the embedded guide rather than a terminal unavailable state.
- **FR-008**: Only an authenticated administrator MAY save OAuth configuration, start authorization, retry managed-root creation, verify a connection, synchronize now, toggle automatic synchronization, or disconnect.
- **FR-009**: Authorization MUST use protected request state, request offline access, and request only `https://www.googleapis.com/auth/drive.file`.
- **FR-010**: The callback MUST reject denied, invalid-state, missing-refresh-credential, missing-email, and provider-error outcomes without changing an existing valid connection.
- **FR-011**: The client secret and refresh credential MUST be encrypted at rest; stored secrets MUST never be rendered back into form state and MUST NOT appear in browser output, local browser storage, application logs, notifications, reports, source control, or exception messages.
- **FR-012**: The system MUST obtain temporary access credentials from the stored refresh credential only when required and MUST NOT permanently persist access credentials.
- **FR-013**: V1 MUST NOT use Google Picker, a Picker API key, a Google project number/App ID, client-side folder selection, arbitrary existing-folder selection, Picker JavaScript, or an endpoint that exposes a temporary access token to a browser selector.
- **FR-014**: Immediately after the first successful OAuth callback, AssestMe MUST create a new `AssestMe` folder directly in My Drive through the public Google Drive API and store the returned folder ID and name as its managed root.
- **FR-015**: Managed-root creation MUST NOT search for, select, or adopt a pre-existing same-name folder; remote identity MUST rely on Google-returned IDs and stable AssestMe ID prefixes below that root.
- **FR-016**: Application settings MUST store at least client identifier, encrypted client secret, enabled state, connected account email, encrypted refresh credential, and managed-root folder identifier/name in a dedicated Google Drive settings group; callback URI, API key, and project number MUST NOT be persisted.
- **FR-016A**: Changing the effective OAuth client identifier or client secret MUST disable synchronization and clear the prior connected account, refresh credential, and root so explicit reconnection creates a new managed root.
- **FR-017**: A connected-without-root state caused by root-creation failure MUST remain explicit and retryable; synchronization MUST remain disabled until root creation succeeds.
- **FR-017A**: Automatic synchronization MUST become enabled by default when managed-root creation succeeds, including the first OAuth callback and a successful root-creation retry; it MUST remain explicitly toggleable afterward.
- **FR-018**: Disconnect MUST disable synchronization and clear local credential/account/root state without deleting remote data.
- **FR-019**: When reliable remote revocation is attempted, a revocation failure MUST be reported explicitly while local secret removal remains safe and unambiguous.
- **FR-020**: The remote hierarchy MUST be `<root>/<client>/<assessment>/{Findings Sheet,Documenti,Evidenze}` with no per-Finding evidence folder.
- **FR-020A**: Every folder, native Sheet, evidence file, and generated report MUST be created with the exact Google parent ID supplied by the managed hierarchy; a parent ID MUST never be interpreted as a display-path folder name.
- **FR-021**: Managed client, assessment, evidence, and generated-report names MUST contain stable, zero-padded local ID prefixes; the Assessment Sheet name MUST be stable and include only the assessment ID after `Findings -`.
- **FR-022**: Display-name portions MUST be sanitized for readable Drive names without removing or changing stable ID prefixes.
- **FR-023**: The system MUST identify managed objects by stable local-ID prefix within the expected parent and MUST fail explicitly when more than one object carries the same managed prefix.
- **FR-024**: Each assessment MUST have exactly one managed native Google Sheet and MUST NOT substitute an XLSX file for it.
- **FR-025**: Each managed Sheet MUST contain the four tabs `Assessment`, `Findings`, `Soluzioni`, and `Evidenze` while preserving user-added tabs.
- **FR-026**: The `Assessment` tab MUST contain the specified assessment/client identity, title, date, state, scope, narrative, language, and last-synchronization fields in readable key/value form.
- **FR-027**: The `Findings` tab MUST contain one current local Finding per row and all specified existing domain fields, including stable IDs, ordering, classifications, scope relations, selected solution identities and descriptions, state, resolution, and report inclusion.
- **FR-028**: The `Soluzioni` tab MUST contain one current local FindingSolution per row with stable IDs, parent Finding, ordering, narrative, effort, estimate, billing, and recommended/implemented flags; alternative solutions MUST NOT be compressed as JSON.
- **FR-029**: The `Evidenze` tab MUST contain one current local Evidence per row with stable IDs, parent Finding, ordering, type, title, remote link or original URL, file metadata, notes, hash, and report inclusion.
- **FR-030**: Every assessment synchronization MUST ensure required tabs, clear only AssestMe-managed ranges, rewrite current local rows, preserve manual tabs, maintain readable headers, and freeze table headers where supported by the approved client library.
- **FR-030A**: Sheet value rows MUST preserve column positions by serializing null or absent cells as empty cells rather than sparse numeric-key objects rejected by the Google Sheets values API.
- **FR-031**: A file Evidence MUST be copied unchanged from private local storage into the assessment `Evidenze` folder and its Drive link written to the Sheet.
- **FR-032**: A URL Evidence MUST write the original URL to the Sheet and MUST NOT create a remote file.
- **FR-033**: Every existing GeneratedReport for the assessment MUST be copied unchanged from its immutable local file into `Documenti` using a deterministic ID-bearing name.
- **FR-034**: Synchronization MUST validate required local evidence and generated-report files against the local file contract and fail that assessment explicitly rather than omit data or create placeholders.
- **FR-035**: Synchronization MUST NOT regenerate PDF or XLSX output for Google Drive.
- **FR-036**: V1 MAY create, update, rename, or recreate its managed remote objects but MUST NOT automatically delete any remote object, including stale managed copies and unrelated files.
- **FR-037**: A canonical per-assessment representation MUST include the active root, client, assessment, Findings and exported relations, solutions, evidences, and generated reports and MUST yield a deterministic content hash.
- **FR-038**: A local per-assessment sync state MUST record at least the last successful content hash, last successful time, last sanitized error, and error time.
- **FR-039**: Normal synchronization MUST compare the canonical hash before contacting Google and skip unchanged assessments.
- **FR-040**: Forced synchronization MUST bypass only the hash skip and MUST preserve all validation, authorization, concurrency, and non-destructive rules.
- **FR-041**: Assessments MUST be processed in bounded chunks rather than loaded without limit.
- **FR-042**: A successful sync MUST atomically advance successful state and clear prior error state only after all managed content for the assessment succeeds.
- **FR-043**: A failed sync MUST leave the last successful hash unchanged, store a sanitized actionable error, remain retryable, and continue evaluating other assessments where safe.
- **FR-044**: The application MUST provide an `assestme:google-drive-sync` command with a `--force` option and schedule normal execution with overlap prevention.
- **FR-045**: The scheduled command MUST perform no Google call when integration is disabled, incomplete, or no assessment requires synchronization.
- **FR-046**: Manual and scheduled synchronization MUST use a file-cache application lock so the same synchronization scope cannot mutate remote state concurrently.
- **FR-047**: The UI MUST show the Google application configuration form, exact effective callback URI, whether each secret is already configured, account, root, automatic state, last synchronization time in `Europe/Rome`, current status, and actions appropriate to the current connection state without exposing stored secrets or stack traces.
- **FR-047A**: Before or beside technical configuration, the Settings page MUST contain a native Filament collapsible section titled `Come configurare Google Drive` with the prescribed description and six steps covering project creation, Drive and Sheets API enablement, Branding/Audience/Data Access, Web application OAuth client creation, exact callback URI copying, and entry of client ID/secret. It MUST explain Internal versus External audiences, External Testing test users and seven-day authorization expiry, and request only `drive.file`; official current Google links MAY supplement but MUST NOT replace the embedded guide.
- **FR-048**: The UI MUST provide visible accessible equivalents for every available action and MUST NOT communicate state with color alone.
- **FR-049**: Failure handling MUST cover revoked refresh credentials, access-token failure, inaccessible/deleted root, quota, timeout, Drive API, Sheets API, unreadable local file, and duplicate managed remote prefixes.
- **FR-050**: The implementation MUST support normal user-owned Drive folders only and MUST NOT claim Shared Drive support.
- **FR-051**: Automated tests MUST replace Google boundaries and MUST NOT call real Google services in CI.
- **FR-052**: Automated coverage MUST include incomplete configuration with visible guide and unavailable connect action; complete two-field configuration with hidden retained secret, calculated read-only callback and available connect action; absence of all Picker/key/project surfaces; OAuth success with automatic root creation; root-creation failure/retry; OAuth configuration-change disconnect; authorization failure; disconnect; complete dataset synchronization; managed overwrite; failure/retry; non-destruction; scheduler skip/change/error behavior; and local-save independence.
- **FR-053**: The feature MUST NOT introduce Node/npm, Redis, queue workers, service accounts, a proprietary Google REST client, a generic cloud-provider abstraction, or a second spreadsheet/export engine.
- **FR-054**: The implementation MUST use only public package APIs, documented extension points, Laravel configuration/container facilities, and AssestMe-owned services; it MUST NOT edit `vendor/`, patch or fork Composer packages, monkey-patch dependencies, copy vendor classes for modification, or depend on non-public implementation details.

### Key Entities

- **Google Drive Settings**: Singleton installation-level configuration and connection state containing the OAuth client identifier, encrypted client secret, enabled state, connected account identity, encrypted refresh credential, and managed root identity/name. The callback URI is derived, not stored.
- **Assessment Sync State**: One local state per Assessment recording the last successful canonical hash and time plus the latest sanitized failure and time.
- **Canonical Sync Snapshot**: Deterministically ordered representation of the root, Client, Assessment, Findings and relations, Solutions, Evidences, and GeneratedReports used for both hashing and remote projection.
- **Managed Remote Object**: A Drive folder, file, or Sheet owned by this feature and identified within its parent by a stable AssestMe local-ID prefix or stable assessment Sheet name.
- **Assessment Sheet Projection**: Four tabular views (`Assessment`, `Findings`, `Soluzioni`, `Evidenze`) fully rewritten from the current canonical snapshot.

## Success Criteria

### Measurable Outcomes

- **SC-001**: With no Google environment values, an authenticated administrator can follow the embedded guide, configure the OAuth client with two fields, copy the calculated callback, connect an account, have `My Drive/AssestMe` created automatically, and see synchronization already active in a structured connected-state panel in one uninterrupted settings journey.
- **SC-002**: The required realistic dataset produces one client folder, one assessment folder, one four-tab native Sheet, two immutable generated documents, one copied file evidence, and one URL evidence reference with zero omitted required objects.
- **SC-003**: After any managed local value changes, the next forced synchronization rewrites the corresponding managed Sheet value from local data in a single run.
- **SC-004**: For an unchanged assessment, a normal scheduled run makes zero Google calls.
- **SC-005**: For every simulated required Google or local-file failure, the prior successful hash remains unchanged, a sanitized error is visible, the next run retries, and local workspace save plus PDF/XLSX generation remain operational.
- **SC-006**: Every successful first connection stores the exact ID of the newly created managed root and never adopts an ambiguous pre-existing same-name folder.
- **SC-007**: Automated tests complete without a real Google account and demonstrate that refresh credentials never appear in response bodies, rendered pages, notifications, or captured logs.
- **SC-008**: Synchronization evaluates assessments in chunks of no more than 50 records and prevents two concurrent runs from mutating the same synchronization scope.
- **SC-009**: The complete repository verification gate passes after implementation, while real Google OAuth, Drive, and Sheets operation are reported `NOT VERIFIED` unless manually exercised with evidence.

## Assumptions

- AssestMe continues to have exactly one authenticated administrator and one connected Google account per installation.
- Google Drive is a readable continuity copy, not a restorable application backup; existing backup and restore contracts remain unchanged.
- Normal personal Drive folders are the V1 target; Shared Drives are explicitly outside scope.
- User-created additional Sheet tabs and unrelated remote files may coexist with managed content and are preserved.
- Local soft-deleted child records are absent from the current managed Sheet rewrite but any prior remote copies are not deleted.
- The scheduler cadence may follow the existing operations convention; correctness depends on deterministic change detection rather than a specific minute interval.
- Real Google manual acceptance requires administrator-supplied installation credentials and is not implied by fake-based automated tests.
