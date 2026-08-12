<?php

declare(strict_types=1);

use App\Filament\Clusters\AssetCluster;
use App\Filament\Clusters\IntegrationsCluster;
use App\Filament\Clusters\SettingsCluster;
use App\Filament\Pages\BackupSettingsPage;
use App\Filament\Pages\DiagnosticsPage;
use App\Filament\Pages\FattureInCloudSettingsPage;
use App\Filament\Pages\GeneralSettingsPage;
use App\Filament\Pages\GoogleDriveSettingsPage;
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
use Illuminate\Support\Facades\Route;

it('discovers native clusters and assigns each clustered component exactly once', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->hasTopNavigation())->toBeTrue()
        ->and($panel->isSidebarCollapsibleOnDesktop())->toBeFalse()
        ->and($panel->isSidebarFullyCollapsibleOnDesktop())->toBeFalse()
        ->and($panel->getClusters())->toContain(AssetCluster::class, IntegrationsCluster::class, SettingsCluster::class)
        ->and($panel->getClusteredComponents(AssetCluster::class))->toEqualCanonicalizing([
            AssetResource::class,
            AssetTypeResource::class,
        ])
        ->and(SettingsCluster::getClusteredComponents())->toEqualCanonicalizing([
            GeneralSettingsPage::class,
            ReportSettingsPage::class,
            BackupSettingsPage::class,
            IntegrationsCluster::class,
            DiagnosticsPage::class,
            FindingTemplateResource::class,
            CategoryResource::class,
            RiskProfileResource::class,
            EffortLevelResource::class,
        ])
        ->and($panel->getClusteredComponents(IntegrationsCluster::class))->toEqualCanonicalizing([
            FattureInCloudSettingsPage::class,
            GoogleDriveSettingsPage::class,
        ])
        ->and(AssetResource::getCluster())->toBe(AssetCluster::class)
        ->and(AssetTypeResource::getCluster())->toBe(AssetCluster::class)
        ->and(GeneralSettingsPage::getCluster())->toBe(SettingsCluster::class)
        ->and(ReportSettingsPage::getCluster())->toBe(SettingsCluster::class)
        ->and(BackupSettingsPage::getCluster())->toBe(SettingsCluster::class)
        ->and(IntegrationsCluster::getCluster())->toBe(SettingsCluster::class)
        ->and(GoogleDriveSettingsPage::getCluster())->toBe(IntegrationsCluster::class)
        ->and(FattureInCloudSettingsPage::getCluster())->toBe(IntegrationsCluster::class)
        ->and(DiagnosticsPage::getCluster())->toBe(SettingsCluster::class);
});

it('uses contrast-aware vector branding for the panel themes', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->getBrandName())->toBe(__('assestme.app.name'))
        ->and($panel->getBrandLogo())->toBe(asset('images/brand/assestme-logo-black.svg'))
        ->and($panel->getDarkModeBrandLogo())->toBe(asset('images/brand/assestme-logo-white.svg'))
        ->and($panel->getBrandLogoHeight())->toBe('2.25rem')
        ->and($panel->getFavicon())->toBe(asset('images/brand/assestme-logo-black.svg'))
        ->and(public_path('images/brand/assestme-logo-black.svg'))->toBeFile()
        ->and(public_path('images/brand/assestme-logo-white.svg'))->toBeFile();
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
        ->and($labels)->not->toContain('Sedi', 'Asset', 'Tipologie asset', 'Generale', 'Report', 'Template', 'Categorie', 'Matrice priorità', 'Livelli di impegno', 'Integrazioni', 'Fatture in Cloud', 'Google Drive', 'Tag')
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
        ->and(IntegrationsCluster::getSubNavigationPosition())->toBe(SubNavigationPosition::Start)
        ->and(AssetCluster::getUrl())->toEndWith('/admin/asset')
        ->and(AssetResource::getUrl('index'))->toEndWith('/admin/asset/assets')
        ->and(AssetTypeResource::getUrl('index'))->toEndWith('/admin/asset/asset-types')
        ->and(SettingsCluster::getUrl())->toEndWith('/admin/settings')
        ->and(IntegrationsCluster::getUrl())->toBe(FattureInCloudSettingsPage::getUrl())
        ->and(Route::has('filament.admin.integrations'))->toBeFalse()
        ->and(GeneralSettingsPage::getUrl())->toEndWith('/admin/settings/general-settings-page')
        ->and(ReportSettingsPage::getUrl())->toEndWith('/admin/settings/report-settings-page')
        ->and(BackupSettingsPage::getUrl())->toEndWith('/admin/settings/backup-settings-page')
        ->and(FattureInCloudSettingsPage::getUrl())->toEndWith('/admin/settings/integrations/fatture-in-cloud-settings-page')
        ->and(GoogleDriveSettingsPage::getUrl())->toEndWith('/admin/settings/integrations/google-drive-settings-page')
        ->and(DiagnosticsPage::getUrl())->toEndWith('/admin/settings/diagnostics')
        ->and(FindingTemplateResource::getUrl('index'))->toEndWith('/admin/settings/finding-templates')
        ->and(CategoryResource::getUrl('index'))->toEndWith('/admin/settings/categories')
        ->and(RiskProfileResource::getUrl('index'))->toEndWith('/admin/settings/risk-profiles')
        ->and(EffortLevelResource::getUrl('index'))->toEndWith('/admin/settings/effort-levels')
        ->and(AssetResource::getRouteBaseName())->toBe('filament.admin.asset.resources.assets')
        ->and(GeneralSettingsPage::getRouteName())->toBe('filament.admin.settings.pages.general-settings-page');

    $assetSubNavigation = app(AssetCluster::class)->getCachedSubNavigation();
    $settingsSubNavigation = app(SettingsCluster::class)->getCachedSubNavigation();
    $integrationsSubNavigation = app(IntegrationsCluster::class)->getCachedSubNavigation();

    expect(navigationLabels($assetSubNavigation))->toBe(['Asset', 'Tipologie asset'])
        ->and(navigationLabels($settingsSubNavigation))->toBe([
            'Generale',
            'Report',
            'Backup',
            'Integrazioni',
            'Template',
            'Diagnostica',
            'Categorie',
            'Matrice priorità',
            'Livelli di impegno',
        ])
        ->and(navigationLabels($integrationsSubNavigation))->toBe([
            'Fatture in Cloud',
            'Google Drive',
        ]);
});

it('redirects each cluster entry to its first real destination', function (): void {
    $this->actingAs(User::factory()->create());

    $this->get(AssetCluster::getUrl())->assertRedirect(AssetResource::getUrl('index'));
    $this->get(SettingsCluster::getUrl())->assertRedirect(GeneralSettingsPage::getUrl());
    expect(IntegrationsCluster::getUrl())->toBe(FattureInCloudSettingsPage::getUrl());
});

it('requires authentication for every relocated navigation destination', function (string $url): void {
    $this->get($url)->assertRedirect('/admin/login');
})->with([
    'assets' => fn (): string => AssetResource::getUrl('index'),
    'asset types' => fn (): string => AssetTypeResource::getUrl('index'),
    'general settings' => fn (): string => GeneralSettingsPage::getUrl(),
    'report settings' => fn (): string => ReportSettingsPage::getUrl(),
    'backup settings' => fn (): string => BackupSettingsPage::getUrl(),
    'Google Drive settings' => fn (): string => GoogleDriveSettingsPage::getUrl(),
    'Fatture in Cloud settings' => fn (): string => FattureInCloudSettingsPage::getUrl(),
    'diagnostics' => fn (): string => DiagnosticsPage::getUrl(),
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
