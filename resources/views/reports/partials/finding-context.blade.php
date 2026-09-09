<?php declare(strict_types=1); ?>

<table class="finding-context-layout" role="presentation">
    <tr>
        @if ($finding->riskMatrix !== null)
            <td class="finding-context-layout__risk">
                <section class="risk-evaluation">
                    <h2 class="finding-section__label">
                        {{ __('assestme.reports.document.risk_evaluation') }}
                    </h2>

                    <table class="risk-compact-layout" role="presentation">
                        <tr>
                            <td class="risk-compact-layout__matrix">
                                <table class="risk-mini-layout" role="presentation">
                                    <tr>
                                        <td class="risk-axis-cell">
                                            <span class="risk-axis risk-axis--vertical">
                                                {{ __('assestme.reports.document.consequence') }}
                                            </span>
                                        </td>

                                        <td>
                                            <table
                                                class="risk-mini-matrix"
                                                aria-label="{{ __('assestme.reports.document.risk_evaluation') }}"
                                            >
                                                <tbody>
                                                    @foreach (array_reverse($finding->riskMatrix->consequences) as $consequence)
                                                        <tr data-consequence-id="{{ $consequence['id'] }}">
                                                            @foreach ($finding->riskMatrix->likelihoods as $likelihood)
                                                                @php
                                                                    $cell = $finding->riskMatrix->cell(
                                                                        $consequence['id'],
                                                                        $likelihood['id'],
                                                                    );

                                                                    $current = $cell['current'] ?? false;
                                                                @endphp

                                                                <td data-likelihood-id="{{ $likelihood['id'] }}">
                                                                    <span
                                                                        class="risk-dot {{ $current ? 'risk-dot--current' : '' }}"
                                                                        style="background-color: {{ $cell['priority_color'] ?? '#D7D7D2' }}"
                                                                        title="{{ $cell['priority_label'] ?? '' }}"
                                                                    ></span>
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>

                                            <div class="risk-axis risk-axis--horizontal">
                                                {{ __('assestme.reports.document.likelihood') }}
                                            </div>
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
                <h2 class="finding-section__label">{{ __('assestme.reports.document.affected_systems') }}</h2>
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
    <section class="finding-section technical-notes"><h2 class="finding-section__label">{{ __('assestme.reports.document.technical_notes') }}</h2><div class="pre-line">{{ $finding->technicalNotes }}</div></section>
@endif

@if ($report->setting('show_resolution') === true && (filled($finding->resolutionNotes) || $finding->implementedSolution() !== null || filled($finding->resolvedAtLabel)))
    <section class="finding-section resolution-details">
        <h2 class="finding-section__label">{{ __('assestme.reports.document.resolution') }}</h2>
        @if (filled($finding->resolutionNotes))<div class="pre-line">{{ $finding->resolutionNotes }}</div>@endif
        @if (filled($finding->resolvedAtLabel))<div class="muted">{{ __('assestme.reports.document.resolved_at') }}: {{ $finding->resolvedAtLabel }}</div>@endif
    </section>
@endif
