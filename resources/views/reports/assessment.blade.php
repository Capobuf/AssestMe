<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="{{ $report->locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 24mm 16mm 22mm; }
        body { color: #1f2937; font-family: "DejaVu Sans", sans-serif; font-size: 9pt; line-height: 1.4; }
        h1, h2, h3, p { margin-top: 0; }
        a { color: {{ $report->setting('primary_color') }}; }
        .page-break { page-break-before: always; }
        .avoid-break, .solution, .evidence, .summary-finding { page-break-inside: avoid; }
        .cover { padding-top: 42mm; text-align: center; }
        .cover img { max-height: 28mm; max-width: 75mm; margin: 0 4mm 14mm; }
        .cover h1 { color: {{ $report->setting('primary_color') }}; font-size: 26pt; margin-bottom: 8mm; }
        .muted { color: #6b7280; }
        .confidentiality { border: 1px solid #9ca3af; display: inline-block; font-size: 8pt; margin-top: 18mm; padding: 2mm 4mm; text-transform: uppercase; }
        .section-title { border-bottom: 2px solid {{ $report->setting('primary_color') }}; color: {{ $report->setting('primary_color') }}; font-size: 16pt; margin: 7mm 0 5mm; padding-bottom: 2mm; }
        .subsection-title { color: {{ $report->setting('primary_color') }}; font-size: 11pt; margin: 5mm 0 2mm; }
        table { border-collapse: collapse; width: 100%; }
        .meta-table td, .detail-table td { padding: 2mm 3mm; vertical-align: top; }
        .summary-table { font-size: 8pt; table-layout: fixed; }
        .summary-table th, .summary-table td { border: 1px solid #d1d5db; overflow-wrap: break-word; padding: 2mm; text-align: left; vertical-align: top; }
        .summary-table th { background: #e5e7eb; }
        .summary-table .number { width: 6%; }
        .summary-details td { background: #f9fafb; border-top: 0; }
        .summary-details-table td { background: transparent; border: 0; padding: 0 3mm 0 0; }
        .label { color: #4b5563; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; }
        .content-block { margin-bottom: 4mm; }
        .finding-header { background: #f3f4f6; border-left: 5px solid {{ $report->setting('primary_color') }}; margin-bottom: 4mm; padding: 4mm; }
        .finding-header h2 { font-size: 15pt; margin-bottom: 2mm; }
        .entrepreneur-focus { background: #eff6ff; border-left: 5px solid {{ $report->setting('primary_color') }}; margin: 4mm 0; padding: 4mm; }
        .entrepreneur-focus h3 { color: {{ $report->setting('primary_color') }}; font-size: 12pt; margin-bottom: 2mm; }
        .badge { border: 1px solid #9ca3af; display: inline-block; font-size: 8pt; margin-right: 1mm; padding: 1mm 2mm; }
        .solution { border: 1px solid #d1d5db; margin-bottom: 4mm; padding: 3mm; }
        .solution.recommended { border-left: 5px solid {{ $report->setting('primary_color') }}; }
        .evidence { margin-bottom: 6mm; }
        .evidence .subsection-title { margin-top: 0; }
        .evidence img { display: block; height: auto; margin: 2mm auto 0; max-height: 150mm; max-width: 100%; }
        .attachment-list { margin: 0; padding-left: 6mm; }
        .estimate-note { border: 1px solid #d1d5db; font-size: 8pt; margin: 5mm 0; padding: 2.5mm; }
        .signature-line { margin-top: 14mm; }
        .pre-line { white-space: pre-line; }
    </style>
</head>
<body>
    @if ($report->setting('cover') === true)
        <section class="cover">
            @foreach ($report->logos as $logo)
                <img src="{{ $logo->dataUri }}" alt="">
            @endforeach
            <h1>{{ $report->title }}</h1>
            @if ($report->setting('cover_title_mode') === 'separate')
                <p>{{ $report->clientName }}</p>
            @endif
            <p class="muted">{{ $report->assessmentDateLabel }}</p>
            @if (filled($report->setting('business_name')) || filled($report->setting('consultant_name')))
                <p>{{ $report->setting('business_name') ?: $report->setting('consultant_name') }}</p>
            @endif
            <div class="confidentiality">{{ $report->setting('confidentiality_label') }}</div>
        </section>
        <div class="page-break"></div>
    @endif

    <section>
        <h1 class="section-title">{{ __('assestme.reports.document.assessment_information') }}</h1>
        <table class="meta-table">
            <tr>
                <td><div class="label">{{ __('assestme.reports.document.client') }}</div>{{ $report->clientName }}</td>
                <td><div class="label">{{ __('assestme.reports.document.assessment_date') }}</div>{{ $report->assessmentDateLabel }}</td>
            </tr>
            <tr>
                <td>
                    @if (filled($report->clientLegalName) && strcasecmp(trim($report->clientName), trim((string) $report->clientLegalName)) !== 0)
                        <div class="label">{{ __('assestme.reports.document.legal_name') }}</div>{{ $report->clientLegalName }}
                    @endif
                </td>
                <td><div class="label">{{ __('assestme.reports.document.status') }}</div>{{ $report->assessmentStatusLabel }}</td>
            </tr>
            @if ($report->clientAddress !== null || $report->clientVatNumber !== null || $report->clientTaxCode !== null)
                <tr>
                    <td><div class="label">{{ __('assestme.reports.document.address') }}</div>{{ $report->clientAddress }}</td>
                    <td>
                        @if ($report->clientVatNumber !== null)<div>{{ __('assestme.reports.document.vat_number') }}: {{ $report->clientVatNumber }}</div>@endif
                        @if ($report->clientTaxCode !== null)<div>{{ __('assestme.reports.document.tax_code') }}: {{ $report->clientTaxCode }}</div>@endif
                    </td>
                </tr>
            @endif
            <tr><td colspan="2"><div class="label">{{ __('assestme.reports.document.scope') }}</div><div class="pre-line">{{ $report->assessmentScope }}</div></td></tr>
            @if ($report->assessmentSites !== [])
                <tr><td colspan="2"><div class="label">{{ __('assestme.reports.document.included_sites') }}</div>{{ implode(', ', $report->assessmentSites) }}</td></tr>
            @endif
        </table>
        @if ($report->introduction !== null)
            <div class="content-block"><div class="label">{{ __('assestme.reports.document.introduction') }}</div><div class="pre-line">{{ $report->introduction }}</div></div>
        @endif
        @include('reports.partials.estimate-note')
    </section>

    @if (collect(['consultant_name', 'business_name', 'consultant_role', 'consultant_email', 'consultant_phone', 'consultant_website', 'consultant_address', 'consultant_vat_number', 'consultant_pec', 'consultant_tax_code'])->contains(fn (string $key): bool => filled($report->setting($key))))
        <section class="avoid-break">
            <h1 class="section-title">{{ __('assestme.reports.document.consultant_information') }}</h1>
            <table class="meta-table">
                @foreach ([
                    'consultant_name' => 'name',
                    'business_name' => 'business_name',
                    'consultant_role' => 'role',
                    'consultant_email' => 'email',
                    'consultant_phone' => 'phone',
                    'consultant_address' => 'address',
                    'consultant_vat_number' => 'vat_number',
                    'consultant_pec' => 'pec',
                    'consultant_tax_code' => 'tax_code',
                ] as $settingKey => $labelKey)
                    @if (filled($report->setting($settingKey)))
                        <tr><td><div class="label">{{ __('assestme.reports.document.'.$labelKey) }}</div>{{ $report->setting($settingKey) }}</td></tr>
                    @endif
                @endforeach
                @if (filled($report->setting('consultant_website')))
                    <tr><td><div class="label">{{ __('assestme.reports.document.website') }}</div><a href="{{ $report->setting('consultant_website') }}">{{ $report->setting('consultant_website') }}</a></td></tr>
                @endif
            </table>
        </section>
    @endif

    @if ($report->setting('executive_summary') === true && $report->executiveSummary !== null)
        <section class="avoid-break"><h1 class="section-title">{{ __('assestme.reports.document.executive_summary') }}</h1><div class="pre-line">{{ $report->executiveSummary }}</div></section>
    @endif

    @if ($report->setting('content_index') === true)
        <section><h1 class="section-title">{{ __('assestme.reports.document.content_index') }}</h1><ol>@foreach ($report->findings as $finding)<li>{{ $finding->number }}. {{ $finding->title }}</li>@endforeach</ol></section>
    @endif

    @if ($report->setting('risk_legend') === true && $report->priorityLegend !== [])
        <section class="avoid-break">
            <h1 class="section-title">{{ __('assestme.reports.document.priority_legend') }}</h1>
            <table class="detail-table">
                @foreach ($report->priorityLegend as $priority)
                    <tr>
                        <td style="border-left: 5px solid {{ $priority->color }}"><strong>{{ $priority->label }}</strong></td>
                        @if ($report->setting('show_priority_descriptions') === true && filled($priority->description))
                            <td>{{ $priority->description }}</td>
                        @endif
                    </tr>
                @endforeach
            </table>
        </section>
    @endif

    @if ($report->setting('summary_table') === true)
        <section>
            <h1 class="section-title">{{ __('assestme.reports.document.findings_summary') }}</h1>
            <table class="summary-table">
                <thead><tr><th class="number">#</th><th style="width: 38%">{{ __('assestme.reports.document.finding') }}</th><th>{{ __('assestme.reports.document.category') }}</th><th>{{ __('assestme.reports.document.priority') }}</th><th>{{ __('assestme.reports.document.status') }}</th></tr></thead>
                    @foreach ($report->findings as $finding)
                        @php($recommended = $finding->recommendedSolution())
                        <tbody class="summary-finding"><tr>
                            <td>{{ $finding->number }}</td><td>{{ $finding->title }}</td><td>{{ $finding->category }}</td><td>{{ $finding->priorityLabel }}</td><td>{{ $finding->statusLabel }}</td>
                        </tr>
                        <tr class="summary-details"><td colspan="5">
                            <table class="summary-details-table"><tr>
                                <td style="width: 25%"><div class="label">{{ __('assestme.reports.document.scope') }}</div>{{ $finding->scopeLabel }}</td>
                                <td style="width: 35%"><div class="label">{{ __('assestme.reports.document.recommended_solution') }}</div><strong>{{ $recommended->title }}</strong><div class="pre-line">{{ $recommended->description }}</div></td>
                                <td style="width: 18%"><div class="label">{{ __('assestme.reports.document.effort') }}</div>{{ $recommended->effortLabel }}</td>
                                <td style="width: 22%"><div class="label">{{ __('assestme.reports.document.estimate') }}</div>{{ $report->setting('costs') === true ? $recommended->estimateLabel : '—' }}</td>
                            </tr></table>
                        </td></tr></tbody>
                    @endforeach
            </table>
        </section>
    @endif

    @foreach ($report->findings as $finding)
        @if ($report->setting('new_page_per_finding') === true)<div class="page-break"></div>@endif
        @php($recommended = $finding->recommendedSolution())
        <article>
            <header class="finding-header" style="border-left-color: {{ $finding->priorityColor }}">
                <h2>{{ $finding->number }}. {{ $finding->title }}</h2>
                <span class="badge">{{ $finding->priorityLabel }}</span><span class="badge">{{ $finding->statusLabel }}</span>
            </header>
            @if (filled($finding->entrepreneurNotes))
                <section class="entrepreneur-focus avoid-break"><h3>{{ __('assestme.reports.document.entrepreneur_notes') }}</h3><div class="pre-line">{{ $finding->entrepreneurNotes }}</div></section>
            @endif
            <div class="content-block"><h3 class="subsection-title">{{ __('assestme.reports.document.problem') }}</h3><div class="pre-line">{{ $finding->problem }}</div></div>

            <h3 class="subsection-title">{{ __('assestme.reports.document.recommended_solution') }}</h3>
            <div class="solution recommended">
                <strong>{{ $recommended->title }}</strong><p class="pre-line">{{ $recommended->description }}</p>
                @if ($recommended->effortLabel !== null)<p><span class="label">{{ __('assestme.reports.document.effort') }}</span><br>{{ $recommended->effortLabel }}@if ($recommended->effortNotes !== null) — {{ $recommended->effortNotes }}@endif</p>@endif
                @if ($report->setting('costs') === true)<p><span class="label">{{ __('assestme.reports.document.economic_estimate') }}</span><br>{{ $recommended->estimateLabel }}@if ($recommended->estimateNotes !== null) — {{ $recommended->estimateNotes }}@endif</p>@endif
            </div>
            @if ($report->setting('alternative_solutions') === true && $finding->alternativeSolutions() !== [])
                <h3 class="subsection-title">{{ __('assestme.reports.document.alternative_solutions') }}</h3>
                @foreach ($finding->alternativeSolutions() as $solution)
                    <div class="solution"><strong>{{ $solution->title }}</strong><p class="pre-line">{{ $solution->description }}</p>
                        @if ($solution->comparisonNotes !== null)<p class="muted pre-line">{{ $solution->comparisonNotes }}</p>@endif
                        @if ($solution->effortLabel !== null)<p>{{ __('assestme.reports.document.effort') }}: {{ $solution->effortLabel }}@if ($solution->effortNotes !== null) — {{ $solution->effortNotes }}@endif</p>@endif
                        @if ($report->setting('costs') === true)<p>{{ __('assestme.reports.document.estimate') }}: {{ $solution->estimateLabel }}@if ($solution->estimateNotes !== null) — {{ $solution->estimateNotes }}@endif</p>@endif
                    </div>
                @endforeach
            @endif

            <h3 class="subsection-title">{{ __('assestme.reports.document.scope_and_classification') }}</h3>
            <div class="content-block"><div class="label">{{ __('assestme.reports.document.scope') }}</div><div class="pre-line">{{ $finding->scopeLabel }}</div></div>
            @if ($finding->sites !== [])<div class="content-block"><div class="label">{{ __('assestme.reports.document.sites') }}</div>{{ implode(', ', $finding->sites) }}</div>@endif
            @if ($finding->assets !== [])
                <div class="content-block"><div class="label">{{ __('assestme.reports.document.assets') }}</div><ul>@foreach ($finding->assets as $asset)<li>{{ $asset->displayLabel }}</li>@endforeach</ul></div>
            @endif
            @if ($finding->tags !== [])<div class="content-block"><div class="label">{{ __('assestme.reports.document.tags') }}</div>{{ implode(', ', $finding->tags) }}</div>@endif
            <div class="content-block"><div class="label">{{ __('assestme.reports.document.priority_and_risk') }}</div>
                {{ $finding->priorityLabel }}
                @if (filled($finding->category)) — {{ __('assestme.reports.document.category') }}: {{ $finding->category }}@endif
                @if ($finding->consequenceLabel !== null) — {{ __('assestme.reports.document.consequence') }}: {{ $finding->consequenceLabel }}@endif
                @if ($finding->likelihoodLabel !== null) — {{ __('assestme.reports.document.likelihood') }}: {{ $finding->likelihoodLabel }}@endif
                @if ($finding->priorityOverridden) — {{ __('assestme.reports.document.manual_priority') }}@endif
            </div>
            @if ($finding->priorityRationale !== null)<div class="content-block"><div class="label">{{ __('assestme.reports.document.priority_rationale') }}</div><div class="pre-line">{{ $finding->priorityRationale }}</div></div>@endif

            @if ($report->setting('evidence') === true && $finding->includedImageEvidence() !== [])
                @foreach ($finding->includedImageEvidence() as $evidence)
                    <div class="evidence">
                        <h3 class="subsection-title">{{ __('assestme.reports.document.evidence') }}</h3>
                        @if (filled($evidence->title))<div class="label">{{ $evidence->title }}</div>@endif
                        <img src="{{ $evidence->imageDataUri }}" alt="">
                        @if ($report->setting('evidence_captions') === true && $evidence->caption !== null)<p class="muted">{{ $evidence->caption }}</p>@endif
                    </div>
                @endforeach
            @endif
            @if ($report->setting('evidence') === true && $finding->includedFileEvidence() !== [])
                <div class="content-block"><div class="label">{{ __('assestme.reports.document.attachments') }}</div><ul class="attachment-list">
                    @foreach ($finding->includedFileEvidence() as $evidence)<li>{{ $evidence->title }}@if ($evidence->originalFilename !== null) — {{ $evidence->originalFilename }}@endif @if ($evidence->mimeType !== null)— {{ __('assestme.reports.document.file_type') }}: {{ $evidence->mimeType }}@endif</li>@endforeach
                </ul></div>
            @endif
            @if ($report->setting('evidence') === true && $finding->includedUrlEvidence() !== [])
                <div class="content-block"><div class="label">{{ __('assestme.reports.document.links') }}</div><ul class="attachment-list">
                    @foreach ($finding->includedUrlEvidence() as $evidence)<li><a href="{{ $evidence->url }}">{{ $evidence->title }}</a></li>@endforeach
                </ul></div>
            @endif
            @if ($report->setting('technical_notes') === true && $finding->technicalNotes !== null)<div class="content-block"><div class="label">{{ __('assestme.reports.document.technical_notes') }}</div><div class="pre-line">{{ $finding->technicalNotes }}</div></div>@endif
            @if ($finding->resolutionNotes !== null || $finding->implementedSolution() !== null)
                <div class="content-block"><div class="label">{{ __('assestme.reports.document.resolution') }}</div>
                    @if ($finding->implementedSolution() !== null)<div>{{ __('assestme.reports.document.implemented_solution') }}: {{ $finding->implementedSolution()?->title }}</div>@endif
                    @if ($finding->resolutionNotes !== null)<div class="pre-line">{{ $finding->resolutionNotes }}</div>@endif
                    @if ($finding->resolvedAtLabel !== null)<div>{{ __('assestme.reports.document.resolved_at') }}: {{ $finding->resolvedAtLabel }}</div>@endif
                </div>
            @endif
        </article>
    @endforeach

    @if ($report->setting('methodology') === true && ($report->methodologyNotes !== null || filled($report->setting('methodology_text'))))
        <div class="page-break"></div><section><h1 class="section-title">{{ __('assestme.reports.document.methodology') }}</h1>
            @if ($report->methodologyNotes !== null)<div class="pre-line">{{ $report->methodologyNotes }}</div>@endif
            @if (filled($report->setting('methodology_text')))<div class="pre-line">{{ $report->setting('methodology_text') }}</div>@endif
        </section>
    @endif
    @if ($report->setting('disclaimer') === true && filled($report->setting('disclaimer_text')))<section class="avoid-break"><h1 class="section-title">{{ __('assestme.reports.document.disclaimer') }}</h1><div class="pre-line">{{ $report->setting('disclaimer_text') }}</div></section>@endif
    @if ($report->setting('signature_block') === true)
        <section class="avoid-break"><h1 class="section-title">{{ __('assestme.reports.document.signature') }}</h1>
            @if (filled($report->setting('signature_text')))<p class="pre-line">{{ $report->setting('signature_text') }}</p>@endif
            <p>{{ $report->setting('signature_name') }}@if (filled($report->setting('signature_role'))) — {{ $report->setting('signature_role') }}@endif</p>
            <p>{{ __('assestme.reports.document.date_line') }}</p><p class="signature-line">{{ __('assestme.reports.document.signature_line') }}</p>
        </section>
    @endif

    @if ($report->setting('evidence') === true && $report->attachments() !== [])
        <section class="avoid-break"><h1 class="section-title">{{ __('assestme.reports.document.attachment_list') }}</h1><ul class="attachment-list">
            @foreach ($report->attachments() as $evidence)<li>{{ $evidence->title }}@if ($evidence->originalFilename !== null) — {{ $evidence->originalFilename }}@endif ({{ $evidence->mimeType }})</li>@endforeach
        </ul></section>
    @endif
</body>
</html>
