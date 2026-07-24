# AGENTS.md — AssestMe

Read `plan.md` completely before changing code. `plan.md` version 2.4 is the authoritative product, architecture, deployment, data, UI, persistence, import, report, test, and acceptance specification.

## Authority

- Decisions marked `APPROVED` are normative.
- You may update only Progress, Discoveries, exact locked dependency versions, and factual command results without user approval.
- Do not reinterpret, weaken, or silently supersede an approved decision.
- When an approved central decision is technically impossible, mark it `BLOCKED`, provide reproducible evidence, stop the affected milestone, and do not implement a fallback.
- A mandatory native Filament workspace capability blocks the affected milestone. PDF rendering follows the completed D-009/D-059 selection; do not add a fallback or parallel production renderer.

## Required stack

- No host operating-system distribution is normative.
- PHP 8.3 with the extensions locked by the plan.
- Laravel 13.
- Filament 5.
- SQLite.
- Filament Table Builder as the authoritative Finding navigator, with native Filament components for the selected-Finding editor.
- Spatie Laravel PDF with WeasyPrint as the sole production renderer.
- PhpSpreadsheet.
- Opis JSON Schema.
- File cache/session and synchronous queue.
- Laravel Dusk for browser testing.

Do not require Node.js, npm, pnpm, Vite builds, Redis, Chromium, or an external cloud PDF/storage/service dependency. The optional Selenium service is browser-test infrastructure only and is not part of PDF generation. Docker Compose through `docker/compose.dev.yml` is the only supported development installation profile. CloudPanel is the approved production destination, but its configuration and procedure are not implemented yet.

## No fallback and no fake success

Never:

- switch the native workspace implementation, PDF driver, database, storage, or export library automatically;
- omit failed findings, evidence, attachments, import rows, or report sections;
- catch an exception and show success;
- create TODO placeholders in a completed milestone;
- return empty files or mock reports;
- patch files under `vendor/`;
- create a fork without explicit approval.

## Working method

1. Inspect runtime, repository, `composer.lock`, and current application state.
2. Read this file and all of `plan.md`.
3. Update Progress before the milestone.
4. Implement one vertical slice.
5. Add success and failure-path tests.
6. Run focused checks selected from the files and behavior changed, rerunning a failed command or test before broadening the scope.
7. Run `scripts/verify.sh` only at the conclusion of a coherent set of changes, before merging to `main`, at milestone completion, after dependency/runtime/test-infrastructure changes, or when explicitly requested. Ordinary implementation prompts and intermediate edits do not implicitly start the complete gate.
8. Record discoveries, locked versions, and evidence in `plan.md`.
9. Keep `main` releasable.
10. Do not declare completion until URL, login, PDF, XLSX, backup/restore, automated tests, manual QA, and benchmark pass.

Run development and verification commands through the Docker Compose development profile. Environment-neutral scripts may remain directly executable for CI, diagnostics, and internal reuse, but direct-host PHP/Composer development is not a supported installation method.

## Architecture

- Keep Filament resources/pages thin.
- Put business behavior in typed actions/services.
- Use Eloquent directly; do not add generic repositories.
- Do not add CQRS, microservices, modules, speculative adapters, or one-implementation interfaces.
- Autosave and explicit save must use the signed persistence protocol.
- Template copying creates detached finding/solution snapshots.
- Generated files store complete immutable payload and settings snapshots.
- Filesystem deletion uses staged recovery operations.
- Completed/archived assessments are read-only until explicit reopening.
- Every save uses optimistic locking and an idempotency request UUID.

## Code

- Add `declare(strict_types=1);` to every new PHP file.
- Use English for identifiers, commits, tests, comments, and docblocks.
- Use Italian translation keys for all v1 user-visible text.
- Use typed properties, arguments, returns, enums, DTOs, and array shapes.
- Do not use dynamic properties, unbounded `mixed`, direct `env()` calls outside configuration, debug calls, ignored static-analysis errors, or a PHPStan baseline.
- Comments explain non-obvious technical/business reasons; they do not narrate syntax.
- Do not render untrusted HTML.

