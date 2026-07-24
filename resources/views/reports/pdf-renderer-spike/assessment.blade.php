@php
    app()->setLocale($report->locale);
@endphp
<!DOCTYPE html>
<html lang="{{ $report->locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $report->title }}</title>
    @include('reports.pdf-renderer-spike._styles')
</head>
<body>
    <section class="sheet cover" data-proof-section="cover">
        <div>
            <p class="cover__kicker">{{ __('pdf_renderer_spike.cover_kicker') }}</p>
            <h1>{{ $report->title }}</h1>
            <p class="cover__client">{{ $report->clientLegalName }}</p>
        </div>
        <div>
            <p>{{ __('pdf_renderer_spike.cover_subject') }}</p>
            <div class="cover__meta">
                <div>
                    <span>Assessment</span>
                    {{ $report->assessmentTitle }}
                </div>
                <div>
                    <span>Data</span>
                    {{ $report->assessmentDateLabel }}
                </div>
            </div>
        </div>
    </section>

    <section class="sheet report-sheet overview" data-proof-section="overview">
        <div class="eyebrow">D-059 · Chrome-first</div>
        <h1 class="page-title">{{ __('pdf_renderer_spike.overview') }}</h1>
        <p class="lead">{{ $report->executiveSummary }}</p>

        <div class="overview-grid">
            <article class="panel">
                <h2>{{ $report->clientLegalName }}</h2>
                <p>{{ $report->clientAddress }}</p>
                <p>{{ $report->assessmentScope }}</p>
            </article>
            <article class="panel">
                <h2>Perimetro del proof</h2>
                <p>{{ $report->introduction }}</p>
            </article>
        </div>

        <div class="legend">
            @foreach ($report->priorityLegend as $priority)
                <div class="legend__item">
                    <span class="legend__dot" style="background: {{ $priority->color }}"></span>
                    <div><strong>{{ $priority->label }}</strong><br>{{ $priority->description }}</div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="sheet summary" data-proof-section="summary">
        <div class="eyebrow">A4 landscape</div>
        <h1 class="page-title">{{ __('pdf_renderer_spike.summary') }}</h1>
        <table class="summary-table">
            <thead>
                <tr>
                    <th>{{ __('pdf_renderer_spike.summary_number') }}</th>
                    <th>{{ __('pdf_renderer_spike.summary_title') }}</th>
                    <th>{{ __('pdf_renderer_spike.summary_priority') }}</th>
                    <th>{{ __('pdf_renderer_spike.summary_solutions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report->findings as $finding)
                    <tr>
                        <td>{{ str_pad((string) $finding->number, 2, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $finding->title }}</td>
                        <td><span class="priority-pill" style="background: {{ $finding->priorityColor }}">{{ $finding->priorityLabel }}</span></td>
                        <td>{{ count($finding->solutions) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    @foreach ($report->findings as $finding)
        @if ($finding->number === 2)
            <section class="sheet report-sheet" data-proof-section="finding-2-page-1">
                @include('reports.pdf-renderer-spike._finding_header', ['finding' => $finding, 'continuation' => false])

                <div class="section">
                    <h2>{{ __('pdf_renderer_spike.problem') }}</h2>
                    <p>{{ $finding->problem }}</p>
                </div>

                <div class="section">
                    <h2>{{ __('pdf_renderer_spike.solutions') }}</h2>
                    @include('reports.pdf-renderer-spike._solution', ['solution' => $finding->solutions[0]])
                </div>
            </section>

            <section class="sheet report-sheet" data-proof-section="finding-2-page-2">
                @include('reports.pdf-renderer-spike._finding_header', ['finding' => $finding, 'continuation' => true])

                <div class="section">
                    <h2>{{ __('pdf_renderer_spike.solutions') }} — {{ __('pdf_renderer_spike.continuation') }}</h2>
                    @include('reports.pdf-renderer-spike._solution', ['solution' => $finding->solutions[1]])
                    @include('reports.pdf-renderer-spike._solution', ['solution' => $finding->solutions[2]])
                </div>
            </section>
        @else
            <section class="sheet report-sheet" data-proof-section="finding-{{ $finding->number }}">
                @include('reports.pdf-renderer-spike._finding_header', ['finding' => $finding, 'continuation' => false])

                <div class="finding-layout">
                    <div>
                        <div class="section">
                            <h2>{{ __('pdf_renderer_spike.problem') }}</h2>
                            <p>{{ $finding->problem }}</p>
                        </div>

                        <div class="section">
                            <h2>{{ __('pdf_renderer_spike.solutions') }}</h2>
                            @foreach ($finding->solutions as $solution)
                                @include('reports.pdf-renderer-spike._solution', ['solution' => $solution])
                            @endforeach
                        </div>
                    </div>

                    <aside>
                        <div class="section">
                            <h2>{{ __('pdf_renderer_spike.risk_matrix') }}</h2>
                            <div class="risk-matrix">
                                <div class="risk-matrix__cell" style="background: {{ $finding->consequenceColor }}">
                                    <span>{{ __('pdf_renderer_spike.consequence') }}</span>
                                    {{ $finding->consequenceLabel }}
                                </div>
                                <div class="risk-matrix__cell" style="background: {{ $finding->likelihoodColor }}">
                                    <span>{{ __('pdf_renderer_spike.likelihood') }}</span>
                                    {{ $finding->likelihoodLabel }}
                                </div>
                                <div class="risk-matrix__cell" style="background: {{ $finding->priorityColor }}">
                                    <span>{{ __('pdf_renderer_spike.priority') }}</span>
                                    {{ $finding->priorityLabel }}
                                </div>
                            </div>
                        </div>

                        @foreach ($finding->assets as $asset)
                            <div class="section">
                                <h2>{{ __('pdf_renderer_spike.assets') }}</h2>
                                <div class="asset-card">
                                    <dl>
                                        <dt>{{ __('pdf_renderer_spike.asset_name') }}</dt>
                                        <dd>{{ $asset->name }}</dd>
                                        <dt>{{ __('pdf_renderer_spike.asset_site') }}</dt>
                                        <dd>{{ $asset->site }}</dd>
                                        <dt>{{ __('pdf_renderer_spike.asset_manufacturer') }}</dt>
                                        <dd>{{ $asset->manufacturer }} {{ $asset->model }}</dd>
                                        <dt>{{ __('pdf_renderer_spike.asset_ip') }}</dt>
                                        <dd>{{ $asset->ipAddress }}</dd>
                                    </dl>
                                </div>
                            </div>
                        @endforeach

                        @foreach ($finding->includedImageEvidence() as $evidence)
                            <div class="section">
                                <h2>{{ __('pdf_renderer_spike.evidence') }}</h2>
                                <figure class="evidence">
                                    <img src="{{ $evidence->imageDataUri }}" alt="{{ $evidence->title }}">
                                    <figcaption>{{ __('pdf_renderer_spike.evidence_caption') }} — {{ $evidence->caption }}</figcaption>
                                </figure>
                            </div>
                        @endforeach
                    </aside>
                </div>

                @if ($loop->last)
                    <p class="fixed-note">{{ __('pdf_renderer_spike.fixed_note') }}</p>
                @endif
            </section>
        @endif
    @endforeach
</body>
</html>
