# ADR: Product boundary and language

- Status: Accepted
- Baseline: AssestMe specification 2.7
- Source commit: `d466ff1c0c3851fecee3d468cde7cb9359db5ae4`

## Context

Defines the product boundary, singleton account model, terminology, language, and economic exclusions.

## Decisions

| ID | Status | Decision |
|---|---|---|
| D-002 | APPROVED | Exactly one administrator account; no registration, roles, or client access |
| D-011 | APPROVED | Italian v1 UI/report, translation keys from the first commit |
| D-014 | SUPERSEDED | Economic values are indicative, never totaled as a quotation, and always understood as excluding VAT |
| D-014 v2 | APPROVED | AssestMe may compose and create a transient remote Fatture in Cloud quote from an Assessment; it still stores no local quotation, commercial history, totals, or generated-document snapshot, and assessment estimates remain indicative and excluding VAT |
| D-015 | APPROVED | Asset association is optional and scope can be organization-wide |
| D-034 | APPROVED | No audit log or historical finding revisions in v1 |
| D-035 | APPROVED | API, MCP server, Jira, runZero, and scanner imports are outside v1 |
| D-039 | SUPERSEDED | VAT treatment is not modeled or configurable; reports and XLSX contain one mandatory VAT-excluded estimate note |
| D-039 v2 | APPROVED | A current Fatture in Cloud VAT type may be selected as an opaque remote row reference during transient quote composition; AssestMe performs and persists no VAT treatment, taxable amount, tax amount, or fiscal calculation, while reports and XLSX retain the mandatory VAT-excluded estimate note |
| D-040 | APPROVED | Optional consultant identity/contact/logo and text-only signature fields are explicitly defined for reports |
| D-043 | APPROVED | Italian user-facing terminology uses Azienda/Aziende and Intera azienda; internal `Client`, `clients`, `client_id`, relations, and persistence contracts remain unchanged |
| D-075 | APPROVED | The optional Fatture in Cloud integration uses OAuth authorization-code flow with exact minimum scopes, accepts exactly one provider company, stores only encrypted connection credentials/configuration plus an opaque Client mapping, reads products and VAT types live, and keeps quote composition/version discovery/results remote and transient |

## Consequences

- The listed decisions remain normative with their recorded status.
- `SUPERSEDED` entries remain historical evidence and must not be reactivated implicitly.
- Detailed application contracts live in the reference pages linked from [the documentation index](../index.md); those pages may clarify implementation but may not contradict these decisions.
- A future change requires explicit approval and a recorded superseding decision, not a silent edit.
