<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\AssessmentGoogleDriveSync;
use App\Settings\GoogleDriveSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('keeps Google Drive optional when installation credentials are absent', function (): void {
    expect(config('services.google.client_id'))->toBeNull()
        ->and(config('services.google.client_secret'))->toBeNull()
        ->and(array_keys((array) config('services.google')))->toBe(['client_id', 'client_secret']);

    $settings = app(GoogleDriveSettings::class);

    expect($settings->sync_enabled)->toBeTrue()
        ->and($settings->client_id)->toBeNull()
        ->and($settings->encrypted_client_secret)->toBeNull()
        ->and($settings->google_account_email)->toBeNull()
        ->and($settings->encrypted_refresh_token)->toBeNull()
        ->and($settings->root_folder_id)->toBeNull()
        ->and($settings->root_folder_name)->toBeNull();
});

it('encrypts Google application secrets in the settings repository', function (): void {
    $settings = app(GoogleDriveSettings::class);
    $settings->client_id = 'ui-client-id';
    $settings->encrypted_client_secret = 'ui-client-secret';
    $settings->save();

    $payloads = DB::table('settings')
        ->where('group', 'google_drive')
        ->whereIn('name', ['encrypted_client_secret'])
        ->pluck('payload', 'name');

    expect($payloads)->toHaveCount(1)
        ->and((string) $payloads['encrypted_client_secret'])->not->toContain('ui-client-secret')
        ->and(app(GoogleDriveSettings::class)->encrypted_client_secret)->toBe('ui-client-secret');
});

it('does not persist selector-only application settings', function (): void {
    $names = DB::table('settings')
        ->where('group', 'google_drive')
        ->pluck('name');

    expect($names)->not->toContain('redirect_uri')
        ->not->toContain('encrypted_picker_api_key')
        ->not->toContain('project_number');
});

it('encrypts the Google refresh token in the settings repository', function (): void {
    $settings = app(GoogleDriveSettings::class);
    $settings->google_account_email = 'admin@example.test';
    $settings->encrypted_refresh_token = 'refresh-secret-value';
    $settings->save();

    $payload = DB::table('settings')
        ->where('group', 'google_drive')
        ->where('name', 'encrypted_refresh_token')
        ->value('payload');

    expect($payload)->toBeString()
        ->and($payload)->not->toContain('refresh-secret-value')
        ->and(app(GoogleDriveSettings::class)->encrypted_refresh_token)->toBe('refresh-secret-value');
});

it('persists one retryable Google Drive sync state per assessment', function (): void {
    expect(Schema::hasTable('assessment_google_drive_syncs'))->toBeTrue();

    $assessment = Assessment::factory()->create();
    $state = AssessmentGoogleDriveSync::query()->create([
        'assessment_id' => $assessment->getKey(),
        'last_content_hash' => str_repeat('a', 64),
        'last_synced_at' => now(),
        'last_error' => 'Quota Google superata.',
        'last_error_at' => now(),
    ]);

    expect($assessment->fresh()->googleDriveSync?->is($state))->toBeTrue()
        ->and($state->last_synced_at?->timezone('UTC')->offset)->toBe(0)
        ->and($state->last_error)->toBe('Quota Google superata.');
});
