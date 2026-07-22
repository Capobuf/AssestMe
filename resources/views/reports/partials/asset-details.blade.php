<?php declare(strict_types=1); ?>

<table class="asset-details">
    <tr>
        <td class="asset-details__name" colspan="2"><strong>{{ $asset->name ?: $asset->type }}</strong></td>
    </tr>
    @if (filled($asset->type))
        <tr><th>{{ __('assestme.reports.document.asset_type') }}</th><td>{{ $asset->type }}</td></tr>
    @endif
    @if (filled($asset->site))
        <tr><th>{{ __('assestme.reports.document.site') }}</th><td>{{ $asset->site }}</td></tr>
    @endif
    @if (filled($asset->manufacturer) || filled($asset->model))
        <tr>
            <th>{{ __('assestme.reports.document.manufacturer_model') }}</th>
            <td>{{ trim(($asset->manufacturer ?? '').' '.($asset->model ?? '')) }}</td>
        </tr>
    @endif
    @if (filled($asset->hostname))
        <tr><th>{{ __('assestme.reports.document.hostname') }}</th><td>{{ $asset->hostname }}</td></tr>
    @endif
    @if (filled($asset->ipAddress))
        <tr><th>{{ __('assestme.reports.document.ip_address') }}</th><td>{{ $asset->ipAddress }}</td></tr>
    @endif
</table>
