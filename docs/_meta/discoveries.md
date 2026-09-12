# Discoveries

- 2026-08-13: Acceptance run `31693715445` showed that exporting `ASSESTME_FIC_DUSK_FAKE=1` around `scripts/verify.sh` also changes the preceding application suite; the fake must be enabled only inside the Dusk runner.
- 2026-08-13: On the GitHub Ubuntu runner with PHP 8.3.33, the extracted-release HTTP process terminated with a segmentation fault immediately after beginning the in-process `migrate` Artisan call. Installer Artisan work must use the already-approved bounded Symfony Process boundary and the validated pending production environment.
- 2026-09-09: Both attempts of acceptance run `34325914898` terminated the host PHP 8.3.33
  built-in server with `SIGSEGV`/exit 139 at different request phases, while the same commit passed
  the complete Compose gate. The Ubuntu 24.04 setup-php runtime loads shared XSL by default, but
  neither AssestMe's runtime contract nor its locked production packages require `ext-xsl`.
- 2026-09-09: Disabling shared XSL did not prevent the native termination: run `34336782783`
  confirmed XSL disabled, then its release server exited 139 at
  `installer.finalize.database_probe.begin`. A successful run on the preceding commit used the
  same runner image and PHP 8.3.33, so the available evidence establishes an intermittent native
  failure, not an application assertion failure or a deterministic PHP-version boundary.
- 2026-09-09: Run `34338488153` recognized the first release-server exit 139 and repeated the
  complete journey against a fresh extraction, but the second server also exited 139 at a different
  finalization phase. Setup-php's documented Ubuntu 24.04 PHP 8.3 default contains dozens of shared
  extensions outside AssestMe's runtime contract; its `none` extension directive disables them all
  before explicitly requested extensions are enabled again.

- On the inspected `develop` HEAD, `scripts/preflight.sh` performs only local runtime, extension,
  project, database-driver, and WeasyPrint probes; it starts no HTTP server or browser process and is
  safe to compose into the browser-free core gate.
- The former release builder and post-deploy smoke script contained no CloudPanel API, filesystem,
  service, or control-panel contract. Their technical scope is the generic prebuilt shared-hosting
  release, while `deploy/cloudpanel`, `CLOUDPANEL_*`, and `Deploy CloudPanel CI` remain actual
  CloudPanel responsibilities.
- The canonical extracted-release journey exposed that Laravel's in-process `optimize` command boots
  fresh application state while building the configuration cache and can discard view data shared by
  the active installer request. Running only the post-install optimization as the already-approved
  bounded Symfony subprocess preserves the final response and keeps a nonzero exit fail-closed.
- Laravel Dusk in the locked dependency set writes screenshots, DOM sources, and browser-console
  logs under `tests/Browser/screenshots`, `tests/Browser/source`, and `tests/Browser/console`.
  Its automatic failure handling does not persist DOM for every PHPUnit assertion path, so an
  installer diagnostic that fails through `Assert::fail()` must call `storeSource()` explicitly.

## Documentation migration discoveries

- The inspected `plan.md` is specification 2.7.
- The inspected root `AGENTS.md` still declared plan version 2.4; this was stale and is removed in the replacement.
- The former documentation policy prohibited additional product/architecture Markdown files. This package explicitly replaces that policy through the new documentation authority model; it is not a silent exception.
- The repository's gate-receipt helper contains a special hash treatment for root `plan.md`. Keeping a short compatibility `plan.md` avoids a missing-file failure. Progress moved to `_meta/progress.md` no longer receives the former plan-section-specific exclusion; changing that optimization is a separate code task, not part of this documentation-only package.
- Current hosting scope is not CloudPanel-only: specification 2.7 includes traditional PHP hosting, cPanel, Plesk, PHP 8.3.0+, automatic PHP CLI/WeasyPrint detection, and optional server-database client discovery.

## CloudPanel CI deployment discoveries

- Before the 2026-08-13 gate separation, the sole GitHub Actions workflow was
  `.github/workflows/quality.yml`; its terminal develop path coupled normal quality, database,
  extracted-release diagnostics, bootstrap, publication, and deployment.
- CloudPanel dploy releases do not retain a `.git` directory. The restricted deployment script therefore reports the verified release path but does not derive or declare a commit from the release directory.
- On 2026-08-02, the dedicated restricted SSH key, server-side command, and five repository secrets were configured. A direct forced-command deployment activated the release and passed `artisan about`; the GitHub workflow run was cancelled before its deployment job to avoid a duplicate deployment while the obsolete Git metadata check was removed.

