# AGENTS.md — AssestMe

Read `plan.md` completely before changing code. `plan.md` version 2.3 is the authoritative product, architecture, deployment, data, UI, persistence, import, report, test, and acceptance specification.

## Authority

- Decisions marked `APPROVED` are normative.
- You may update only Progress, Discoveries, exact locked dependency versions, and factual command results without user approval.
- Do not reinterpret, weaken, or silently supersede an approved decision.
- When an approved central decision is technically impossible, mark it `BLOCKED`, provide reproducible evidence, stop the affected milestone, and do not implement a fallback.
- A mandatory native Filament workspace capability or DOMPDF incompatibility blocks the affected milestone.

## Required stack

- No host operating-system distribution is normative.
- PHP 8.3 with the extensions locked by the plan.
- Laravel 13.
- Filament 5.
- SQLite.
- Native Filament 5 `Repeater::table()` for the assessment workspace.
- Spatie Laravel PDF with DOMPDF.
- PhpSpreadsheet.
- Opis JSON Schema.
- File cache/session and synchronous queue.
- Laravel Dusk for browser testing.

Do not require Node.js, npm, pnpm, Vite builds, Redis, Chromium in production, or an external PDF/storage/service dependency. Docker is permitted for future packaging but is not a current development, verification, or runtime requirement.

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
6. Run focused tests.
7. Run `composer quality`, Dusk where applicable, diagnostics, and the milestone acceptance commands.
8. Record discoveries, locked versions, and evidence in `plan.md`.
9. Keep `main` releasable.
10. Do not declare completion until URL, login, PDF, XLSX, backup/restore, automated tests, manual QA, and benchmark pass.

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

- The native Filament 5 table Repeater is the central grid and must be proven with 10, 25, and 50 findings.
- Long fields are multiline.
- Nested solutions/evidence use a row slide-over, not visible grid cells.
- Show explicit saving, saved, unsaved, offline, error, and conflict states.
- Right-click actions always have visible accessible equivalents.
- Do not use color alone.
- Mobile supports essential vertical editing and evidence capture; do not claim spreadsheet parity.

## Tests and gates

Required:

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
scripts/verify.sh
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

Start the app, verify a real login, generate and validate real PDF/XLSX files, verify backup/restore, run all gates, and report the exact local URL and one-time local test credentials. Never print production credentials.
