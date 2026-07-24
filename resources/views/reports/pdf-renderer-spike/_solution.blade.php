@php
    /** @var \App\Data\Reports\ReportSolutionData $solution */
@endphp

<article class="solution" data-solution-id="{{ $solution->id }}">
    <h3>
        <span class="solution__token">SOL-{{ $solution->id }}</span>
        {{ __('pdf_renderer_spike.solution') }} {{ $solution->sortOrder }} — {{ $solution->title }}
        @if ($solution->recommended)
            <span class="tag">{{ __('pdf_renderer_spike.recommended') }}</span>
        @endif
        @if ($solution->implemented)
            <span class="tag">{{ __('pdf_renderer_spike.implemented') }}</span>
        @endif
    </h3>
    <p>{{ $solution->description }}</p>
    <div class="solution__meta">
        <span>{{ __('pdf_renderer_spike.effort') }}: {{ $solution->effortLabel }}</span>
        <span>{{ __('pdf_renderer_spike.estimate') }}: {{ $solution->estimateLabel }}</span>
    </div>
</article>