## Dusk verification discoveries

- On 2026-08-02, the isolated full Dusk suite stopped in `ApprovedUxQaTest` before completing because the required `/usr/bin/weasyprint` binary was not executable. This is an environment prerequisite failure outside the two Dusk determinism tests; no renderer fallback was introduced.
- The Docker development runtime supplies the required executable `/usr/bin/weasyprint` (version 57.2) and Selenium Chromium. It reproduces the CI browser path while retaining isolated Dusk database and storage roots.

## Assessment integrity discoveries

- Workspace selection and dependent public actions previously had multiple mutation paths with no shared explicit pre-action save result; one server-side gate now covers them without adding a JavaScript orchestrator.
- The prior Finding editor stored each pending Evidence item through a separate versioned action. The existing signed Finding request can carry the normalized batch and idempotency response, so no new persistence table or library was required.
- `GeneralSettings::active_risk_profile_id` already existed, while editor options, imports, and urgency queries bypassed it. `is_default` remains independent and no existing Finding IDs require migration.
- The former schema hardcoded the default profile's risk codes. Keeping it as `finding-template-v1.schema.json` permits unchanged legacy validation while the canonical v2 schema accepts technical-code shape and leaves database semantics to one application path.
- The bundled baseline contained eight templates. The reviewed thematic catalogue produces 221 templates in 17 existing categories, preserves all eight old IDs, adds no normative claim, and round trips deterministically after repeated seed execution.
- Dusk `DatabaseTruncation` removes seeded domain rows before an isolated test method; browser tests that render active-profile options must seed the domain fixture in that method. This is test isolation behavior, not an application fallback.

## Google Drive readable-sync discoveries

- Socialite's Google provider requires `openid` and `email` identity scopes to return the connected
  account email; `drive.file` remains the sole Drive data scope.
- Yaza Laravel Google Drive Storage v5 registers the `google` Flysystem driver dynamically, so the
  integration can construct a parent-rooted disk at execution time from the transient access token
  without adding a permanent filesystem disk or exposing credentials in configuration output.
- Revolution Laravel Google Sheets accepts an application-owned transient Google client. Its sheet
  value operations preserve additional tabs when the application ensures only its four named tabs
  and clears only `A:Z` on those tabs.
- The host runtime used for focused feature tests has no executable `/usr/bin/weasyprint`; the
  dedicated local-independence test is present but skips there and must execute in the maintained
  Docker runtime before its real PDF portion can be claimed.
- The initial Filament page treated missing `GOOGLE_*` values as a terminal unavailable state. A
  native Settings form can instead persist the same application values with encrypted write-only
  secrets; one resolver keeps optional environment values as defaults for existing deployments.
- Filament derives this page URL as `/admin/settings/integrations/google-drive-settings-page`; OAuth completion
  now resolves that route from the page class instead of returning to the former hard-coded 404.
- Filament 5 discovers a cluster class in the cluster directory through both its `Cluster` and `Page`
  passes. A nested cluster is consequently registered twice in the parent's raw component map; the
  parent cluster must de-duplicate its effective component list to avoid duplicate navigation items.
- Google documents `drive.file` as a non-sensitive per-file scope and supports Drive operations on
  app-created objects; creating the root with `parents: ['root']` returns the authoritative My Drive
  folder ID without requiring list/search access or ambiguous same-name adoption.
- Current Google Auth Platform documentation separates Branding, Audience, Data Access, and Clients.
  External apps in Testing require listed test users, and authorizations using non-identity scopes,
  including offline refresh tokens, expire after seven days.
- Filament 5 native `Section`, `Callout`, `Action`, and copyable disabled `TextInput` components cover
  the embedded guide and calculated callback without custom frontend components or JavaScript.
- Yaza's parent-rooted disk builder treats its `folder` setting as a display path in the installed
  adapter configuration. Passing an opaque Google folder ID there created a same-named folder instead
  of placing content beneath that ID; exact-parent creation now uses the public Drive client directly.
- Google client model serialization removes null entries from Sheet value rows. Remaining numeric
  keys can then become a JSON object rejected by the values API, so null and absent cells must be
  normalized to explicit empty strings before submission.

## Finding template learning discoveries

- The ADR index still described D-001 through D-070 even though accepted D-071 and D-072 already
  existed in their thematic ADRs. Adding the approved fingerprint contract as D-073 required only
  synchronizing that index and coverage statement; no historical decision was rewritten.
