<?php declare(strict_types=1); ?>

<section class="sheet summary findings-summary-section">
    <h1 class="page-title">{{ __('assestme.reports.document.findings_summary') }}</h1>
    <p class="findings-summary__description">{{ __('assestme.reports.document.findings_summary_description') }}</p>

    <table class="summary-table">
        <thead><tr>
            <th>{{ __('assestme.reports.document.finding') }}</th>
            <th>{{ __('assestme.reports.document.problem') }}</th>
            <th>{{ __('assestme.reports.document.primary_solution') }}</th>
            <th>{{ __('assestme.reports.document.priority') }}</th>
            <th>{{ __('assestme.reports.document.effort') }}</th>
            <th>{{ __('assestme.reports.document.estimate') }}</th>
        </tr></thead>
        <tbody>
        @foreach ($report->findings as $finding)
            @php($primary = $finding->primarySolution())
            <tr>
                <td><strong>{{ str_pad((string) $finding->number, 2, '0', STR_PAD_LEFT) }} · {{ $finding->title }}</strong></td>
                <td>{{ $finding->problemExcerpt() }}</td>
                <td><span class="summary-role">{{ $primary->implemented ? __('assestme.reports.document.implemented') : __('assestme.reports.document.recommended') }}</span><strong>{{ $primary->title }}</strong><br>{{ $finding->primarySolutionExcerpt() }}</td>
                <td><span class="priority-marker" style="border-color: {{ $finding->priorityColor }}"></span>{{ $finding->priorityLabel }}</td>
                <td>{{ $primary->effortLabel ?? __('assestme.reports.document.not_available') }}</td>
                <td>{{ $report->setting('costs') === true ? $primary->estimateLabel : '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</section>
