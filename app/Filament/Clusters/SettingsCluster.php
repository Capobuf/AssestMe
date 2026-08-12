<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Support\Icons\Heroicon;

final class SettingsCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('assestme.clusters.settings');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('assestme.clusters.settings');
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Top;
    }

    /** @return array<class-string> */
    public static function getClusteredComponents(): array
    {
        return array_values(array_unique(parent::getClusteredComponents()));
    }
}
