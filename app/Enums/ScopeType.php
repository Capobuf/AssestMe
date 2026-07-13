<?php

declare(strict_types=1);

namespace App\Enums;

enum ScopeType: string
{
    case Organization = 'organization';
    case SelectedSites = 'selected_sites';
    case Network = 'network';
    case SelectedAssets = 'selected_assets';
    case Custom = 'custom';
}
