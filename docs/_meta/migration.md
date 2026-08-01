# Replace the monolithic plan

## Scope

This is a documentation-only migration. It does not modify PHP, shell scripts, workflows, tests, configuration, or runtime behavior.

## Preflight

From the repository root on the intended branch:

```bash
git status --short
git rev-parse HEAD
git hash-object plan.md
```

For the inspected baseline, the expected source values are:

```text
HEAD: d466ff1c0c3851fecee3d468cde7cb9359db5ae4
plan.md blob: c201bf64ec39519e1f26eba5f18e63886f3ae8ae
```

If either differs, do not assume the package is current. Compare the newer documentation and port only verified changes.

## Preserve the exact historical source

Optional but recommended before replacing the active plan:

```bash
mkdir -p docs/_meta/archive
git show d466ff1c0c3851fecee3d468cde7cb9359db5ae4:plan.md > docs/_meta/archive/plan-v2.7.md
test "$(git hash-object docs/_meta/archive/plan-v2.7.md)" = "c201bf64ec39519e1f26eba5f18e63886f3ae8ae"
```

The archived file is historical evidence and must not become a second active authority.

## Apply

1. Extract this package into the repository root.
2. Replace root `README.md`, `AGENTS.md`, and `plan.md` with package versions.
3. Add/replace the package `docs/` tree.
4. Do not delete implementation files or unrelated documentation.
5. Review `git diff --check` and the complete Markdown diff.

## Verify

```bash
git diff --check
find . -name '*.md' -type f -print
```

Run the package's documented link-validation method or an equivalent repository-supported Markdown link checker. Confirm:

- `docs/index.md` is reachable from root README, AGENTS, and plan stub;
- D-001 through D-070 occur in the ADR set with no missing/duplicate base assignment;
- no active instruction names plan version 2.4;
- the four hosting guides remain present;
- no non-documentation file changed.

## Gate-receipt note

`scripts/gate-receipts.sh` currently has special treatment for the old root plan's Progress section. The compatibility stub keeps the file present, so gates remain executable. Changes to `_meta/progress.md` may invalidate receipt reuse more often than before. Altering that optimization is a separate, reviewable code task and is intentionally not bundled here.
