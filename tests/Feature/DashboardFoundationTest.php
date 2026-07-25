<?php

declare(strict_types=1);

use App\Actions\Operations\RecordOperationalCheck;
use App\Enums\AssessmentStatus;
use App\Enums\DeletionOperationStatus;
use App\Enums\FindingStatus;
use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use App\Filament\Pages\BackupSettingsPage;
use App\Filament\Widgets\ApplicationStatus;
use App\Filament\Widgets\AssessmentStatsOverview;
use App\Filament\Widgets\LatestAssessments;
use App\Filament\Widgets\UrgentFindings;
use App\Models\Assessment;
use App\Models\DeletionOperation;
use App\Models\Finding;
use App\Models\PriorityLevel;
use App\Models\User;
use App\Services\Backups\LatestBackupStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\MilestoneOneSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

it('shows actionable dashboard counts and only the latest five assessments', function (): void {
    $administrator = User::factory()->create();
    $this->actingAs($administrator);
    $this->seed(MilestoneOneSeeder::class);

    $assessments = Assessment::factory()->count(6)->sequence(
        fn ($sequence): array => [
            'title' => 'Assessment '.($sequence->index + 1),
            'status' => $sequence->index === 0 ? AssessmentStatus::Completed : AssessmentStatus::Draft,
            'assessment_date' => today()->subDays($sequence->index),
            'updated_at' => now()->subMinutes($sequence->index),
        ],
    )->create();
    $assessments->each(function (Assessment $assessment, int $index): void {
        $assessment->client->update(['trade_name' => 'Azienda '.($index + 1)]);
    });

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
        ->assertSee(__('assestme.dashboard.urgent_findings'))
        ->assertDontSee(__('assestme.dashboard.cleanup_failures'));

    Livewire::test(LatestAssessments::class)
        ->assertSee('Azienda 1')
        ->assertSee('Azienda 5')
        ->assertDontSee('Azienda 6');

    Livewire::test(UrgentFindings::class)
        ->assertSee(__('assestme.dashboard.urgent_findings_list'))
        ->assertSee($assessment->findings()->where('status', FindingStatus::Open)->sole()->title);

    Livewire::test(ApplicationStatus::class)
        ->assertSee(__('assestme.dashboard.application_status'))
        ->assertSee(__('assestme.dashboard.cleanup_failures'))
        ->assertSee(__('assestme.dashboard.cleanup_pending_count', ['count' => 1]))
        ->assertSee(__('assestme.dashboard.check'))
        ->assertSee(__('assestme.dashboard.condition'))
        ->assertSee(__('assestme.dashboard.last_event'))
        ->assertSee(__('assestme.dashboard.details'))
        ->assertSeeHtml('class="assestme-application-status__table"')
        ->assertSeeHtml('class="assestme-application-status__condition-content"')
        ->assertSee(__('assestme.backups.actions.manage'))
        ->assertSeeHtml('href="'.BackupSettingsPage::getUrl().'"');
});

it('does not register the account welcome widget on the dashboard', function (): void {
    $widgets = array_values(Filament::getPanel('admin')->getWidgets());

    expect($widgets)
        ->toContain(
            AssessmentStatsOverview::class,
            LatestAssessments::class,
            UrgentFindings::class,
            ApplicationStatus::class,
        )
        ->not->toContain(AccountWidget::class);
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

        Livewire::test(ApplicationStatus::class)
            ->assertSee(__('assestme.dashboard.backup_failed_short'))
            ->assertSee(__('assestme.dashboard.database_integrity'))
            ->assertSee('17/07/2026 10:15')
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
