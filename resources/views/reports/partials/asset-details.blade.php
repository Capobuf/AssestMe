<?php declare(strict_types=1); ?>

@php
    $metadata = array_values(array_filter([
        $asset->site,
        $asset->manufacturer,
        $asset->ipAddress,
    ], static fn (?string $value): bool => filled($value)));
    $primaryName = $asset->name ?: $asset->type;
@endphp

<section class="asset-details">
    <div class="asset-details__name">{{ $primaryName }}</div>

    @if ($metadata !== [])
        <div class="asset-details__metadata">
            @foreach ($metadata as $item)
                <div class="asset-details__metadata-item">{{ $item }}</div>
            @endforeach
        </div>
    @endif
</section>
