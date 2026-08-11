# Workspace Contract: Finding Template Learning

## Action availability

| Finding/Assessment state | Available Finding menu actions |
|---|---|
| Draft, no source | `Salva come template`, `Duplica`, `Elimina` |
| Draft, active/disabled non-deleted source | `Aggiorna template di origine`, `Salva come nuovo template`, `Duplica`, `Elimina` |
| Draft, soft-deleted source | `Salva come nuovo template`, `Duplica`, `Elimina`; update is unavailable |
| Completed or archived | No create/link/update/duplicate/delete mutation actions |

Server actions independently enforce the same state contract.

## Create preview

The Workspace first persists dirty current Finding state. The preview then has exactly one mode:

1. `new`: concise confirmation; submit label `Salva come template` or `Salva come nuovo template`;
2. `exact`: one certain template with title, category, disabled state when applicable, and accessible
   `Apri template`; submit label `Collega al template esistente`; no save-anyway path;
3. `similar`: up to three candidates with title, category, disabled state, and `Apri template`;
   submit label `Salva comunque come nuovo`;
4. failure: localized validation/conflict message, no mutation, and modal remains safely dismissible.

When priority is manually overridden, the preview states that the assessment-specific priority is
not becoming the global default.

## Update preview

The compact summary includes these groups with changed/unchanged state where useful:

- title;
- problem;
- category;
- reusable notes and default scope;
- risk classification/rationale;
- solutions: counts added, changed, removed.

It always states: `I finding già presenti negli assessment non verranno modificati.` It is not an
editor and contains no row-by-row textual diff.

## Conflict and deleted-source response

A source changed since the Finding's last known state produces a localized conflict that explains
the timing, reveals no hash, performs no overwrite/merge, and leaves these paths available:

- cancel;
- `Apri template` when the record is reachable;
- separately invoke `Salva come nuovo template`.

A soft-deleted source is not restored or updated. A legacy null fingerprint with differing content
uses the same safe no-overwrite posture with wording that lineage cannot be verified.

## Mutation postconditions

Every successful create, link, or changed update:

- is based on already-persisted Workspace state;
- has one current source ID and matching current template fingerprint;
- has every active Finding solution aligned to exactly one active template solution external ID;
- increments assessment version exactly once;
- emits one localized success outcome only after commit.

`Template già allineato` does not rewrite reusable template content. It increments the assessment
version only when it initializes or repairs the Finding's known lineage state.

## Import boundary

These actions do not change template JSON, bundled baseline, seeders, preview/import semantics, or
replace mode. An explicit later replace import retains its existing ability to replace template
content, after which stale Finding fingerprints correctly block Workspace overwrite.
