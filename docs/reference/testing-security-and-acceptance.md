# Testing, security, and acceptance

## Verification model

Development and CI verification are autonomous and isolated. Test commands use disposable database
and storage roots and must not mutate configured application data. The layers are deliberately
separate: the pre-commit check owns static checks and Unit tests, the canonical application path owns
Feature and application-browser coverage, and installer acceptance owns only the extracted release.

## Focused checks

Use checks proportional to the affected behavior. A graphical/navigation change does not
automatically require benchmark, backup/restore, complete report suites, storage audit, or the
entire Dusk suite. A focused check does not certify the broader CI acceptance paths.

After a failure, rerun the failing test or command first. Broaden only when the failure or
dependency surface requires it.

## Mandatory pre-commit check

```bash
docker compose -f docker/compose.dev.yml exec -T app scripts/check.sh
```

Run this browser-free command before every commit. It covers exactly:

```bash
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test --testsuite=Unit
```

Unit tests run explicitly with disposable SQLite. This check does not run Feature tests, server
databases, Dusk, installer acceptance, benchmark, storage audit, strict Canary CLI, real
backup/restore, dependency audit, release build, or deployment. CI runs
`composer audit --locked --no-interaction` in the `check` job after this script.

## Canonical application path

```bash
scripts/test-app.sh
```

This path fails unless `ASSESTME_TEST_DB_DRIVER=mariadb`. It runs the complete Feature suite once on
MariaDB 12.3.3, the real `ServerDatabaseBackupRestoreTest` round trip on that database, and exactly
the four maintained application Browser files. It does not rerun Unit tests, Composer validation,
Pint, PHPStan, or another aggregate gate.

The separate `installer` CI job builds the release ZIP once, validates and extracts it, then runs
`ReleaseInstallationTest` once against MariaDB 12.3.3. It verifies final lock/state, health, real
login, diagnostics, and scheduler behavior without rerunning the application suite.

## Database coverage

MariaDB 12.3.3 is the pinned canonical CI database. SQLite and MySQL 8.4.11 run only a small
compatibility smoke covering migrations and seeds, product/capability detection, and fundamental
diagnostics. Compatibility smoke runs on pushes to `develop`/`main` and manual dispatch, not ordinary
pull requests. SQLite and MySQL do not run the complete Feature suite, Dusk, or real backup/restore.

## Browser evidence

The maintained automated browser set contains five files total: four application/browser-specific
files plus the separate release installer. Application Dusk covers login/workspace/save/report
smoke, IndexedDB local-draft recovery, functional risk-matrix interaction, and reactive PDF preview
fullscreen behavior. It does not use screenshot or pixel geometry assertions as normative coverage.
Manual physical checks remain required for:

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

For the optional Fatture in Cloud quote integration:

- OAuth authorization is stateful and requests exactly
  `entity.clients:a products:r settings:r issued_documents.quotes:a`;
- the client secret, access token, and refresh token are encrypted and are never rendered, logged,
  or included in notifications; a blank secret save retains the effective value;
- the callback URI is generated from the authenticated named callback route and shown read-only;
- callback adoption requires exactly one provider company and the configured VAT default must be an
  enabled type returned live for that company;
- one controlled token refresh is allowed after an unauthorized API response; rotated refresh tokens
  replace the encrypted prior value;
- 401, 403, 429, invalid JSON, timeout, and provider failures become bounded actionable Italian
  states, without raw provider bodies or secrets;
- local disconnect clears connection state and explains separate provider-side revocation because
  the verified v2 contract exposes no revocation endpoint;
- provider behavior is covered with Laravel HTTP fakes; automated tests never require credentials or
  contact the live provider. Real OAuth/provider acceptance remains `NOT VERIFIED` until executed and
  recorded explicitly.

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

On 2026-09-12 the refactored local verification paths passed against the pinned MariaDB 12.3.3 and
MySQL 8.4.11 Compose services: the pre-commit check, complete Feature suite, real MariaDB
backup/restore, four-file application Dusk set, extracted-ZIP MariaDB installer, and bounded
SQLite/MySQL compatibility smokes. Exact counts and timings are recorded in
[`docs/_meta/progress.md`](../_meta/progress.md). GitHub-hosted workflow execution, publication,
CloudPanel deployment, and the physical Edge, Firefox, iOS Safari, and Android Chrome checklist
remain `NOT VERIFIED`.
