# UI, persistence, import, and export

## Navigation

Filament 5 native clusters and parent items define the hierarchy:

- `Aziende` groups companies, sites, assets, and asset types;
- `Impostazioni` groups general, report, risk/effort, templates, and backup-related pages;
- nested `Integrazioni` groups the optional Fatture in Cloud and Google Drive pages under
  `Impostazioni`, using left-positioned native cluster navigation;
- global Tag functionality is outside v1.

Standard resources use native responsive Filament layouts and explicit save/cancel destinations. Right-click enhancements duplicate visible accessible actions and are never the only path.

## Dashboard

The dashboard is an operational homepage, not a reporting or BI surface. Its priority order is:

1. resume a real draft Assessment, falling back deterministically to the latest Assessment;
2. launch Assessment creation, company management, the Assessment list, or settings;
3. show the five recent companies from their latest real Assessment activity, with companies that
   have no Assessment handled explicitly;
4. show the latest five Assessments;
5. show a lightweight archive launcher with real counts and index links for companies, sites,
   assets, and Finding templates.

Backup, database-integrity, cleanup, and diagnostic behavior remains available on its dedicated
pages and is not duplicated on the dashboard.

## Finding workspace

The Workspace is a three-area workbench:

1. compact Filament Table navigator;
2. dominant selected-Finding editor;
3. contextual properties.

The navigator remains the authoritative list engine. It includes only selection-critical information, expandable icon search, visible reorder, and no unusable filter control. Long text, solutions, and evidence are edited in the selected record, not navigator cells.

Responsive behavior has two intentional modes:

- lateral desktop composition at an effective container width of at least 64rem;
- sequential navigator/editor behavior below that threshold.

Contextual properties remain reachable on narrow containers. Mobile supports essential vertical editing and evidence capture but does not claim desktop workbench parity.
The five contextual-property sections beside the selected Finding are collapsible and open by default; users may close them individually when they need a more compact inspector.

Finding asset selection is optional for every scope. Selecting `selected_assets` reveals the asset selector but does not make it required, including for Findings copied from imported templates. Submitting that selector empty is a successful save, not a validation-error state.

## Save protocol

Autosave and explicit save call the same authoritative server action.

Each save includes:

- signed/validated payload;
- persisted record version for optimistic locking;
- unique request UUID for idempotency.

The UI exposes saving, saved, unsaved, offline, validation error, server error, and conflict states. It must never report saved before the server confirms persistence.

A stale version produces an explicit conflict. Repeated delivery of the same request UUID returns the original result rather than applying the mutation twice.

Before selection changes, tab changes, close, new/copy/duplicate/delete/reorder, completion, PDF, or XLSX, the server persists the dirty current form. The dependent action runs only after an explicit successful result. Finding and assessment forms keep independent dirty/error state.

The same pre-action save rule applies before creating, exactly linking, or updating a Finding
Template from a Finding. These operations are atomic with lineage/fingerprint changes, solution-key
realignment, and one coherent assessment-version increment. Completed or archived assessments hide
and reject them until reopening.

The Finding `⋮` menu offers `Salva come template` without a source, and `Aggiorna template di
origine` plus `Salva come nuovo template` with a live non-deleted source. Exact duplicate previews
offer linking instead of duplicate creation; possible similarities show no more than three
review-only candidates and allow explicit save-as-new. Source updates show a compact reusable-field
and solution summary and state that existing assessment Findings are not changed.

The update guard compares the locked current source fingerprint with the Finding's known state. A
mismatch, unverifiable legacy lineage, or deleted source produces an explicit no-overwrite result;
there is no merge or force-overwrite action. Users can cancel, open the template when reachable, or
use the separate save-as-new lineage action.

A Finding save carries its pending file and URL Evidence in the signed payload hash. Finding, solutions, associations, Evidence rows, idempotency response, and one assessment-version increment commit as one aggregate. Prepared private files are compensated when persistence fails; pending uploads are removed only after success.

## Fatture in Cloud quote composer

`Crea preventivo` is available only for an editable Assessment and uses the same pre-action save
gate as the other Workspace-dependent actions. The composer opens only after the authoritative
Assessment and selected-Finding state has been persisted successfully.

The composer is a transient work area. Manual groups, free rows, repeated Finding assignments,
catalog selections, prior-version rows, provider document IDs, and success/error state are not
stored in AssestMe. The only provider-specific domain persistence is the exact client mapping for
the connected company; OAuth/company/default-VAT settings remain encrypted or typed application
settings as applicable.

One manual group or free row produces exactly one provider quote item. Finding references use the
exact `F-xxxxxx` token and remain part of the item description. Products and enabled VAT types are
read live as optional suggestions; every commercial value remains editable and AssestMe neither
calculates tax nor writes provider catalogs.
Measures returned by the live product list are offered as editable suggestions rather than a local
catalog. Assigning exactly one Finding to a Finding row prefills its editable title and Problem while
keeping the exact reference visible in the description; assigning multiple Findings leaves the title
blank and retains their sorted unique references.

Previous-version reuse accepts only the greatest unambiguous exact AssestMe marker for the current
Assessment. Rows with references that cannot be matched exactly to current Findings are preserved
and visibly marked instead of being guessed or discarded.

Submission is single-flight. The UI keeps all transient input on ordinary, rate-limit, or ambiguous
transport failure and reports success only from a provider response or an exact unique post-failure
marker reconciliation. A provider document ID and safe provider link are shown only after that
confirmed success.

## Local IndexedDB recovery

- Existing assessment and Finding forms keep durable local drafts in an application-owned IndexedDB store.
- The server remains authoritative.
- Local drafts do not bypass validation, optimistic locking, signatures, or idempotency.
- Recovery is explicit and must not silently overwrite newer server data.
- Offline state is shown clearly.

## Completion and reopening

Completion validates the same scope, solution, risk, and evidence contracts used by save/report generation. Asset association remains optional for every Finding scope. Completed or archived records are read-only until an explicit reopening action succeeds.

## Template import/export

- Accept and structurally validate schema v1 and v2; export schema v2 only.
- Resolve v2 risk codes against the enabled active profile during both preview and import, including matrix-coherence validation.
- Preserve stable external IDs.
- Apply full replacement semantics for matching imported templates.
- Use transactions for authoritative changes.
- Report validation and collision failures precisely.
- Export a deterministic round-trippable representation.
- An explicit `replace` import can still replace locally improved baseline template content; this
  workflow does not add template origin metadata or modify import conflict semantics.

## XLSX export

Assessment XLSX is generated by the dedicated PhpSpreadsheet exporter, not a generic Filament table plugin. It includes the complete textual assessment summary and the fixed VAT-excluded note exactly once.
