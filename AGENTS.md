# AGENTS.md — AssestMe

Read [docs/index.md](docs/index.md) before changing code. Read only the ADRs and reference pages relevant to the affected area; do not load historical progress or the archived source unless the task requires it.

## Authority

Apply these sources in order:

1. the user's explicit instruction for the current task;
2. an `Accepted` ADR, using the newest ADR when one explicitly supersedes another;
3. canonical reference and explanation pages linked by `docs/index.md`;
4. operational how-to guides;
5. `README.md` and the root `plan.md` compatibility index.

An agent must not silently reinterpret, weaken, or supersede an accepted decision. A central decision that is technically impossible must be marked `BLOCKED` with reproducible evidence; do not implement a fallback without explicit approval.

## Required stack

- PHP web and CLI 8.3.0 or newer; no host operating-system distribution is normative.
- Laravel 13, Filament 5, and Livewire 4.
- SQLite, MySQL, and MariaDB for fresh installations; no cross-driver data migration.
- Filament Table Builder as the authoritative Finding navigator.
- Spatie Laravel PDF with WeasyPrint as the sole production renderer.
- PhpSpreadsheet for assessment XLSX output.
- Opis JSON Schema for template interchange validation.
- File cache/session and synchronous queue.
- Laravel Dusk for browser behavior tests.

Do not require Node.js, npm, pnpm, Vite builds, Redis, Chromium, or external cloud PDF/storage services. Selenium is optional browser-test infrastructure only.

## No fallback and no fake success

Never:

- switch the workspace implementation, PDF driver, database, storage, or export library automatically;
- omit failed findings, evidence, attachments, import rows, or report sections;
- catch an exception and report success;
- create TODO placeholders in a completed milestone;
- return empty or mock generated files;
- edit `vendor/`;
- create a fork without explicit approval.

## Working method

1. Inspect the current branch, runtime, repository state, `composer.lock`, and relevant code.
2. Read this file, `docs/index.md`, and the relevant ADR/reference pages.
3. Record the task in `docs/_meta/progress.md` only when real work starts.
4. Implement one coherent vertical slice.
5. Add success and failure-path tests consistent with the existing suite.
6. Run focused checks selected from the actual change surface. Rerun the failing test or command before broadening scope.
7. Run `scripts/verify.sh` only at the conclusion of a coherent change set, before merging to `main`, at milestone completion, after dependency/runtime/test-infrastructure changes, or when explicitly requested.
8. Record factual discoveries in `docs/_meta/discoveries.md`; do not use discoveries to silently create new requirements.
9. Update an ADR only when an accepted architectural decision changes with explicit approval.
10. Keep `main` releasable and do not declare completion without the required evidence.

## Architecture

- Keep Filament resources and pages thin.
- Put business behavior in typed actions and services.
- Use Eloquent directly; do not add generic repositories.
- Do not add CQRS, microservices, modules, speculative adapters, or one-implementation interfaces.
- Autosave and explicit save use the same signed persistence protocol.
- Template copying creates detached Finding and solution snapshots.
- Generated files store complete immutable payload and settings snapshots.
- Filesystem deletion uses staged recovery operations.
- Completed or archived assessments remain read-only until explicitly reopened.
- Every workspace save uses optimistic locking and an idempotency request UUID.

## Code

- Add `declare(strict_types=1);` to every new PHP file.
- Use English for identifiers, commits, tests, comments, and docblocks.
- Use Italian translation keys for all v1 user-visible text.
- Use typed properties, arguments, returns, enums, DTOs, and documented array shapes.
- Do not use dynamic properties, unbounded `mixed`, direct `env()` outside configuration, debug calls, ignored static-analysis errors, or a PHPStan baseline.
- Comments explain non-obvious technical or business reasons, not syntax.
- Do not render untrusted HTML.

## Data and files

- Store timestamps in UTC and present them in `Europe/Rome`.
- Preserve SQLite PRAGMAs, portable migrations, and explicit MySQL/MariaDB capability checks.
- Keep private storage and SQLite outside `public`.
- Use generated physical filenames and SHA-256 hashes.
- Do not invent merge behavior for JSON import.
- Do not delete a referenced solution.
- Do not create more than one user.
- Referenced risk or effort records are disabled, not deleted.
- Do not model VAT treatment or calculations. VAT numbers are anagraphic identifiers only.
- PDF and XLSX each contain `Tutti gli importi indicati sono stime orientative e si intendono IVA esclusa.` exactly once.

## UI

- The Finding workspace is a three-area workbench: compact navigator, dominant editor, contextual properties.
- Filament Table Builder remains the authoritative navigator and must support the approved 10/25/50-Finding evidence.
- Long fields, solutions, and evidence are edited in the selected-Finding workbench, not navigator cells.
- Show saving, saved, unsaved, offline, error, and conflict states explicitly.
- Right-click actions always have visible accessible equivalents.
- Do not communicate state with color alone.
- Narrow containers use deliberate sequential navigator/editor behavior.
- Mobile supports essential vertical editing and evidence capture; do not claim desktop parity.

## Tests and gates

The complete gate is:

```bash
scripts/verify.sh
```

It orchestrates Composer validation/audit, Pint, PHPStan, application tests, strict Canary, diagnostics, the 50-Finding benchmark, storage audit, and Dusk. Focused checks remain valid during implementation but do not replace the complete gate.

Manual Edge, Firefox, iOS Safari, Android Chrome, and real-hosting acceptance must never be reported as passed without execution evidence.

## Documentation policy

- `docs/index.md` is the canonical documentation map.
- ADRs contain durable architectural decisions only.
- Reference pages contain contracts and exact behavior.
- How-to pages contain procedures.
- `_meta` contains source traceability, current progress, discoveries, outcome, and migration evidence.
- Do not duplicate a requirement across documents. Link to the canonical source.
- Keep `plan.md` as a short compatibility index only.
- Do not create a new Markdown file when an existing canonical page can be updated cleanly.

## Final handoff

Start the application through `docker/compose.dev.yml`, verify a real login, generate and validate real PDF/XLSX files, verify backup/restore, run applicable gates, and report exact evidence. Never print production credentials or claim a real environment that was not tested.
