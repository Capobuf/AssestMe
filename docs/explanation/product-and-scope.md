# Product and scope

## Purpose

Define what AssestMe is, what it must support, and what remains outside the first product boundary.

## Product

AssestMe is an internal, single-instance web application used by one administrator to conduct structured IT assessments for multiple small and medium-sized organizations.

The administrator can:

1. manage companies and their sites;
2. optionally register generic or detailed IT assets;
3. maintain a reusable Finding-template library;
4. import and export templates through a versioned JSON contract;
5. create an assessment for a company;
6. add, locate, edit, duplicate, order, and remove Findings through the approved structured navigator and selected-record editor;
7. scope a Finding to the whole company, selected sites, a network, selected assets, or custom text;
8. define multiple remediation solutions;
9. select one recommended solution and, for resolved Findings, one implemented solution;
10. evaluate priority manually or through a consequence × likelihood matrix;
11. record implementation effort and indicative economic estimates;
12. attach evidence as approved files or links;
13. generate a configurable professional PDF;
14. generate a complete textual XLSX export;
15. archive or permanently delete data according to the global deletion policy.

When the optional Fatture in Cloud connection is configured, an editable Assessment may also open a
transient Italian quote composer and create the resulting quote directly in the single connected
provider company. AssestMe does not persist the composed rows, remote quote history, totals, or a
commercial document snapshot.

## Explicit exclusions

AssestMe is not:

- a local quotation or invoicing system;
- a project-management system;
- a vulnerability scanner;
- a CMDB requiring complete asset registration;
- a multi-tenant SaaS;
- a client portal;
- an AI service;
- an audit-trail system;
- a document version-control system.

There is no public API, MCP server, Jira integration, runZero integration, or scanner import in v1. AI may generate external JSON template libraries, but AssestMe does not store or infer whether AI produced a template.

## Account model

- Exactly one row may exist in `users`.
- There is no public registration, client authentication, role system, or permission package.
- The singleton is enforced by database and application validation and by tests.
- Optional TOTP MFA includes recovery codes and a CLI reset path.

## Language and terminology

- UI and first-release reports are Italian.
- Translation keys are used from the first implementation.
- User-facing terminology is `Azienda`, `Aziende`, and `Intera azienda`.
- Existing English implementation identifiers such as `Client`, `clients`, `client_id`, and relations remain unchanged.
- Code, identifiers, tests, comments, docblocks, and commit messages are English.
- The spelling `AssestMe` is intentional and final.

## Economic boundary

- Assessment values remain indicative estimates. A quote exists only when explicitly composed and
  created in Fatture in Cloud.
- Current provider VAT types may be referenced transiently on remote quote rows. VAT treatment,
  taxable amounts, tax amounts, and fiscal calculations are not modeled or persisted by AssestMe.
- PDF and XLSX each contain the fixed VAT-excluded note exactly once.
- VAT numbers remain anagraphic identifiers.

## Asset boundary

Asset registration and Finding asset association are optional for every Finding scope, including `selected_assets` and Findings copied from imported templates. Any selected asset must still belong to the assessment company.
