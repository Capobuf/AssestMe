# ADR: Workspace, UI, and persistence

- Status: Accepted
- Baseline: AssestMe specification 2.7
- Source commit: `d466ff1c0c3851fecee3d468cde7cb9359db5ae4`

## Context

Defines the Finding workbench, navigation, responsive modes, save protocol, lifecycle, and offline recovery.

## Decisions

| ID | Status | Decision |
|---|---|---|
| D-008 (v1) | SUPERSEDED | Advance Table Repeater was the selected assessment-grid component; superseded by D-008 on 2026-07-13 |
| D-008 (v2) | SUPERSEDED | The assessment workspace used only the native Filament 5 table Repeater; superseded by D-008 on 2026-07-18 |
| D-008 | APPROVED | The Finding workspace uses a Filament Table structured list with a single-record lateral inspector; native Repeaters remain permitted for bounded child collections such as Finding solutions |
| D-016 | APPROVED | Autosave and explicit draft save use the same persistence layer; every dependent Workspace navigation, mutation, completion, and generation action first persists the dirty current context and stops when that save fails |
| D-020 | APPROVED | Optimistic locking and idempotent workspace-save requests cover Finding aggregates, Evidence created with that Finding save, and reorder payloads; replay returns the original applied version without a second mutation |
| D-021 | APPROVED | Completed assessments are read-only until explicitly reopened |
| D-044 | APPROVED | Assessment creation proposes a reusable automatically generated but user-editable title, exposes only organization/site/custom assessment scopes, filters one or more sites by company, and makes the Workspace the primary post-create and list-row destination |
| D-046 (v1) | SUPERSEDED | The dashboard separated four compact clickable operational KPIs, the latest five assessments, urgent findings, and a compact application-status section; superseded by D-046 v2 on 2026-08-12 |
| D-046 (v2) | SUPERSEDED | The operational homepage retained secondary attention counts and compact system health; superseded by D-046 v3 on 2026-08-12 |
| D-046 (v3) | APPROVED | The dashboard is an operational homepage ordered around resuming real assessment work, four primary launchers, recent companies, the latest five assessments, and a lightweight archive launcher with real counts for companies, sites, assets, and Finding templates; backup, database-integrity, cleanup, attention counts, charts, inferred activity, scores, trends, and deadlines are not presented on the dashboard |
| D-047 | APPROVED | Company, site, asset, template, and generated-file Filament surfaces use the approved native responsive layouts, explicit create/cancel destinations, conditional solution fields, and compact tabular generated-file history |
| D-054 | APPROVED | The Finding workspace has exactly two intentional container-responsive modes: lateral desktop split at an effective workspace-container width of at least 64rem, and sequential list/detail below that threshold; the intermediate absolute-positioned inspector is removed |
| D-055 | APPROVED | The Finding workspace presents a three-area application workbench with a compact Filament Table navigator, a dominant selected-Finding editor, and contextual properties; narrow containers retain deliberate sequential navigator/editor behavior |
| D-056 | APPROVED | The Finding workbench uses the approved lime/green/white/black palette, one solid primary action per context, aggregated export and Finding-creation actions, and a reduced navigator row containing only selection-critical information |
| D-057 (v1) | SUPERSEDED | Filament 5 native clusters and parent items defined the hierarchical sidebar, Tag was removed from v1, and Finding assets remained required for `selected_assets`; superseded by D-057 v2 on 2026-08-11 |
| D-057 (v2) | SUPERSEDED | Filament 5 native clusters and parent items defined the hierarchical sidebar, with Asset nested below companies; superseded by D-057 v3 on 2026-09-08 |
| D-057 (v3) | APPROVED | Filament 5 native clusters and parent items define navigation; Asset is a primary menu cluster containing assets and asset types while sites remain below companies; Tag is removed from v1; Finding assets are optional for every scope, with a conditional non-required selector that can create a minimal Asset bound to the Assessment company and create its enabled Asset type inline; and implementation verification is proportional while `scripts/verify.sh` remains the complete authoritative gate |
| D-058 | APPROVED | The compact Finding navigator retains expandable icon search but removes its unusable filter control, places icon-only native reorder beside search, and keeps Finding titles visible during reorder; the non-functional summary preview tab is removed, contextual properties use compact collapsible sections with reliable internal scrolling, and generated-file history receives an aligned responsive tabular presentation |
| D-062 | APPROVED | Existing assessment and Finding forms use an application-owned IndexedDB draft store for durable local recovery; server persistence remains authoritative, signed, explicit, optimistic-locked, and idempotent |
| D-073 | APPROVED | A Finding linked to a FindingTemplate retains a canonical SHA-256 fingerprint of the reusable source-template content it knows; an explicit Workspace-to-template update is allowed only when the locked current template fingerprint matches that known state, with no implicit merge or force overwrite |

## Consequences

- The listed decisions remain normative with their recorded status.
- `SUPERSEDED` entries remain historical evidence and must not be reactivated implicitly.
- Detailed application contracts live in the reference pages linked from [the documentation index](../index.md); those pages may clarify implementation but may not contradict these decisions.
- A future change requires explicit approval and a recorded superseding decision, not a silent edit.
