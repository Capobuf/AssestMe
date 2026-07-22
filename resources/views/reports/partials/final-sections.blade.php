<?php declare(strict_types=1); ?>

@if ($report->setting('methodology') === true && (filled($report->methodologyNotes) || filled($report->setting('methodology_text'))))
    <section class="final-section final-section--new-page">
        <h2 class="editorial-heading">{{ __('assestme.reports.document.methodology') }}</h2>
        @if (filled($report->methodologyNotes))
            <div class="pre-line">{{ $report->methodologyNotes }}</div>
        @endif
        @if (filled($report->setting('methodology_text')))
            <div class="pre-line">{{ $report->setting('methodology_text') }}</div>
        @endif
    </section>
@endif

@if ($report->setting('disclaimer') === true && filled($report->setting('disclaimer_text')))
    <section class="final-section avoid-break">
        <h2 class="editorial-heading">{{ __('assestme.reports.document.disclaimer') }}</h2>
        <div class="pre-line">{{ $report->setting('disclaimer_text') }}</div>
    </section>
@endif

@if ($report->setting('signature_block') === true)
    <section class="final-section avoid-break">
        <h2 class="editorial-heading">{{ __('assestme.reports.document.signature') }}</h2>
        @if (filled($report->setting('signature_text')))
            <div class="pre-line">{{ $report->setting('signature_text') }}</div>
        @endif
        @if (filled($report->setting('signature_name')) || filled($report->setting('signature_role')))
            <p>
                {{ $report->setting('signature_name') }}
                @if (filled($report->setting('signature_role')))
                    &mdash; {{ $report->setting('signature_role') }}
                @endif
            </p>
        @endif
        <span class="label">{{ __('assestme.reports.document.date') }}</span>
        <div class="signature-line"></div>
        <span class="label" style="margin-top: 5mm">{{ __('assestme.reports.document.signature') }}</span>
        <div class="signature-line"></div>
    </section>
@endif

@if ($report->setting('evidence') === true && $report->attachments() !== [])
    <section class="final-section avoid-break">
        <h2 class="editorial-heading">{{ __('assestme.reports.document.attachment_list') }}</h2>
        <ul class="attachment-list">
            @foreach ($report->attachments() as $evidence)
                <li>
                    {{ $evidence->title }}
                    @if (filled($evidence->originalFilename))
                        &mdash; {{ $evidence->originalFilename }}
                    @endif
                    @if (filled($evidence->mimeType))
                        ({{ $evidence->mimeType }})
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif
