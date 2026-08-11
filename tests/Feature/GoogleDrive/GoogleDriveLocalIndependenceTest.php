<?php

declare(strict_types=1);

use App\Actions\Reports\GenerateAssessmentPdf;
use App\Actions\Reports\GenerateAssessmentWorkbook;
use App\Models\Assessment;
use App\Services\GoogleDrive\GoogleDriveSyncService;
use App\Services\GoogleDrive\GoogleWorkspaceClient;
use App\Settings\GoogleDriveSettings;
use Database\Seeders\MilestoneOneSeeder;
use Database\Seeders\MilestoneTwoSeeder;
use Illuminate\Support\Facades\Storage;

it('keeps local saves and real PDF/XLSX generation independent from a Drive failure', function (): void {
    $weasyPrintBinary = (string) config('laravel-pdf.weasyprint.binary', '/usr/bin/weasyprint');
    if (! is_executable($weasyPrintBinary)) {
        $this->markTestSkipped('WeasyPrint binary is not executable in this host runtime.');
    }

    $this->seed(MilestoneOneSeeder::class);
    $this->seed(MilestoneTwoSeeder::class);
    Storage::fake('local');
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
    $assessment = Assessment::factory()->create(['title' => 'Prima del guasto']);
    $google = Mockery::mock(GoogleWorkspaceClient::class);
    $google->shouldReceive('ensureFolder')->once()->andThrow(new RuntimeException('timeout'));
    app()->instance(GoogleWorkspaceClient::class, $google);

    expect(app(GoogleDriveSyncService::class)->syncAll(force: true)->failed)->toBe(1);

    $assessment->update(['title' => 'Salvataggio locale riuscito']);
    $xlsx = app(GenerateAssessmentWorkbook::class)($assessment->fresh());
    $pdf = app(GenerateAssessmentPdf::class)($assessment->fresh());

    expect($assessment->fresh()->title)->toBe('Salvataggio locale riuscito')
        ->and(Storage::disk('local')->exists($xlsx->file_path))->toBeTrue()
        ->and(Storage::disk('local')->exists($pdf->file_path))->toBeTrue()
        ->and(Storage::disk('local')->get($pdf->file_path))->toStartWith('%PDF-');
});
