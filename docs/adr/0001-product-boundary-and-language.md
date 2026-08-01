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
| D-014 | APPROVED | Economic values are indicative, never totaled as a quotation, and always understood as excluding VAT |
| D-015 | APPROVED | Asset association is optional and scope can be organization-wide |
| D-034 | APPROVED | No audit log or historical finding revisions in v1 |
| D-035 | APPROVED | API, MCP server, Jira, runZero, and scanner imports are outside v1 |
| D-039 | APPROVED | VAT treatment is not modeled or configurable; reports and XLSX contain one mandatory VAT-excluded estimate note |
| D-040 | APPROVED | Optional consultant identity/contact/logo and text-only signature fields are explicitly defined for reports |
| D-043 | APPROVED | Italian user-facing terminology uses Azienda/Aziende and Intera azienda; internal `Client`, `clients`, `client_id`, relations, and persistence contracts remain unchanged |

## Consequences

- The listed decisions remain normative with their recorded status.
- `SUPERSEDED` entries remain historical evidence and must not be reactivated implicitly.
- Detailed application contracts live in the reference pages linked from [the documentation index](../index.md); those pages may clarify implementation but may not contradict these decisions.
- A future change requires explicit approval and a recorded superseding decision, not a silent edit.
