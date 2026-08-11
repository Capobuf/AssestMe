<?php

declare(strict_types=1);

use App\Models\AssessmentGoogleDriveSync;
use App\Services\GoogleDrive\GoogleDriveSyncService;
use App\Services\GoogleDrive\GoogleWorkspaceClient;
use App\Settings\GoogleDriveSettings;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $settings = app(GoogleDriveSettings::class);
    $settings->sync_enabled = true;
    $settings->google_account_email = 'admin@example.test';
    $settings->encrypted_refresh_token = 'refresh-secret';
    $settings->root_folder_id = 'root-123';
    $settings->root_folder_name = 'AssestMe';
    $settings->save();
});

it('fails before any Google call when a required local file is missing', function (): void {
    $assessment = completeGoogleDriveAssessment();
    Storage::disk('local')->delete('evidence/evidence.txt');
    app()->instance(GoogleWorkspaceClient::class, Mockery::mock(GoogleWorkspaceClient::class)->shouldIgnoreMissing(false));

    expect(fn () => app(GoogleDriveSyncService::class)->syncAssessment($assessment, force: true))
        ->toThrow(RuntimeException::class, 'file locale');

    $state = AssessmentGoogleDriveSync::query()->findOrFail($assessment->getKey());
    expect($state->last_content_hash)->toBeNull()
        ->and($state->last_error)->toContain('file locale');
});

it('fails on local size or hash mismatch without advancing prior success', function (string $field, int|string $value): void {
    $assessment = completeGoogleDriveAssessment();
    $evidence = $assessment->findings()->firstOrFail()->evidences()->whereNotNull('file_path')->firstOrFail();
    $evidence->update([$field => $value]);
    AssessmentGoogleDriveSync::query()->create([
        'assessment_id' => $assessment->getKey(),
        'last_content_hash' => str_repeat('a', 64),
        'last_synced_at' => now()->subHour(),
    ]);
    app()->instance(GoogleWorkspaceClient::class, Mockery::mock(GoogleWorkspaceClient::class)->shouldIgnoreMissing(false));

    expect(fn () => app(GoogleDriveSyncService::class)->syncAssessment($assessment, force: true))
        ->toThrow(RuntimeException::class, 'integrità');

    $state = AssessmentGoogleDriveSync::query()->findOrFail($assessment->getKey());
    expect($state->last_content_hash)->toBe(str_repeat('a', 64))
        ->and($state->last_error)->toContain('integrità');
})->with([
    'size' => ['size_bytes', 999],
    'hash' => ['sha256', str_repeat('b', 64)],
]);
