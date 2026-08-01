# ADR: Reporting and exports

- Status: Accepted
- Baseline: AssestMe specification 2.7
- Source commit: `d466ff1c0c3851fecee3d468cde7cb9359db5ae4`

## Context

Defines the sole renderer, immutable output snapshots, report composition, preview, download, and XLSX implementation.

## Decisions

| ID | Status | Decision |
|---|---|---|
| D-009 (v1) | SUPERSEDED | Spatie Laravel PDF with DOMPDF as the only renderer and no renderer validation path; superseded by D-009 on 2026-07-24 |
| D-009 | APPROVED | Use WeasyPrint through Spatie Laravel PDF as the sole production renderer; the completed Chrome-first D-059 validation accepted WeasyPrint, with no automatic fallback or parallel renderer |
| D-010 | APPROVED | One configurable professional report layout |
| D-022 | APPROVED | Every generated report stores a complete normalized immutable payload snapshot |
| D-026 | APPROVED | PhpSpreadsheet is the direct XLSX implementation dependency |
| D-050 | APPROVED | PDF generation creates one immutable authoritative report, applies any configured freeze, immediately downloads that same authenticated hash-verified file, and retains it in generated-file history |
| D-051 | APPROVED | Report settings retain consultant/company/both branding and add typed cover-title mode plus optional priority-legend descriptions without changing immutable settings snapshots |
| D-052 | SUPERSEDED | The A4 portrait DOMPDF composition is retained as implementation history; its future report composition is superseded by D-059 on 2026-07-24 |
| D-059 (v1) | SUPERSEDED | The portrait-only DOMPDF editorial layout and manually duplicated indicative HTML preview are retained as implementation history; superseded by D-059 on 2026-07-24 |
| D-059 | APPROVED | Accepted pending technical validation: one Blade/CSS editorial report must support mixed A4 orientation and a same-view preview without changing immutable report persistence |
| D-060 | APPROVED | The report-settings preview renders a transient PDF through the same application-owned WeasyPrint renderer service and `reports.assessment` Blade/CSS used for production, exposes only operative settings with explicit scope descriptions, and supports a temporary dependency-free fullscreen view without creating immutable report state |

## Consequences

- The listed decisions remain normative with their recorded status.
- `SUPERSEDED` entries remain historical evidence and must not be reactivated implicitly.
- Detailed application contracts live in the reference pages linked from [the documentation index](../index.md); those pages may clarify implementation but may not contradict these decisions.
- A future change requires explicit approval and a recorded superseding decision, not a silent edit.
