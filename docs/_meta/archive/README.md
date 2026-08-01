# Historical source archive

Do not manually reconstruct the former monolithic plan.

To create the exact source snapshot from the verified repository object:

```bash
git show d466ff1c0c3851fecee3d468cde7cb9359db5ae4:plan.md > docs/_meta/archive/plan-v2.7.md
test "$(git hash-object docs/_meta/archive/plan-v2.7.md)" = "c201bf64ec39519e1f26eba5f18e63886f3ae8ae"
```

`plan-v2.7.md` is historical evidence only and is not part of the active documentation reading path.
