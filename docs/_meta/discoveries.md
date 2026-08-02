# Discoveries

## Documentation migration discoveries

- The inspected `plan.md` is specification 2.7.
- The inspected root `AGENTS.md` still declared plan version 2.4; this was stale and is removed in the replacement.
- The former documentation policy prohibited additional product/architecture Markdown files. This package explicitly replaces that policy through the new documentation authority model; it is not a silent exception.
- The repository's gate-receipt helper contains a special hash treatment for root `plan.md`. Keeping a short compatibility `plan.md` avoids a missing-file failure. Progress moved to `_meta/progress.md` no longer receives the former plan-section-specific exclusion; changing that optimization is a separate code task, not part of this documentation-only package.
- Current hosting scope is not CloudPanel-only: specification 2.7 includes traditional PHP hosting, cPanel, Plesk, PHP 8.3.0+, automatic PHP CLI/WeasyPrint detection, and optional server-database client discovery.

## CloudPanel CI deployment discoveries

- The sole GitHub Actions workflow is `.github/workflows/quality.yml`. Its `publish-develop-release` job is the existing terminal develop job and requires `quality`, `database-compatibility`, `cloudpanel-release`, and `clean-checkout-bootstrap`.
- The local Git executable resolves to `/usr/bin/git`; the restricted CloudPanel deployment script uses that absolute path when it reports the commit from the release selected by dploy.
- No repository-side server credentials, SSH keys, host fingerprints, or CloudPanel secrets were available during this change. The external setup and a live deploy are therefore NOT VERIFIED.

## Update rule

Add only reproducible observations discovered during implementation or verification. Do not turn a discovery into an accepted decision without explicit approval and an ADR update.
