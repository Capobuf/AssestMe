# Testing, security, and acceptance

## Verification model

Development and CI verification are autonomous and isolated. Maintained commands must not read, migrate, truncate, seed, back up, restore, benchmark, or otherwise mutate the configured application database or private storage.

Tests create disposable database and storage roots and clean them on success and failure. Backup/restore and deployment tests operate only on temporary paths.

## Focused checks

Use checks proportional to the affected behavior. A graphical/navigation change does not automatically require benchmark, backup/restore, complete report suites, storage audit, or the entire Dusk suite. A focused check does not certify the complete gate.

After a failure, rerun the failing test or command first. Broaden only when the failure or dependency surface requires it.

## Complete gate

```bash
docker compose -f docker/compose.dev.yml exec -T app scripts/verify.sh
```

The aggregate gate is fail-closed outside the marked `app` service from
`docker/compose.dev.yml`. Host execution is not a supported verification path and is rejected before
Composer, database, storage, benchmark, or browser work begins.

The aggregate gate executes each expensive gate at most once and covers:

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

It also includes storage audit and the maintained report/export/backup evidence. No skipped Canary page, browser console error, PHP warning, notice, or deprecation is accepted.

## Database matrix

CI proves common behavior using real SQLite, MySQL, and MariaDB services at explicitly pinned versions. Documentation must not describe an unexecuted database/server version as compatible or certified.

## Browser evidence

The maintained Dusk suite is the automated browser gate. Manual physical checks remain required for:

- Edge;
- Firefox;
- iOS Safari;
- Android Chrome;
- device/camera behavior where applicable.

These checks are `NOT VERIFIED` until executed on a reference installation and suitable devices.

## Security requirements

- No public registration or client access.
- Exactly one administrator.
- Optional TOTP MFA with recovery codes and CLI reset.
- HTTPS before production credential entry.
- Temporary Basic Authentication or IP restriction before first administrator creation on an exposed production site.
- Installer state is encrypted and resumable without storing administrator credentials.
- `.env` writes are allowlisted and atomic.
- Finalization uses filesystem locking.
- A private irreversible installed lock closes `/install`; installed or anomalous instances never reopen it.
- Private storage and SQLite remain outside `public`.
- Untrusted HTML is not rendered.
- No secrets, passwords, cookies, or `.env` contents are placed in acceptance evidence.

For the optional Google Drive output integration:

- OAuth is stateful and requests offline consent;
- `drive.file` is the sole Drive data scope; `openid` and `email` are identity scopes used to show
  the connected account;
- only client ID and client secret are configurable in the native Settings page; the secret is
  encrypted and write-only, while a blank value retains the current effective secret;
- the callback URI is calculated from the named AssestMe route, displayed read-only, and copyable;
- changing the effective OAuth client ID or client secret disables synchronization and clears the
  prior connection/root;
- the refresh credential is encrypted in the dedicated settings group and is never sent to the
  browser, logs, notifications, command output, or persisted sync errors;
- after OAuth, AssestMe creates a new `AssestMe` folder directly in My Drive and persists its
  Google-returned ID; it neither searches for nor adopts a same-name folder;
- no browser selector, browser access-token endpoint, API key, or project number is used;
- provider failures are converted to bounded actionable Italian messages;
- application locks use the configured file cache, and assessments are processed in chunks of at
  most 50;
- automated tests replace the Google boundary and make no real Google request.

Stored client and refresh secrets are never rendered. On 2026-08-11 the administrator reported a
successful real OAuth connection, and an AssestMe forced run against that connected account completed
3 of 3 assessments through real Drive and Sheets APIs after the exact-parent and dense-row fixes.
The OAuth callback journey was not independently observed and the complete manual lifecycle checklist
remains `NOT VERIFIED`; this bounded evidence does not claim Shared Drive support or exhaustive
provider acceptance.

## Installer acceptance

The installer performs real deterministic probes for:

- PHP web and CLI 8.3.0+;
- required extensions;
- secure writable paths;
- WeasyPrint and a minimal PDF;
- database product and capabilities;
- empty/new database boundary;
- migrations and idempotent domain seeds;
- singleton administrator creation;
- PDF and health checks;
- scheduler command and heartbeat;
- backup state appropriate to the selected driver.

It does not ask for application name or ordinary PHP/WeasyPrint/client paths. `APP_NAME` remains `AssestMe`. Optional environment overrides exist only for non-standard paths.

## Final product acceptance

Completion requires evidence for:

- reproducible local startup and real login;
- all automated gates;
- 10/25/50-Finding workspace behavior and benchmark;
- real PDF and XLSX generation/validation;
- backup verification and restore on disposable data;
- installer closure and login;
- scheduler heartbeat;
- real hosting acceptance where claimed;
- manual browser/device checklist.

## Current verified outcome

The D-063–D-070 release/installer and SQLite/MySQL/MariaDB scope passed the complete local automated gate recorded in the source plan. Real CloudPanel acceptance and the physical Edge, Firefox, iOS Safari, and Android Chrome checklist remain `NOT VERIFIED`. Global product acceptance therefore remains open.
