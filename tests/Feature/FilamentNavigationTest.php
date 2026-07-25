<?php

declare(strict_types=1);

use App\Filament\Clusters\AssetCluster;
use App\Filament\Clusters\SettingsCluster;
use App\Filament\Pages\BackupSettingsPage;
use App\Filament\Pages\GeneralSettingsPage;
use App\Filament\Pages\ReportSettingsPage;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\AssetTypes\AssetTypeResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\EffortLevels\EffortLevelResource;
use App\Filament\Resources\FindingTemplates\FindingTemplateResource;
use App\Filament\Resources\RiskProfiles\RiskProfileResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Enums\SubNavigationPosition;

it('discovers native clusters and assigns each clustered component exactly once', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->getClusters())->toContain(AssetCluster::class, SettingsCluster::class)
        ->and($panel->getClusteredComponents(AssetCluster::class))->toEqualCanonicalizing([
            AssetResource::class,
            AssetTypeResource::class,
        ])
        ->and($panel->getClusteredComponents(SettingsCluster::class))->toEqualCanonicalizing([
            GeneralSettingsPage::class,
            ReportSettingsPage::class,
            BackupSettingsPage::class,
            FindingTemplateResource::class,
            CategoryResource::class,
            RiskProfileResource::class,
            EffortLevelResource::class,
        ])
        ->and(AssetResource::getCluster())->toBe(AssetCluster::class)
        ->and(AssetTypeResource::getCluster())->toBe(AssetCluster::class)
        ->and(GeneralSettingsPage::getCluster())->toBe(SettingsCluster::class)
        ->and(ReportSettingsPage::getCluster())->toBe(SettingsCluster::class)
        ->and(BackupSettingsPage::getCluster())->toBe(SettingsCluster::class);
});

it('builds the approved hierarchy with native Filament navigation items', function (): void {
    $this->actingAs(User::factory()->create());

    $items = collect(Filament::getNavigation())
        ->flatMap(static fn (NavigationGroup $group) => $group->getItems())
        ->values();
    $labels = $items->map(static fn (NavigationItem $item): string => $item->getLabel())->all();
    $companies = $items->first(
        static fn (NavigationItem $item): bool => $item->getLabel() === ClientResource::getNavigationLabel(),
    );
    $settings = $items->first(
        static fn (NavigationItem $item): bool => $item->getLabel() === SettingsCluster::getNavigationLabel(),
    );

    expect($labels)->toContain('Dashboard', 'Assessment', 'Aziende', 'Impostazioni')
        ->and($labels)->not->toContain('Sedi', 'Asset', 'Tipologie asset', 'Generale', 'Report', 'Template', 'Categorie', 'Matrice priorità', 'Livelli di impegno', 'Tag')
        ->and($companies)->toBeInstanceOf(NavigationItem::class)
        ->and($companies->getUrl())->toBe(ClientResource::getUrl('index'))
        ->and(collect($companies->getChildItems())->map(
            static fn (NavigationItem $item): string => $item->getLabel(),
        )->all())->toBe(['Sedi', 'Asset'])
        ->and($settings)->toBeInstanceOf(NavigationItem::class)
        ->and($settings->getUrl())->toBe(SettingsCluster::getUrl())
        ->and(AssetCluster::getNavigationParentItem())->toBe(ClientResource::getNavigationLabel());
});

it('uses clustered URLs route names and ordered native subnavigation', function (): void {
    $this->actingAs(User::factory()->create());

    expect(AssetCluster::getSubNavigationPosition())->toBe(SubNavigationPosition::Top)
        ->and(SettingsCluster::getSubNavigationPosition())->toBe(SubNavigationPosition::Top)
        ->and(AssetCluster::getUrl())->toEndWith('/admin/asset')
        ->and(AssetResource::getUrl('index'))->toEndWith('/admin/asset/assets')
        ->and(AssetTypeResource::getUrl('index'))->toEndWith('/admin/asset/asset-types')
        ->and(SettingsCluster::getUrl())->toEndWith('/admin/settings')
        ->and(GeneralSettingsPage::getUrl())->toEndWith('/admin/settings/general-settings-page')
        ->and(ReportSettingsPage::getUrl())->toEndWith('/admin/settings/report-settings-page')
        ->and(BackupSettingsPage::getUrl())->toEndWith('/admin/settings/backup-settings-page')
        ->and(FindingTemplateResource::getUrl('index'))->toEndWith('/admin/settings/finding-templates')
        ->and(CategoryResource::getUrl('index'))->toEndWith('/admin/settings/categories')
        ->and(RiskProfileResource::getUrl('index'))->toEndWith('/admin/settings/risk-profiles')
        ->and(EffortLevelResource::getUrl('index'))->toEndWith('/admin/settings/effort-levels')
        ->and(AssetResource::getRouteBaseName())->toBe('filament.admin.asset.resources.assets')
        ->and(GeneralSettingsPage::getRouteName())->toBe('filament.admin.settings.pages.general-settings-page');

    $assetSubNavigation = app(AssetCluster::class)->getCachedSubNavigation();
    $settingsSubNavigation = app(SettingsCluster::class)->getCachedSubNavigation();

    expect(navigationLabels($assetSubNavigation))->toBe(['Asset', 'Tipologie asset'])
        ->and(navigationLabels($settingsSubNavigation))->toBe([
            'Generale',
            'Report',
            'Backup',
            'Template',
            'Categorie',
            'Matrice priorità',
            'Livelli di impegno',
        ]);
});

it('redirects each cluster entry to its first real destination', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get(AssetCluster::getUrl())->assertRedirect(AssetResource::getUrl('index'));
    $this->get(SettingsCluster::getUrl())->assertRedirect(GeneralSettingsPage::getUrl());
});

it('requires authentication for every relocated navigation destination', function (string $url): void {
    $this->get($url)->assertRedirect('/admin/login');
})->with([
    'assets' => fn (): string => AssetResource::getUrl('index'),
    'asset types' => fn (): string => AssetTypeResource::getUrl('index'),
    'general settings' => fn (): string => GeneralSettingsPage::getUrl(),
    'report settings' => fn (): string => ReportSettingsPage::getUrl(),
    'backup settings' => fn (): string => BackupSettingsPage::getUrl(),
    'templates' => fn (): string => FindingTemplateResource::getUrl('index'),
    'categories' => fn (): string => CategoryResource::getUrl('index'),
    'risk matrix' => fn (): string => RiskProfileResource::getUrl('index'),
    'effort levels' => fn (): string => EffortLevelResource::getUrl('index'),
]);

/**
 * @param  list<NavigationGroup>  $groups
 * @return list<string>
 */
function navigationLabels(array $groups): array
{
    return collect($groups)
        ->flatMap(static fn (NavigationGroup $group) => $group->getItems())
        ->map(static fn (NavigationItem $item): string => $item->getLabel())
        ->values()
        ->all();
}
