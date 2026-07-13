<?php

declare(strict_types=1);

namespace Tests\Browser;

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

        $this->browse(function (Browser $browser) use ($administrator, $asset, $client, $site): void {
            $browser->loginAs($administrator)
                ->visit('/admin/clients')
                ->waitForText('Clienti')
                ->assertSee('Cliente prova browser S.r.l.')
                ->assertSee('Cliente Browser')
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