## Data and files

- Store timestamps UTC and display Europe/Rome.
- Preserve SQLite PRAGMAs and portable migrations.
- Do not expose private storage or SQLite under the public root.
- Use generated physical filenames and SHA-256.
- Reject unsupported file types exactly as listed in the plan.
- Do not invent implicit merge behavior for JSON import.
- Do not delete a referenced solution.
- Do not create more than one user.
- Risk profiles own consequence, likelihood, priority, and matrix records; effort levels are global.
- Referenced risk/effort records are disabled, never deleted.
- Do not add VAT treatment/display fields, enums, settings, rates, taxable amounts, tax amounts, or fiscal calculations. VAT numbers are anagraphic identifiers only.
- PDF and XLSX each contain the fixed note `Tutti gli importi indicati sono stime orientative e si intendono IVA esclusa.` exactly once, never per amount.
- Report consultant fields are optional name, business name, role, email, phone, website, address, VAT number, PEC, tax code, private logo, and text-only signature name/role. Do not add handwritten-signature upload in v1.

## UI

- The Finding workspace is an application-style three-area workbench: compact navigator, dominant editor, and contextual properties panel.
- Filament Table Builder remains the authoritative Finding list engine and must be proven with 10, 25, and 50 findings.
- Long fields are multiline; solutions and evidence are edited in the selected-Finding workbench, never in navigator cells.
- Show explicit saving, saved, unsaved, offline, error, and conflict states.
- Right-click actions always have visible accessible equivalents.
- Do not use color alone.
- Narrow viewports use deliberate sequential navigator/editor views; contextual properties remain accessible from the editor.
- Mobile supports essential vertical editing and evidence capture; do not claim desktop workbench parity.

## Tests and gates

The authoritative complete gate is:

```bash
scripts/verify.sh
```

It orchestrates the required Composer validation/audit, Pint, PHPStan, application tests, strict Canary, diagnostics, the 50-Finding benchmark, storage audit, and Dusk exactly once. `composer quality` and `composer browser` remain supported focused subgates. Successful subgates write content- and runtime-addressed receipts, so `scripts/verify.sh` may reuse them only when the exact executable source, authoritative plan through section 21, dependency, and runtime fingerprint still matches. Factual updates confined to plan Progress, Discoveries, or Final outcome do not invalidate executable evidence. Focused Dusk invocations never write a full-browser receipt. Any relevant source, dependency, or runtime change invalidates reuse.

During one implementation, verification is proportional to the affected areas. A graphical or navigation change does not automatically require the benchmark, backup/restore, complete PDF or XLSX suites, storage audit, or the whole Dusk suite. Focused checks do not weaken, replace, or certify the complete gate. After a failure, rerun the failing test or command first; broaden only when the failure or dependency surface justifies it.

The underlying commands remain independently callable for diagnosis, but do not run them and then repeat them through the aggregate gate without receipt reuse:

```bash
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test
php artisan canary:check --strict
php artisan dusk
composer audit --locked
php artisan assestme:diagnose
php artisan assestme:benchmark --findings=50
```

If the installed Canary command differs, use its official strict zero-skip command and update the plan.

- No skipped Canary page.
- No browser console error.
- No warning, notice, or deprecation.
- Use file SQLite for locking/storage/integration tests.
- Complete the manual Edge, Firefox, iOS Safari, and Android Chrome checklist.
- Do not replace tests with manual confidence.

## Documentation policy

Do not create additional product, architecture, or feature Markdown files.

Allowed: code, comments, translations, shell scripts, configuration stubs, JSON schemas/fixtures, CI workflows, and test artifacts. `plan.md` remains the only product/architecture document.

## Final handoff

Start the app through `docker/compose.dev.yml`, verify a real login, generate and validate real PDF/XLSX files, verify backup/restore, run all gates, and report the exact local URL and one-time local test credentials. Never print production credentials. Do not implement CloudPanel without a later approved decision.
