<?php

declare(strict_types=1);

use App\Data\GoogleDrive\GoogleDriveObjectData;
use App\Models\Assessment;
use App\Models\AssessmentGoogleDriveSync;
use App\Services\GoogleDrive\GoogleDriveSyncService;
use App\Services\GoogleDrive\GoogleWorkspaceClient;
use App\Settings\GoogleDriveSettings;
use Mockery\MockInterface;

beforeEach(function (): void {
    config()->set('services.google', [
        'client_id' => 'client', 'client_secret' => 'secret',
    ]);
    $settings = app(GoogleDriveSettings::class);
    $settings->sync_enabled = true;
    $settings->google_account_email = 'admin@example.test';
    $settings->encrypted_refresh_token = 'refresh-secret';
    $settings->root_folder_id = 'root-123';
    $settings->root_folder_name = 'AssestMe';
    $settings->save();
});

it('preserves the prior success, sanitizes provider failure, and clears the error on retry', function (): void {
    $assessment = Assessment::factory()->create();
    AssessmentGoogleDriveSync::query()->create([
        'assessment_id' => $assessment->getKey(),
        'last_content_hash' => str_repeat('a', 64),
        'last_synced_at' => now()->subDay(),
    ]);
    $failing = Mockery::mock(GoogleWorkspaceClient::class);
    $failing->shouldReceive('ensureFolder')->once()
        ->andThrow(new RuntimeException('raw-provider-payload refresh-secret'));
    app()->instance(GoogleWorkspaceClient::class, $failing);

    $failed = app(GoogleDriveSyncService::class)->syncAll(force: true);
    $state = AssessmentGoogleDriveSync::query()->findOrFail($assessment->getKey());
    expect($failed->failed)->toBe(1)
        ->and($state->last_content_hash)->toBe(str_repeat('a', 64))
        ->and($state->last_error)->not->toContain('raw-provider-payload', 'refresh-secret');

    app()->instance(GoogleWorkspaceClient::class, retryableGoogleWorkspaceMock());
    $retried = app(GoogleDriveSyncService::class)->syncAll(force: true);
    $state->refresh();

    expect($retried->synchronized)->toBe(1)
        ->and($retried->failed)->toBe(0)
        ->and($state->last_content_hash)->not->toBe(str_repeat('a', 64))
        ->and($state->last_error)->toBeNull()
        ->and($state->last_error_at)->toBeNull();
});

it('continues with later assessments after an isolated provider failure', function (): void {
    Assessment::factory()->count(2)->create();
    $folder = new GoogleDriveObjectData('folder', 'Folder', GoogleWorkspaceClient::FOLDER_MIME_TYPE, null);
    $sheet = new GoogleDriveObjectData('sheet', 'Sheet', GoogleWorkspaceClient::SPREADSHEET_MIME_TYPE, null);
    $calls = 0;
    $google = Mockery::mock(GoogleWorkspaceClient::class);
    $google->shouldReceive('ensureFolder')->andReturnUsing(
        static function () use (&$calls, $folder): GoogleDriveObjectData {
            if (++$calls === 1) {
                throw new RuntimeException('quota response');
            }

            return $folder;
        },
    );
    $google->shouldReceive('ensureSpreadsheet')->andReturn($sheet);
    $google->shouldReceive('replaceSpreadsheet');
    app()->instance(GoogleWorkspaceClient::class, $google);

    $result = app(GoogleDriveSyncService::class)->syncAll(force: true);

    expect($result->evaluated)->toBe(2)
        ->and($result->failed)->toBe(1)
        ->and($result->synchronized)->toBe(1)
        ->and(AssessmentGoogleDriveSync::query()->whereNotNull('last_synced_at')->count())->toBe(1);
});

function retryableGoogleWorkspaceMock(): MockInterface
{
    $folder = new GoogleDriveObjectData('folder', 'Folder', GoogleWorkspaceClient::FOLDER_MIME_TYPE, null);
    $sheet = new GoogleDriveObjectData('sheet', 'Sheet', GoogleWorkspaceClient::SPREADSHEET_MIME_TYPE, null);
    $google = Mockery::mock(GoogleWorkspaceClient::class);
    $google->shouldReceive('ensureFolder')->byDefault()->andReturn($folder);
    $google->shouldReceive('ensureSpreadsheet')->byDefault()->andReturn($sheet);
    $google->shouldReceive('replaceSpreadsheet')->byDefault();

    return $google;
}
