<?php declare(strict_types=1); ?>

@php
    $metadata = array_values(array_filter([
        $asset->site,
        trim(($asset->manufacturer ?? '').' '.($asset->model ?? '')),
        $asset->hostname,
        $asset->ipAddress,
    ], static fn (?string $value): bool => filled($value)));
@endphp

<section class="asset-details">
    <div class="asset-details__name">{{ $asset->name ?: $asset->type }}</div>

    @if (filled($asset->type))
        <div class="asset-details__type">{{ $asset->type }}</div>
    @endif

    @if ($metadata !== [])
        <div class="asset-details__metadata">{{ implode(' · ', $metadata) }}</div>
    @endif
</section>
