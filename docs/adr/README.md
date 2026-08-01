# Architecture decision records

## Rules

- Accepted ADR decisions are normative.
- Each base decision ID D-001 through D-070 appears in exactly one thematic ADR.
- Historical superseded versions remain in the same thematic ADR as their base ID.
- Do not create an ADR for a bug fix, ordinary UI refinement, progress entry, command result, or dependency version already locked by `composer.lock`.
- A decision changes only with explicit approval and a superseding entry.

## Index

| ADR | Area | Decision IDs |
|---|---|---|
| [0001-product-boundary-and-language](0001-product-boundary-and-language.md) | Product boundary and language | D-002, D-011, D-014, D-015, D-034, D-035, D-039, D-040, D-043 |
| [0002-runtime-and-dependencies](0002-runtime-and-dependencies.md) | Runtime and dependencies | D-001, D-003, D-004, D-005, D-006, D-007, D-030, D-031, D-036, D-037, D-042 |
| [0003-domain-data-and-import](0003-domain-data-and-import.md) | Domain, data, and import | D-012, D-013, D-024, D-025, D-032, D-033, D-038, D-045, D-048, D-049, D-053, D-063 |
| [0004-workspace-ui-and-persistence](0004-workspace-ui-and-persistence.md) | Workspace, UI, and persistence | D-008, D-016, D-020, D-021, D-044, D-046, D-047, D-054, D-055, D-056, D-057, D-058, D-062 |
| [0005-reporting-and-exports](0005-reporting-and-exports.md) | Reporting and exports | D-009, D-010, D-022, D-026, D-050, D-051, D-052, D-059, D-060 |
| [0006-storage-deletion-and-backup](0006-storage-deletion-and-backup.md) | Storage, deletion, and backup | D-017, D-018, D-023, D-029, D-061, D-066 |
| [0007-security-and-installation-state](0007-security-and-installation-state.md) | Security and installation state | D-028, D-065 |
| [0008-testing-and-verification](0008-testing-and-verification.md) | Testing and verification | D-027, D-041, D-067 |
| [0009-deployment-and-installer](0009-deployment-and-installer.md) | Deployment and installer | D-019, D-064, D-068, D-069, D-070 |

## Coverage

- Base IDs present: 70/70.
- Duplicate base IDs across ADRs: 0.
- Missing base IDs: 0.
- Baseline: specification 2.7, source `plan.md` blob `c201bf64ec39519e1f26eba5f18e63886f3ae8ae`.
