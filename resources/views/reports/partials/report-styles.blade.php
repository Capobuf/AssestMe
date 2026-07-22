<?php declare(strict_types=1); ?>

<style>
    @page { margin: 24mm 16mm 22mm; }

    html, body { margin: 0; padding: 0; }
    body {
        background: #FFFFFF;
        color: #111111;
        font-family: "DejaVu Sans", sans-serif;
        font-size: 9.25pt;
        line-height: 1.45;
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
    .section-kicker {
        color: #666666;
        font-size: 7.25pt;
        font-weight: bold;
        letter-spacing: 0.09em;
        margin-bottom: 2mm;
        text-transform: uppercase;
    }
    .section-heading {
        border-bottom: 0.35mm solid #111111;
        margin: 0 0 7mm;
        padding-bottom: 3mm;
    }
    .section-heading__number {
        color: #111111;
        font-size: 42pt;
        font-weight: bold;
        line-height: 0.9;
        width: 25mm;
    }
    .section-heading__title {
        font-size: 19pt;
        font-weight: bold;
        line-height: 1.08;
        padding-top: 3mm;
    }
    .section-heading__title::after {
        border-top: 0.7mm solid {{ $report->setting('primary_color') }};
        content: "";
        display: block;
        margin-top: 3mm;
        width: 16mm;
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
        height: 244mm;
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
        font-size: 11pt;
        line-height: 1.5;
        margin: -1mm 0 8mm 25mm;
        width: 78%;
    }
    .kpi-row { border-bottom: 0.3mm solid #D7D7D2; border-top: 0.3mm solid #D7D7D2; margin-bottom: 8mm; table-layout: fixed; }
    .kpi-row td { border-left: 0.25mm solid #D7D7D2; padding: 4mm 3mm 3mm; }
    .kpi-row td:first-child { border-left: 0; padding-left: 0; }
    .kpi__value { font-size: 23pt; font-weight: bold; line-height: 1; }
    .kpi__marker {
        display: inline-block;
        height: 2mm;
        margin-right: 1.5mm;
        width: 2mm;
    }
    .information-grid { border-bottom: 0.25mm solid #D7D7D2; margin-bottom: 7mm; table-layout: fixed; }
    .information-grid td { border-top: 0.25mm solid #D7D7D2; padding: 3mm 4mm 3mm 0; width: 50%; }
    .information-grid td + td { border-left: 0.25mm solid #D7D7D2; padding-left: 4mm; }
    .executive-summary { border-left: 0.7mm solid {{ $report->setting('primary_color') }}; margin: 8mm 0; padding-left: 5mm; }
    .executive-summary__title { font-size: 12pt; margin-bottom: 2mm; }

    .consultant-information { border-top: 0.35mm solid #111111; margin-top: 8mm; padding-top: 4mm; }
    .consultant-information__title { font-size: 12pt; margin-bottom: 3mm; }
    .consultant-information__grid { table-layout: fixed; }
    .consultant-information__grid td { border-top: 0.25mm solid #D7D7D2; padding: 2.5mm 3mm 2.5mm 0; width: 50%; }
    .consultant-information__grid td + td { padding-left: 3mm; }

    .content-index { margin: 0; padding-left: 8mm; }
    .content-index li { border-bottom: 0.25mm solid #D7D7D2; padding: 2.5mm 0; }
    .priority-legend td { border-top: 0.25mm solid #D7D7D2; padding: 3mm 2mm; }
    .priority-legend__marker { width: 4mm; }
    .priority-legend__bar { display: block; height: 8mm; width: 1.2mm; }
    .priority-legend__label { font-weight: bold; width: 38mm; }

    .findings-summary-section { page-break-before: always; }
    .findings-summary__description { color: #666666; margin: -2mm 0 7mm 25mm; }
    .finding-summary { border-bottom: 0.3mm solid #111111; margin-bottom: 5mm; table-layout: fixed; }
    .finding-summary td { border-left: 0.25mm solid #D7D7D2; overflow-wrap: break-word; padding: 3mm 2.5mm; word-wrap: break-word; }
    .finding-summary td:first-child { border-left: 0; }
    .finding-summary__main { border-top: 0.3mm solid #111111; }
    .finding-summary__details { background: #F3F3EF; border-top: 0.25mm solid #D7D7D2; }
    .finding-summary__number { font-size: 18pt; font-weight: bold; line-height: 1; width: 10%; }
    .finding-summary__title { font-size: 10.5pt; font-weight: bold; width: 34%; }
    .finding-summary__category { width: 19%; }
    .finding-summary__priority { width: 19%; }
    .finding-summary__status { width: 18%; }
    .priority-marker { display: inline-block; height: 2mm; margin-right: 1.5mm; width: 2mm; }

    .finding-detail { page-break-before: auto; }
    .finding-detail--new-page { page-break-before: always; }
    .finding-heading { border-bottom: 0.4mm solid #111111; margin-bottom: 4mm; padding-bottom: 5mm; table-layout: fixed; }
    .finding-heading__number {
        font-size: 44pt;
        font-weight: bold;
        line-height: 0.86;
        width: 30mm;
    }
    .finding-heading__content { padding-left: 4mm; }
    .finding-title {
        line-height: 1.02;
        margin: 0;
        overflow-wrap: break-word;
        word-wrap: break-word;
    }
    .finding-title--default { font-size: 30pt; }
    .finding-title--medium { font-size: 25pt; }
    .finding-title--compact { font-size: 21pt; }
    .finding-indicators { margin: 3mm 0 7mm 34mm; }
    .finding-indicator {
        border: 0.25mm solid #D7D7D2;
        display: inline-block;
        font-size: 7.25pt;
        margin: 0 1.5mm 1.5mm 0;
        padding: 1mm 2mm;
        text-transform: uppercase;
    }
    .finding-indicator--priority { border-left-width: 1mm; }
    .management-note { background: #F3F3EF; border-left: 1.3mm solid #111111; margin: 5mm 0 8mm 34mm; padding: 5mm; }
    .management-note__label { font-size: 7.25pt; font-weight: bold; letter-spacing: 0.09em; margin-bottom: 2mm; text-transform: uppercase; }
    .management-note .pre-line { font-size: 10.5pt; line-height: 1.5; }
    .finding-section { margin: 7mm 0 0 34mm; }
    .finding-section__label { font-size: 8pt; font-weight: bold; letter-spacing: 0.1em; margin-bottom: 3mm; text-transform: uppercase; }
    .recommended-solution { border-top: 0.7mm solid {{ $report->setting('primary_color') }}; padding-top: 4mm; }
    .solution-title { font-size: 14pt; line-height: 1.15; margin-bottom: 3mm; }
    .solution-comparison { border-left: 0.5mm solid #D7D7D2; color: #666666; margin-top: 4mm; padding-left: 3mm; }
    .solution-metrics { background: #F3F3EF; margin-top: 5mm; table-layout: fixed; }
    .solution-metrics td { border-left: 0.25mm solid #D7D7D2; padding: 3.5mm; }
    .solution-metrics td:first-child { border-left: 0; }
    .solution-metrics strong { display: block; font-size: 11pt; margin-bottom: 1mm; }
    .alternative-solution { border-top: 0.3mm solid #111111; margin: 5mm 0; padding-top: 3mm; page-break-inside: auto; }
    .alternative-solution__title { font-size: 11pt; font-weight: bold; margin-bottom: 2mm; }
    .alternative-solution__meta { margin-top: 3mm; table-layout: fixed; }
    .alternative-solution__meta td { border-top: 0.25mm solid #D7D7D2; padding: 2.5mm 3mm 0 0; }
    .classification-table { table-layout: fixed; }
    .classification-table th,
    .classification-table td { border-top: 0.25mm solid #D7D7D2; padding: 2.5mm 0; text-align: left; }
    .classification-table th { color: #666666; font-size: 7.25pt; letter-spacing: 0.07em; padding-right: 4mm; text-transform: uppercase; width: 31%; }
    .associated-assets { margin-top: 8mm; }
    .asset-details { border-top: 0.3mm solid #111111; margin-bottom: 4mm; }
    .asset-details th,
    .asset-details td { border-bottom: 0.25mm solid #D7D7D2; padding: 2.2mm 0; text-align: left; }
    .asset-details th { color: #666666; font-size: 7.25pt; padding-right: 4mm; text-transform: uppercase; width: 31%; }
    .asset-details__name { font-size: 10.5pt; }
    .technical-notes,
    .resolution-details { border-top: 0.3mm solid #111111; margin-top: 8mm; padding-top: 3mm; }

    .finding-evidence { page-break-before: always; }
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
    .estimate-note { border-top: 0.3mm solid #111111; color: #666666; font-size: 8pt; margin: 8mm 0 0; padding-top: 2.5mm; }
    .signature-line { border-bottom: 0.3mm solid #111111; height: 13mm; width: 68mm; }
</style>
