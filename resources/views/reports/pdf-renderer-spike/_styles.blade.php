<style>
    :root {
        --ink: #172033;
        --muted: #5D6678;
        --line: #D8DDE7;
        --soft: #F3F5F8;
        --accent: #B42318;
        --paper-shadow: 0 3mm 12mm rgba(24, 32, 51, 0.16);
    }

    * {
        box-sizing: border-box;
    }

    html,
    body {
        margin: 0;
        padding: 0;
        color: var(--ink);
        font-family: Arial, Helvetica, sans-serif;
        font-size: 10pt;
        line-height: 1.42;
    }

    h1,
    h2,
    h3,
    p {
        margin-top: 0;
    }

    p {
        orphans: 3;
        widows: 3;
    }

    .sheet {
        position: relative;
        background: #FFFFFF;
        break-after: page;
    }

    .sheet:last-child {
        break-after: auto;
    }

    .cover {
        page: cover;
        color: #FFFFFF;
        background: linear-gradient(150deg, #18233A 0%, #243B62 58%, #B42318 100%);
    }

    .cover > div:last-child {
        position: absolute;
        right: 21mm;
        bottom: 23mm;
        left: 21mm;
    }

    .cover__kicker {
        margin: 0 0 8mm;
        font-size: 11pt;
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
    }

    .cover h1 {
        max-width: 155mm;
        margin: 0 0 8mm;
        font-size: 31pt;
        line-height: 1.08;
    }

    .cover__client {
        margin: 0;
        font-size: 17pt;
    }

    .cover__meta {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 5mm;
        padding-top: 7mm;
        border-top: 0.4mm solid rgba(255, 255, 255, 0.45);
    }

    .cover__meta span {
        display: block;
        margin-bottom: 1mm;
        color: rgba(255, 255, 255, 0.72);
        font-size: 8pt;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .report-sheet {
        page: report;
    }

    .summary {
        page: summary;
    }

    .eyebrow {
        margin-bottom: 3mm;
        color: var(--accent);
        font-size: 8pt;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
    }

    .page-title {
        margin-bottom: 9mm;
        font-size: 27pt;
        line-height: 1.08;
    }

    .lead {
        max-width: 160mm;
        color: #354056;
        font-size: 13pt;
        line-height: 1.5;
    }

    .overview-grid {
        display: table;
        width: 100%;
        table-layout: fixed;
        margin-top: 10mm;
        border-spacing: 6mm 0;
    }

    .panel {
        display: table-cell;
        width: 50%;
        padding: 6mm;
        border: 0.3mm solid var(--line);
        border-radius: 2mm;
        background: #FFFFFF;
    }

    .panel h2 {
        margin-bottom: 3mm;
        font-size: 12pt;
    }

    .panel p:last-child {
        margin-bottom: 0;
    }

    .legend {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        margin-top: 7mm;
    }

    .legend__item {
        display: flex;
        width: 49%;
        align-items: center;
        gap: 3mm;
        margin-bottom: 3mm;
        padding: 3mm;
        border: 0.3mm solid var(--line);
        border-radius: 1.5mm;
    }

    .legend__dot {
        width: 3mm;
        height: 3mm;
        flex: 0 0 auto;
        border-radius: 50%;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 4mm;
        border-bottom: 0.3mm solid var(--line);
        text-align: left;
        vertical-align: top;
    }

    th {
        color: var(--muted);
        font-size: 8pt;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .summary-table td {
        font-size: 11pt;
    }

    .priority-pill,
    .tag {
        display: inline-block;
        padding: 1.2mm 2.6mm;
        border-radius: 999px;
        color: #FFFFFF;
        font-size: 8pt;
        font-weight: 700;
    }

    .tag {
        margin-left: 2mm;
        color: #24314A;
        background: #E8EDF5;
    }

    .finding-header {
        display: table;
        width: 100%;
        table-layout: fixed;
        margin-bottom: 6mm;
    }

    .finding-number {
        display: table-cell;
        width: 15mm;
        height: 15mm;
        border-radius: 50%;
        color: #FFFFFF;
        background: var(--ink);
        font-size: 13pt;
        font-weight: 700;
        line-height: 15mm;
        text-align: center;
        vertical-align: top;
    }

    .finding-header > div:last-child {
        display: table-cell;
        padding-left: 5mm;
        vertical-align: top;
    }

    .finding-header h1 {
        margin: 0;
        font-size: 22pt;
        line-height: 1.12;
    }

    .continuation-label {
        margin-bottom: 2mm;
        color: var(--muted);
        font-size: 8pt;
        font-weight: 700;
        text-transform: uppercase;
    }

    .finding-layout {
        display: table;
        width: 100%;
        table-layout: fixed;
    }

    .finding-layout > div {
        display: table-cell;
        width: 66%;
        padding-right: 7mm;
        vertical-align: top;
    }

    .finding-layout > aside {
        display: table-cell;
        width: 34%;
        vertical-align: top;
    }

    .section {
        margin-bottom: 6mm;
    }

    .section h2 {
        margin-bottom: 2.5mm;
        font-size: 11pt;
    }

    .risk-matrix {
        display: table;
        width: 100%;
        table-layout: fixed;
        border-spacing: 1mm 0;
        margin-top: 3mm;
    }

    .risk-matrix__cell {
        display: table-cell;
        width: 33.333%;
        min-height: 16mm;
        padding: 3mm;
        border-radius: 1.5mm;
        color: #FFFFFF;
    }

    .risk-matrix__cell span {
        display: block;
        margin-bottom: 1mm;
        font-size: 7pt;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .asset-card {
        padding: 4mm;
        border: 0.3mm solid var(--line);
        border-left: 1.2mm solid #2563EB;
        border-radius: 1.5mm;
    }

    .asset-card dl {
        margin: 0;
    }

    .asset-card dt {
        margin-top: 1.5mm;
        color: var(--muted);
        font-size: 8pt;
    }

    .asset-card dd {
        margin: 0;
        font-weight: 700;
    }

    .solution {
        break-inside: avoid;
        margin-bottom: 4mm;
        padding: 4.5mm;
        border: 0.3mm solid var(--line);
        border-left: 1.2mm solid #52627D;
        border-radius: 1.5mm;
        background: #FFFFFF;
    }

    .solution h3 {
        margin-bottom: 2mm;
        font-size: 11pt;
    }

    .solution__token {
        display: block;
        margin-bottom: 1mm;
        color: var(--muted);
        font-size: 7pt;
        letter-spacing: 0.08em;
    }

    .solution p {
        margin-bottom: 2.5mm;
    }

    .solution__meta {
        display: flex;
        justify-content: space-between;
        color: var(--muted);
        font-size: 8pt;
    }

    .evidence {
        margin: 4mm 0 0;
        break-inside: avoid;
    }

    .evidence img {
        display: block;
        width: 100%;
        max-height: 76mm;
        object-fit: cover;
        border: 0.3mm solid var(--line);
        border-radius: 1.5mm;
    }

    .evidence figcaption {
        margin-top: 2mm;
        color: var(--muted);
        font-size: 8pt;
    }

    .fixed-note {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        color: var(--muted);
        font-size: 7.5pt;
    }

    @page cover {
        size: A4 portrait;
        margin: 0;
        counter-reset: page 0;

        @bottom-right {
            content: none;
        }
    }

    @page report {
        size: A4 portrait;
        margin: 15mm 16mm 17mm;

        @bottom-right {
            content: counter(page);
            color: #5D6678;
            font: 9pt Arial, Helvetica, sans-serif;
        }
    }

    @page summary {
        size: A4 landscape;
        margin: 15mm 16mm 17mm;

        @bottom-right {
            content: counter(page);
            color: #5D6678;
            font: 9pt Arial, Helvetica, sans-serif;
        }
    }

    @media print {
        .cover {
            width: 210mm;
            height: 296.5mm;
            padding: 23mm 21mm;
        }

        .report-sheet {
            min-height: 265mm;
        }

        .summary {
            min-height: 178mm;
        }
    }

    @media screen {
        body {
            padding: 12mm 0;
            background: #E7EAF0;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 12mm;
            padding: 15mm 16mm 17mm;
            box-shadow: var(--paper-shadow);
        }

        .cover {
            height: 297mm;
            padding: 23mm 21mm;
        }

        .summary {
            width: 297mm;
            min-height: 210mm;
        }
    }
</style>
