<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Backups\CreateBackup;
use App\Filament\Clusters\AssetCluster;
use App\Filament\Clusters\SettingsCluster;
use App\Filament\Pages\BackupSettingsPage;
use App\Filament\Pages\GeneralSettingsPage;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\Assessment;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Client;
use App\Models\FindingTemplate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class FilamentNavigationTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_native_navigation_clusters_and_optional_asset_scope_are_accessible_and_console_clean(): void
    {
        $this->seed(DatabaseSeeder::class);
        $administrator = User::factory()->create();
        $client = Client::factory()->create(['legal_name' => 'Azienda navigazione S.r.l.']);
        Asset::factory()->for($client)->create([
            'asset_type_id' => AssetType::query()->firstOrFail()->getKey(),
            'name' => 'Asset navigazione',
        ]);
        $assessment = Assessment::factory()->for($client)->create();
        $finding = app(CopyTemplateToAssessment::class)(
            $assessment,
            FindingTemplate::query()->where('default_scope_type', 'organization')->firstOrFail(),
        );
        $archivePath = app(CreateBackup::class)(null);

        $this->browse(function (Browser $browser) use ($administrator, $archivePath, $assessment, $finding): void {
            $browser->loginAs($administrator)
                ->resize(1440, 900)
                ->visit('/admin')
                ->waitFor('.fi-topbar-nav-groups');

            Assert::assertSame(
                ['Dashboard', 'Assessment', 'Aziende', 'Impostazioni'],
                self::topLevelNavigationLabels($browser),
            );
            Assert::assertNotContains('Tag', self::topLevelNavigationLabels($browser));
            Assert::assertTrue($browser->element('.fi-user-menu-trigger')?->isDisplayed() ?? false);
            self::assertDesktopTopNavigationLayout($browser);

            $browser->click(self::topNavigationLink(ClientResource::getUrl('index')))
                ->waitForText('Aziende');
            $browser->assertPathIs(self::path(ClientResource::getUrl('index')));

            self::resizeViewport($browser, 768, 900);
            self::openResponsiveNavigation($browser);
            Assert::assertSame(
                ['Dashboard', 'Assessment', 'Aziende', 'Impostazioni'],
                self::topLevelSidebarLabels($browser),
            );
            Assert::assertSame(['Sedi', 'Asset'], self::companyChildLabels($browser));
            $browser->click(self::sidebarLink(SiteResource::getUrl('index')))
                ->waitForText('Sedi')
                ->assertPathIs(self::path(SiteResource::getUrl('index')));
            self::openResponsiveNavigation($browser);
            Assert::assertSame(['Sedi', 'Asset'], self::companyChildLabels($browser));

            $browser->click(self::sidebarLink(AssetCluster::getUrl()))
                ->waitForText('Asset navigazione')
                ->assertPathIs(self::path(AssetResource::getUrl('index')));
            Assert::assertSame(['Asset', 'Tipologie asset'], self::subNavigationLabels($browser));

            $browser->visit(AssetResource::getUrl('create'))
                ->waitForText('Annulla')
                ->press('Annulla')
                ->waitForLocation(self::path(AssetResource::getUrl('index')))
                ->assertPathIs(self::path(AssetResource::getUrl('index')));

            self::resizeViewport($browser, 1440, 900);
            $browser->click(self::topNavigationLink(SettingsCluster::getUrl()))
                ->waitForText('Impostazioni generali')
                ->assertPathIs(self::path(GeneralSettingsPage::getUrl()));
            Assert::assertSame([
                'Generale',
                'Report',
                'Backup',
                'Google Drive',
                'Template',
                'Diagnostica',
                'Categorie',
                'Matrice priorità',
                'Livelli di impegno',
            ], self::subNavigationLabels($browser));
            Assert::assertSame(
                ['Dashboard', 'Assessment', 'Aziende', 'Impostazioni'],
                self::topLevelNavigationLabels($browser),
            );

            $browser->click(sprintf('.fi-page-sub-navigation-tabs a[href="%s"]', BackupSettingsPage::getUrl()))
                ->waitForText('Archivi locali gestiti')
                ->assertPathIs(self::path(BackupSettingsPage::getUrl()))
                ->assertSee('Pianificazione applicativa')
                ->assertSee('Ripristino');
            $browser->press('Istruzioni ripristino')
                ->waitForText('Archivio selezionato')
                ->assertSee(basename($archivePath))
                ->assertSee('Comandi')
                ->assertSee('Garanzie del ripristino')
                ->press('Chiudi');
            self::resizeViewport($browser, 390, 844);
            self::openResponsiveNavigation($browser);
            Assert::assertSame(
                ['Dashboard', 'Assessment', 'Aziende', 'Impostazioni'],
                self::topLevelSidebarLabels($browser),
            );
            self::closeResponsiveNavigation($browser);
            Assert::assertLessThanOrEqual(1, (int) $browser->script(
                'return document.documentElement.scrollWidth - document.documentElement.clientWidth;',
            )[0], 'The backup page overflows horizontally on a narrow viewport.');
            self::assertResponsiveContentBelowTopbar($browser);
            self::resizeViewport($browser, 1440, 900);

            $workspaceUrl = AssessmentResource::getUrl('workspace', [
                'record' => $assessment,
                'finding' => $finding->getKey(),
            ]);
            $browser->visit($workspaceUrl)
                ->waitFor('[data-assestme-finding-inspector]')
                ->waitUntil('return document.documentElement.dataset.assestmeWorkspaceAsset === "loaded"');
            self::assertDesktopTopNavigationLayout($browser);
            Assert::assertGreaterThanOrEqual(1360, (float) $browser->script(
                'return document.querySelector(".assestme-findings-workspace").getBoundingClientRect().width;',
            )[0], 'The workspace does not use the horizontal space released by the desktop sidebar.');
            Assert::assertLessThanOrEqual(1, (int) $browser->script(
                'return document.documentElement.scrollWidth - document.documentElement.clientWidth;',
            )[0], 'The workspace overflows horizontally after enabling top navigation.');
            $browser->waitUntil(<<<'JS'
                return Array.from(document.querySelectorAll('[data-assestme-workbench-properties] .assestme-workbench-section--properties .fi-section-content-ctn'))
                    .every((section) => section.getAttribute('aria-expanded') === 'true');
                JS);
            Assert::assertSame(5, (int) $browser->script(<<<'JS'
                return document.querySelectorAll('[data-assestme-workbench-properties] .assestme-workbench-section--properties .fi-section-content-ctn[aria-expanded="true"]').length;
                JS)[0], 'Every lateral Finding property section should be open by default.');

            Assert::assertFalse(self::assetSelectorState($browser)['present']);

            self::setScope($browser, 'selected_sites');
            Assert::assertFalse(self::assetSelectorState($browser)['present']);

            self::setScope($browser, 'network');
            Assert::assertFalse(self::assetSelectorState($browser)['present']);

            self::setScope($browser, 'custom');
            Assert::assertFalse(self::assetSelectorState($browser)['present']);

            self::setScope($browser, 'selected_assets');
            $assetSelector = self::assetSelectorState($browser);
            Assert::assertTrue($assetSelector['present']);
            Assert::assertFalse($assetSelector['required']);

            $browser->click('[data-dusk="save-finding"]')
                ->waitUntil('return document.querySelector("[data-assestme-save-status]").dataset.status === "saved"');

            $persistedFinding = $finding->fresh();
            Assert::assertSame('selected_assets', $persistedFinding->scope_type->value);
            Assert::assertSame(0, $persistedFinding->assets()->count());

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The navigation and workspace flow contains severe console errors.');

            $browser->click('.fi-no-notification-close-btn')
                ->waitUntilMissing('.fi-no-notification');
            $browser->click('.fi-user-menu-trigger')
                ->waitForText(__('filament-panels::layout.actions.logout.label'))
                ->press(__('filament-panels::layout.actions.logout.label'))
                ->waitForLocation('/admin/login')
                ->assertPathIs('/admin/login');
        });
    }

    /** @return list<string> */
    private static function topLevelNavigationLabels(Browser $browser): array
    {
        return $browser->script(<<<'JS'
            return Array.from(document.querySelectorAll('.fi-topbar-nav-groups > .fi-topbar-item .fi-topbar-item-label'))
                .map((label) => label.textContent.trim());
            JS)[0];
    }

    /** @return list<string> */
    private static function topLevelSidebarLabels(Browser $browser): array
    {
        return $browser->script(<<<'JS'
            return Array.from(document.querySelectorAll('.fi-sidebar-group-items > .fi-sidebar-item > .fi-sidebar-item-btn > .fi-sidebar-item-label'))
                .map((label) => label.textContent.trim());
            JS)[0];
    }

    /** @return list<string> */
    private static function companyChildLabels(Browser $browser): array
    {
        return $browser->script(<<<'JS'
            const company = Array.from(document.querySelectorAll('.fi-sidebar-group-items > .fi-sidebar-item'))
                .find((item) => item.querySelector(':scope > .fi-sidebar-item-btn > .fi-sidebar-item-label')?.textContent.trim() === 'Aziende');

            return Array.from(company?.querySelectorAll(':scope > .fi-sidebar-sub-group-items > .fi-sidebar-item > .fi-sidebar-item-btn > .fi-sidebar-item-label') ?? [])
                .map((label) => label.textContent.trim());
            JS)[0];
    }

    /** @return list<string> */
    private static function subNavigationLabels(Browser $browser): array
    {
        return $browser->script(<<<'JS'
            return Array.from(document.querySelectorAll('.fi-page-sub-navigation-tabs .fi-tabs-item-label'))
                .map((label) => label.textContent.trim());
            JS)[0];
    }

    /** @return array{present: bool, required: bool} */
    private static function assetSelectorState(Browser $browser): array
    {
        return $browser->script(<<<'JS'
            const field = Array.from(document.querySelectorAll('[data-field-wrapper]'))
                .find((wrapper) => wrapper.querySelector('.fi-fo-field-label-content')?.childNodes[0]?.textContent.trim() === 'Asset');

            return {
                present: Boolean(field),
                required: Boolean(field?.querySelector('.fi-fo-field-label-required-mark')),
            };
            JS)[0];
    }

    private static function setScope(Browser $browser, string $scope): void
    {
        $scope = json_encode($scope, JSON_THROW_ON_ERROR);
        $browser->script(<<<JS
            const root = document.querySelector('.assestme-findings-workspace').closest('[wire\\\\:id]');
            Livewire.find(root.getAttribute('wire:id')).\$set('findingData.scope_type', {$scope});
            JS);
        $browser->waitUntil('return window.Livewire !== undefined')->pause(350);
    }

    private static function path(string $url): string
    {
        return (string) parse_url($url, PHP_URL_PATH);
    }

    private static function topNavigationLink(string $url): string
    {
        return sprintf('.fi-topbar-nav-groups a[href="%s"]', $url);
    }

    private static function sidebarLink(string $url): string
    {
        return sprintf('.fi-sidebar a[href="%s"]', $url);
    }

    private static function openResponsiveNavigation(Browser $browser): void
    {
        $browser->click('.fi-topbar-open-sidebar-btn')
            ->waitUntil('return document.querySelector(".fi-sidebar").classList.contains("fi-sidebar-open")');
    }

    private static function closeResponsiveNavigation(Browser $browser): void
    {
        $browser->script('document.querySelector(".fi-sidebar-close-overlay").click();');
        $browser->waitUntil('return ! document.querySelector(".fi-sidebar").classList.contains("fi-sidebar-open")');
    }

    private static function assertDesktopTopNavigationLayout(Browser $browser): void
    {
        $layout = $browser->script(<<<'JS'
            const sidebar = document.querySelector('.fi-sidebar');
            const main = document.querySelector('.fi-main-ctn');
            const topbar = document.querySelector('.fi-topbar');
            const navigation = document.querySelector('.fi-topbar-nav-groups');

            return {
                bodyHasTopNavigation: document.body.classList.contains('fi-body-has-top-navigation'),
                viewportWidth: document.documentElement.clientWidth,
                sidebarRight: sidebar.getBoundingClientRect().right,
                mainLeft: main.getBoundingClientRect().left,
                mainWidth: main.getBoundingClientRect().width,
                navigationTop: navigation.getBoundingClientRect().top,
                navigationBottom: navigation.getBoundingClientRect().bottom,
                topbarTop: topbar.getBoundingClientRect().top,
                topbarBottom: topbar.getBoundingClientRect().bottom,
                navigationOverflow: navigation.scrollWidth - navigation.clientWidth,
            };
            JS)[0];

        Assert::assertTrue($layout['bodyHasTopNavigation']);
        Assert::assertLessThanOrEqual(1, (float) $layout['sidebarRight'], 'The desktop sidebar remains visible.');
        Assert::assertLessThanOrEqual(1, (float) $layout['mainLeft'], 'The main content retains a sidebar offset.');
        Assert::assertGreaterThanOrEqual((float) $layout['viewportWidth'] - 1, (float) $layout['mainWidth']);
        Assert::assertGreaterThanOrEqual((float) $layout['topbarTop'], (float) $layout['navigationTop']);
        Assert::assertLessThanOrEqual((float) $layout['topbarBottom'], (float) $layout['navigationBottom']);
        Assert::assertLessThanOrEqual(1, (float) $layout['navigationOverflow'], 'The desktop top navigation overflows.');
    }

    private static function assertResponsiveContentBelowTopbar(Browser $browser): void
    {
        $browser->script('window.scrollTo(0, 0);');
        $browser->pause(100);

        $layout = $browser->script(<<<'JS'
            const topbar = document.querySelector('.fi-topbar');
            const main = document.querySelector('.fi-main');

            return {
                topbarBottom: topbar.getBoundingClientRect().bottom,
                mainTop: main.getBoundingClientRect().top,
            };
            JS)[0];

        Assert::assertGreaterThanOrEqual(
            (float) $layout['topbarBottom'],
            (float) $layout['mainTop'],
            'Responsive content renders below the native topbar.',
        );
    }

    private static function resizeViewport(Browser $browser, int $width, int $height): void
    {
        $browser->resize($width, $height);

        $dimensions = $browser->script('return [window.innerWidth, window.innerHeight];');
        $browser->resize(
            $width + ($width - (int) $dimensions[0][0]),
            $height + ($height - (int) $dimensions[0][1]),
        )->pause(150);
    }
}
