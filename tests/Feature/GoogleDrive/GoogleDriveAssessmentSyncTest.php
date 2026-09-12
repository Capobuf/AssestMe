<?php

declare(strict_types=1);

use App\Data\GoogleDrive\GoogleDriveObjectData;
use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\EvidenceType;
use App\Enums\GeneratedReportFormat;
use App\Models\Assessment;
use App\Models\Finding;
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

it('projects one complete assessment without omitting documents or evidence', function (): void {
    $assessment = completeGoogleDriveAssessment();
    $clientPrefix = sprintf('C-%06d', $assessment->client_id);
    $assessmentPrefix = sprintf('A-%06d', $assessment->getKey());
    $google = Mockery::mock(GoogleWorkspaceClient::class);
    $google->shouldReceive('ensureFolder')->once()->with('root-123', $clientPrefix, Mockery::pattern('/^'.preg_quote($clientPrefix, '/').' - /'))
        ->andReturn(driveObject('client-folder', 'Client'));
    $google->shouldReceive('ensureFolder')->once()->with('client-folder', $assessmentPrefix, Mockery::pattern('/^'.preg_quote($assessmentPrefix, '/').' - /'))
        ->andReturn(driveObject('assessment-folder', 'Assessment'));
    $google->shouldReceive('ensureFolder')->once()->with('assessment-folder', 'Documenti', 'Documenti')
        ->andReturn(driveObject('documents-folder', 'Documenti'));
    $google->shouldReceive('ensureFolder')->once()->with('assessment-folder', 'Evidenze', 'Evidenze')
        ->andReturn(driveObject('evidence-folder', 'Evidenze'));
    $google->shouldReceive('uploadFile')->twice()->withArgs(
        static fn (string $parent, string $prefix, string $name, string $bytes, string $mime): bool => $parent === 'documents-folder' && str_starts_with($prefix, 'R-') && $bytes !== '' && $mime !== '',
    )->andReturn(driveObject('report-file', 'Report', 'application/octet-stream', 'https://drive.test/report'));
    $google->shouldReceive('uploadFile')->once()->withArgs(
        static fn (string $parent, string $prefix, string $name, string $bytes, string $mime): bool => $parent === 'evidence-folder' && str_starts_with($prefix, 'F-') && str_contains($prefix, ' - E-')
                && $bytes === 'evidence bytes' && $mime === 'text/plain',
    )->andReturn(driveObject('evidence-file', 'Evidence', 'text/plain', 'https://drive.test/evidence'));
    $google->shouldReceive('ensureSpreadsheet')->once()
        ->with('assessment-folder', 'Findings - '.$assessmentPrefix, 'Findings - '.$assessmentPrefix)
        ->andReturn(driveObject('sheet-123', 'Findings', GoogleWorkspaceClient::SPREADSHEET_MIME_TYPE));
    $google->shouldReceive('replaceSpreadsheet')->once()->withArgs(
        static function (string $id, array $assessmentRows, array $findingRows, array $solutionRows, array $evidenceRows): bool {
            return $id === 'sheet-123'
                && count($assessmentRows) === 13
                && count($findingRows) === 3
                && count($solutionRows) === 3
                && count($evidenceRows) === 3
                && $evidenceRows[1][6] === 'https://drive.test/evidence'
                && $evidenceRows[2][6] === 'https://example.test/reference';
        },
    );

    app()->instance(GoogleWorkspaceClient::class, $google);
    $outcome = app(GoogleDriveSyncService::class)->syncAssessment($assessment, force: true);

    expect($outcome)->toBe('synchronized')
        ->and($assessment->googleDriveSync()->first()?->last_content_hash)->toMatch('/^[a-f0-9]{64}$/')
        ->and($assessment->googleDriveSync()->first()?->last_synced_at)->not->toBeNull()
        ->and($assessment->googleDriveSync()->first()?->last_error)->toBeNull();
});

function completeGoogleDriveAssessment(): Assessment
{
    $assessment = Assessment::factory()->create([
        'title' => 'Assessment completo',
        'assessment_date' => '2026-08-11',
    ]);
    $firstFinding = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $secondFinding = Finding::factory()->for($assessment)->create(['sort_order' => 2]);
    foreach ([$firstFinding, $secondFinding] as $index => $finding) {
        $solution = $finding->solutions()->create([
            'external_key' => 'solution-complete-'.$index,
            'title' => 'Soluzione completa '.($index + 1),
            'description' => 'Descrizione',
            'estimate_type' => EstimateType::Approximate,
            'amount_min' => '100.00',
            'currency_code' => 'EUR',
            'billing_frequency' => BillingFrequency::OneOff,
            'sort_order' => 1,
        ]);
        $finding->update(['recommended_solution_id' => $solution->getKey()]);
    }

    Storage::disk('local')->put('evidence/evidence.txt', 'evidence bytes');
    $firstFinding->evidences()->create([
        'type' => EvidenceType::File,
        'title' => 'Verbale',
        'file_path' => 'evidence/evidence.txt',
        'original_filename' => 'evidence.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => strlen('evidence bytes'),
        'sha256' => hash('sha256', 'evidence bytes'),
        'sort_order' => 1,
    ]);
    $secondFinding->evidences()->create([
        'type' => EvidenceType::Url,
        'title' => 'URL',
        'url' => 'https://example.test/reference',
        'sort_order' => 2,
    ]);

    foreach ([GeneratedReportFormat::Pdf, GeneratedReportFormat::Xlsx] as $format) {
        $bytes = $format->value.' bytes';
        $path = 'reports/'.$format->value.'.'.$format->value;
        Storage::disk('local')->put($path, $bytes);
        $assessment->generatedReports()->create([
            'format' => $format,
            'version' => 1,
            'file_path' => $path,
            'file_name' => basename($path),
            'file_size_bytes' => strlen($bytes),
            'file_sha256' => hash('sha256', $bytes),
            'payload_sha256' => hash('sha256', '{}'),
            'payload_snapshot' => [],
            'settings_snapshot' => [],
            'generated_at' => now(),
        ]);
    }

    return $assessment;
}

function driveObject(
    string $id,
    string $name,
    string $mime = GoogleWorkspaceClient::FOLDER_MIME_TYPE,
    ?string $link = null,
): GoogleDriveObjectData {
    return new GoogleDriveObjectData($id, $name, $mime, $link);
}
