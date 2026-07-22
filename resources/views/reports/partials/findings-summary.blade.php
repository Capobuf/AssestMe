<?php declare(strict_types=1); ?>

<section class="findings-summary-section">
    <table class="section-heading">
        <tr>
            <td class="section-heading__number">02</td>
            <td class="section-heading__title">{{ __('assestme.reports.document.findings_summary') }}</td>
        </tr>
    </table>
    <p class="findings-summary__description">{{ __('assestme.reports.document.findings_summary_description') }}</p>

    @foreach ($report->findings as $finding)
        @php($recommended = $finding->recommendedSolution())

        <table class="finding-summary">
            <tr class="finding-summary__main">
                <td class="finding-summary__number">{{ str_pad((string) $finding->number, 2, '0', STR_PAD_LEFT) }}</td>
                <td class="finding-summary__title">{{ $finding->title }}</td>
                <td class="finding-summary__category">
                    <span class="label">{{ __('assestme.reports.document.category') }}</span>
                    {{ $finding->category }}
                </td>
                <td class="finding-summary__priority">
                    <span class="label">{{ __('assestme.reports.document.priority') }}</span>
                    <span class="priority-marker" style="background-color: {{ $finding->priorityColor }}"></span>
                    {{ $finding->priorityLabel }}
                </td>
                <td class="finding-summary__status">
                    <span class="label">{{ __('assestme.reports.document.status') }}</span>
                    {{ $finding->statusLabel }}
                </td>
            </tr>
            <tr class="finding-summary__details">
                <td></td>
                <td>
                    <span class="label">{{ __('assestme.reports.document.scope') }}</span>
                    {{ $finding->scopeLabel }}
                </td>
                <td colspan="2">
                    <span class="label">{{ __('assestme.reports.document.recommended_solution') }}</span>
                    <strong>{{ $recommended->title }}</strong>
                </td>
                <td>
                    <span class="label">{{ __('assestme.reports.document.effort') }}</span>
                    {{ $recommended->effortLabel ?? __('assestme.reports.document.not_available') }}
                    @if ($report->setting('costs') === true)
                        <span class="label" style="margin-top: 2mm">{{ __('assestme.reports.document.estimate') }}</span>
                        {{ $recommended->estimateLabel }}
                    @endif
                </td>
            </tr>
        </table>
    @endforeach
</section>
