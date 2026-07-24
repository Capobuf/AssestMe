@php
    /** @var \App\Data\Reports\ReportFindingData $finding */
    /** @var bool $continuation */
@endphp

<header class="finding-header">
    <div class="finding-number">{{ str_pad((string) $finding->number, 2, '0', STR_PAD_LEFT) }}</div>
    <div>
        @if ($continuation)
            <div class="continuation-label">
                {{ __('pdf_renderer_spike.finding') }} {{ $finding->number }} — {{ __('pdf_renderer_spike.continuation') }}
            </div>
        @else
            <div class="eyebrow">{{ __('pdf_renderer_spike.finding') }} {{ $finding->number }}</div>
        @endif
        <h1>{{ $finding->title }}</h1>
    </div>
</header>
