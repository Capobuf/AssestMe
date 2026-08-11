# Source map

This table maps every top-level section of the former specification 2.7 to its canonical replacement.

| Former section | Canonical replacement |
|---|---|
| 1. Product definition | `docs/explanation/product-and-scope.md` |
| 2. Signed decision register | `docs/adr/README.md` and the nine thematic ADRs |
| 3. Authoritative external references | `docs/reference/external-references.md` |
| 4. Required project files | `docs/reference/repository-layout.md` |
| 5. Installation, development, production | `docs/how-to/development-and-verification.md` and hosting guides |
| 6. Architecture and coding method | `docs/explanation/architecture.md`, root `AGENTS.md` |
| 7. Data model and domain contracts | `docs/reference/domain-and-application-contracts.md` |
| 8. Settings | domain/application reference and reporting reference |
| 9. Filament UI/UX | `docs/reference/ui-persistence-import-export.md` |
| 10. Persistence/autosave/conflict protocol | UI/persistence reference and ADR 0004 |
| 11. Template JSON import/export | domain reference and UI/import reference |
| 12. Evidence files | reports/evidence reference |
| 13. PDF report | reports/evidence reference and ADR 0005 |
| 14. XLSX | UI/import/export reference and ADR 0005 |
| 15. Seed data and fixtures | domain reference, repository layout, testing reference |
| 16. Testing, CI, browser QA, performance | testing/security/acceptance reference and ADR 0008 |
| 17. Security and operations | testing/security/acceptance reference, ADR 0007, hosting guides |
| 18. Git and agent execution policy | root `AGENTS.md`, development how-to |
| 19. Milestones | `_meta/progress.md`, testing/acceptance reference |
| 20. Final acceptance | testing/security/acceptance reference |
| 21. Review resolution matrix | `_meta/validation-report.md` and relevant ADR/reference pages |
| 22. Progress | `_meta/progress.md` |
| 23. Discoveries and deviations | `_meta/discoveries.md` |
| 24. Final outcome | `_meta/final-outcome.md` |

## Decision coverage

D-001 through D-075 are assigned exactly once in `docs/adr/README.md`. Superseded historical versions are retained alongside the applicable base decision.

## Raw historical recovery

The exact source plan is recoverable by commit and blob using `_meta/source-baseline.md`. The active documentation intentionally does not duplicate the old wall-of-text chronology.
