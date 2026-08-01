# Architecture

## Purpose

Describe the stable application shape and the deliberate exclusions that keep AssestMe maintainable.

## Application shape

AssestMe is a conventional Laravel monolith with Filament administration UI and explicit application actions/services.

```text
app/
  Actions/
  Console/
  Data/
  Enums/
  Filament/
  Models/
  Policies/
  Services/
```

Filament resources and pages remain thin. Business behavior belongs in typed application actions or services. Eloquent is used directly; generic repository abstractions are not added.

## Deliberate exclusions

Do not add:

- CQRS;
- microservices;
- speculative modules;
- one-implementation interfaces;
- generic repositories;
- Redis or a queue worker;
- Node.js frontend builds;
- a second report renderer;
- a second source of truth for Findings.

## Persistence model

- Autosave and explicit draft save share the same signed persistence path.
- Workspace saves use optimistic locking and a request UUID for idempotency.
- Template copying creates detached snapshots so later template edits do not change existing Findings.
- Generated files keep complete immutable payload and settings snapshots.
- Completed and archived assessments are read-only until explicitly reopened.
- Filesystem deletion uses staged recovery because SQL and filesystem operations cannot be falsely described as one atomic transaction.
- Local IndexedDB drafts are recovery data only; server persistence remains authoritative.

## Runtime boundaries

- The supported development environment is the project-owned Docker Compose profile.
- The production package is a prebuilt ZIP; Composer and Node.js are not required on the production server.
- The web installer checks real capabilities and never installs operating-system packages.
- No operating-system distribution or CPU architecture is normative.

## Output architecture

- PDF uses one Blade/CSS report and WeasyPrint through Spatie Laravel PDF.
- Preview uses the same renderer service and report view as production.
- XLSX uses PhpSpreadsheet directly.
- JSON templates are validated against the versioned schema with Opis JSON Schema.

## Failure behavior

Failures are explicit. The application must not silently omit data, switch implementation, produce placeholders, or report success after an exception.
