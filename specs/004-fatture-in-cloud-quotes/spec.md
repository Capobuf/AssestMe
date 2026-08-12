# Feature Specification: Fatture in Cloud Quotes

**Feature Branch**: `develop`

**Created**: 2026-08-11

**Status**: Draft

**Input**: User description: "Connect AssestMe to one Fatture in Cloud company and let the administrator manually compose and create remote quotes from an Assessment without persisting commercial compositions locally."

## Invariants

- AssestMe remains authoritative for assessments; Fatture in Cloud (FIC) remains authoritative for commercial documents.
- The integration is optional. Installation, bootstrap, assessments, reports, backups, and diagnostics work without provider credentials or connectivity.
- Local persistence is limited to integration configuration/connection, the single FIC company and default VAT identity, and exact AssestMe Client-to-FIC Client mappings. Quote compositions, catalogs, versions, totals, and created quotes are never persisted locally.
- Grouping is always manual. No estimate type, including `bundled`, creates a group automatically.
- The transient internal `group` concept remains unchanged, but the composer presents it only as `Riga da finding`; `free` is presented as `Riga libera`.
- Quote composition has no local draft state or save action. Its only final commercial action is `Crea preventivo in Fatture in Cloud`.
- All new user-facing content is Italian. No English locale, switcher, parallel translations, or new localization architecture is introduced.
- Secrets and refresh credentials are encrypted and write-only and never appear in browser state, logs, notifications, command output, or saved provider errors.
- Opening the composer from a dirty Workspace uses the same signed, optimistic-locked, idempotent save protocol as other dependent Workspace actions and stops on any non-success result.
- Completed or archived Assessments remain read-only: the quote action is hidden and rejected until the Assessment is reopened.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Connect one provider company (Priority: P1)

The administrator opens `Impostazioni > Fatture in Cloud`, saves the application credentials, completes authorization, and selects a default VAT type for the only FIC company available to the account.

**Why this priority**: Every quote workflow depends on a secure and unambiguous provider connection.

**Independent Test**: Starting disconnected, the administrator can save credentials, authorize, see the one company and its VAT types, select a default, verify, reconnect, and disconnect.

**Acceptance Scenarios**:

1. **Given** valid credentials and an account controlling exactly one company, **When** authorization completes, **Then** the page shows that company and requires a live default VAT type before becoming ready.
2. **Given** an account controlling zero or multiple companies, **When** discovery completes, **Then** connection is rejected in Italian and no company is adopted.
3. **Given** an existing connection, **When** the effective application identifier or secret changes, **Then** the old connection and company-dependent settings are cleared before reconnection.
4. **Given** an existing connection, **When** the administrator disconnects, **Then** local connection data is cleared without exposing credentials and the page explains where provider-side authorization can be revoked.

---

### User Story 2 - Enter the composer with a resolved client (Priority: P2)

From an editable Assessment Workspace, the administrator selects `Crea preventivo`; dirty state is saved first, the composer opens, and the Assessment company resolves to one exact FIC client.

**Why this priority**: It establishes the safe boundary from assessment work to commercial work.

**Independent Test**: A dirty Workspace can save and open the composer, which reuses a valid mapping, finds exact fiscal-identifier candidates, or creates a FIC client when none exists.

**Acceptance Scenarios**:

1. **Given** a dirty Workspace, **When** the action is selected, **Then** authoritative save completes before navigation and the composer opens only after success.
2. **Given** validation, server, offline, or conflict state, **When** the action is requested, **Then** the composer does not open and the existing explicit Workspace state remains visible.
3. **Given** a valid stored mapping for the connected FIC company, **When** the composer opens, **Then** the mapped client is used.
4. **Given** no valid mapping and an available VAT number or tax code, **When** resolution runs, **Then** only exact fiscal-identifier matches are candidates; names are never fuzzy-matched.
5. **Given** no exact FIC client, **When** creation is confirmed, **Then** the client is created from the AssestMe company and only the exact returned mapping is stored.

---

