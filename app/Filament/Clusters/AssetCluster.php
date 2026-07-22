<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use App\Filament\Resources\Clients\ClientResource;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

final class AssetCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('assestme.assets.navigation');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('assestme.assets.navigation');
    }

    public static function getNavigationParentItem(): string
    {
        return ClientResource::getNavigationLabel();
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
    }
}
