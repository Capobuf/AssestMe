<?php declare(strict_types=1); ?>

@php
    $recommended = $finding->recommendedSolution();
    $titleLength = mb_strlen($finding->title);
    $titleClass = match (true) {
        $titleLength > 110 => 'finding-title--compact',
        $titleLength > 70 => 'finding-title--medium',
        default => 'finding-title--default',
    };
    $showEffort = $recommended->effortLabel !== null;
    $showEstimate = $report->setting('costs') === true;
    $scopeValue = $finding->scopeLabel;
    if ($finding->sites !== []) {
        $scopeValue .= "\n".__('assestme.reports.document.sites').': '.implode(', ', $finding->sites);
    }
    $classification = [
        ['label' => __('assestme.reports.document.scope'), 'value' => $scopeValue],
    ];

    if (filled($finding->consequenceLabel)) {
        $classification[] = ['label' => __('assestme.reports.document.consequence'), 'value' => $finding->consequenceLabel];
    }
    if (filled($finding->likelihoodLabel)) {
        $classification[] = ['label' => __('assestme.reports.document.likelihood'), 'value' => $finding->likelihoodLabel];
    }
    if ($finding->priorityOverridden && filled($finding->priorityRationale)) {
        $classification[] = ['label' => __('assestme.reports.document.priority_rationale'), 'value' => $finding->priorityRationale];
    }
@endphp

<article class="finding-detail {{ $report->setting('new_page_per_finding') === true ? 'finding-detail--new-page' : '' }}">
    <header class="finding-heading">
        <div class="finding-heading__number">{{ str_pad((string) $finding->number, 2, '0', STR_PAD_LEFT) }}</div>
        <h1 class="finding-title {{ $titleClass }}">{{ $finding->title }}</h1>
        <div class="finding-indicators">
            <span class="finding-indicator finding-indicator--priority" style="border-left-color: {{ $finding->priorityColor }}">{{ $finding->priorityLabel }}</span>
            <span class="finding-indicator">{{ $finding->statusLabel }}</span>
            @if (filled($finding->category))
                <span class="finding-indicator">{{ $finding->category }}</span>
            @endif
        </div>
    </header>

    @if (filled($finding->entrepreneurNotes))
        <section class="management-note" style="border-left-color: {{ $finding->priorityColor }}">
            <div class="management-note__label">{{ __('assestme.reports.document.entrepreneur_notes') }}</div>
            <div class="pre-line">{{ $finding->entrepreneurNotes }}</div>
        </section>
    @endif

    <section class="finding-section">
        <div class="finding-section__label">{{ __('assestme.reports.document.problem') }}</div>
        <div class="pre-line">{{ $finding->problem }}</div>
    </section>

    <section class="finding-section recommended-solution">
        <div class="finding-section__label">{{ __('assestme.reports.document.recommended_solution') }}</div>
        <h2 class="solution-title">{{ $recommended->title }}</h2>
        <div class="pre-line">{{ $recommended->description }}</div>

        @if (filled($recommended->comparisonNotes))
            <div class="solution-comparison pre-line">{{ $recommended->comparisonNotes }}</div>
        @endif

        @if ($showEffort || $showEstimate)
            <table class="solution-metrics">
                <tr>
                    @if ($showEffort)
                        <td>
                            <span class="label">{{ __('assestme.reports.document.effort') }}</span>
                            <strong>{{ $recommended->effortLabel }}</strong>
                            @if (filled($recommended->effortNotes))
                                <div class="muted pre-line">{{ $recommended->effortNotes }}</div>
                            @endif
                        </td>
                    @endif
                    @if ($showEstimate)
                        <td>
                            <span class="label">{{ __('assestme.reports.document.indicative_estimate') }}</span>
                            <strong>{{ $recommended->estimateLabel }}</strong>
                            @if (filled($recommended->estimateNotes))
                                <div class="muted pre-line">{{ $recommended->estimateNotes }}</div>
                            @endif
                        </td>
                    @endif
                </tr>
            </table>
        @endif
    </section>

    @if ($report->setting('alternative_solutions') === true && $finding->alternativeSolutions() !== [])
        <section class="finding-section">
            <div class="finding-section__label">{{ __('assestme.reports.document.alternative_solutions') }}</div>
            @foreach ($finding->alternativeSolutions() as $index => $solution)
                <section class="alternative-solution">
                    <div class="alternative-solution__title">
                        {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }} &mdash; {{ $solution->title }}
                    </div>
                    <div class="pre-line">{{ $solution->description }}</div>

                    @if (filled($solution->comparisonNotes))
                        <div class="solution-comparison pre-line">{{ $solution->comparisonNotes }}</div>
                    @endif

                    @if ($solution->effortLabel !== null || $report->setting('costs') === true)
                        <table class="alternative-solution__meta">
                            <tr>
                                @if ($solution->effortLabel !== null)
                                    <td>
                                        <span class="label">{{ __('assestme.reports.document.effort') }}</span>
                                        {{ $solution->effortLabel }}
                                        @if (filled($solution->effortNotes))
                                            <div class="muted pre-line">{{ $solution->effortNotes }}</div>
                                        @endif
                                    </td>
                                @endif
                                @if ($report->setting('costs') === true)
                                    <td>
                                        <span class="label">{{ __('assestme.reports.document.estimate') }}</span>
                                        {{ $solution->estimateLabel }}
                                        @if (filled($solution->estimateNotes))
                                            <div class="muted pre-line">{{ $solution->estimateNotes }}</div>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        </table>
                    @endif
                </section>
            @endforeach
        </section>
    @endif

    <section class="finding-section">
        <div class="finding-section__label">{{ __('assestme.reports.document.classification') }}</div>
        <table class="classification-table">
            @foreach ($classification as $item)
                <tr>
                    <th>{{ $item['label'] }}</th>
                    <td class="pre-line">{{ $item['value'] }}</td>
                </tr>
            @endforeach
        </table>
    </section>

    @if ($finding->assets !== [])
        <section class="finding-section associated-assets">
            <div class="associated-assets__first">
                <div class="finding-section__label">{{ __('assestme.reports.document.associated_assets') }}</div>
                @include('reports.partials.asset-details', ['asset' => $finding->assets[0]])
            </div>

            @foreach (array_slice($finding->assets, 1) as $asset)
                @include('reports.partials.asset-details', ['asset' => $asset])
            @endforeach
        </section>
    @endif

    @if ($report->setting('technical_notes') === true && filled($finding->technicalNotes))
        <section class="finding-section technical-notes">
            <div class="finding-section__label">{{ __('assestme.reports.document.technical_notes') }}</div>
            <div class="pre-line">{{ $finding->technicalNotes }}</div>
        </section>
    @endif

    @if (filled($finding->resolutionNotes) || $finding->implementedSolution() !== null || filled($finding->resolvedAtLabel))
        <section class="finding-section resolution-details">
            <div class="finding-section__label">{{ __('assestme.reports.document.resolution') }}</div>
            @if ($finding->implementedSolution() !== null)
                <div><span class="label">{{ __('assestme.reports.document.implemented_solution') }}</span>{{ $finding->implementedSolution()?->title }}</div>
            @endif
            @if (filled($finding->resolutionNotes))
                <div class="pre-line">{{ $finding->resolutionNotes }}</div>
            @endif
            @if (filled($finding->resolvedAtLabel))
                <div class="muted">{{ __('assestme.reports.document.resolved_at') }}: {{ $finding->resolvedAtLabel }}</div>
            @endif
        </section>
    @endif
</article>
