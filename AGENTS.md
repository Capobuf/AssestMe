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
7. Before creating every commit, run `docker compose -f docker/compose.dev.yml exec -T app scripts/check.sh`. Run focused MariaDB, browser, installer, or compatibility checks when the affected surface requires them; CI owns the complete separated paths.
8. Record factual discoveries in `docs/_meta/discoveries.md`; do not use discoveries to silently create new requirements.
9. Update an ADR only when an accepted architectural decision changes with explicit approval.
10. Keep `main` releasable and do not declare completion without the required evidence.

## Branch policy

`main` is the only permanent branch. Do not use permanent integration branches such as `develop`,
and keep `main` releasable.

A temporary work branch is mandatory for every change that affects project behavior or operation,
regardless of size. This includes features, fixes, functional refactoring, tests, migrations,
dependencies, CI, builds, deployments, and operational configuration. Read-only analysis and checks,
and exclusively documentary changes, do not require a branch. Documentation that accompanies a
functional change belongs on the same work branch.

Before starting a change that requires a branch:

1. update and inspect local and remote refs;
2. identify every branch not yet merged into `main`;
3. determine each branch's purpose from its name, commits, diff, and pull request when present;
4. compare that purpose with the current request.

A branch is open while it contains work not merged into `main`. If an open branch clearly concerns
the same functional area as the new request, continue there without asking for confirmation. Judge
relevance by functional area, not only by the wording of the original request, and do not create
parallel branches for successive changes in the same area. If relevance is ambiguous, ask before
changing the repository.

When no relevant branch exists and no other branch is open, create a short, descriptive work branch
from the latest `main`. When unrelated branches are open, stop before changing the repository and,
for each branch, report its name, purpose, completed and pending work, verification results, relation
to `main`, and any pull request. Ask whether to resume, verify, or complete an existing branch; leave
it open and authorize a new branch; or stop the new activity. Do not choose on the user's behalf.

At the end of work, run the applicable checks and report the changes, commit, branch state, and exact
results. Ask for explicit confirmation before merging. Do not merge while a mandatory check has
failed or was not run. After confirmation, update the branch from the latest `main`, resolve conflicts,
and complete any resulting changes or checks before merging directly into `main`; a pull request is
not required. Verify that `main` contains the final commit, then delete the local and remote work
branch. Without confirmation, leave the branch open and say so. Later work in the same area requires
a new branch after the prior branch has been merged and deleted.

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

The mandatory pre-commit gate is browser-free and runs Composer validation, Pint, PHPStan, and the Unit suite once against disposable SQLite:

```bash
docker compose -f docker/compose.dev.yml exec -T app scripts/check.sh
```

CI runs the complete Feature suite once on MariaDB 12.3.3, the real MariaDB backup/restore round
trip, four maintained application Dusk files, and one extracted-release MariaDB installer journey.
SQLite and MySQL 8.4.11 receive only bounded migration/seed, capability, and diagnostic smokes on
pushes to `main` and manual runs. Composer audit runs only in CI. Focused checks remain
valid during implementation but do not replace the applicable CI path.

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

Start the application through `docker/compose.dev.yml` when application behavior is affected, run the mandatory pre-commit check plus every applicable focused CI path, and report exact evidence. Never print production credentials or claim a real environment that was not tested.
