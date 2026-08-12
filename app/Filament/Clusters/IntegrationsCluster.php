<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use App\Filament\Pages\FattureInCloudSettingsPage;
use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Pages\PageConfiguration;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

final class IntegrationsCluster extends Cluster
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('assestme.clusters.integrations');
    }

    public static function getClusterBreadcrumb(): string
    {
        return __('assestme.clusters.integrations');
    }

    public static function getSubNavigationPosition(): SubNavigationPosition
    {
        return SubNavigationPosition::Start;
    }

    public static function registerRoutes(Panel $panel, ?PageConfiguration $configuration = null): void
    {
        // This navigation-only cluster points directly to its first real page, so it
        // must not expose an intermediate redirect route to authorization sweeps.
    }

    /** @param array<string, mixed> $parameters */
    public static function getUrl(
        array $parameters = [],
        bool $isAbsolute = true,
        ?string $panel = null,
        ?Model $tenant = null,
        bool $shouldGuessMissingParameters = false,
        ?string $configuration = null,
    ): string {
        return FattureInCloudSettingsPage::getUrl(
            $parameters,
            $isAbsolute,
            $panel,
            $tenant,
            $shouldGuessMissingParameters,
            $configuration,
        );
    }

    public static function prependClusterSlug(Panel $panel, string $slug): string
    {
        return SettingsCluster::prependClusterSlug($panel, parent::prependClusterSlug($panel, $slug));
    }
}
