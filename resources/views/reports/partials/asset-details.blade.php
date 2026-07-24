<?php declare(strict_types=1); ?>

@php
    $metadata = array_values(array_filter([
        $asset->site,
        trim(($asset->manufacturer ?? '').' '.($asset->model ?? '')),
        $asset->hostname,
        $asset->ipAddress,
    ], static fn (?string $value): bool => filled($value)));
    $primaryName = $asset->name ?: $asset->type;
    $showType = filled($asset->type)
        && filled($asset->name)
        && strcasecmp(trim((string) $asset->name), trim((string) $asset->type)) !== 0;
@endphp

<section class="asset-details">
    <div class="asset-details__name">{{ $primaryName }}</div>

    @if ($showType)
        <div class="asset-details__type">{{ $asset->type }}</div>
    @endif

    @if ($metadata !== [])
        <div class="asset-details__metadata">
            @foreach ($metadata as $item)
                <div class="asset-details__metadata-item">{{ $item }}</div>
            @endforeach
        </div>
    @endif
</section>
