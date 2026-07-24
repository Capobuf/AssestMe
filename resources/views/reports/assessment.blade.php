<?php declare(strict_types=1); ?>
@php
    $statusGlyph = static fn (string $status): string => match ($status) {
        'open', 'planned' => '○',
        'resolved' => '✓',
        default => '–',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $report->locale }}">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    @include('reports.partials.report-styles')
</head>
<body>
    @if ($report->setting('cover') === true)
        @include('reports.partials.cover')
    @endif

    @include('reports.partials.overview')

    @if ($report->setting('content_index') === true)
        @include('reports.partials.content-index')
    @endif

    @if ($report->setting('risk_legend') === true && $report->priorityLegend !== [])
        @include('reports.partials.priority-legend')
    @endif

    @if ($report->setting('summary_table') === true)
        @include('reports.partials.findings-summary')
    @endif

    @foreach ($report->findings as $finding)
        @include('reports.partials.finding-detail', ['finding' => $finding])

        @if ($report->setting('evidence') === true && $finding->includedEvidence() !== [])
            @include('reports.partials.finding-evidence', ['finding' => $finding])
        @endif
    @endforeach

    @include('reports.partials.final-sections')
</body>
</html>
