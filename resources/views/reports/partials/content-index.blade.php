<?php declare(strict_types=1); ?>

<section>
    <h2 class="editorial-heading">{{ __('assestme.reports.document.content_index') }}</h2>
    <ol class="content-index">
        @foreach ($report->findings as $finding)
            <li>{{ str_pad((string) $finding->number, 2, '0', STR_PAD_LEFT) }} &mdash; {{ $finding->title }}</li>
        @endforeach
    </ol>
</section>
