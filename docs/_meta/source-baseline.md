# Source baseline

## Verified source

- Repository: `Capobuf/AssestMe`
- Branch: `develop`
- Commit inspected: `d466ff1c0c3851fecee3d468cde7cb9359db5ae4`
- Commit date: 2026-08-01 18:25:42 UTC
- Commit message: `feat: add data-dusk attribute for scheduler shell in installation complete view and update test case`
- Original `plan.md` specification: 2.7
- Original `plan.md` Git blob: `c201bf64ec39519e1f26eba5f18e63886f3ae8ae`
- Original `AGENTS.md` Git blob: `959d8bca5833bb9f0c64017083eed210171a3687`

The source `AGENTS.md` incorrectly named plan version 2.4 while the inspected plan was 2.7. The replacement removes the hard-coded version.

## Recover the exact original plan

The complete original plan remains in Git history and can be materialized without transcription:

```bash
git show d466ff1c0c3851fecee3d468cde7cb9359db5ae4:plan.md > docs/_meta/archive/plan-v2.7.md
git hash-object docs/_meta/archive/plan-v2.7.md
```

The second command must print:

```text
c201bf64ec39519e1f26eba5f18e63886f3ae8ae
```

Do not regenerate or manually reconstruct this archive. It is historical evidence, not an active source of truth.

## Existing operator-document blobs inspected

- `docs/hosting-installation.md`: `89552b23e26b2dfbcfb07596c11b08dc3be3524b`
- `docs/cpanel-installation.md`: `a700b931239a0e00f24921cbe160b27f744eb3d4`
- `docs/cloudpanel-installation.md`: `ae7556b639b46afb597ac8b98cd6c33ef0f05c32`
- `docs/cloudpanel-acceptance-checklist.md`: `5fdd59be53b39b4f37f5ed15682a592c0b4c6bc5`
