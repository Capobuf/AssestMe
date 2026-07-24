<?php declare(strict_types=1); ?>

@php
    $priorityCounts = $report->priorityCounts();
    $openCount = count(array_filter($report->findings, static fn ($finding): bool => ! in_array($finding->status, ['resolved', 'accepted', 'not_applicable'], true)));
    $resolvedCount = count(array_filter($report->findings, static fn ($finding): bool => $finding->status === 'resolved'));
    $assessmentInformation = [
        ['label' => __('assestme.reports.document.client'), 'value' => $report->clientName, 'url' => false],
        ['label' => __('assestme.reports.document.assessment_date'), 'value' => $report->assessmentDateLabel, 'url' => false],
        ['label' => __('assestme.reports.document.status'), 'value' => $report->assessmentStatusLabel, 'url' => false],
        ['label' => __('assestme.reports.document.scope'), 'value' => $report->assessmentScope, 'url' => false],
    ];

    if (filled($report->clientLegalName) && strcasecmp(trim($report->clientName), trim($report->clientLegalName)) !== 0) {
        $assessmentInformation[] = ['label' => __('assestme.reports.document.legal_name'), 'value' => $report->clientLegalName, 'url' => false];
    }
    if ($report->assessmentSites !== []) {
        $assessmentInformation[] = ['label' => __('assestme.reports.document.included_sites'), 'value' => implode(', ', $report->assessmentSites), 'url' => false];
    }
    if (filled($report->clientAddress)) {
        $assessmentInformation[] = ['label' => __('assestme.reports.document.address'), 'value' => $report->clientAddress, 'url' => false];
    }
    if (filled($report->clientVatNumber)) {
        $assessmentInformation[] = ['label' => __('assestme.reports.document.vat_number'), 'value' => $report->clientVatNumber, 'url' => false];
    }
    if (filled($report->clientTaxCode)) {
        $assessmentInformation[] = ['label' => __('assestme.reports.document.tax_code'), 'value' => $report->clientTaxCode, 'url' => false];
    }
@endphp

<section class="sheet report-sheet overview">
    <h1 class="page-title">{{ __('assestme.reports.document.assessment_overview') }}</h1>

    <table class="kpi-row">
        <tr>
            <td>
                <div class="kpi__value">{{ count($report->findings) }}</div>
                <div class="label">{{ __('assestme.reports.document.total_findings') }}</div>
            </td>
            <td><div class="kpi__value">{{ $openCount }}</div><div class="label">{{ __('assestme.reports.document.open_findings') }}</div></td>
            <td><div class="kpi__value">{{ $resolvedCount }}</div><div class="label">{{ __('assestme.reports.document.resolved_findings') }}</div></td>
        </tr>
    </table>

    <table class="priority-distribution" role="presentation">
        <tr>
            @foreach ($priorityCounts as $priorityCount)
                <td>
                    <span
                        class="priority-distribution__marker"
                        style="border-color: {{ $priorityCount['color'] }}"
                    ></span>

                    <strong>{{ $priorityCount['count'] }}</strong>
                    {{ $priorityCount['label'] }}
                </td>
            @endforeach
        </tr>
    </table>

    @if (filled($report->introduction))
        <div class="overview__introduction pre-line">{{ $report->introduction }}</div>
    @endif

    <table class="information-grid">
        @foreach (array_chunk($assessmentInformation, 2) as $row)
            <tr>
                @foreach ($row as $item)
                    <td>
                        <span class="label">{{ $item['label'] }}</span>
                        @if ($item['url'])
                            <a href="{{ $item['value'] }}">{{ $item['value'] }}</a>
                        @else
                            <div class="pre-line">{{ $item['value'] }}</div>
                        @endif
                    </td>
                @endforeach
                @if (count($row) === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>

    @if (filled($report->executiveSummary))
        <section class="executive-summary avoid-break">
            <h2 class="executive-summary__title">{{ __('assestme.reports.document.executive_summary') }}</h2>
            <div class="pre-line">{{ $report->executiveSummary }}</div>
        </section>
    @endif

    @include('reports.partials.estimate-note')

    @if ($report->setting('risk_legend') === true && $report->priorityLegend !== [])
        @include('reports.partials.priority-legend')
    @endif

    @include('reports.partials.consultant-information')
</section>
