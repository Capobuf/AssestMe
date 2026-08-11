<?php

declare(strict_types=1);

use App\Data\GoogleDrive\GoogleDriveObjectData;
use App\Models\Assessment;
use App\Models\AssessmentGoogleDriveSync;
use App\Models\Finding;
use App\Services\GoogleDrive\GoogleDriveSyncService;
use App\Services\GoogleDrive\GoogleWorkspaceClient;
use App\Settings\GoogleDriveSettings;

it('realigns changed local content without deleting unrelated remote content', function (): void {
    config()->set('services.google', [
        'client_id' => 'client', 'client_secret' => 'secret',
    ]);
    $settings = app(GoogleDriveSettings::class);
    $settings->sync_enabled = true;
    $settings->google_account_email = 'admin@example.test';
    $settings->encrypted_refresh_token = 'refresh-secret';
    $settings->root_folder_id = 'root-one';
    $settings->root_folder_name = 'Root one';
    $settings->save();
    $assessment = Assessment::factory()->create(['title' => 'Versione iniziale']);
    Finding::factory()->for($assessment)->create(['title' => 'Finding locale autorevole']);

    $roots = [];
    $remoteFindingTitle = null;
    $google = Mockery::mock(GoogleWorkspaceClient::class);
    $google->shouldReceive('ensureFolder')->times(12)->andReturnUsing(
        static function (string $parentId) use (&$roots): GoogleDriveObjectData {
            if (str_starts_with($parentId, 'root-')) {
                $roots[] = $parentId;
            }

            return new GoogleDriveObjectData('folder', 'Folder', GoogleWorkspaceClient::FOLDER_MIME_TYPE, null);
        },
    );
    $google->shouldReceive('ensureSpreadsheet')->times(3)
        ->andReturn(new GoogleDriveObjectData('sheet', 'Sheet', GoogleWorkspaceClient::SPREADSHEET_MIME_TYPE, null));
    $google->shouldReceive('replaceSpreadsheet')->times(3)->andReturnUsing(
        static function (string $id, array $assessmentRows, array $findingRows) use (&$remoteFindingTitle): void {
            $remoteFindingTitle = $findingRows[1][3];
        },
    );
    app()->instance(GoogleWorkspaceClient::class, $google);

    $first = app(GoogleDriveSyncService::class)->syncAll();
    $unchanged = app(GoogleDriveSyncService::class)->syncAll();
    $remoteFindingTitle = 'Modifica remota manuale';
    $conflict = app(GoogleDriveSyncService::class)->syncAll(force: true);
    $assessment->update(['title' => 'Versione locale aggiornata']);
    $changed = app(GoogleDriveSyncService::class)->syncAll();
    expect($first->synchronized)->toBe(1)
        ->and($unchanged->skipped)->toBe(1)
        ->and($conflict->synchronized)->toBe(1)
        ->and($remoteFindingTitle)->toBe('Finding locale autorevole')
        ->and($changed->synchronized)->toBe(1)
        ->and($roots)->toBe(['root-one', 'root-one', 'root-one'])
        ->and(AssessmentGoogleDriveSync::query()->findOrFail($assessment->getKey())->last_error)->toBeNull()
        ->and(method_exists(GoogleWorkspaceClient::class, 'delete'))->toBeFalse();
});
