<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Filament\Clusters\AssetCluster;
use App\Filament\Clusters\SettingsCluster;
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

        $this->browse(function (Browser $browser) use ($administrator, $assessment, $finding): void {
            $browser->loginAs($administrator)
                ->resize(1440, 900)
                ->visit('/admin')
                ->waitFor('.fi-sidebar');

            Assert::assertSame(
                ['Dashboard', 'Assessment', 'Aziende', 'Impostazioni'],
                self::topLevelSidebarLabels($browser),
            );
            Assert::assertNotContains('Tag', self::allSidebarLabels($browser));

            $browser->click(self::sidebarLink(ClientResource::getUrl('index')))
                ->waitForText('Aziende');
            Assert::assertSame(['Sedi', 'Asset'], self::companyChildLabels($browser));
            $browser->assertPathIs(self::path(ClientResource::getUrl('index')));

            $browser->click(self::sidebarLink(SiteResource::getUrl('index')))
                ->waitForText('Sedi')
                ->assertPathIs(self::path(SiteResource::getUrl('index')));
            Assert::assertSame(['Sedi', 'Asset'], self::companyChildLabels($browser));

            $browser->click(self::sidebarLink(AssetCluster::getUrl()))
                ->waitForText('Asset navigazione')
                ->assertPathIs(self::path(AssetResource::getUrl('index')));
            Assert::assertSame(['Asset', 'Tipologie asset'], self::subNavigationLabels($browser));
            Assert::assertSame(['Sedi', 'Asset'], self::companyChildLabels($browser));

            $browser->visit(AssetResource::getUrl('create'))
                ->waitForText('Annulla')
                ->press('Annulla')
                ->waitForLocation(self::path(AssetResource::getUrl('index')))
                ->assertPathIs(self::path(AssetResource::getUrl('index')));

            $browser->click(self::sidebarLink(SettingsCluster::getUrl()))
                ->waitForText('Impostazioni generali')
                ->assertPathIs(self::path(GeneralSettingsPage::getUrl()));
            Assert::assertSame([
                'Generale',
                'Report',
                'Template',
                'Categorie',
                'Matrice priorità',
                'Livelli di impegno',
            ], self::subNavigationLabels($browser));
            Assert::assertSame(
                ['Dashboard', 'Assessment', 'Aziende', 'Impostazioni'],
                self::topLevelSidebarLabels($browser),
            );

            $workspaceUrl = AssessmentResource::getUrl('workspace', [
                'record' => $assessment,
                'finding' => $finding->getKey(),
            ]);
            $browser->visit($workspaceUrl)
                ->waitFor('[data-assestme-finding-inspector]')
                ->waitUntil('return document.documentElement.dataset.assestmeWorkspaceAsset === "loaded"');

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
            Assert::assertTrue($assetSelector['required']);

            $browser->click('[data-dusk="save-finding"]')
                ->waitUntil('return document.querySelector("[data-assestme-save-status]").dataset.status === "error"');

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The navigation and workspace flow contains severe console errors.');
        });
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
    private static function allSidebarLabels(Browser $browser): array
    {
        return $browser->script(<<<'JS'
            return Array.from(document.querySelectorAll('.fi-sidebar .fi-sidebar-item-label'))
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

    private static function sidebarLink(string $url): string
    {
        return sprintf('.fi-sidebar a[href="%s"]', $url);
    }
}
