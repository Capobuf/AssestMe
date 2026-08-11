<?php

declare(strict_types=1);

use App\Services\GoogleDrive\GoogleDriveConfiguration;
use App\Services\GoogleDrive\GoogleDriveTokenService;
use App\Settings\GoogleDriveSettings;
use Google\Client;

beforeEach(function (): void {
    config()->set('services.google', [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ]);
});

it('returns a temporary access token from the encrypted refresh token', function (): void {
    $settings = app(GoogleDriveSettings::class);
    $settings->encrypted_refresh_token = 'refresh-token';
    $settings->save();

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setClientId')->once()->with('client-id');
    $client->shouldReceive('setClientSecret')->once()->with('client-secret');
    $client->shouldReceive('setScopes')->once();
    $client->shouldReceive('fetchAccessTokenWithRefreshToken')->once()->with('refresh-token')->andReturn([
        'access_token' => 'temporary-access-token',
        'expires_in' => 3600,
        'created' => 123456,
    ]);

    $token = (new GoogleDriveTokenService($client, $settings, app(GoogleDriveConfiguration::class)))->accessToken();

    expect($token->token)->toBe('temporary-access-token')
        ->and($token->expiresIn)->toBe(3600);
});

it('fails explicitly when Google does not return an access token', function (): void {
    $settings = app(GoogleDriveSettings::class);
    $settings->encrypted_refresh_token = 'refresh-token';
    $settings->save();

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setClientId');
    $client->shouldReceive('setClientSecret');
    $client->shouldReceive('setScopes');
    $client->shouldReceive('fetchAccessTokenWithRefreshToken')->once()->andReturn([
        'error' => 'invalid_grant',
        'error_description' => 'raw provider detail',
    ]);

    expect(fn () => (new GoogleDriveTokenService($client, $settings, app(GoogleDriveConfiguration::class)))->accessToken())
        ->toThrow(RuntimeException::class, 'Autorizzazione Google revocata. Ricollega l’account.');
});
