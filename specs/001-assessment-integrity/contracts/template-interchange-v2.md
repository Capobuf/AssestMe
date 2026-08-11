# Template Interchange v2 Contract

The executable structural contract is `schemas/finding-template.schema.json`. This design note only
records version routing and semantic rules needed by implementation.

## Version routing

- `schema_version: 1` validates against `schemas/finding-template-v1.schema.json` and remains
  preview/import compatible.
- `schema_version: 2` validates against `schemas/finding-template.schema.json`.
- Any other or missing version fails before database mutation.
- Export always emits `schema_version: 2` with deterministic template/external-ID ordering.

## V2 risk fields

`consequence`, `likelihood`, and `priority` are required properties whose values are either null or
a stable risk technical code matching `^[a-z0-9_]+$` with the repository length limit. They are not
enumerated with seeded profile codes.

After structural validation, preview and import resolve each non-null code against the one enabled
operational profile. A supplied code that is unknown, disabled, or belongs elsewhere fails with:

- zero-based template index;
- template `external_id`;
- field name;
- offending technical code.

When consequence and likelihood are supplied, priority must be supplied and must equal their active
profile matrix cell. No template manual-override contract is introduced.

## Compatibility and exclusions

- V1 input semantics remain supported.
- V2 output preserves template and solution external IDs and full-replacement behavior.
- `tags` and unapproved source/standard/regulation/reference properties remain rejected through
  `additionalProperties: false`.
- Existing detached Finding snapshots and primary keys are outside interchange migration.
