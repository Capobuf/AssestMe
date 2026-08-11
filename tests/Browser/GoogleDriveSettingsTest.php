<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Filament\Pages\GoogleDriveSettingsPage;
use App\Models\User;
use App\Settings\GoogleDriveSettings;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class GoogleDriveSettingsTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_google_application_can_be_configured_vertically_from_settings(): void
    {
        $administrator = User::factory()->create();
        $settings = app(GoogleDriveSettings::class);
        $settings->sync_enabled = false;
        $settings->google_account_email = null;
        $settings->encrypted_refresh_token = null;
        $settings->root_folder_id = null;
        $settings->root_folder_name = null;
        $settings->save();

        $this->browse(function (Browser $browser) use ($administrator): void {
            $browser->loginAs($administrator)
                ->resize(1440, 900)
                ->visit(GoogleDriveSettingsPage::getUrl())
                ->waitForText('Google Drive')
                ->assertSee('AssestMe continua a funzionare normalmente.')
                ->assertSee('Come configurare Google Drive')
                ->assertSee('1. Crea o seleziona un progetto Google Cloud')
                ->assertSee('Configurazione applicazione Google')
                ->assertDisabled('@google-drive-callback')
                ->type('@google-drive-client-id', 'dusk-client-id')
                ->type('@google-drive-client-secret', 'dusk-client-secret')
                ->press('Salva')
                ->waitForText('Collega account Google')
                ->assertSee('Collega account Google')
                ->assertInputValue('@google-drive-client-secret', '')
                ->assertSourceMissing('dusk-client-secret')
                ->assertSourceMissing('Chiave API Picker')
                ->assertSourceMissing('Numero progetto')
                ->assertSourceMissing('apis.google.com/js/api.js');

            $severe = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severe, 'The optional Google Drive settings state has severe console errors.');
        });
    }

    public function test_connected_google_account_uses_the_native_status_and_actions_panel(): void
    {
        $administrator = User::factory()->create();
        $settings = app(GoogleDriveSettings::class);
        $settings->client_id = 'dusk-client-id';
        $settings->encrypted_client_secret = 'dusk-client-secret';
        $settings->sync_enabled = true;
        $settings->google_account_email = 'owner@example.test';
        $settings->encrypted_refresh_token = 'dusk-refresh-token';
        $settings->root_folder_id = 'dusk-root-id';
        $settings->root_folder_name = 'AssestMe';
        $settings->save();

        $this->browse(function (Browser $browser) use ($administrator): void {
            $browser->loginAs($administrator)
                ->resize(1440, 900)
                ->visit(GoogleDriveSettingsPage::getUrl())
                ->waitForText('Stato e utilizzo')
                ->assertSee('owner@example.test')
                ->assertSee('AssestMe')
                ->assertSee('Sincronizzazione automatica')
                ->assertSee('Attiva')
                ->assertSee('Sincronizza ora')
                ->assertSee('Disattiva sincronizzazione automatica')
                ->assertSee('Verifica connessione')
                ->assertSee('Disconnetti')
                ->assertDontSee('L’integrazione Google Drive non è configurata per questa installazione.');

            $severe = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severe, 'The connected Google Drive settings state has severe console errors.');
        });
    }
}
