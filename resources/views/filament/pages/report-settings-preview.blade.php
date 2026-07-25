<?php declare(strict_types=1); ?>

<section class="assestme-report-preview" data-dusk="report-style-preview">
    <h2 class="assestme-report-preview__heading">{{ __('assestme.settings.preview.heading') }}</h2>
    <iframe
        class="assestme-report-preview__frame"
        src="{{ $this->reportPreviewUrl() }}"
        title="{{ __('assestme.settings.preview.heading') }}"
        data-dusk="report-preview-iframe"
    ></iframe>
</section>
