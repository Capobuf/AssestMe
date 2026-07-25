<?php declare(strict_types=1); ?>

<section
    class="assestme-report-preview"
    data-dusk="report-style-preview"
    x-data="{ expanded: false }"
    :class="{ 'is-expanded': expanded }"
    @keydown.escape.window="expanded = false"
>
    <div class="assestme-report-preview__toolbar">
        <h2 class="assestme-report-preview__heading">{{ __('assestme.settings.preview.heading') }}</h2>
        <button
            type="button"
            class="assestme-report-preview__expand"
            data-dusk="report-preview-expand"
            :aria-expanded="expanded.toString()"
            @click="expanded = ! expanded"
        >
            <span x-show="! expanded">{{ __('assestme.settings.preview.expand') }}</span>
            <span x-show="expanded" x-cloak>{{ __('assestme.settings.preview.collapse') }}</span>
        </button>
    </div>
    <iframe
        class="assestme-report-preview__frame"
        src="{{ $this->reportPreviewUrl() }}"
        title="{{ __('assestme.settings.preview.heading') }}"
        data-dusk="report-preview-iframe"
    ></iframe>
</section>