- Comparing all 221 bundled templates with the selected same-category lexical score at 0.86 yielded
  five related candidate pairs and zero clearly anomalous false positives on manual review. A 0.96
  cross-category normalized-title gate yielded zero pairs; lower same-category thresholds increased
  warnings to six pairs at 0.84, eight at 0.82, and twelve at 0.72.
- `SaveFindingTemplate` already generated all template and solution external IDs before validation.
  Returning those assigned IDs from the same authoritative path was sufficient for deterministic
  Finding-key realignment; no second slug/collision implementation was needed.
- A direct host `scripts/verify.sh` run reached the expensive suite before exposing that the host had
  no `/usr/bin/weasyprint`, while the maintained Compose app runtime passed the same 144 previously
  failing backup, benchmark, diagnostic, PDF, XLSX, and installer tests. An explicit Compose marker
  plus a `/.dockerenv` guard now turns this accidental unsupported path into an immediate refusal.

## Update rule

Add only reproducible observations discovered during implementation or verification. Do not turn a discovery into an accepted decision without explicit approval and an ADR update.

## Fatture in Cloud quote discoveries

- The official API v2 contract at OpenAPI commit `2be805afd6667b00b4f1d09b6833472e8eb09d7c`
  exposes Authorization Code exchange/refresh and the required company, VAT, client, product, and
  issued-document methods, but no OAuth revocation endpoint. Local disconnect must erase local
  tokens and explain provider-side authorization removal instead of fabricating a remote call.
- The official generated PHP SDK would add a large generated model/API surface over Guzzle for the
  same narrow endpoints. Laravel HTTP already provides the repository's proportional `Http::fake`
  boundary, so the SDK adds no required provider capability for this feature.
- The live client endpoint accepts one exact fiscal-identifier condition but returned `403` for a
  parenthesized disjunction that combined otherwise accepted fields. Separate exact requests followed
  by local normalized deduplication preserve strict matching and work with the live provider.
- Filament's shipped theme does not guarantee arbitrary utility classes referenced only by a custom
  Blade page, and `fi-input` is intended to be rendered inside `fi-input-wrp`. Application-owned CSS
  plus native input components avoid an undeclared frontend build. Custom Filament app assets also
  need a content-derived app version; the default Filament package version can otherwise leave a
  browser on a stale published stylesheet after application CSS changes.
- Fatture in Cloud does not hydrate an issued document's `entity` from an existing client ID. A
  linked quote must repeat the live client's display details alongside `entity.id`; the live
  non-creating `/issued_documents/totals` check accepted the corrected quote payload with status 200.
  Provider rejection logs can retain the HTTP status, bounded error code, and validation field paths
  without retaining provider messages, fiscal identifiers, commercial values, or credentials.
- Livewire hydrates checkbox option values from the browser as numeric strings even when the initial
  transient composer arrays contain integer Finding IDs. Derived UI indicators and counts therefore
  normalize those IDs before strict membership checks; provider reconciliation and persisted data are
  unchanged.
- The original composer geometry rule appeared after the first refinement block in the application
  stylesheet and won the CSS cascade, leaving the summary below the editor. The final authoritative
  responsive rule now establishes three columns at the existing 64rem desktop boundary and the Dusk
  regression assertion verifies the computed grid rather than relying on element presence alone.
- The official detailed Fatture in Cloud product representation already includes `measure`; deriving
  unique suggestions from the existing read-only product request supplies provider vocabulary while
  keeping the input editable and avoids inventing a standalone measure endpoint or local catalog.
- A reference-only row description has no leading commercial text. Reference normalization must
  therefore recognize the reference block both at the beginning of the string and after a blank-line
  separator, otherwise removing the final Finding can leave a stale reference behind.

## Extracted-release installer discoveries

- A fresh extracted ZIP completed the SQLite installer through
  `installer.finalize.complete` over the maintained HTTP smoke with `artisan serve --no-reload`;
  the observed parent PID remained alive and the selected port remained in LISTEN. Removing
  runner-level `APP_ENV`, `APP_KEY`, and `APP_URL` overrides from post-install CLI checks allowed
  `schedule:run` and `assestme:diagnose --json` to validate the activated release `.env`.
