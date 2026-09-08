# Domain and application contracts

## Identity and tenancy

AssestMe is one application instance with one administrator and multiple customer companies. It is not multi-tenant SaaS. Integer database primary keys remain authoritative; no UUID/ULID migration is introduced.

## Companies, sites, and assets

- Companies own sites, assets, assessments, and related data.
- Sites and assets selected by an assessment or Finding must belong to the same company.
- Asset registration and Finding asset association are optional for every Finding scope.
- When assets are selected, they must belong to the assessment company.
- Asset types are managed under the company/asset navigation hierarchy.

## Assessments

- Creation proposes `<Azienda> — <Ambito> — <gg/mm/aaaa>`.
- The proposed title updates until the user manually edits it; a saved title is ordinary persisted data and is not rewritten after company/site renames.
- Assessment-level scopes are exactly `organization`, `selected_sites`, and `custom`.
- Selected sites are filtered by company and invalid selections are removed after company changes.
- `custom` requires a scope description.
- Create/save and normal row-open navigation lead to the Workspace; general-data edit remains secondary.
- Completed or archived assessments are read-only until explicitly reopened.

## Findings

A Finding belongs to an assessment and carries detached content required for later reporting, including problem, classification, risk, scope, solutions, notes, status, ordering, and evidence associations.

Supported Finding scopes include:

- whole company;
- selected sites;
- network;
- selected assets;
- custom description.

Validation, completion, and report generation use the same scope rule. No UI-only exception may create a different server contract.
The `selected_assets` scope may be saved, completed, and reported without an asset association. This also applies when the Finding was copied from an imported template.

## Solutions

- A Finding has one or more bounded remediation solutions.
- Exactly one may be recommended where required by the report contract.
- A resolved Finding records one implemented solution.
- Monetary estimate types are exact single amount, approximate single amount (`Stima`), and range.
- Monetary solutions use the single ISO currency configured in report settings; solution editors do
  not expose a per-solution currency override. Fresh installations default to EUR.
- Referenced solutions cannot be deleted until references are reassigned.
- Template solutions copied into Findings become detached snapshots.
- Economic values are indicative and exclude VAT.

## Risk and effort

- Priority is derived from consequence × likelihood through the active risk matrix.
- A reasoned manual override is supported.
- Risk profiles own consequence levels, likelihood levels, priority levels, and all sixteen matrix entries.
- Effort levels are global.
- Technical codes are generated during creation and become immutable.
- Persisted integer IDs are stable identities; labels, colors, order, or enabled state do not replace records.
- Referenced levels are disabled rather than deleted.
- The risk editor persists exactly sixteen same-profile combinations.
- `active_risk_profile_id` is the operational source for new Finding/template classifications and imports; `is_default` is descriptive and does not switch operational behavior.
- The active profile must exist and remain enabled. Historical IDs from another or disabled profile are retained and may accompany unrelated edits; a deliberate reclassification must use enabled levels entirely from the active profile.
- Urgent Findings are those assigned to the highest two `sort_order` levels of their own profile, including historical profiles. A single-level profile contributes that one level.

## Finding templates and JSON

- A Finding has at most one operational template lineage through `source_template_id`. When linked,
  it stores a nullable canonical SHA-256 fingerprint of the reusable persisted source-template
  content it last knew; this guard is not a version, revision, or history record.
- The lineage fingerprint contains stable category/risk identities, all reusable template fields,
  and every active solution including its stable external ID, complete content, recommendation, and
  order. It excludes template database identity, timestamps, deletion metadata, and enabled state.
- A manual or derived Finding may explicitly create a new template from title, category, problem,
  reusable notes, default scope, matrix-coherent risk defaults, reusable rationale, and all solutions.
  Assessment/client/site/asset/evidence/status/report/resolution/implemented-solution state is never
  promoted into the template.
- Assessment-specific manual priority override and its customer rationale are not template defaults;
  new templates derive priority from consequence and likelihood under the selectable active profile.
  Historical classifications are never silently remapped.
