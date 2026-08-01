# Documentation worklog

## 2026-08-01 — Plan 2.7 split package

- Baseline fixed to `develop` commit `d466ff1c0c3851fecee3d468cde7cb9359db5ae4`.
- Removed stale hard-coded plan version 2.4 from agent instructions.
- Replaced the monolithic source-of-truth model with one index, focused explanation/reference/how-to pages, nine thematic ADRs, and minimal `_meta` evidence.
- Preserved exact source recoverability through commit and Git blob verification.
- Included all existing hosting operator guides inspected on `develop`.
- Validated all 70 decision IDs, internal Markdown links, file types, and package checksums.

## Remaining repository task

Apply the package on a branch, materialize the optional exact historical archive with the command in `source-baseline.md`, inspect the diff, and run documentation/link checks. No application code change is included.