- A separate fresh extraction reached the structural installation-complete element through Dusk,
  with `.env` and `installed.lock` present, no installer progress state, the parent PID alive, and
  the port still in LISTEN. An initial local login rejection came from Compose's inherited
  `DB_DATABASE` selecting the development database on the next request; removing runner DB and
  backup overrides made the complete release Dusk journey, including real login, pass.
- The prior GitHub runner listener loss was not reproduced locally. Local completion evidence does
  not establish why that runner stopped accepting connections; the exact CI root cause remains
  undetermined until the instrumented workflow records its PID/exit/signal and last marker.
- On Linux, tracing `artisan serve` with `strace -ff -e trace=process` preserves separate per-PID
  exit records while `ss -ltnp` identifies the PHP process that actually owns the requested socket.
  The quiet `strace` option suppresses normal exit records and therefore cannot be used when the
  exit code itself is required evidence.
- The Artisan lifecycle unit test added with the first installer markers depended on a facade root
  leaked from test ordering. Binding that test explicitly to `Tests\\TestCase` made both the
  isolated file and the complete 622-test application suite deterministic without changing
  `FinalizeInstallation`.
- The hosted Ubuntu runner does not guarantee `rg`. Preflight checks that do not need ripgrep
  semantics use recursive extended `grep`, while the required process evidence remains explicitly
  guarded by `command -v ss` and `command -v strace`.
- `artisan serve --no-reload` can terminate its supervisor while the PHP built-in listener remains
  alive and is reparented to PID 1. Cleanup must therefore authorize the recorded listener by exact
  current socket ownership as well as the originally observed parent relationship; waiting for
  `strace` while that traced listener remains alive does not complete.
- On runner PHP 8.3.33 with SQLite 3.45.1 and Laravel 13.19.0, the traced PHP built-in listener
  exited through confirmed `SIGSEGV` (exit 139) for both historical `c8bfab8` and a fresh current
  release attempt; another independent current attempt completed. Current Dusk also recorded the
  same signal. The historical application-regression hypothesis is therefore rejected, while the
  native failure is intermittent and still requires the direct-server isolation result.
- A later same-run comparison passed the pure HTTP path for historical `c8bfab8` and all three
  fresh current extractions, then reproduced `139/SIGSEGV` only in the separate Dusk extraction.
  A conditional isolation step must therefore be evaluated after all Artisan-backed observations;
  evaluating it before Dusk can skip the experiment despite a later qualifying hard termination.
- The equivalent direct PHP built-in server, using the router path derived from Laravel and no
  Artisan supervisor, also exited 139 through confirmed `SIGSEGV` on the same runner and current
  release. This rejects an `artisan serve`-specific termination mechanism. A broad numbered-log
  selector must exclude the separately named direct log or the A/B table can report isolation data
  in the current HTTP column.
- The historically successful `c8bfab8` run and the same-commit diagnostic failure report the same
  Actions runner version, Ubuntu image version, PHP 8.3.33, and Laravel 13.19.0. The historical run
  did not emit the SQLite runtime and predates the process-tracing harness; the recorded version
  comparison therefore neither identifies a changed runtime component nor proves a tracing effect.
- The 2026-09-08 publication gate found five new Composer advisories affecting the locked Filament
  5.6.8, Livewire 4.3.3, and CommonMark 2.9.2 packages. Compatible updates to Filament 5.8.1,
  Livewire 4.4.4, and CommonMark 2.10.1 clear the locked dependency audit without changing the
  declared major-version constraints.

## Canonical MariaDB verification discoveries

- A test path moved from the development image to a clean hosted runner must provision every CLI
  binary consumed by its assertions, not only the production renderer. The same clean-checkout
  boundary must wrap every PHPUnit and Dusk entry point with the existing owned empty `.env`
  placeholder; otherwise one missing-file warning is repeated once per test.
- Running the complete Feature suite on MariaDB 12.3.3 exposed test-only SQLite assumptions in
  decimal value types, identifier reuse, SQL identifier quoting, SQLite trigger syntax, and
  nested transaction handling. The corrections make the assertions and failure mechanisms
  driver-aware without changing application behavior or supported database semantics.
- The installer continues to present the shared `mysql` server choice, while its real capability
  probe identifies and persists MariaDB as the actual product. The extracted-release browser
  journey proved this boundary against MariaDB 12.3.3.
- A remote Selenium browser and its PHP server need separate bind, browser, and readiness hosts.
  Selecting an available acceptance port also prevents an unrelated pre-existing listener from
  being mistaken for the newly started extracted-release server.
