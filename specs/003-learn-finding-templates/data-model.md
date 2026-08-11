# Data Model: Learn from Assessment Findings

## Finding lineage addition

Existing table `findings` gains one forward-only portable column.

| Column | Type | Rules |
|---|---|---|
| `source_template_fingerprint` | nullable char(64) | Lowercase SHA-256 of the reusable persisted source-template state last known by this Finding; no blanket backfill |

`source_template_id` remains the sole current lineage reference. A non-null fingerprint with a null
source has no operational effect. Hard deletion may clear the source through the existing foreign
key; soft deletion retains the key but blocks update.

## Fingerprint projection

The ordered fingerprint document includes:

- category persisted identity;
- title, problem, entrepreneur notes, technical notes;
- default scope type and description;
- consequence, likelihood, and priority persisted identities;
- reusable priority rationale;
- active solutions ordered by `sort_order`, then persisted stable identity, each with external ID,
  title, description, comparison notes, effort identity/notes, estimate type, stable decimal amounts,
  currency, billing frequency/custom value, estimate notes, recommendation state, and sort order.

It excludes template database ID, `external_id`, timestamps, deletion metadata, and `is_enabled` at
the template level. Unicode is NFC, line endings are LF, null remains null, enum values are scalar,
and monetary decimals are fixed to two places before canonical JSON hashing.

## Semantic exact projection

Finding and template are projected to the same shape as reusable content, with these differences:

- operational database IDs and all template/solution external identities are omitted;
- text uses compatibility normalization, lowercase, punctuation-to-space, and collapsed whitespace;
- category and risk/effort records use their stable persisted identity;
- solution order, recommendation state, and complete normalized content remain included;
- enabled/deleted/timestamp state remains excluded.

An SHA-256 equality over these documents is an exact semantic duplicate. It is separate from the
lineage fingerprint: semantically normalized punctuation/case changes may remain exact while the
lineage fingerprint still detects an actual persisted source edit.

## Finding solution identity transitions

```text
manual-<finding-solution-id>
    └── create new template ──> authoritative new template-solution external ID

template external ID
    └── update same source ──> same external ID

manual-<finding-solution-id>
    └── update same source ──> authoritative new external ID

any active key
    └── exact link ──> semantically mapped existing external ID
```

All active keys first move to unique temporary values inside the same transaction, then to final
values. Any failure rolls back both phases.

## Aggregate mutation states

### Create new lineage

```text
draft Finding + expected assessment version
  -> authoritative template save
  -> solution key realignment
  -> source_template_id + current fingerprint
  -> one assessment version increment
```

### Link exact template

```text
draft Finding + exact current semantic signature
  -> no template mutation
  -> deterministic solution mapping/key realignment
  -> source_template_id + current fingerprint
  -> one assessment version increment
```

### Update source

```text
known fingerprint matches locked current source
  -> no reusable diff: no template write; initialize lineage only if required
  -> reusable diff: authoritative full replacement + key/fingerprint realignment
  -> one assessment version increment only when Finding/template state changes
```

### Rejected states

- assessment not draft;
- source soft-deleted;
- current fingerprint differs from known fingerprint;
- legacy null fingerprint with non-identical reusable content;
- exact-link candidate no longer exact under lock;
- new template classification not selectable in the active profile;
- any authoritative template validation or persistence failure.

Every rejected state leaves template, Finding lineage, solution keys, fingerprint, and assessment
version unchanged.

## Typed application results

- `SavedFindingTemplate`: persisted template plus ordered solution external IDs assigned by the
  authoritative save.
- `FindingTemplateSyncResult`: persisted/linked template, resulting Finding, applied assessment
  version, and outcome (`created`, `linked`, `updated`, or `already_aligned`).

No revision entity, history entity, alternate lineage relation, origin metadata, or import metadata
is added.
