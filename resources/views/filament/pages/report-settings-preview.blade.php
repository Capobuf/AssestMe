<?php declare(strict_types=1); ?>

<section class="assestme-report-preview" data-dusk="report-style-preview">
    <h2 class="text-base font-semibold">{{ __('assestme.settings.preview.heading') }}</h2>
    <p class="text-sm text-gray-500">{{ __('assestme.settings.preview.description') }}</p>
    <iframe
        src="{{ $this->reportPreviewUrl() }}"
        title="{{ __('assestme.settings.preview.heading') }}"
        data-dusk="report-preview-iframe"
        style="background:#e7eaef;border:1px solid #d4d4d8;border-radius:.5rem;height:72rem;margin-top:.75rem;width:100%"
    ></iframe>
</section>
