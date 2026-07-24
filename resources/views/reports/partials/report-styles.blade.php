<?php declare(strict_types=1); ?>

<style>
    @page { margin: 13mm 17mm 23mm 20mm; }

    body {
        color: #111111;
        font-family: "DejaVu Sans", sans-serif;
        font-size: 8.75pt;
        line-height: 1.35;
        margin: 0;
        padding: 0;
    }

    * { box-sizing: border-box; }
    h1, h2, h3, h4, p { margin-top: 0; }
    table { border-collapse: collapse; width: 100%; }
    th, td { vertical-align: top; }
    a { color: #111111; text-decoration: underline; }

    .page-break { page-break-before: always; }
    .avoid-break,
    .management-note,
    .solution-metrics,
    .finding-summary,
    .asset-details,
    .associated-assets__first,
    .evidence-block,
    .consultant-information { page-break-inside: avoid; }
    .pre-line { white-space: pre-line; }
    .muted { color: #666666; }
    .label {
        color: #666666;
        display: block;
        font-size: 7.25pt;
        font-weight: bold;
        letter-spacing: 0.08em;
        margin-bottom: 1mm;
        text-transform: uppercase;
    }
    .priority-glyph,
    .status-glyph {
        font-family: "DejaVu Sans", sans-serif;
        font-weight: normal;
        margin-right: 1.2mm;
    }
    .section-kicker {
        color: #666666;
        font-size: 7.25pt;
        font-weight: bold;
        letter-spacing: 0.09em;
        margin-bottom: 2mm;
        text-transform: uppercase;
    }
    .section-heading {
        border-bottom: 0;
        margin: 0 0 5mm;
        table-layout: auto;
    }
    .section-heading__number,
    .section-heading__title { vertical-align: top; }
    .section-heading__number {
        color: #111111;
        font-size: 36pt;
        font-weight: bold;
        line-height: 0.9;
        padding-right: 4mm;
        width: 24mm;
    }
    .section-heading__title {
        border-left: 0.7mm solid {{ $report->setting('primary_color') }};
        font-size: 23pt;
        font-weight: bold;
        line-height: 1.08;
        padding: 0 0 0 6.5mm;
    }
    .editorial-heading {
        border-bottom: 0.35mm solid #111111;
        font-size: 18pt;
        line-height: 1.1;
        margin: 9mm 0 5mm;
        padding-bottom: 2.5mm;
    }
    .subsection-heading {
        border-top: 0.25mm solid #D7D7D2;
        font-size: 8pt;
        letter-spacing: 0.09em;
        margin: 7mm 0 3mm;
        padding-top: 2.5mm;
        text-transform: uppercase;
    }

    .cover {
        height: 249mm;
        position: relative;
    }
    .cover__masthead {
        border-bottom: 0.3mm solid #111111;
        font-size: 7.5pt;
        font-weight: bold;
        letter-spacing: 0.1em;
        padding-bottom: 3mm;
    }
    .cover__masthead-right {
        font-size: 13pt;
        text-align: right;
        width: 18mm;
    }
    .cover__accent {
        border-top: 1mm solid {{ $report->setting('primary_color') }};
        margin-top: 4mm;
        width: 18mm;
    }
    .cover__center { margin-top: 46mm; width: 84%; }
    .cover__title {
        font-size: 33pt;
        line-height: 1.02;
        margin: 0 0 8mm;
        overflow-wrap: break-word;
        word-wrap: break-word;
    }
    .cover__client {
        font-size: 14pt;
        font-weight: normal;
        margin-bottom: 4mm;
    }
    .cover__date { color: #666666; font-size: 9pt; }
    .cover__client-logo {
        display: block;
        height: auto;
        margin-top: 7mm;
        max-height: 16mm;
        max-width: 40mm;
        width: auto;
    }
    .cover__footer {
        border-top: 0.3mm solid #111111;
        bottom: 0;
        left: 0;
        padding-top: 5mm;
        position: absolute;
        width: 100%;
    }
    .cover__footer-logo {
        display: block;
        height: auto;
        max-height: 21mm;
        max-width: 52mm;
        width: auto;
    }
    .cover__footer-client-logo {
        display: block;
        height: auto;
        max-height: 16mm;
        max-width: 40mm;
        width: auto;
    }
    .cover__footer-copy { padding-left: 6mm; }
    .cover__footer-name { font-size: 11pt; font-weight: bold; }
    .cover__footer-role { color: #666666; font-size: 8pt; margin-top: 1mm; }

    .overview { page-break-before: auto; }
    .overview__introduction {
        font-size: 10pt;
        line-height: 1.45;
        margin: 4mm 0;
    }
    .kpi-row { border: 0; margin-bottom: 5mm; table-layout: fixed; }
    .kpi-row td { border: 0; border-left: 0.25mm solid #D7D7D2; padding: 1.5mm 3mm 1.5mm; }
    .kpi-row td:first-child { border-left: 0; padding-left: 0; }
    .kpi__value { font-size: 21pt; font-weight: bold; line-height: 1; }
    .information-grid { background: #F3F3EF; border: 0; margin-bottom: 4mm; table-layout: fixed; }
    .information-grid td { border: 0; padding: 2.5mm 4mm; width: 50%; }
    .executive-summary { border-left: 0.7mm solid {{ $report->setting('primary_color') }}; margin: 4mm 0; padding-left: 5mm; }
    .executive-summary__title { font-size: 12pt; margin-bottom: 2mm; }

    .consultant-information { border-top: 0.35mm solid #111111; margin-top: 8mm; padding-top: 4mm; }
    .consultant-information__title { font-size: 12pt; margin-bottom: 3mm; }
    .consultant-information__grid { table-layout: fixed; }
    .consultant-information__grid td { border-top: 0.25mm solid #D7D7D2; padding: 2.5mm 3mm 2.5mm 0; width: 50%; }
    .consultant-information__grid td + td { padding-left: 3mm; }

    .content-index { margin: 0; padding-left: 8mm; }
    .content-index li { border-bottom: 0.25mm solid #D7D7D2; padding: 2.5mm 0; }
    .priority-legend-section { border: 0; margin-top: 3mm; padding: 0; }
    .priority-legend-heading { font-size: 12pt; margin: 0 0 2mm; }
    .priority-legend--detailed { border-collapse: separate; border-spacing: 0 1.5mm; }
    .priority-legend--detailed td { border: 0; padding: 1mm 2mm 1mm 0; }
    .priority-legend__marker { width: 5mm; }
    .priority-legend__label { font-weight: bold; width: 38mm; }
    .priority-legend-inline { margin-top: 3mm; table-layout: auto; width: auto; }
    .priority-legend-inline th,
    .priority-legend-inline td { border: 0; padding: 0 5mm 0 0; white-space: nowrap; }
    .priority-legend-inline th {
        color: #666666;
        font-size: 7pt;
        letter-spacing: 0.08em;
        text-align: left;
        text-transform: uppercase;
    }
    .priority-legend-inline td { font-size: 8pt; }

    .findings-summary-section { page-break-before: always; }
    .findings-summary-section .section-heading { margin-bottom: 4mm; }
    .findings-summary__description { color: #666666; margin: 0 0 4mm 24mm; }
    .finding-summary { font-size: 8.25pt; line-height: 1.3; margin-bottom: 1.5mm; table-layout: fixed; }
    .finding-summary td { overflow-wrap: break-word; padding: 1mm 1.25mm 1mm 0; word-wrap: break-word; }
    .finding-summary__main td { border-top: 0.35mm solid #111111; }
    .finding-summary__details td { border: 0; color: #333333; padding-bottom: 1.25mm; }
    .finding-summary .label { font-size: 6.5pt; margin-bottom: 0.4mm; }
    .finding-summary__number { font-size: 15pt; font-weight: bold; line-height: 1; width: 20mm; }
    .finding-summary__title { font-size: 9.25pt; font-weight: bold; width: 70mm; }
    .finding-summary__category { width: 31mm; }
    .finding-summary__priority { width: 26mm; }
    .finding-summary__status { width: 26mm; }
    .finding-summary__scope { width: 34mm; }
    .finding-summary__solution { width: 64mm; }
    .finding-summary__effort { width: 25mm; }
    .finding-summary__estimate { width: 30mm; }

    .finding-detail { page-break-before: auto; }
    .finding-detail--new-page { page-break-before: always; padding-top: 12mm; }
    .finding-heading { border-bottom: 0.4mm solid #111111; margin-bottom: 2mm; padding-bottom: 2mm; page-break-inside: avoid; }
    .finding-heading__number {
        font-size: 22pt;
        font-weight: bold;
        line-height: 1;
        margin-bottom: 2mm;
    }
    .finding-title {
        line-height: 1.06;
        margin: 0;
        overflow-wrap: break-word;
        word-wrap: break-word;
    }
    .finding-title--default { font-size: 26pt; }
    .finding-title--medium { font-size: 22pt; }
    .finding-title--compact { font-size: 19pt; }
    .finding-meta { margin-top: 3mm; table-layout: auto; width: auto; }
    .finding-meta td {
        border: 0;
        font-size: 7.25pt;
        font-weight: bold;
        padding: 0 4mm 0 0;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .finding-meta td + td { border-left: 0.25mm solid #D7D7D2; padding-left: 4mm; }
    .finding-meta__token { display: inline-block; white-space: nowrap; }
    .finding-meta__priority-bar { display: inline-block; height: 4mm; margin-right: 1.5mm; vertical-align: middle; width: 0.8mm; }
    .management-note { background: #F3F3EF; border-left: 0.8mm solid {{ $report->setting('primary_color') }}; margin: 3mm 0 4mm; padding: 2.5mm 3mm 2.5mm 4mm; }
    .management-note__label { color: {{ $report->setting('primary_color') }}; font-size: 7.25pt; font-weight: bold; letter-spacing: 0.09em; margin-bottom: 1.5mm; text-transform: uppercase; }
    .management-note .pre-line { font-size: 9.75pt; line-height: 1.48; }
    .finding-section { margin: 3mm 0 0; }
    .finding-section__label { font-size: 8pt; font-weight: bold; letter-spacing: 0.1em; margin-bottom: 1.5mm; text-transform: uppercase; }
    .recommended-solution { padding-top: 2mm; }
    .recommended-solution .finding-section__label { border-left: 0.8mm solid {{ $report->setting('primary_color') }}; padding-left: 2mm; }
    .solution-title { font-size: 12.5pt; line-height: 1.15; margin-bottom: 1.5mm; }
    .solution-comparison { border-left: 0.5mm solid #D7D7D2; color: #666666; margin-top: 2.5mm; padding-left: 2.5mm; }
    .solution-metrics {
        background: transparent;
        border-bottom: 0.25mm solid #D7D7D2;
        border-top: 0.25mm solid #D7D7D2;
        margin-top: 3mm;
        table-layout: fixed;
    }
    .solution-metrics td { border: 0; padding: 2.5mm 4mm 2.5mm 0; }
    .solution-metrics td + td { border-left: 0.25mm solid #D7D7D2; padding-left: 4mm; }
    .solution-metrics strong { display: block; font-size: 11pt; margin-bottom: 1mm; }
    .alternative-solution { border-top: 0.25mm solid #D7D7D2; margin: 4mm 0; padding-top: 3mm; page-break-inside: avoid; }
    .alternative-solution__title { font-size: 11pt; font-weight: bold; margin-bottom: 2mm; }
    .alternative-solution__meta { margin-top: 3mm; table-layout: fixed; }
    .alternative-solution__meta td { border-top: 0.25mm solid #D7D7D2; padding: 2.5mm 3mm 0 0; }
    .finding-scope { margin-top: 4mm; }
    .finding-scope__row { table-layout: auto; width: auto; }
    .finding-scope__row td { border: 0; padding: 0; }
    .finding-scope__row .label { padding-right: 4mm; white-space: nowrap; }
    .risk-evaluation { margin-top: 4mm; }
    .risk-evaluation__grid { table-layout: fixed; }
    .risk-evaluation__grid td { border: 0; padding: 2mm 5mm 2mm 0; vertical-align: baseline; }
    .risk-evaluation__grid td + td { border-left: 0.25mm solid #D7D7D2; padding-left: 5mm; }
    .risk-evaluation__grid td:nth-child(1),
    .risk-evaluation__grid td:nth-child(2) { width: 30%; }
    .risk-evaluation__grid td:nth-child(3) { width: 40%; }
    .risk-evaluation__grid strong { display: block; font-size: 11pt; }
    .risk-evaluation__result strong { font-size: 12pt; }
    .risk-evaluation__rationale { color: #666666; font-size: 7.75pt; margin-top: 2mm; }
    .associated-assets { margin-top: 4mm; }
    .asset-details { border-top: 0.25mm solid #D7D7D2; margin-bottom: 2.5mm; padding-top: 2mm; }
    .asset-details__name { font-size: 10.5pt; font-weight: bold; }
    .asset-details__type { color: #333333; font-size: 8.5pt; margin-top: 0.5mm; }
    .asset-details__metadata { color: #666666; font-size: 7.75pt; margin-top: 1mm; }
    .asset-details__metadata-item { margin-top: 0.35mm; }
    .technical-notes,
    .resolution-details { border-top: 0.3mm solid #111111; margin-top: 8mm; padding-top: 3mm; }

    .finding-evidence { page-break-before: always; padding-top: 12mm; }
    .finding-evidence__eyebrow { border-bottom: 0.25mm solid #D7D7D2; color: #666666; font-size: 7.25pt; font-weight: bold; letter-spacing: 0.08em; margin-bottom: 4mm; padding-bottom: 2mm; text-transform: uppercase; }
    .finding-evidence__title { font-size: 19pt; margin-bottom: 7mm; }
    .evidence-block { margin-bottom: 8mm; }
    .evidence-block__label { color: #666666; font-size: 7.25pt; font-weight: bold; letter-spacing: 0.08em; text-transform: uppercase; }
    .evidence-block__title { font-size: 11pt; font-weight: bold; margin-top: 1.5mm; }
    .evidence-image { display: block; height: auto; margin: 2mm auto 0; max-height: 145mm; max-width: 100%; width: auto; }
    .evidence-caption { border-top: 0.25mm solid #D7D7D2; color: #666666; font-size: 8pt; margin-top: 2mm; padding-top: 2mm; }
    .evidence-reference { border-top: 0.3mm solid #111111; padding-top: 3mm; }
    .evidence-reference__meta { color: #666666; font-size: 8pt; margin-top: 1.5mm; }

    .final-section { margin-top: 10mm; }
    .final-section--new-page { page-break-before: always; }
    .attachment-list { margin: 0; padding-left: 6mm; }
    .attachment-list li { border-bottom: 0.25mm solid #D7D7D2; padding: 2mm 0; }
    .estimate-note { border: 0; color: #666666; font-size: 7.75pt; margin: 5mm 0 0; padding: 0; }
    .signature-line { border-bottom: 0.3mm solid #111111; height: 13mm; width: 68mm; }
</style>
