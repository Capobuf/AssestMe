<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Operations\RecordOperationalCheck;
use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use App\Filament\Pages\GeneralSettingsPage;
use App\Filament\Pages\ReportSettingsPage;
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
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class MilestoneOneFoundationTest extends DuskTestCase
{
    use DatabaseTruncation;

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
        app(RecordOperationalCheck::class)(
            OperationalCheckType::Backup,
            OperationalCheckStatus::Failed,
            'Browser backup failure proof.',
        );
        app(RecordOperationalCheck::class)(
            OperationalCheckType::DatabaseIntegrity,
            OperationalCheckStatus::Failed,
            'Browser integrity failure proof.',
        );
        $riskProfile = RiskProfile::query()->where('is_default', true)->firstOrFail();
        $effortLevel = EffortLevel::query()->where('code', 'low')->firstOrFail();

        $this->browse(function (Browser $browser) use ($administrator, $asset, $client, $effortLevel, $riskProfile, $site): void {
            $browser->loginAs($administrator)
                ->visit('/admin')
                ->waitForText('Assessment in bozza');
            $browser->script('window.scrollTo(0, document.documentElement.scrollHeight)');
            $browser->waitForText('Stato applicazione')
                ->assertSee('L’ultimo tentativo non è riuscito; l’applicazione resta disponibile.')
                ->assertSee('Integrità database')
                ->assertSee('Esegui la diagnostica e verifica il problema segnalato.')
                ->assertDontSee('risolvere il problema prima di continuare')
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
