<?php

declare(strict_types=1);

use App\Data\GoogleDrive\GoogleDriveObjectData;
use App\Filament\Pages\GoogleDriveSettingsPage;
use App\Models\Assessment;
use App\Models\AssessmentGoogleDriveSync;
use App\Models\User;
use App\Services\GoogleDrive\GoogleDriveConfiguration;
use App\Services\GoogleDrive\GoogleDriveTokenService;
use App\Services\GoogleDrive\GoogleWorkspaceClient;
use App\Settings\GoogleDriveSettings;
use Google\Client;
use Livewire\Livewire;

it('renders the embedded guide and two-field configuration while incomplete', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(GoogleDriveSettingsPage::class)
        ->assertSuccessful()
        ->assertSee('Come configurare Google Drive')
        ->assertSee('1. Crea o seleziona un progetto Google Cloud')
        ->assertSee('2. Abilita le API')
        ->assertSee('3. Configura Google Auth Platform')
        ->assertSee('4. Crea il client OAuth')
        ->assertSee('5. Registra l’URI di callback')
        ->assertSee('6. Inserisci ID e secret in AssestMe')
        ->assertSee('ID client OAuth')
        ->assertSee('Secret client OAuth')
        ->assertSee('URI di callback')
        ->assertSet('data.callback_uri', route('google-drive.oauth.callback'))
        ->assertDontSee('Chiave API Picker')
        ->assertDontSee('Numero progetto')
        ->assertDontSee('Collega account Google');
});

it('configures the Google application from UI only and never rehydrates the secret', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(GoogleDriveSettingsPage::class)
        ->set('data.client_id', 'ui-client-id')
        ->set('data.client_secret', 'ui-client-secret')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Collega account Google')
        ->assertSet('data.client_secret', null)
        ->assertSet('data.callback_uri', route('google-drive.oauth.callback'))
        ->assertDontSee('ui-client-secret');

    $settings = app(GoogleDriveSettings::class);
    expect($settings->client_id)->toBe('ui-client-id')
        ->and($settings->encrypted_client_secret)->toBe('ui-client-secret')
        ->and(app(GoogleDriveConfiguration::class)->callbackUri())->toBe(route('google-drive.oauth.callback'));
});

it('retains the write-only client secret when a blank value is saved', function (): void {
    $settings = app(GoogleDriveSettings::class);
    $settings->client_id = 'ui-client-id';
    $settings->encrypted_client_secret = 'stored-client-secret';
    $settings->save();
    $this->actingAs(User::factory()->create());

    Livewire::test(GoogleDriveSettingsPage::class)
        ->assertSet('data.client_secret', null)
        ->set('data.client_secret', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($settings->refresh()->encrypted_client_secret)->toBe('stored-client-secret');
});

it('rejects incomplete UI configuration without changing stored values', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(GoogleDriveSettingsPage::class)
        ->set('data.client_id', '')
        ->set('data.client_secret', '')
        ->call('save')
        ->assertHasErrors([
            'data.client_id' => 'required',
            'data.client_secret' => 'required',
        ]);

    expect(app(GoogleDriveSettings::class)->client_id)->toBeNull();
});

it('clears a connected account when effective OAuth configuration changes', function (): void {
    $settings = configuredGoogleDriveSettingsPage();
    $settings->client_id = 'old-client-id';
    $settings->encrypted_client_secret = 'old-client-secret';
    $settings->save();
    $this->actingAs(User::factory()->create());

    Livewire::test(GoogleDriveSettingsPage::class)
        ->set('data.client_id', 'new-client-id')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotified(__('assestme.google_drive.notifications.configuration_saved_reconnect'));

    $settings->refresh();
    expect($settings->sync_enabled)->toBeFalse()
        ->and($settings->google_account_email)->toBeNull()
        ->and($settings->encrypted_refresh_token)->toBeNull()
        ->and($settings->root_folder_id)->toBeNull()
        ->and($settings->root_folder_name)->toBeNull();
});

it('renders connected account and managed root with usage actions', function (): void {
    configuredGoogleDriveSettingsPage();
    $this->actingAs(User::factory()->create());

    Livewire::test(GoogleDriveSettingsPage::class)
        ->assertSuccessful()
        ->assertSee('owner@example.test')
        ->assertSee('AssestMe')
        ->assertSee('Sincronizza ora')
        ->assertSee('Verifica connessione')
        ->assertSee('Disconnetti')
        ->assertDontSee('Scegli cartella')
        ->assertDontSee('Cambia cartella')
        ->assertDontSee('refresh-token');
});

