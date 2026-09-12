<?php

declare(strict_types=1);

use App\Data\GoogleDrive\GoogleDriveObjectData;
use App\Models\Assessment;
use App\Models\AssessmentGoogleDriveSync;
use App\Services\GoogleDrive\BuildGoogleDriveAssessmentSnapshot;
use App\Services\GoogleDrive\GoogleWorkspaceClient;
use App\Settings\GoogleDriveSettings;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;

beforeEach(function (): void {
    config()->set('services.google', [
        'client_id' => 'client',
        'client_secret' => 'secret',
    ]);
    $settings = app(GoogleDriveSettings::class);
    $settings->sync_enabled = true;
    $settings->google_account_email = 'admin@example.test';
    $settings->encrypted_refresh_token = 'refresh-secret';
    $settings->root_folder_id = 'root-123';
    $settings->root_folder_name = 'AssestMe';
    $settings->save();
});

it('is a zero-call no-op while automatic synchronization is disabled', function (): void {
    $settings = app(GoogleDriveSettings::class);
    $settings->sync_enabled = false;
    $settings->save();
    Assessment::factory()->create();
    app()->instance(GoogleWorkspaceClient::class, Mockery::mock(GoogleWorkspaceClient::class)->shouldIgnoreMissing(false));

    expect(Artisan::call('assestme:google-drive-sync'))->toBe(0)
        ->and(Artisan::output())->toContain('Valutati: 0');
});

it('skips an unchanged assessment without contacting Google', function (): void {
    $assessment = Assessment::factory()->create();
    $hash = app(BuildGoogleDriveAssessmentSnapshot::class)->build($assessment, 'root-123')->contentHash;
    AssessmentGoogleDriveSync::query()->create([
        'assessment_id' => $assessment->getKey(),
        'last_content_hash' => $hash,
        'last_synced_at' => now(),
    ]);
    app()->instance(GoogleWorkspaceClient::class, Mockery::mock(GoogleWorkspaceClient::class)->shouldIgnoreMissing(false));

    expect(Artisan::call('assestme:google-drive-sync'))->toBe(0)
        ->and(Artisan::output())->toContain('Saltati: 1');
});

it('synchronizes changed assessments in chunks no larger than fifty', function (): void {
    Assessment::factory()->count(51)->create();
    $google = successfulGoogleWorkspaceMock();
    $google->shouldReceive('ensureFolder')->times(204)
        ->andReturn(new GoogleDriveObjectData('folder', 'Folder', GoogleWorkspaceClient::FOLDER_MIME_TYPE, null));
    $google->shouldReceive('ensureSpreadsheet')->times(51)
        ->andReturn(new GoogleDriveObjectData('sheet', 'Sheet', GoogleWorkspaceClient::SPREADSHEET_MIME_TYPE, null));
    $google->shouldReceive('replaceSpreadsheet')->times(51);
    app()->instance(GoogleWorkspaceClient::class, $google);

    $selects = [];
    DB::listen(static function ($query) use (&$selects): void {
        if (preg_match('/from ["`]assessments["`]/', $query->sql) === 1) {
            $selects[] = $query->sql;
        }
    });

    expect(Artisan::call('assestme:google-drive-sync'))->toBe(0)
        ->and(Artisan::output())->toContain('Sincronizzati: 51')
        ->and(collect($selects)->contains(static fn (string $sql): bool => str_contains($sql, 'limit 50')))->toBeTrue();
});

it('force bypasses only the unchanged hash comparison', function (): void {
    $assessment = Assessment::factory()->create();
    $hash = app(BuildGoogleDriveAssessmentSnapshot::class)->build($assessment, 'root-123')->contentHash;
    AssessmentGoogleDriveSync::query()->create([
        'assessment_id' => $assessment->getKey(),
        'last_content_hash' => $hash,
        'last_synced_at' => now(),
    ]);
    $google = successfulGoogleWorkspaceMock();
    $google->shouldReceive('ensureFolder')->times(4)
        ->andReturn(new GoogleDriveObjectData('folder', 'Folder', GoogleWorkspaceClient::FOLDER_MIME_TYPE, null));
    $google->shouldReceive('ensureSpreadsheet')->once()
        ->andReturn(new GoogleDriveObjectData('sheet', 'Sheet', GoogleWorkspaceClient::SPREADSHEET_MIME_TYPE, null));
    $google->shouldReceive('replaceSpreadsheet')->once();
    app()->instance(GoogleWorkspaceClient::class, $google);

    expect(Artisan::call('assestme:google-drive-sync', ['--force' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('Sincronizzati: 1');
});

function successfulGoogleWorkspaceMock(): MockInterface
{
    $google = Mockery::mock(GoogleWorkspaceClient::class);
    $google->shouldReceive('ensureFolder')->byDefault()
        ->andReturn(new GoogleDriveObjectData('folder', 'Folder', GoogleWorkspaceClient::FOLDER_MIME_TYPE, null));
    $google->shouldReceive('ensureSpreadsheet')->byDefault()
        ->andReturn(new GoogleDriveObjectData('sheet', 'Sheet', GoogleWorkspaceClient::SPREADSHEET_MIME_TYPE, null));
    $google->shouldReceive('replaceSpreadsheet')->byDefault();

    return $google;
}
