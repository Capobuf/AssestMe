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

## Update rule

Add only reproducible observations discovered during implementation or verification. Do not turn a discovery into an accepted decision without explicit approval and an ADR update.