### User Story 3 - Compose rows manually (Priority: P3)

The administrator reviews Findings and source solutions, creates temporary groups, assigns any Finding to one or more groups, adds free rows, and edits every resulting commercial row.

**Why this priority**: Only the administrator may decide how technical findings become commercial content.

**Independent Test**: Without submission, the administrator can create/edit/remove groups, assignments, and free rows while all composition stays transient.

**Acceptance Scenarios**:

1. **Given** multiple Findings, **When** they are assigned to one group, **Then** that group represents one future FIC row and includes all exact Finding references.
2. **Given** one Finding, **When** it is assigned to different groups, **Then** every explicit assignment remains present.
3. **Given** `bundled` source solutions, **When** the composer initializes, **Then** no automatic group or assignment exists.
4. **Given** exact estimates with the same currency and billing frequency, **When** the sum helper is requested, **Then** the compatible amount is suggested but remains editable.
5. **Given** range, variable, quote-required, analysis-required, not-applicable, mixed-currency, or mixed-frequency estimates, **When** a row is composed, **Then** no numeric price is invented.
6. **Given** a group or free row, **When** edited, **Then** title, description, net unit price, quantity, measure, discount, VAT, and optional product remain under administrator control.

---

### User Story 4 - Enrich rows from FIC catalogs (Priority: P4)

The administrator may select a real FIC product and VAT type for each row while retaining control of all editable commercial fields.

**Why this priority**: Provider data improves accuracy without turning AssestMe into product or tax software.

**Independent Test**: The composer reads products/VAT types, applies supported product suggestions, preserves product identity/code, defaults VAT, and allows override.

**Acceptance Scenarios**:

1. **Given** an available product, **When** selected, **Then** its exact identifier/code are retained and supported values are suggested without preventing edits.
2. **Given** a configured default VAT, **When** a row is created, **Then** it is selected unless explicitly overridden.
3. **Given** a disabled or unavailable product/VAT, **When** final validation runs, **Then** submission stops with an Italian corrective message.
4. **Given** catalog use, **When** the composer is abandoned or completed, **Then** no FIC product is modified and no local catalog or tax calculation is persisted.

---

### User Story 5 - Reuse an exact previous remote version (Priority: P5)

If an earlier AssestMe-created quote exists for the Assessment, the composer identifies the latest exact version and lets the administrator load it or start from zero.

**Why this priority**: Remote-only continuity avoids local commercial history and unrelated document adoption.

**Independent Test**: Exact technical markers can be parsed and ordered, and loaded rows reconcile only to valid current-Assessment Finding references.

**Acceptance Scenarios**:

1. **Given** quotes with technical subject `[ASSESTME assessment=<assessment-id> version=<positive-integer>]`, **When** discovery runs, **Then** the greatest exact version for the current Assessment is offered.
2. **Given** malformed, partial, case-different, non-quote, foreign-Assessment, or legacy subjects, **When** discovery runs, **Then** they are not treated as prior versions.
3. **Given** a chosen prior version, **When** loaded, **Then** detailed rows are reconstructed only in transient state.
4. **Given** `F-xxxxxx` tokens in prior descriptions, **When** reconciled, **Then** only exact six-digit references belonging to the current Assessment link; no title/name/legacy guessing occurs.

---

### User Story 6 - Create one reliable remote quote (Priority: P6)

The administrator validates the composition and creates a real FIC quote, receiving reliable confirmation or an actionable failure state.

**Why this priority**: It completes the outcome while handling uncertain network results honestly.

**Independent Test**: A provider replacement proves normal success, ordinary failure, and timeout-after-submit reconciliation through an exact marker.

**Acceptance Scenarios**:

