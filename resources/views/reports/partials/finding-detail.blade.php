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
        <section class="finding-section problem-section">
            <h2 class="finding-section__label finding-section__label--prominent">
                {{ __('assestme.reports.document.problem') }}
            </h2>
            <div class="pre-line">{{ $finding->problem }}</div>
        </section>

        @if (filled($finding->entrepreneurNotes))
            <section class="problem-explanation">
                <h2 class="finding-section__label finding-section__label--prominent">
                    {{ __('assestme.reports.document.entrepreneur_notes') }}
                </h2>
                <div class="pre-line">{{ $finding->entrepreneurNotes }}</div>
            </section>
        @endif

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
