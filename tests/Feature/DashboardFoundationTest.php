<?php

declare(strict_types=1);

use App\Actions\Operations\RecordOperationalCheck;
use App\Enums\AssessmentStatus;
use App\Enums\DeletionOperationStatus;
use App\Enums\FindingStatus;
use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use App\Filament\Widgets\AssessmentStatsOverview;
use App\Filament\Widgets\LatestAssessments;
use App\Models\Assessment;
use App\Models\DeletionOperation;
use App\Models\Finding;
use App\Models\PriorityLevel;
use App\Models\User;
use App\Services\Backups\LatestBackupStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\MilestoneOneSeeder;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

it('shows actionable dashboard counts and only the latest five assessments', function (): void {
    $administrator = User::factory()->create();
    $this->actingAs($administrator);
    $this->seed(MilestoneOneSeeder::class);

    Assessment::factory()->count(6)->sequence(
        fn ($sequence): array => [
            'title' => 'Assessment '.($sequence->index + 1),
            'status' => $sequence->index === 0 ? AssessmentStatus::Completed : AssessmentStatus::Draft,
            'updated_at' => now()->subMinutes($sequence->index),
        ],
    )->create();

    $assessment = Assessment::query()->latest('updated_at')->firstOrFail();
    Finding::factory()->for($assessment)->create([
        'status' => FindingStatus::Open,
        'priority_level_id' => PriorityLevel::query()->where('code', 'critical')->value('id'),
        'include_in_report' => true,
    ]);
    Finding::factory()->for($assessment)->create([
        'status' => FindingStatus::Resolved,
        'priority_level_id' => PriorityLevel::query()->where('code', 'high')->value('id'),
        'include_in_report' => false,
    ]);
    DeletionOperation::query()->create([
        'uuid' => fake()->uuid(),
        'entity_type' => Assessment::class,
        'entity_id' => $assessment->id,
        'status' => DeletionOperationStatus::CleanupFailed,
        'trash_path' => 'trash/test',
        'manifest' => [],
        'error_text' => 'Cleanup failed.',
    ]);

    Livewire::test(AssessmentStatsOverview::class)
        ->assertSee(__('assestme.dashboard.draft_assessments'))
        ->assertSee(__('assestme.dashboard.completed_assessments'))
        ->assertSee(__('assestme.dashboard.open_findings'))
        ->assertSee(__('assestme.dashboard.cleanup_failures'));

    Livewire::test(LatestAssessments::class)
        ->assertSee('Assessment 1')
        ->assertSee('Assessment 5')
        ->assertDontSee('Assessment 6');
});

it('reports the newest managed backup without treating unrelated archives as successful', function (): void {
    $root = storage_path('framework/testing/dashboard-backups-'.bin2hex(random_bytes(6)));
    File::ensureDirectoryExists($root);
    config()->set('assestme.backup.root', $root);

    try {
        $older = $root.DIRECTORY_SEPARATOR.'assestme-20260712-023000.tar.gz';
        $newer = $root.DIRECTORY_SEPARATOR.'assestme-20260713-023000.tar.gz';
        $unrelated = $root.DIRECTORY_SEPARATOR.'manual.tar.gz';
        File::put($older, 'older');
        File::put($newer, 'newer');
        File::put($unrelated, 'unrelated');
        touch($older, 100);
        touch($newer, 200);
        touch($unrelated, 300);

        $status = app(LatestBackupStatus::class)->latestSuccessfulAt();

        expect($status)->toBeInstanceOf(CarbonImmutable::class)
            ->and($status?->getTimestamp())->toBe(200);
    } finally {
        File::deleteDirectory($root);
    }
});

it('shows persisted backup and integrity failures without exposing technical error text', function (): void {
    $administrator = User::factory()->create();
    $this->actingAs($administrator);
    $failedAt = CarbonImmutable::parse('2026-07-17 08:15:00', 'UTC');
    $root = storage_path('framework/testing/dashboard-operations-'.bin2hex(random_bytes(6)));
    File::ensureDirectoryExists($root);
    config()->set('assestme.backup.private_storage_path', $root);

    try {
        $record = app(RecordOperationalCheck::class);
        $record(
            OperationalCheckType::Backup,
            OperationalCheckStatus::Failed,
            'Sensitive backup path failed.',
            $failedAt,
        );
        $record(
            OperationalCheckType::DatabaseIntegrity,
            OperationalCheckStatus::Failed,
            'database disk image is malformed',
            $failedAt,
        );

        Livewire::test(AssessmentStatsOverview::class)
            ->assertSee(__('assestme.dashboard.backup_failed', ['date' => '17/07/2026 10:15']))
            ->assertSee(__('assestme.dashboard.database_integrity'))
            ->assertSee(__('assestme.dashboard.integrity_failed', ['date' => '17/07/2026 10:15']))
            ->assertSee(__('assestme.dashboard.integrity_failure_help'))
            ->assertDontSee('Sensitive backup path failed.')
            ->assertDontSee('database disk image is malformed');
    } finally {
        File::deleteDirectory($root);
    }
});

it('casts deletion operation status and manifest deterministically', function (): void {
    $operation = DeletionOperation::query()->create([
        'uuid' => fake()->uuid(),
        'entity_type' => Assessment::class,
        'entity_id' => 123,
        'status' => DeletionOperationStatus::Staged,
        'trash_path' => 'trash/example',
        'manifest' => [
            ['source' => 'evidence/example.png', 'trash' => 'trash/example/example.png'],
        ],
    ])->fresh();

    expect($operation?->status)->toBe(DeletionOperationStatus::Staged)
        ->and($operation?->manifest)->toBe([
            ['source' => 'evidence/example.png', 'trash' => 'trash/example/example.png'],
        ]);
});
