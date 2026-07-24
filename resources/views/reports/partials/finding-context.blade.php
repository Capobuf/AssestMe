<?php declare(strict_types=1); ?>

@if ($finding->riskMatrix !== null)
    <section class="risk-evaluation">
        <div class="finding-section__label">{{ __('assestme.reports.document.risk_evaluation') }}</div>
        <div class="risk-layout">
            <table class="risk-matrix">
                <thead><tr><th>{{ __('assestme.reports.document.consequence') }} ↓ / {{ __('assestme.reports.document.likelihood') }} →</th>
                    @foreach ($finding->riskMatrix->likelihoods as $level)<th>{{ $level['label'] }}</th>@endforeach
                </tr></thead>
                <tbody>
                @foreach ($finding->riskMatrix->consequences as $consequence)
                    <tr><th>{{ $consequence['label'] }}</th>
                        @foreach ($finding->riskMatrix->likelihoods as $likelihood)
                            @php($cell = $finding->riskMatrix->cell($consequence['id'], $likelihood['id']))
                            <td class="{{ ($cell['current'] ?? false) ? 'risk-matrix__current' : '' }}" style="border-color: {{ $cell['priority_color'] ?? '#D7D7D2' }}">
                                {{ $cell['priority_label'] ?? '—' }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="risk-result">
                <span class="label">{{ __('assestme.reports.document.resulting_priority') }}</span>
                <strong><i class="priority-marker" style="border-color: {{ $finding->riskMatrix->resultingPriorityColor }}"></i>{{ $finding->riskMatrix->resultingPriorityLabel }}</strong>
                <span>{{ __('assestme.reports.document.consequence') }}: {{ $finding->consequenceLabel }}</span>
                <span>{{ __('assestme.reports.document.likelihood') }}: {{ $finding->likelihoodLabel }}</span>
            </div>
        </div>
        @if ($finding->priorityOverridden && filled($finding->priorityRationale))
            <div class="risk-evaluation__rationale pre-line">{{ $finding->priorityRationale }}</div>
        @endif
    </section>
@endif

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
