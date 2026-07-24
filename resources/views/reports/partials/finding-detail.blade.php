<?php declare(strict_types=1); ?>

@php
    $titleLength = mb_strlen($finding->title);
    $titleClass = match (true) {
        $titleLength > 110 => 'finding-title--compact',
        $titleLength > 70 => 'finding-title--medium',
        default => 'finding-title--default',
    };
    $firstPageSolutions = $finding->solutionsByIds($finding->pagePlan->firstPageSolutionIds);
    $secondPageSolutions = $finding->solutionsByIds($finding->pagePlan->secondPageSolutionIds);
@endphp

<article class="sheet report-sheet finding-detail" data-finding="{{ $finding->number }}" data-finding-page="1">
    @include('reports.partials.finding-header', ['continuation' => false])

    <div class="finding-body">
        @if (filled($finding->entrepreneurNotes))
            <section class="problem-explanation">
                <div class="finding-section__label">{{ __('assestme.reports.document.entrepreneur_notes') }}</div>
                <div class="pre-line">{{ $finding->entrepreneurNotes }}</div>
            </section>
        @endif

        <section class="finding-section">
            <div class="finding-section__label">{{ __('assestme.reports.document.problem') }}</div>
            <div class="pre-line">{{ $finding->problem }}</div>
        </section>

        @foreach ($firstPageSolutions as $solution)
            @include('reports.partials.solution-block', ['solution' => $solution, 'primary' => $loop->first])
        @endforeach

        @if (! $finding->pagePlan->hasSecondPage)
            @include('reports.partials.finding-context')
        @endif
    </div>
</article>

@if ($finding->pagePlan->hasSecondPage)
    <article class="sheet report-sheet finding-detail finding-detail--continuation" data-finding="{{ $finding->number }}" data-finding-page="2">
        @include('reports.partials.finding-header', ['continuation' => true])

        <div class="finding-body">
            @foreach ($secondPageSolutions as $solution)
                @include('reports.partials.solution-block', ['solution' => $solution, 'primary' => false])
            @endforeach

            @include('reports.partials.finding-context')
        </div>
    </article>
@endif
