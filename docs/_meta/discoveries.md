# Discoveries

## Documentation migration discoveries

- The inspected `plan.md` is specification 2.7.
- The inspected root `AGENTS.md` still declared plan version 2.4; this was stale and is removed in the replacement.
- The former documentation policy prohibited additional product/architecture Markdown files. This package explicitly replaces that policy through the new documentation authority model; it is not a silent exception.
- The repository's gate-receipt helper contains a special hash treatment for root `plan.md`. Keeping a short compatibility `plan.md` avoids a missing-file failure. Progress moved to `_meta/progress.md` no longer receives the former plan-section-specific exclusion; changing that optimization is a separate code task, not part of this documentation-only package.
- Current hosting scope is not CloudPanel-only: specification 2.7 includes traditional PHP hosting, cPanel, Plesk, PHP 8.3.0+, automatic PHP CLI/WeasyPrint detection, and optional server-database client discovery.

## CloudPanel CI deployment discoveries

- The sole GitHub Actions workflow is `.github/workflows/quality.yml`. Its `publish-develop-release` job is the existing terminal develop job and requires `quality`, `database-compatibility`, `cloudpanel-release`, and `clean-checkout-bootstrap`.
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
- Filament derives this page URL as `/admin/settings/google-drive-settings-page`; OAuth completion
  now resolves that route from the page class instead of returning to the former hard-coded 404.
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