it('retries automatic root creation for a connected account without a root', function (): void {
    $settings = connectedGoogleDriveSettingsWithoutRoot();
    $google = Mockery::mock(GoogleWorkspaceClient::class);
    $google->shouldReceive('createApplicationRoot')->once()->andReturn(
        new GoogleDriveObjectData('managed-root', 'AssestMe', GoogleWorkspaceClient::FOLDER_MIME_TYPE, null),
    );
    app()->instance(GoogleWorkspaceClient::class, $google);
    $this->actingAs(User::factory()->create());

    Livewire::test(GoogleDriveSettingsPage::class)
        ->assertSee('Crea cartella AssestMe')
        ->call('prepareRoot')
        ->assertNotified(__('assestme.google_drive.notifications.root_created'));

    expect($settings->refresh()->root_folder_id)->toBe('managed-root')
        ->and($settings->root_folder_name)->toBe('AssestMe')
        ->and($settings->sync_enabled)->toBeTrue();
});

it('keeps synchronization disabled when automatic root retry fails', function (): void {
    $settings = connectedGoogleDriveSettingsWithoutRoot();
    $settings->sync_enabled = true;
    $settings->save();
    $google = Mockery::mock(GoogleWorkspaceClient::class);
    $google->shouldReceive('createApplicationRoot')->once()->andThrow(new RuntimeException('raw provider detail'));
    app()->instance(GoogleWorkspaceClient::class, $google);
    $this->actingAs(User::factory()->create());

    Livewire::test(GoogleDriveSettingsPage::class)
        ->call('prepareRoot')
        ->assertNotified(__('assestme.google_drive.notifications.root_creation_failed'));

    expect($settings->refresh()->sync_enabled)->toBeFalse()
        ->and($settings->root_folder_id)->toBeNull();
});

it('requires authentication for the Google Drive settings page', function (): void {
    $this->get(GoogleDriveSettingsPage::getUrl())->assertRedirect('/admin/login');
});

it('toggles automatic synchronization only for a configured connection', function (): void {
    configuredGoogleDriveSettingsPage();
    $this->actingAs(User::factory()->create());

    Livewire::test(GoogleDriveSettingsPage::class)->call('toggleSync')->assertHasNoErrors();

    expect(app(GoogleDriveSettings::class)->sync_enabled)->toBeFalse();
});

it('clears every local credential on disconnect after attempting remote revocation', function (): void {
    $settings = configuredGoogleDriveSettingsPage();
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setClientId');
    $client->shouldReceive('setClientSecret');
    $client->shouldReceive('setScopes');
    $client->shouldReceive('revokeToken')->once()->with('refresh-token')->andReturnTrue();
    app()->instance(GoogleDriveTokenService::class, new GoogleDriveTokenService(
        $client,
        $settings,
        app(GoogleDriveConfiguration::class),
    ));
    $this->actingAs(User::factory()->create());

    Livewire::test(GoogleDriveSettingsPage::class)->call('disconnect')->assertHasNoErrors();

    $settings->refresh();
    expect($settings->sync_enabled)->toBeFalse()
        ->and($settings->google_account_email)->toBeNull()
        ->and($settings->encrypted_refresh_token)->toBeNull()
        ->and($settings->root_folder_id)->toBeNull()
        ->and($settings->root_folder_name)->toBeNull();
});

it('shows aggregate Rome time and a sanitized current error', function (): void {
    configuredGoogleDriveSettingsPage();
    $assessment = Assessment::factory()->create();
    AssessmentGoogleDriveSync::query()->create([
        'assessment_id' => $assessment->getKey(),
        'last_synced_at' => '2026-08-11 10:00:00',
        'last_error' => 'Verifica autorizzazioni e riprova.',
        'last_error_at' => '2026-08-11 10:05:00',
    ]);
    $this->actingAs(User::factory()->create());

    Livewire::test(GoogleDriveSettingsPage::class)
        ->assertSee('Errore da risolvere')
        ->assertSee('11/08/2026 12:00')
        ->assertSee('Verifica autorizzazioni e riprova.');
});

function connectedGoogleDriveSettingsWithoutRoot(): GoogleDriveSettings
{
    config()->set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ]);
    $settings = app(GoogleDriveSettings::class);
    $settings->google_account_email = 'owner@example.test';
    $settings->encrypted_refresh_token = 'refresh-token';
    $settings->root_folder_id = null;
    $settings->root_folder_name = null;
    $settings->sync_enabled = false;
    $settings->save();

    return $settings;
}

function configuredGoogleDriveSettingsPage(): GoogleDriveSettings
{
    $settings = connectedGoogleDriveSettingsWithoutRoot();
    $settings->root_folder_id = 'folder-123';
    $settings->root_folder_name = 'AssestMe';
    $settings->sync_enabled = true;
    $settings->save();

    return $settings;
}
