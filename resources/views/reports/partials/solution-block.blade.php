<?php declare(strict_types=1); ?>

@php
    $sameRole = $solution->recommended && $solution->implemented;
    $role = match (true) {
        $sameRole => __('assestme.reports.document.recommended_and_implemented'),
        $solution->implemented => __('assestme.reports.document.implemented_solution'),
        $solution->recommended => __('assestme.reports.document.recommended_solution'),
        default => __('assestme.reports.document.alternative_solution'),
    };
@endphp

<section class="solution-block {{ $primary ? 'solution-block--primary' : '' }}" data-solution-id="{{ $solution->id }}">
    <h2 class="finding-section__label">{{ $role }}</h2>
    <h3>{{ $solution->title }}</h3>
    <div class="pre-line">{{ $solution->description }}</div>
    @if (filled($solution->comparisonNotes))
        <div class="solution-comparison pre-line">{{ $solution->comparisonNotes }}</div>
    @endif
    <table
        class="solution-metrics {{ $report->setting('costs') === true ? '' : 'solution-metrics--single' }}"
        role="presentation"
    >
        <tr>
            <td class="solution-metrics__effort">
                <span class="label">
                    {{ __('assestme.reports.document.effort') }}
                </span>

                <strong>
                    {{ $solution->effortLabel ?? __('assestme.reports.document.not_available') }}
                </strong>

                @if (filled($solution->effortNotes))
                    <span class="muted pre-line">{{ $solution->effortNotes }}</span>
                @endif
            </td>

            @if ($report->setting('costs') === true)
                <td class="solution-metrics__estimate">
                    <span class="label">
                        {{ __('assestme.reports.document.estimate') }}
                    </span>

                    <strong>{{ $solution->estimateLabel }}</strong>

                    @if (filled($solution->estimateNotes))
                        <span class="muted pre-line">{{ $solution->estimateNotes }}</span>
                    @endif
                </td>
            @endif
        </tr>
    </table>
</section>
