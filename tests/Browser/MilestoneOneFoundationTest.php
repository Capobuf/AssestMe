<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Filament\Pages\GeneralSettingsPage;
use App\Filament\Pages\ReportSettingsPage;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\AssetTypes\AssetTypeResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\EffortLevels\EffortLevelResource;
use App\Filament\Resources\RiskProfiles\RiskProfileResource;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\Assessment;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Client;
use App\Models\EffortLevel;
use App\Models\RiskProfile;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\MilestoneOneSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\File;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class MilestoneOneFoundationTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_operational_dashboard_renders_hero_launchers_and_responsive_layout(): void
    {
        $this->seed(MilestoneOneSeeder::class);
        $administrator = User::factory()->create();
        $client = Client::factory()->create(['trade_name' => 'Azienda Dashboard']);
        Assessment::factory()->for($client)->create([
            'title' => 'Assessment operativo dashboard',
            'assessment_date' => '2026-08-12',
        ]);
        $artifactRoot = base_path('storage/app/qa-artifacts');
        File::ensureDirectoryExists($artifactRoot);

        $this->browse(function (Browser $browser) use ($administrator, $artifactRoot): void {
            $browser->resize(1440, 900)
                ->loginAs($administrator)
                ->visit('/admin')
                ->waitFor('[data-dusk="dashboard-hero"]')
                ->waitForText('Riprendi il lavoro')
                ->assertSee('Nuovo assessment')
                ->assertSee('Aziende recenti')
                ->assertSee('Ultimi assessment')
                ->assertSee('Archivio AssestMe')
                ->assertMissing('.fi-account-widget')
                ->assertPresent("a[href='".AssessmentResource::getUrl('create')."']")
                ->script("localStorage.setItem('theme', 'dark'); document.documentElement.classList.add('dark');");
            $browser->pause(250);

            $desktop = $browser->script(<<<'JS'
                const hero = document.querySelector('[data-dusk="dashboard-hero"]');
                const launchers = document.querySelector('.assestme-dashboard-launchers');
                const primaryGrid = document.querySelector('.assestme-dashboard-primary-grid');
                const archiveGrid = document.querySelector('.assestme-dashboard-archive__grid');

                return {
                    width: document.documentElement.clientWidth,
                    scrollWidth: document.documentElement.scrollWidth,
                    heroWidth: hero?.getBoundingClientRect().width ?? 0,
                    launcherCount: launchers?.children.length ?? 0,
                    launcherColumns: getComputedStyle(launchers).gridTemplateColumns.split(' ').length,
                    primaryColumns: getComputedStyle(primaryGrid).gridTemplateColumns.split(' ').length,
                    archiveCount: archiveGrid?.children.length ?? 0,
                    archiveColumns: getComputedStyle(archiveGrid).gridTemplateColumns.split(' ').length,
                };
                JS)[0];

            Assert::assertLessThanOrEqual($desktop['width'] + 1, $desktop['scrollWidth']);
            Assert::assertGreaterThan(900, $desktop['heroWidth']);
            Assert::assertSame(4, $desktop['launcherCount']);
            Assert::assertSame(4, $desktop['launcherColumns']);
            Assert::assertSame(2, $desktop['primaryColumns']);
            Assert::assertSame(4, $desktop['archiveCount']);
            Assert::assertSame(4, $desktop['archiveColumns']);
            $browser->driver->takeScreenshot("{$artifactRoot}/dashboard-operational-1440x900-dark.png");

            $browser->resize(390, 844)
                ->visit('/admin')
                ->waitFor('[data-dusk="dashboard-hero"]')
                ->waitForText('Riprendi il lavoro')
                ->assertSee('Nuovo assessment')
                ->assertSee('Aziende recenti')
                ->assertSee('Ultimi assessment')
                ->assertSee('Archivio AssestMe')
                ->assertPresent('[data-dusk="dashboard-archive"]')
                ->pause(250);

            $mobile = $browser->script(<<<'JS'
                const hero = document.querySelector('[data-dusk="dashboard-hero"]');
                const launchers = document.querySelector('.assestme-dashboard-launchers');
                const primaryGrid = document.querySelector('.assestme-dashboard-primary-grid');
                const archiveGrid = document.querySelector('.assestme-dashboard-archive__grid');

                return {
                    width: document.documentElement.clientWidth,
                    scrollWidth: document.documentElement.scrollWidth,
                    heroWidth: hero?.getBoundingClientRect().width ?? 0,
                    launcherColumns: getComputedStyle(launchers).gridTemplateColumns.split(' ').length,
                    primaryColumns: getComputedStyle(primaryGrid).gridTemplateColumns.split(' ').length,
                    archiveColumns: getComputedStyle(archiveGrid).gridTemplateColumns.split(' ').length,
                };
                JS)[0];

            Assert::assertLessThanOrEqual($mobile['width'] + 1, $mobile['scrollWidth']);
            Assert::assertLessThanOrEqual($mobile['width'], $mobile['heroWidth'] + 1);
            Assert::assertSame(1, $mobile['launcherColumns']);
            Assert::assertSame(1, $mobile['primaryColumns']);
            Assert::assertSame(1, $mobile['archiveColumns']);
            $browser->driver->takeScreenshot("{$artifactRoot}/dashboard-operational-390x844-dark.png");

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The browser console contains severe errors.');
        });
    }

    public function test_client_and_site_resources_render_without_console_errors(): void
    {
        $this->seed(MilestoneOneSeeder::class);
        $administrator = User::factory()->create();
        $client = Client::factory()->create([
            'legal_name' => 'Cliente prova browser S.r.l.',
            'trade_name' => 'Cliente Browser',
        ]);
        $site = Site::factory()->for($client)->create([
            'name' => 'Sede prova browser',
            'city' => 'Torino',
        ]);
        $assetType = AssetType::factory()->create([
            'name' => 'Server browser',
            'slug' => 'server-browser',
            'sort_order' => 1,
        ]);
        $asset = Asset::factory()
            ->for($client)
            ->for($site)
            ->for($assetType, 'assetType')
            ->create(['name' => 'Asset prova browser']);
        Category::factory()->create(['name' => 'Categoria prova browser', 'sort_order' => 0]);
        Assessment::factory()->for($client)->create(['title' => 'Assessment dashboard browser']);
        $riskProfile = RiskProfile::query()->where('is_default', true)->firstOrFail();
        $effortLevel = EffortLevel::query()->where('code', 'low')->firstOrFail();

        $this->browse(function (Browser $browser) use ($administrator, $asset, $client, $effortLevel, $riskProfile, $site): void {
            $browser->loginAs($administrator)
                ->visit('/admin')
                ->waitForText('Riprendi il lavoro');
            $browser->script('window.scrollTo(0, document.documentElement.scrollHeight)');
            $browser->waitForText('Archivio AssestMe')
                ->waitForText('Ultimi assessment')
                ->waitForText('Cliente Browser')
                ->visit(ClientResource::getUrl('index'))
                ->waitForText('Aziende')
                ->assertSee('Cliente prova browser S.r.l.')
                ->assertSee('Cliente Browser')
                ->assertSee('Esporta tabella')
                ->assertPresent("a[href='".ClientResource::getUrl('edit', ['record' => $client])."']")
                ->waitUntil('window.FilamentRightClick !== undefined');

            $contextMenuState = $browser->script(<<<'JS'
                const row = document.querySelector('.fi-ta-record, .fi-ta-row');
                const surface = row?.closest('[data-filament-right-click-record-config]');
                row?.dispatchEvent(new MouseEvent('contextmenu', {
                    bubbles: true,
                    cancelable: true,
                    clientX: 120,
                    clientY: 160,
                }));

                const menu = document.querySelector('.fi-right-click-menu');

                return {
                    hasRow: Boolean(row),
                    hasSurface: Boolean(surface),
                    menuIsOpen: Boolean(menu && ! menu.hidden && menu.classList.contains('fi-open')),
                    recordKey: row?.getAttribute('wire:key') ?? null,
                };
                JS);
            $contextMenuState = $contextMenuState[0] ?? [];
            $contextMenuDiagnostics = json_encode($contextMenuState, JSON_THROW_ON_ERROR);
            Assert::assertTrue($contextMenuState['hasRow'] ?? false, $contextMenuDiagnostics);
            Assert::assertTrue($contextMenuState['hasSurface'] ?? false, $contextMenuDiagnostics);
            Assert::assertMatchesRegularExpression(
                '/\\.table\\.records\\.[^.]+$/',
                (string) ($contextMenuState['recordKey'] ?? ''),
                $contextMenuDiagnostics,
            );
            Assert::assertTrue($contextMenuState['menuIsOpen'] ?? false, $contextMenuDiagnostics);

            $browser->waitFor('.fi-right-click-menu.fi-open')
                ->assertSeeIn('.fi-right-click-menu.fi-open', 'Modifica')
                ->assertSeeIn('.fi-right-click-menu.fi-open', 'Archivia')
                ->click('.fi-right-click-menu.fi-open [data-action="contextEdit"]')
                ->waitForText('Modifica azienda')
                ->assertSee('Ragione sociale')
                ->visit(ClientResource::getUrl('edit', ['record' => $client]))
                ->waitForText('Ragione sociale')
                ->assertSee('Archivia');

            $clientInputValues = $browser->script(
                'return Array.from(document.querySelectorAll("input")).map((element) => element.value);',
            );
            Assert::assertContains('Cliente prova browser S.r.l.', $clientInputValues[0] ?? []);

            $browser->visit(SiteResource::getUrl('index'))
                ->waitForText('Sedi')
                ->assertSee('Sede prova browser')
                ->assertSee('Torino')
                ->visit(SiteResource::getUrl('edit', ['record' => $site]))
                ->waitForText('Azienda');

            $siteInputValues = $browser->script(
                'return Array.from(document.querySelectorAll("input")).map((element) => element.value);',
            );
            Assert::assertContains('Sede prova browser', $siteInputValues[0] ?? []);

            $browser->visit(AssetTypeResource::getUrl('index'))
                ->waitForText('Tipologie asset')
                ->assertSee('Server browser')
                ->visit(AssetResource::getUrl('index'))
                ->waitForText('Asset')
                ->assertSee('Asset prova browser')
                ->assertSee('Cliente prova browser S.r.l.')
                ->visit(AssetResource::getUrl('edit', ['record' => $asset]))
                ->waitForText('Identificazione');

            $assetInputValues = $browser->script(
                'return Array.from(document.querySelectorAll("input")).map((element) => element.value);',
            );
            Assert::assertContains('Asset prova browser', $assetInputValues[0] ?? []);

            $browser->visit(CategoryResource::getUrl('index'))
                ->waitForText('Categorie')
                ->assertSee('Categoria prova browser')
                ->visit('/admin/profile')
                ->waitForText('Autenticazione a due fattori (2FA)')
                ->assertSee('App di autenticazione')
                ->assertSee('Configurazione')
                ->visit(RiskProfileResource::getUrl('index'))
                ->waitForText('Profilo predefinito')
                ->assertSee('Profilo predefinito')
                ->visit(RiskProfileResource::getUrl('edit', ['record' => $riskProfile]))
                ->waitForText('Conseguenze')
                ->assertSee('Matrice')
                ->visit(EffortLevelResource::getUrl('index'))
                ->waitForText('Basso')
                ->assertSee('Basso')
                ->visit(EffortLevelResource::getUrl('edit', ['record' => $effortLevel]))
                ->waitForText('Codice')
                ->visit(GeneralSettingsPage::getUrl())
                ->waitForText('Impostazioni generali')
                ->assertSee('Profilo di rischio attivo')
                ->visit(ReportSettingsPage::getUrl())
                ->waitForText('Impostazioni report')
                ->assertSee('Identità del consulente');

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The browser console contains severe errors.');
        });
    }
}
