<?php

declare(strict_types=1);

use App\Data\GoogleDrive\GoogleDriveObjectData;
use App\Filament\Pages\GoogleDriveSettingsPage;
use App\Models\User;
use App\Services\GoogleDrive\GoogleDriveConfiguration;
use App\Services\GoogleDrive\GoogleWorkspaceClient;
use App\Settings\GoogleDriveSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function (): void {
    config()->set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ]);
});

it('requires authentication to start and finish Google authorization', function (): void {
    $this->get(route('google-drive.oauth.redirect'))->assertRedirect('/admin/login');
    $this->get(route('google-drive.oauth.callback'))->assertRedirect('/admin/login');
});

it('starts stateful offline authorization with drive file as the sole Drive data scope', function (): void {
    $this->actingAs(User::factory()->create());

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('setScopes')->once()->with([
        'openid',
        'email',
        'https://www.googleapis.com/auth/drive.file',
    ])->andReturnSelf();
    $provider->shouldReceive('with')->once()->with([
        'access_type' => 'offline',
        'prompt' => 'consent',
        'include_granted_scopes' => 'false',
    ])->andReturnSelf();
    $provider->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://accounts.google.test/authorize'));
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $this->get(route('google-drive.oauth.redirect'))
        ->assertRedirect('https://accounts.google.test/authorize');
});

it('starts authorization from UI stored configuration without environment values', function (): void {
    config()->set('services.google', array_fill_keys(['client_id', 'client_secret'], null));
    $settings = app(GoogleDriveSettings::class);
    $settings->client_id = 'ui-client-id';
    $settings->encrypted_client_secret = 'ui-client-secret';
    $settings->save();
    $this->actingAs(User::factory()->create());

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('setScopes')->once()->andReturnSelf();
    $provider->shouldReceive('with')->once()->andReturnSelf();
    $provider->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://accounts.google.test/authorize'));
    Socialite::shouldReceive('driver')->once()->with('google')->andReturnUsing(function () use ($provider): GoogleProvider {
        expect(config('services.google.client_id'))->toBe('ui-client-id')
            ->and(config('services.google.client_secret'))->toBe('ui-client-secret')
            ->and(config('services.google.redirect'))->toBe(route('google-drive.oauth.callback'));

        return $provider;
    });

    $this->get(route('google-drive.oauth.redirect'))
        ->assertRedirect('https://accounts.google.test/authorize');

    expect(app(GoogleDriveConfiguration::class)->isComplete())->toBeTrue();
});

it('returns to settings with a sanitized notification when OAuth cannot start', function (): void {
    $this->actingAs(User::factory()->create());
    Socialite::shouldReceive('driver')->once()->with('google')
        ->andThrow(new RuntimeException('raw OAuth provider configuration'));

    $this->get(route('google-drive.oauth.redirect'))
        ->assertRedirect(GoogleDriveSettingsPage::getUrl(isAbsolute: false))
        ->assertSessionHas('filament.notifications');
});

it('stores a successful callback refresh token encrypted and never renders or logs it', function (): void {
    $this->actingAs(User::factory()->create());
    Log::spy();

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->once()->andReturn(SocialiteUser::fake([
        'email' => 'owner@example.test',
        'refreshToken' => 'top-secret-refresh-token',
    ]));
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    $google = Mockery::mock(GoogleWorkspaceClient::class);
    $google->shouldReceive('createApplicationRoot')->once()->andReturn(
        new GoogleDriveObjectData('root-created', 'AssestMe', GoogleWorkspaceClient::FOLDER_MIME_TYPE, null),
    );
    app()->instance(GoogleWorkspaceClient::class, $google);

    $response = $this->get(route('google-drive.oauth.callback'))
        ->assertRedirect(GoogleDriveSettingsPage::getUrl(isAbsolute: false));

    $rawPayload = (string) DB::table('settings')
        ->where('group', 'google_drive')
        ->where('name', 'encrypted_refresh_token')
        ->value('payload');

    expect(app(GoogleDriveSettings::class)->google_account_email)->toBe('owner@example.test')
        ->and(app(GoogleDriveSettings::class)->encrypted_refresh_token)->toBe('top-secret-refresh-token')
        ->and(app(GoogleDriveSettings::class)->root_folder_id)->toBe('root-created')
        ->and(app(GoogleDriveSettings::class)->root_folder_name)->toBe('AssestMe')
        ->and(app(GoogleDriveSettings::class)->sync_enabled)->toBeTrue()
        ->and($rawPayload)->not->toContain('top-secret-refresh-token')
        ->and($response->getContent())->not->toContain('top-secret-refresh-token');

    Log::shouldNotHaveReceived('debug');
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('warning');
    Log::shouldNotHaveReceived('error');
});

it('keeps the account connected and synchronization disabled when root creation fails', function (): void {
    $this->actingAs(User::factory()->create());
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->once()->andReturn(SocialiteUser::fake([
        'email' => 'owner@example.test',
        'refreshToken' => 'refresh-token',
    ]));
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    $google = Mockery::mock(GoogleWorkspaceClient::class);
    $google->shouldReceive('createApplicationRoot')->once()->andThrow(new RuntimeException('raw provider detail'));
    app()->instance(GoogleWorkspaceClient::class, $google);

    $this->get(route('google-drive.oauth.callback'))
        ->assertRedirect(GoogleDriveSettingsPage::getUrl(isAbsolute: false))
        ->assertSessionHas('filament.notifications');

    $settings = app(GoogleDriveSettings::class);
    expect($settings->google_account_email)->toBe('owner@example.test')
        ->and($settings->encrypted_refresh_token)->toBe('refresh-token')
        ->and($settings->root_folder_id)->toBeNull()
        ->and($settings->sync_enabled)->toBeFalse();
});

it('preserves an existing connection when callback lacks a refresh token', function (): void {
    $this->actingAs(User::factory()->create());
    $settings = app(GoogleDriveSettings::class);
    $settings->google_account_email = 'existing@example.test';
    $settings->encrypted_refresh_token = 'existing-refresh-token';
    $settings->root_folder_id = 'existing-root';
    $settings->root_folder_name = 'Existing root';
    $settings->sync_enabled = true;
    $settings->save();

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->once()->andReturn(SocialiteUser::fake([
        'email' => 'new@example.test',
        'refreshToken' => null,
    ]));
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $this->get(route('google-drive.oauth.callback'))
        ->assertRedirect(GoogleDriveSettingsPage::getUrl(isAbsolute: false));

    $settings->refresh();
    expect($settings->google_account_email)->toBe('existing@example.test')
        ->and($settings->encrypted_refresh_token)->toBe('existing-refresh-token')
        ->and($settings->root_folder_id)->toBe('existing-root')
        ->and($settings->sync_enabled)->toBeTrue();
});

it('preserves an existing connection when Socialite rejects the callback', function (): void {
    $this->actingAs(User::factory()->create());
    $settings = app(GoogleDriveSettings::class);
    $settings->google_account_email = 'existing@example.test';
    $settings->encrypted_refresh_token = 'existing-refresh-token';
    $settings->save();

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->once()->andThrow(new RuntimeException('provider raw secret detail'));
    Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

    $this->get(route('google-drive.oauth.callback'))
        ->assertRedirect(GoogleDriveSettingsPage::getUrl(isAbsolute: false))
        ->assertSessionHas('filament.notifications');

    expect($settings->refresh()->encrypted_refresh_token)->toBe('existing-refresh-token');
});
