# AssestMe documentation

## Purpose

This page is the canonical navigation and authority map. It replaces the former use of one monolithic `plan.md` for product, architecture, operations, progress, and history.

## Read by task

| Task | Required documents |
|---|---|
| Any code change | Root `AGENTS.md`, this index, relevant ADR and reference page |
| Product scope or terminology | `explanation/product-and-scope.md`, ADR 0001 |
| Architecture or dependency change | `explanation/architecture.md`, ADR 0002, relevant domain ADR |
| Models, migrations, import, risk | `reference/domain-and-application-contracts.md`, ADR 0003 |
| Workspace, navigation, save, offline drafts | `reference/ui-persistence-import-export.md`, ADR 0004 |
| PDF, XLSX, evidence, generated files | `reference/reports-evidence-and-files.md`, ADR 0005 |
| Backup, restore, deletion | ADR 0006 and report/file reference |
| Authentication or installer state | ADR 0007 and ADR 0009 |
| Tests, CI, acceptance | `reference/testing-security-and-acceptance.md`, ADR 0008 |
| Local development | `how-to/development-and-verification.md` |
| Production hosting | `how-to/hosting-installation.md` and the applicable panel page |
| Documentation migration or audit | `_meta/source-map.md`, `_meta/migration.md`, `_meta/validation-report.md` |

## Canonical documents

### Explanation

- [Product and scope](explanation/product-and-scope.md)
- [Architecture](explanation/architecture.md)

### Reference

- [Runtime and dependencies](reference/runtime-and-dependencies.md)
- [Domain and application contracts](reference/domain-and-application-contracts.md)
- [UI, persistence, import, and export](reference/ui-persistence-import-export.md)
- [Reports, evidence, and private files](reference/reports-evidence-and-files.md)
- [Testing, security, and acceptance](reference/testing-security-and-acceptance.md)
- [Repository layout](reference/repository-layout.md)
- [External references](reference/external-references.md)

### How-to

- [Development and verification](how-to/development-and-verification.md)
- [Hosting installation](how-to/hosting-installation.md)
- [cPanel installation](how-to/cpanel-installation.md)
- [CloudPanel installation](how-to/cloudpanel-installation.md)
- [CloudPanel acceptance checklist](how-to/cloudpanel-acceptance-checklist.md)

### Decisions

- [ADR index](adr/README.md)

### Project evidence

- [Source baseline](./_meta/source-baseline.md)
- [Source map](./_meta/source-map.md)
- [Progress](./_meta/progress.md)
- [Discoveries](./_meta/discoveries.md)
- [Final outcome](./_meta/final-outcome.md)
- [Migration procedure](./_meta/migration.md)
- [Validation report](./_meta/validation-report.md)

## Authority and change rules

1. Accepted ADRs are normative for architectural decisions.
2. Reference pages are normative for application contracts and exact behavior.
3. Explanation pages define context and boundaries but do not override an ADR or reference contract.
4. How-to pages explain operations and must not silently introduce product behavior.
5. `_meta/progress.md`, `_meta/discoveries.md`, and `_meta/final-outcome.md` record evidence; they do not create requirements.
6. An accepted decision changes only through explicit approval and a superseding ADR entry.
7. Unknown or unexecuted evidence is written as `NOT VERIFIED`, never inferred.

## Documentation scope

The structure is intentionally limited. Do not add tutorials, a wiki, one page per feature, or one ADR per minor UI change unless an actual durable decision requires it.