1. **Given** a valid client and composition, **When** submitted once, **Then** one quote is created with one FIC row per group/free row, the exact next-version marker, an Italian visible subject, and exact Finding references.
2. **Given** an in-flight submission, **When** submission is attempted again, **Then** it is blocked.
3. **Given** an ordinary rejection, **When** creation fails, **Then** no success is reported and transient composition remains available for correction.
4. **Given** timeout or lost connection after the request may have arrived, **When** reconciliation finds exactly one quote with the submitted marker, **Then** its real identity/link is reported; otherwise the outcome remains explicitly ambiguous.
5. **Given** success, **When** confirmation is shown, **Then** no quote, version, row, total, or composition is stored locally.

### Edge Cases

- Authorization expires or is revoked while loading clients, catalogs, or versions.
- The provider returns a retry delay, malformed response, unavailable mapped record, or multiple exact-identifier candidates.
- Stored company/client/product/VAT identity belongs to a prior connection or is no longer accessible.
- Relevant provider results span multiple pages.
- A Finding is deleted after composer initialization.
- There are no rows, or a row has missing title, invalid quantity/price/discount/VAT.
- A loaded prior row has no valid current-Assessment reference; it stays editable but unlinked.
- Reconciliation finds zero or multiple documents with the submitted marker.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST provide one Italian `Impostazioni > Fatture in Cloud` page for configuration, authorization, status, verification, reconnection, disconnection, company state, VAT availability, and default VAT selection.
- **FR-002**: Application secrets and refresh credentials MUST be encrypted and write-only; a blank secret replacement MUST retain the current effective secret.
- **FR-003**: Changing the effective application identifier or secret MUST clear prior authorization, company/default VAT, and mapping validity before reuse.
- **FR-004**: Authorization MUST request only permissions required to read/create clients, read products/VAT types, and read/create quotes.
- **FR-005**: A usable connection MUST control exactly one FIC company; zero or multiple MUST be rejected without automatic selection.
- **FR-006**: The exact company identifier/name and one enabled live default VAT identifier MUST be stored.
- **FR-007**: Disconnect MUST clear local authorization and company-dependent state and MUST explain that provider-side authorization is revoked from FIC because its current public API exposes no revocation endpoint.
- **FR-008**: `Crea preventivo` MUST be limited to editable Assessments and MUST use the authoritative pre-action Workspace save guard.
- **FR-009**: The composer MUST require a ready integration and current Assessment company.
- **FR-010**: At most one exact FIC client identifier per AssestMe Client and current FIC company MAY be persisted and MUST be validated before reuse.
- **FR-011**: Client lookup MUST use available VAT number and/or tax code with exact normalized equality and MUST NOT use fuzzy/name matching.
- **FR-012**: With no exact match, the administrator MUST be able to create a FIC client and persist only the returned mapping.
- **FR-013**: Each Finding MUST be exposed with reference `F-` plus zero-padded six-digit local ID, Problem context, and source solutions.
- **FR-014**: The administrator MUST explicitly create temporary groups and add/remove/move/duplicate Finding assignments; one group maps to one remote row.
- **FR-015**: No estimate type, including `bundled`, MUST create groups or assignments automatically.
- **FR-016**: Free rows without Finding assignments MUST be supported.
- **FR-017**: Every row MUST expose title, description, net unit price, quantity, measure, percentage discount, VAT, and optional FIC product.
- **FR-018**: A sum MAY be suggested only when all selected source values are exact and share currency/frequency; all other combinations require explicit price entry.
- **FR-019**: Group descriptions MUST include sorted unique references for all assigned Findings, including a Finding assigned to multiple groups.
- **FR-020**: Composition, assignments, catalogs, prior version, totals, and created-quote response MUST remain transient and MUST NOT enter local commercial persistence.
- **FR-021**: FIC products MUST be read-only; selection retains exact product identifier/code and treats supported product values as editable suggestions.
- **FR-022**: VAT types MUST be live for the connected company; default and row override are supported, but AssestMe MUST NOT calculate VAT or persist tax amounts.
- **FR-023**: The technical subject marker MUST be exactly `[ASSESTME assessment=<assessment-id> version=<positive-integer>]`; only full-string case-sensitive matches identify versions.
- **FR-024**: Discovery MUST consider quote documents only, choose the greatest exact current-Assessment version, and load detailed rows only on request.
- **FR-025**: Prior rows MUST reconcile only exact `F-xxxxxx` tokens to non-deleted Findings in the current Assessment; unmatched/legacy data MUST NOT be guessed.
- **FR-026**: Submission MUST validate connection/client, at least one complete row, finite non-negative prices, positive quantities, supported discounts, enabled VAT, and valid Finding assignments.
- **FR-027**: Final mapping MUST create a `quote`, one item per group/free row, marker in technical subject, Italian visible subject, and supported product/code/name/description/quantity/measure/net-price/discount/VAT fields.
- **FR-028**: Submission MUST be single-flight and MUST NOT report success without a real provider document identifier and provider-returned link when available.
- **FR-029**: Ordinary errors MUST stop creation and preserve input. Expired auth, retry delay, and ambiguous post-request timeout receive distinct behavior only where required.
- **FR-030**: Ambiguous outcomes MUST reconcile through the exact submitted marker; exactly one match is success, while zero/multiple remain unresolved.
- **FR-031**: Every new user-facing string MUST be Italian using existing conventions, without another locale or localization architecture.
- **FR-032**: The optional integration MUST NOT require credentials, network, or provider availability for installation, bootstrap, diagnostics, ordinary work, reports, backups, or release validation.
- **FR-033**: On wide screens the composer MUST use a three-area workbench with compact Finding/solution context, a dominant row editor, and a narrow transient summary; intermediate and narrow layouts MUST deliberately reflow without horizontal composer scrolling.
- **FR-034**: User-facing controls and row headers MUST use `Riga da Finding` and `Riga Libera`, MUST NOT present the internal `group` term, and MUST provide transient Finding search over reference, title, and Problem for the 50-Finding target.
- **FR-035**: The resolved-client and previous-version controls MUST remain compact while preserving exact-client selection/creation, retry, `Carica precedente`, `Parti da zero`, and unmatched-row behavior.
- **FR-036**: The optional FIC product control MUST precede the editable title, description, quantity, measure, net unit price, discount, and VAT controls so that applying suggestions does not follow manual entry of the values it may replace.
- **FR-037**: The summary MUST expose only values derived from transient state: total rows, Finding rows, free rows, unique linked Findings over total Findings, and `Totale netto righe` calculated as net unit price × quantity × (1 − discount/100). The total is display-only, VAT-excluded, never persisted or authoritative, and unavailable rather than invented while row arithmetic is invalid. The summary MUST NOT present a persisted or commercial `Bozza` state.
- **FR-038**: The only final commercial action MUST be `Crea preventivo in Fatture in Cloud`; no local save or draft action MAY exist, and confirmed success MUST replace submission with the real document identifier and provider link when available.
- **FR-039**: The refined composer MUST preserve all existing loading, failure, ambiguous-outcome, and success states with accessible text in both light and dark modes; state MUST NOT be communicated by color alone.
- **FR-040**: The page title MUST use `Crea Preventivo - Fatture in Cloud`; its breadcrumb MUST end with `Preventivo - Fatture in Cloud`; visible section headings MUST use `Cliente`, `Preventivo`, and `Riepilogo`; row/add controls and summary labels MUST use the explicitly approved capitalization.
- **FR-041**: A resolved client MUST be presented with a prominent client icon, its exact provider name, and the accessible `Cliente Trovato` status in one compact block.
- **FR-042**: Finding search MUST have no redundant visible label, MUST retain an accessible name, and MUST use `Cerca nei Finding...` as its placeholder.
- **FR-043**: At the approved wide breakpoint, quantity, editable measure, net unit price, percentage discount, and VAT MUST fit on one deliberate row with widths proportional to their expected content. Measure and net-price controls MUST expose fixed `U.M.` and `€` suffixes respectively.
- **FR-044**: Editable measure suggestions MUST be derived from the measures returned by the live read-only product catalog. The administrator MUST still be able to enter a custom measure, and no standalone provider endpoint or local measure catalog MAY be invented.
- **FR-045**: When exactly one Finding is assigned to a Finding row, the editable title and description MUST be prefilled from that Finding's title and Problem, and the exact Finding reference MUST be visible in the description. With multiple Findings the title MUST remain blank and the description MUST retain sorted unique references; all prefilled content remains editable.
- **FR-046**: Displayed row prices and the transient VAT-excluded total MUST use the fixed euro indicator requested for this composer, while provider-side currency and fiscal authority remain unchanged.
- **FR-047**: The final submission button MUST use `Crea Preventivo`; this copy change MUST NOT alter validation, single-flight behavior, the provider request, or confirmed-success handling.

### Key Entities

- **FIC connection settings**: Application identifier, encrypted secret/refresh credential, connected company, connection state, and default VAT identity.
- **FIC client mapping**: Exact association between one AssestMe Client, current FIC company, and returned FIC client identifier.
- **Quote composer**: Transient Assessment/client/groups/assignments/free rows/catalog/version/submission state.
- **Composer row**: One temporary commercial row with editable fields and zero or more exact Finding references.
- **Remote quote version**: Provider-owned quote discovered by exact marker and never stored as local commercial history.

## Out of Scope

- Local quotation, invoice, tax, payment, catalog, price-list, or commercial-history modules.
- Persisted drafts, rows, totals, versions, remote product/VAT copies, or created-quote records.
- Automatic grouping, fuzzy client matching, legacy guessing, title-based reconciliation, or implicit merge.
- Creating/modifying provider products or VAT types; sending, accepting, invoicing, paying, modifying, or deleting quotes.
- Multi-company selection, roles, workers, live-provider tests, credentials, or generic provider/fake frameworks.
- English UI, a second locale, language selection, or global localization refactoring.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: With valid credentials and exactly one company, an administrator reaches ready state including default VAT in one authorization journey.
- **SC-002**: In all dirty-state tests the composer opens only after save success; validation/offline/server/conflict outcomes open it zero times.
- **SC-003**: A 50-Finding Assessment exposes all 50 Findings and creates zero automatic assignments.
- **SC-004**: Required datasets recognize valid references/markers with 100% exactness and reject all malformed, foreign, and legacy variants.
- **SC-005**: Success creates exactly one remote row per submitted manual group/free row and zero additional rows.
- **SC-006**: Non-exact or incompatible source estimates generate zero invented price suggestions.
- **SC-007**: Ordinary failure produces zero success confirmations; ambiguous results succeed only after exactly one exact-marker match.
- **SC-008**: After abandon or success, local persistence contains no quote draft/row/total/catalog/version/created-quote history.
- **SC-009**: Normal browser coverage adds at most one happy-path journey unless a second critical UI regression cannot be proven below browser level.
- **SC-010**: At the approved wide-browser breakpoint, the Finding context, dominant row editor, and summary are simultaneously visible in three columns; the same composer remains usable without horizontal scrolling in its narrow sequential layout.
- **SC-011**: In UI regression tests, `Riga da Finding`, `Riga Libera`, and the exact transient net total are present where applicable, while `Aggiungi gruppo`, `Salva bozza`, a quote-level `Bozza` state, and VAT calculations occur zero times.
- **SC-012**: At 1440 CSS pixels the five commercial controls occupy exactly one row, selecting one Finding prepopulates both editable text fields and its exact reference, and the 390-pixel layout introduces zero horizontal document overflow.

## Assumptions

- The administrator owns a FIC OAuth application and can authorize the requested permissions.
- Provider use is interactive and internet-connected; outages do not affect unrelated AssestMe behavior.
- Provider identifiers are opaque and retained without deriving business meaning.
- Existing AssestMe company data is used for client creation; provider validation remains explicit.
- Exact fiscal comparison ignores formatting whitespace and a leading Italian `IT` VAT prefix but never becomes fuzzy matching.
- FIC remains authoritative for numbering, totals, VAT interpretation, and commercial output.
