<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Assessment;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Client;
use App\Models\Site;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class MilestoneOneFoundationTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_client_and_site_resources_render_without_console_errors(): void
    {
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
        Category::factory()->create(['name' => 'Categoria prova browser']);
        Tag::factory()->create(['name' => 'Tag prova browser']);
        Assessment::factory()->create(['title' => 'Assessment dashboard browser']);

        $this->browse(function (Browser $browser) use ($administrator, $asset, $client, $site): void {
            $browser->loginAs($administrator)
                ->visit('/admin')
                ->waitForText('Assessment in bozza')
                ->waitForText('Ultimi assessment')
                ->waitForText('Assessment dashboard browser')
                ->visit('/admin/clients')
                ->waitForText('Clienti')
                ->assertSee('Cliente prova browser S.r.l.')
                ->assertSee('Cliente Browser')
                ->assertSee('Esporta tabella')
                ->assertPresent("a[href$='/admin/clients/{$client->getKey()}/edit']")
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
                ->click('.fi-right-click-menu.fi-open [data-action="contextEdit"]')
                ->waitForText('Modifica cliente')
                ->assertSee('Ragione sociale')
                ->visit("/admin/clients/{$client->getKey()}/edit")
                ->waitForText('Ragione sociale');

            $clientInputValues = $browser->script(
                'return Array.from(document.querySelectorAll("input")).map((element) => element.value);',
            );
            Assert::assertContains('Cliente prova browser S.r.l.', $clientInputValues[0] ?? []);

            $browser->visit('/admin/sites')
                ->waitForText('Sedi')
                ->assertSee('Sede prova browser')
                ->assertSee('Torino')
                ->visit("/admin/sites/{$site->getKey()}/edit")
                ->waitForText('Cliente');

            $siteInputValues = $browser->script(
                'return Array.from(document.querySelectorAll("input")).map((element) => element.value);',
            );
            Assert::assertContains('Sede prova browser', $siteInputValues[0] ?? []);

            $browser->visit('/admin/asset-types')
                ->waitForText('Tipologie asset')
                ->assertSee('Server browser')
                ->visit('/admin/assets')
                ->waitForText('Asset')
                ->assertSee('Asset prova browser')
                ->assertSee('Cliente prova browser S.r.l.')
                ->visit("/admin/assets/{$asset->getKey()}/edit")
                ->waitForText('Identificazione');

            $assetInputValues = $browser->script(
                'return Array.from(document.querySelectorAll("input")).map((element) => element.value);',
            );
            Assert::assertContains('Asset prova browser', $assetInputValues[0] ?? []);

            $browser->visit('/admin/categories')
                ->waitForText('Categorie')
                ->assertSee('Categoria prova browser')
                ->visit('/admin/tags')
                ->waitForText('Tag')
                ->assertSee('Tag prova browser')
                ->visit('/admin/profile')
                ->waitForText('Autenticazione a due fattori (2FA)')
                ->assertSee('App di autenticazione')
                ->assertSee('Configurazione');

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The browser console contains severe errors.');
        });
    }
}
