<?php declare(strict_types=1); ?>

<table class="finding-context-layout" role="presentation">
    <tr>
        @if ($finding->riskMatrix !== null)
            <td class="finding-context-layout__risk">
                <section class="risk-evaluation">
                    <div class="finding-section__label">
                        {{ __('assestme.reports.document.risk_evaluation') }}
                    </div>

                    <table class="risk-compact-layout" role="presentation">
                        <tr>
                            <td class="risk-compact-layout__matrix">
                                <table class="risk-mini-layout" role="presentation">
                                    <tr>
                                        <td class="risk-axis risk-axis--vertical">↓</td>

                                        <td>
                                            <table
                                                class="risk-mini-matrix"
                                                aria-label="{{ __('assestme.reports.document.risk_evaluation') }}"
                                            >
                                                <tbody>
                                                    @foreach ($finding->riskMatrix->consequences as $consequence)
                                                        <tr>
                                                            @foreach ($finding->riskMatrix->likelihoods as $likelihood)
                                                                @php
                                                                    $cell = $finding->riskMatrix->cell(
                                                                        $consequence['id'],
                                                                        $likelihood['id'],
                                                                    );

                                                                    $current = $cell['current'] ?? false;
                                                                @endphp

                                                                <td>
                                                                    <span
                                                                        class="risk-dot {{ $current ? 'risk-dot--current' : '' }}"
                                                                        style="background-color: {{ $cell['priority_color'] ?? '#D7D7D2' }}"
                                                                        title="{{ $cell['priority_label'] ?? '' }}"
                                                                    >
                                                                        @if ($current)
                                                                            <span class="risk-dot__current-mark"></span>
                                                                        @endif
                                                                    </span>
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>

                                            <div class="risk-axis risk-axis--horizontal">→</div>
                                        </td>
                                    </tr>
                                </table>
                            </td>

                            <td class="risk-compact-layout__result">
                                <div class="risk-result-compact">
                                    <span
                                        class="priority-marker"
                                        style="border-color: {{ $finding->riskMatrix->resultingPriorityColor }}"
                                    ></span>

                                    <strong>
                                        {{ $finding->riskMatrix->resultingPriorityLabel }}
                                    </strong>

                                    <span class="risk-result-compact__factors">
                                        {{ $finding->consequenceLabel }}
                                        ×
                                        {{ $finding->likelihoodLabel }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </table>

                    @if ($finding->priorityOverridden && filled($finding->priorityRationale))
                        <div class="risk-evaluation__rationale pre-line">
                            {{ $finding->priorityRationale }}
                        </div>
                    @endif
                </section>
            </td>
        @endif

        <td class="finding-context-layout__systems {{ $finding->riskMatrix === null ? 'finding-context-layout__systems--full' : '' }}">
            <section class="affected-systems">
                <div class="finding-section__label">{{ __('assestme.reports.document.affected_systems') }}</div>
                @if ($finding->assets !== [])
                    @foreach ($finding->assets as $asset)
                        @include('reports.partials.asset-details', ['asset' => $asset])
                    @endforeach
                @else
                    <div>{{ $finding->scopeType === 'selected_sites' && $finding->sites !== [] ? implode(', ', $finding->sites) : explode(' — ', $finding->scopeLabel)[0] }}</div>
                @endif
            </section>
        </td>
    </tr>
</table>

@if ($report->setting('technical_notes') === true && filled($finding->technicalNotes))
    <section class="finding-section technical-notes"><div class="finding-section__label">{{ __('assestme.reports.document.technical_notes') }}</div><div class="pre-line">{{ $finding->technicalNotes }}</div></section>
@endif

@if ($report->setting('show_resolution') === true && (filled($finding->resolutionNotes) || $finding->implementedSolution() !== null || filled($finding->resolvedAtLabel)))
    <section class="finding-section resolution-details">
        <div class="finding-section__label">{{ __('assestme.reports.document.resolution') }}</div>
        @if (filled($finding->resolutionNotes))<div class="pre-line">{{ $finding->resolutionNotes }}</div>@endif
        @if (filled($finding->resolvedAtLabel))<div class="muted">{{ __('assestme.reports.document.resolved_at') }}: {{ $finding->resolvedAtLabel }}</div>@endif
    </section>
@endif
