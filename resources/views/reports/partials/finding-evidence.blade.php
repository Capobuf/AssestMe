<?php declare(strict_types=1); ?>

<section class="finding-evidence">
    <div class="finding-evidence__eyebrow">03 / {{ $finding->title }}</div>
    <h2 class="finding-evidence__title">{{ __('assestme.reports.document.evidence') }}</h2>

    @foreach ($finding->includedEvidence() as $evidence)
        @php
            $evidenceNumber = str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT);
            $evidenceTitle = trim((string) $evidence->title);
            $originalFilename = trim((string) $evidence->originalFilename);
            $showEvidenceTitle = $evidenceTitle !== ''
                && ($originalFilename === '' || mb_strtolower($evidenceTitle) !== mb_strtolower($originalFilename));
        @endphp

        @if ($evidence->isImage())
            <section class="evidence-block" data-evidence-type="image">
                <div class="evidence-block__label">{{ __('assestme.reports.document.image_number', ['number' => $evidenceNumber]) }}</div>
                @if ($showEvidenceTitle)
                    <div class="evidence-block__title">{{ $evidenceTitle }}</div>
                @endif
                <img class="evidence-image" src="{{ $evidence->imageDataUri }}" alt="">
                @if ($report->setting('evidence_captions') === true && filled($evidence->caption))
                    <div class="evidence-caption pre-line">{{ $evidence->caption }}</div>
                @endif
            </section>
        @elseif ($evidence->type === 'url')
            <section class="evidence-block evidence-reference" data-evidence-type="url">
                <div class="evidence-block__label">{{ __('assestme.reports.document.link_number', ['number' => $evidenceNumber]) }}</div>
                @if ($showEvidenceTitle)
                    <div class="evidence-block__title">{{ $evidenceTitle }}</div>
                @endif
                <div><a href="{{ $evidence->url }}">{{ $evidence->url }}</a></div>
            </section>
        @else
            <section class="evidence-block evidence-reference" data-evidence-type="file">
                <div class="evidence-block__label">{{ __('assestme.reports.document.file_number', ['number' => $evidenceNumber]) }}</div>
                @if ($showEvidenceTitle)
                    <div class="evidence-block__title">{{ $evidenceTitle }}</div>
                @endif
                @if (filled($evidence->originalFilename))
                    <div>{{ $evidence->originalFilename }}</div>
                @endif
                @if (filled($evidence->mimeType))
                    <div class="evidence-reference__meta">{{ __('assestme.reports.document.file_type') }}: {{ $evidence->mimeType }}</div>
                @endif
            </section>
        @endif
    @endforeach
</section>