- Exact duplicate comparison uses complete normalized reusable meaning, including solutions but not
  operational IDs. Exact duplicates block creation and may be linked; conservative lexical
  similarity only warns and never links or updates automatically. Soft-deleted templates do not
  participate; disabled templates remain disabled.
- An explicit source update is a full replacement through authoritative template persistence. It is
  permitted only when the locked current fingerprint matches the Finding's known fingerprint.
  Existing template-solution external IDs are preserved, new ones are generated authoritatively,
  and existing Findings remain detached snapshots.
- A legacy linked Finding with a null fingerprint initializes lineage only when semantically
  identical to the current source. Otherwise update is unverifiable and blocked. A disabled source
  may be updated without re-enabling; a soft-deleted source cannot be restored or updated here.
- JSON Schema v2 is the canonical export/interchange contract; schema v1 remains accepted for import.
- V2 risk values are nullable stable technical codes. Structural validation checks their shape; preview and import resolve them against the enabled active profile and reject unknown, disabled, cross-profile, or matrix-incoherent classifications with template context.
- Template and solution `external_id` values are stable lowercase identifiers.
- Manual creation generates deterministic IDs from titles, adding `-2`, `-3`, and later numeric suffixes for collisions.
- Existing IDs are immutable; title changes do not regenerate them.
- Imports preserve supplied IDs.
- A template imported with `default_scope_type` set to `selected_assets` does not create an asset requirement when copied to an assessment.
- Updating an existing imported template is a full replacement of import-managed fields and solutions inside one transaction.
- No implicit merge behavior may be invented.
- Import failures are reported with row/item context and do not produce partial fake success.
- The bundled Italian MSP baseline is a deterministic schema-v2 library of 221 independent templates across the approved operational categories; its eight previously distributed template IDs remain unchanged.
- Explicit import in `replace` mode retains its existing ability to replace matching template
  content, including locally improved baseline templates; import protection/origin metadata is not
  part of the Workspace learning contract.

## Settings

Settings cover the approved product behavior only, including:

- general application behavior;
- archive/permanent-delete policy;
- risk profiles and effort levels;
- report branding, title, legends, consultant identity, signature text, footer, and freeze behavior;
- backup presentation and diagnostics.
- optional Fatture in Cloud OAuth client credentials, encrypted access/refresh credentials, the
  single provider company identity, and one enabled provider VAT-type default.

The Fatture in Cloud client secret is write-only; a blank save retains it. Changing either effective
OAuth client credential clears the connection. A successful callback must expose exactly one company.
Local disconnect clears local credentials and tells the administrator how to revoke provider access;
no provider revocation endpoint is invented.

Settings snapshots used for generated reports are immutable. No VAT calculation settings,
handwritten-signature upload, roles, portals, or audit-log settings are added.

## Fatture in Cloud boundary

- The only commercial persistence is an opaque nullable Fatture in Cloud company/client mapping on
  `Client`; it is revalidated against the live provider before use.
- Fiscal-code/VAT-number lookup is exact after deterministic normalization. Names are never used for
  fuzzy automatic matching; multiple exact candidates require an explicit choice.
- Products and enabled VAT types are live read-only references. Product code/identity and a VAT-type
  identifier can seed an editable transient row, but AssestMe neither writes provider catalogs nor
  calculates VAT.
- Groups, free rows, Finding assignments, prior-version reconstruction, prices, quantities,
  discounts, remote quote IDs, and provider links remain transient and are not stored locally.
- A Finding reference is exactly `F-` plus its six-digit local ID. A quote marker is exactly
  `[ASSESTME assessment=<id> version=<positive-int>]`; only anchored, exact markers establish remote
  version lineage.
- One manual group maps to one provider row. Findings may occur in zero, one, or multiple groups;
  AssestMe performs no automatic grouping.

## Generated files

Generated PDF/XLSX records retain format/version metadata, generated physical filename, configured-timezone timestamp, size, SHA-256, immutable normalized payload, and settings snapshot. Download is authenticated and hash-verified.
