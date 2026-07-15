<?php

declare(strict_types=1);

use App\Actions\Reports\DeleteGeneratedReport;
use App\Enums\DeletionOperationStatus;
use App\Enums\GeneratedReportFormat;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Models\Assessment;
use App\Models\DeletionOperation;
use App\Models\GeneratedReport;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->privateRoot = storage_path('framework/testing/generated-report-deletion-'.bin2hex(random_bytes(6)));
    File::ensureDirectoryExists($this->privateRoot);
    config()->set('assestme.backup.private_storage_path', $this->privateRoot);
    config()->set('assestme.deletion.trash_root', $this->privateRoot.DIRECTORY_SEPARATOR.'.trash');
});

afterEach(function (): void {
    File::deleteDirectory($this->privateRoot);
});

it('permanently deletes an immutable generated report through staged recovery', function (): void {
    $assessment = Assessment::factory()->create();
    $report = createStoredGeneratedReport($assessment, $this->privateRoot);
    $absolutePath = $this->privateRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $report->file_path);

    expect(fn () => $report->delete())->toThrow(LogicException::class);

    $operation = app(DeleteGeneratedReport::class)->handle($report);

    expect($operation->status)->toBe(DeletionOperationStatus::Cleaned)
        ->and($operation->entity_type)->toBe(GeneratedReport::class)
        ->and($operation->entity_id)->toBe($report->id)
        ->and($operation->manifest)->toHaveCount(1)
        ->and($operation->manifest[0]['source'])->toBe($report->file_path)
        ->and($operation->manifest[0]['sha256'])->toBe($report->file_sha256)
        ->and(GeneratedReport::query()->find($report->id))->toBeNull()
        ->and(File::exists($absolutePath))->toBeFalse();
});

it('keeps the immutable row and shows an error when its file cannot be staged', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $report = createStoredGeneratedReport($assessment, $this->privateRoot, writeFile: false);
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->assertSee($report->file_name)
        ->mountAction(TestAction::make('deleteGeneratedReport')->arguments(['report' => $report->id]))
        ->assertActionMounted(TestAction::make('deleteGeneratedReport'))
        ->assertMountedActionModalSee(__('assestme.reports.delete.description'))
        ->callMountedAction()
        ->assertNotified(__('assestme.reports.delete.failed'));

    expect(GeneratedReport::query()->find($report->id))->not->toBeNull()
        ->and(DeletionOperation::query()->count())->toBe(0);
});

it('warns instead of reporting success when committed trash cleanup fails', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $report = createStoredGeneratedReport($assessment, $this->privateRoot);
    $files = Mockery::mock(Filesystem::class)->makePartial();
    $files->shouldReceive('deleteDirectory')->once()->andReturnFalse();
    app()->instance(Filesystem::class, $files);
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->callAction(TestAction::make('deleteGeneratedReport')->arguments(['report' => $report->id]))
        ->assertNotified(__('assestme.reports.delete.cleanup_pending'));

    $operation = DeletionOperation::query()->sole();

    expect($operation->status)->toBe(DeletionOperationStatus::CleanupFailed)
        ->and(GeneratedReport::query()->find($report->id))->toBeNull()
        ->and(File::exists($this->privateRoot.DIRECTORY_SEPARATOR.$operation->manifest[0]['trash']))->toBeTrue();
});

function createStoredGeneratedReport(Assessment $assessment, string $privateRoot, bool $writeFile = true): GeneratedReport
{
    $contents = '%PDF-1.4 generated report deletion fixture';
    $relativePath = "reports/{$assessment->getKey()}/".fake()->uuid().'.pdf';
    $absolutePath = $privateRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if ($writeFile) {
        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, $contents);
    }

    return GeneratedReport::query()->create([
        'assessment_id' => $assessment->getKey(),
        'format' => GeneratedReportFormat::Pdf,
        'version' => 1,
        'file_path' => $relativePath,
        'file_name' => 'AssestMe_cliente_2026-07-15_v01.pdf',
        'file_size_bytes' => strlen($contents),
        'file_sha256' => hash('sha256', $contents),
        'payload_sha256' => hash('sha256', '{}'),
        'payload_snapshot' => [],
        'settings_snapshot' => [],
        'generated_at' => now(),
    ]);
}
