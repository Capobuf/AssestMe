<?php declare(strict_types=1); ?>

<style>
    :root {
        --ink: #171717;
        --muted: #666666;
        --line: #D7D7D2;
        --soft: #F3F3EF;
        --accent: {{ $report->setting('primary_color') }};
    }
    * { box-sizing: border-box; }
    html, body { color: var(--ink); font-family: "DejaVu Sans", sans-serif; font-size: 8.7pt; line-height: 1.4; margin: 0; padding: 0; }
    h1, h2, h3, p { margin-top: 0; }
    table { border-collapse: collapse; width: 100%; }
    th, td { vertical-align: top; }
    a { color: inherit; text-decoration: underline; }
    .pre-line { white-space: pre-line; }
    .muted { color: var(--muted); display: block; }
    .label, .finding-section__label {
        color: var(--muted); display: block; font-size: 7pt; font-weight: 700;
        letter-spacing: .07em; margin-bottom: 1mm; text-transform: uppercase;
    }
    .sheet { break-after: page; page: report; }
    .sheet:last-child { break-after: auto; }
    .page-title { font-size: 25pt; line-height: 1.08; margin: 0 0 7mm; }

    .cover { height: 297mm; padding: 22mm 20mm; page: cover; position: relative; }
    .cover__center { margin-top: 58mm; max-width: 155mm; }
    .cover__title { font-size: 34pt; line-height: 1.04; margin-bottom: 7mm; overflow-wrap: anywhere; }
    .cover__client { font-size: 16pt; font-weight: 400; margin-bottom: 4mm; }
    .cover__date { color: var(--muted); font-size: 10pt; }
    .cover__client-logo { display: block; margin-top: 8mm; max-height: 20mm; max-width: 48mm; }
    .cover__footer { border-top: .35mm solid var(--ink); bottom: 22mm; left: 20mm; padding-top: 5mm; position: absolute; width: 170mm; }
    .cover__footer-logo, .cover__footer-client-logo { display: block; max-height: 20mm; max-width: 52mm; }
    .cover__footer-copy { padding-left: 6mm; }
    .cover__footer-name { font-size: 11pt; font-weight: 700; }
    .cover__footer-role { color: var(--muted); margin-top: 1mm; }

    .overview { min-height: 260mm; }
    .kpi-row { margin-bottom: 5mm; table-layout: fixed; }
    .kpi-row td { border-left: .25mm solid var(--line); padding: 1mm 5mm; width: 33.333%; }
    .kpi-row td:first-child { border-left: 0; padding-left: 0; }
    .kpi__value { font-size: 22pt; font-weight: 700; line-height: 1; }
    .priority-distribution { border-bottom: .25mm solid var(--line); border-collapse: collapse; border-top: .25mm solid var(--line); margin-bottom: 5mm; table-layout: fixed; width: 100%; }
    .priority-distribution td { padding: 2.5mm 4mm 2.5mm 0; vertical-align: middle; }
    .priority-distribution__marker { border-left: 1mm solid; display: inline-block; height: 4mm; margin-right: 1.5mm; vertical-align: middle; }
    .overview__introduction { font-size: 9.5pt; margin-bottom: 4mm; }
    .information-grid { background: var(--soft); margin-bottom: 5mm; table-layout: fixed; }
    .information-grid td { padding: 2.5mm 4mm; width: 50%; }
    .executive-summary { border-left: .8mm solid var(--accent); margin: 5mm 0; padding-left: 4mm; }
    .executive-summary__title { font-size: 12pt; margin-bottom: 1.5mm; }
    .estimate-note { color: var(--muted); font-size: 7.5pt; margin-top: 5mm; }
    .priority-legend-section { margin-top: 5mm; }
    .priority-legend-heading { font-size: 11pt; margin-bottom: 2mm; }
    .priority-legend-inline { width: auto; }
    .priority-legend-inline th, .priority-legend-inline td { padding-right: 5mm; white-space: nowrap; }
    .priority-legend--detailed td { padding: 1mm 2mm 1mm 0; }
    .priority-legend__marker { width: 5mm; }
    .priority-legend__label { font-weight: 700; width: 36mm; }
    .consultant-information { break-inside: avoid; margin-top: 6mm; }
    .consultant-information__title { font-size: 11pt; }
    .consultant-information__grid td { border-top: .25mm solid var(--line); padding: 2mm; width: 50%; }

    .summary { page: summary; }
    .findings-summary__description { color: var(--muted); margin-bottom: 3mm; }
    .summary-table { font-size: 7.4pt; table-layout: fixed; }
    .summary-table thead { display: table-header-group; }
    .summary-table th { background: #F0F0EC; border-bottom: .45mm solid var(--ink); border-top: .25mm solid var(--line); color: var(--ink); font-size: 6.5pt; font-weight: 700; letter-spacing: .045em; padding: 2mm 2.2mm; text-align: left; text-transform: uppercase; }
    .summary-table td { border-bottom: .25mm solid var(--line); line-height: 1.35; overflow-wrap: anywhere; padding: 2.5mm 2.2mm; }
    .summary-table tr { break-inside: avoid; }
    .summary-table th:nth-child(1), .summary-table td:nth-child(1) { width: 20%; }
    .summary-table th:nth-child(2), .summary-table td:nth-child(2) { width: 22%; }
    .summary-table th:nth-child(3), .summary-table td:nth-child(3) { width: 25%; }
    .summary-table th:nth-child(4), .summary-table td:nth-child(4) { width: 10%; }
    .summary-table th:nth-child(5), .summary-table td:nth-child(5) { width: 9%; }
    .summary-table th:nth-child(6), .summary-table td:nth-child(6) { width: 14%; }
    .summary-table td:nth-child(4), .summary-table td:nth-child(5), .summary-table td:nth-child(6) { vertical-align: middle; }
    .summary-role { color: var(--accent); display: block; font-size: 6pt; font-weight: 700; letter-spacing: .05em; margin-bottom: .5mm; text-transform: uppercase; }
    .priority-marker { border-left: 1mm solid; display: inline-block; height: 3.5mm; margin-right: 1.5mm; vertical-align: middle; }

    .finding-detail { min-height: 260mm; }
    .finding-heading { border-bottom: .35mm solid var(--ink); break-inside: avoid; margin-bottom: 4mm; padding-bottom: 3mm; }
    .finding-heading__table { border-collapse: collapse; table-layout: fixed; width: 100%; }
    .finding-heading__number { font-size: 20pt; font-weight: 700; line-height: 1.08; padding: 0 4mm 0 0; vertical-align: baseline; width: 17mm; }
    .finding-heading__content { padding: 0; vertical-align: baseline; }
    .finding-title { line-height: 1.08; margin: 0; overflow-wrap: anywhere; }
    .finding-title--default { font-size: 21pt; }
    .finding-title--medium { font-size: 18pt; }
    .finding-title--compact { font-size: 16pt; }
    .finding-detail--continuation .finding-heading { margin-bottom: 3mm; padding-bottom: 2mm; }
    .finding-detail--continuation .finding-heading__number { font-size: 16pt; }
    .finding-detail--continuation .finding-title--default,
    .finding-detail--continuation .finding-title--medium { font-size: 16pt; }
    .finding-detail--continuation .finding-title--compact { font-size: 14pt; }
    .continuation-label { color: var(--muted); font-size: .55em; font-weight: 400; }
    .finding-meta { font-size: 7.2pt; font-weight: 700; margin-top: 2.5mm; text-transform: uppercase; }
    .finding-meta > span { display: inline-block; margin-right: 4mm; vertical-align: middle; }
    .finding-body { margin-left: 21mm; }
    .finding-body > :first-child { margin-top: 0; }
    .status-accepted { color: #15803D; }
    .status-not_applicable { color: #777777; }
    .problem-explanation { margin: 0; padding: 4mm 0 0; }
    .problem-explanation .pre-line { font-size: 9.4pt; line-height: 1.45; }
    .finding-section { margin-top: 4mm; }
    .finding-section__label { margin-bottom: 1mm; }
    .finding-section__label--prominent {
        color: var(--ink); font-size: 11pt; letter-spacing: 0; line-height: 1.2;
        margin: 0 0 1.5mm; text-transform: none;
    }
    .solution-block { border: .25mm solid var(--line); break-inside: avoid; margin-top: 4mm; padding: 3.5mm; }
    .solution-block--primary { background: #F0F0EC; border-left: 1mm solid var(--accent); }
    .solution-block h3 { font-size: 11.5pt; line-height: 1.2; margin-bottom: 1.5mm; }
    .finding-detail--continuation .solution-block { margin-top: 3mm; padding: 3mm; }
    .solution-comparison { border-left: .5mm solid var(--line); color: var(--muted); margin-top: 2mm; padding-left: 2.5mm; }
    .solution-metrics { border-collapse: collapse; border-top: .25mm solid var(--line); margin-top: 3mm; table-layout: fixed; width: 100%; }
    .solution-metrics td { padding: 2mm 0 0; vertical-align: top; }
    .solution-metrics__effort { padding-right: 3mm !important; width: 42%; }
    .solution-metrics__estimate { border-left: .25mm solid var(--line); padding-left: 3mm !important; width: 58%; }
    .solution-metrics--single .solution-metrics__effort { padding-right: 0 !important; width: 100%; }
    .solution-metrics strong { display: block; line-height: 1.25; margin-bottom: .8mm; }
    .solution-metrics .muted { line-height: 1.35; }
    .finding-context-layout { border-collapse: collapse; break-inside: avoid; margin-top: 5mm; table-layout: fixed; width: 100%; }
    .finding-context-layout > tbody > tr > td { padding: 0; vertical-align: top; }
    .finding-context-layout__risk { padding-right: 6mm !important; width: 56%; }
    .finding-context-layout__systems { border-left: .25mm solid var(--line); padding-left: 5mm !important; width: 44%; }
    .finding-context-layout__systems--full { border-left: 0; padding-left: 0 !important; width: 100%; }
    .risk-evaluation { break-inside: avoid; margin-top: 5mm; }
    .finding-context-layout .risk-evaluation { margin-top: 0; }
    .risk-compact-layout { border-collapse: collapse; table-layout: fixed; width: auto; }
    .risk-compact-layout td { border: 0; padding: 0; vertical-align: middle; }
    .risk-compact-layout__matrix { padding-right: 5mm !important; width: 34mm; }
    .risk-compact-layout__result { width: 48mm; }
    .risk-mini-layout { border-collapse: collapse; width: auto; }
    .risk-mini-layout td { padding: 0; vertical-align: middle; }
    .risk-axis { color: var(--muted); font-size: 6pt; font-weight: 700; letter-spacing: .04em; line-height: 1; text-transform: uppercase; }
    .risk-axis-cell { height: 25mm; position: relative; width: 5mm; }
    .risk-axis--vertical {
        left: 50%; position: absolute; top: 50%;
        transform: translate(-50%, -50%) rotate(-90deg); white-space: nowrap;
    }
    .risk-axis--horizontal { margin-top: 1mm; text-align: center; }
    .risk-mini-matrix { border-collapse: separate; border-spacing: 1mm; table-layout: fixed; width: auto; }
    .risk-mini-matrix td { border: 0; height: 5mm; padding: 0; text-align: center; vertical-align: middle; width: 5mm; }
    .risk-dot { border: .25mm solid rgba(0, 0, 0, .18); border-radius: 50%; display: inline-block; height: 3.8mm; vertical-align: middle; width: 3.8mm; }
    .risk-dot--current { border: .75mm solid var(--ink); height: 5mm; width: 5mm; }
    .risk-result-compact strong { display: block; font-size: 12pt; line-height: 1.15; }
    .risk-result-compact .priority-marker { float: left; height: 6mm; margin-right: 2mm; }
    .risk-result-compact__factors { color: var(--muted); display: block; font-size: 8pt; margin-top: 1mm; }
    .risk-evaluation__rationale { color: var(--muted); margin-top: 2mm; }
    .affected-systems { break-inside: avoid; margin-top: 0; }
    .asset-details { border-top: .25mm solid var(--line); padding: 1.2mm 0; }
    .asset-details__name { display: inline; font-weight: 700; }
    .asset-details__type { color: var(--muted); }
    .asset-details__metadata { color: var(--muted); display: inline; }
    .asset-details__metadata-item { display: inline; }
    .asset-details__metadata-item::before { content: " · "; }
    .technical-notes, .resolution-details { border-top: .3mm solid var(--ink); break-inside: avoid; margin-top: 3mm; padding-top: 2mm; }
    .resolution-details { font-size: 8pt; margin-top: 1mm; padding-top: 1mm; }
    .resolution-details .finding-section__label,
    .resolution-details .pre-line,
    .resolution-details .muted { display: inline; margin-right: 2mm; }

    .finding-evidence { break-after: page; break-before: page; page: report; }
    .finding-evidence__eyebrow { color: var(--muted); font-size: 7pt; text-transform: uppercase; }
    .finding-evidence__title { font-size: 20pt; margin: 2mm 0 6mm; }
    .evidence-block { break-inside: avoid; margin-bottom: 7mm; }
    .evidence-block__label { color: var(--muted); font-size: 7pt; font-weight: 700; text-transform: uppercase; }
    .evidence-block__title { font-size: 11pt; font-weight: 700; margin-top: 1mm; }
    .evidence-image { display: block; height: auto; margin: 2mm auto; max-height: 150mm; max-width: 100%; width: auto; }
    .evidence-caption, .evidence-reference__meta { color: var(--muted); font-size: 8pt; }
    .evidence-reference { border-top: .25mm solid var(--line); padding-top: 2mm; }
    .final-section { page: report; margin-top: 8mm; }
    .final-section--new-page { break-before: page; }
    .editorial-heading { border-bottom: .35mm solid var(--ink); font-size: 18pt; padding-bottom: 2mm; }
    .avoid-break, .signature-line, .attachment-list li { break-inside: avoid; }
    .signature-line { border-bottom: .3mm solid var(--ink); height: 13mm; width: 68mm; }

    @page cover {
        size: A4 portrait;
        margin: 0;
        counter-reset: page 0;
        @top-left { content: none; }
        @bottom-right { content: none; }
    }
    @page report {
        size: A4 portrait;
        margin: 17mm 16mm 18mm;
        @top-left { content: {!! json_encode($report->clientName, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) !!}; color: #666; font: 7.5pt "DejaVu Sans"; }
        @top-right { content: none; }
        @bottom-right {
            @if ($report->setting('page_numbers') === true)
                content: counter(page);
            @else
                content: none;
            @endif
            color: #666; font: 8.5pt "DejaVu Sans";
        }
    }
    @page summary {
        size: A4 landscape;
        margin: 15mm 16mm 16mm;
        @top-left { content: {!! json_encode($report->clientName, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) !!}; color: #666; font: 7.5pt "DejaVu Sans"; }
        @bottom-right {
            @if ($report->setting('page_numbers') === true)
                content: counter(page);
            @else
                content: none;
            @endif
            color: #666; font: 8.5pt "DejaVu Sans";
        }
    }
    @media screen {
        body { background: #E7EAEF; padding: 10mm 0; }
        .sheet, .finding-evidence, .final-section--new-page {
            background: #fff; box-shadow: 0 3mm 12mm rgba(0,0,0,.15); margin: 0 auto 10mm;
            min-height: 297mm; padding: 17mm 16mm 18mm; width: 210mm;
        }
        .cover { height: 297mm; padding: 22mm 20mm; }
        .summary { min-height: 210mm; padding: 15mm 16mm 16mm; width: 297mm; }
    }
</style>
