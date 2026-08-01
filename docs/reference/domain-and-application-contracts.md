# Domain and application contracts

## Identity and tenancy

AssestMe is one application instance with one administrator and multiple customer companies. It is not multi-tenant SaaS. Integer database primary keys remain authoritative; no UUID/ULID migration is introduced.

## Companies, sites, and assets

- Companies own sites, assets, assessments, and related data.
- Sites and assets selected by an assessment or Finding must belong to the same company.
- Asset registration is optional except for Findings explicitly scoped as `selected_assets`.
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

## Solutions

- A Finding has one or more bounded remediation solutions.
- Exactly one may be recommended where required by the report contract.
- A resolved Finding records one implemented solution.
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

## Finding templates and JSON

- JSON Schema v1 is the canonical interchange contract.
- Template and solution `external_id` values are stable lowercase identifiers.
- Manual creation generates deterministic IDs from titles, adding `-2`, `-3`, and later numeric suffixes for collisions.
- Existing IDs are immutable; title changes do not regenerate them.
- Imports preserve supplied IDs.
- Updating an existing imported template is a full replacement of import-managed fields and solutions inside one transaction.
- No implicit merge behavior may be invented.
- Import failures are reported with row/item context and do not produce partial fake success.

## Settings

Settings cover the approved product behavior only, including:

- general application behavior;
- archive/permanent-delete policy;
- risk profiles and effort levels;
- report branding, title, legends, consultant identity, signature text, footer, and freeze behavior;
- backup presentation and diagnostics.

Settings snapshots used for generated reports are immutable. No VAT calculation settings, handwritten-signature upload, roles, portals, or audit-log settings are added.

## Generated files

Generated PDF/XLSX records retain format/version metadata, generated physical filename, configured-timezone timestamp, size, SHA-256, immutable normalized payload, and settings snapshot. Download is authenticated and hash-verified.
