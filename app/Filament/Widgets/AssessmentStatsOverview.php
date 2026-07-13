<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AssessmentStatus;
use App\Enums\DeletionOperationStatus;
use App\Enums\FindingPriority;
use App\Enums\FindingStatus;
use App\Models\Assessment;
use App\Models\DeletionOperation;
use App\Models\Finding;
use App\Services\Backups\LatestBackupStatus;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class AssessmentStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $latestBackup = app(LatestBackupStatus::class)->latestSuccessfulAt();
        $backupValue = $latestBackup?->setTimezone('Europe/Rome')->format('d/m/Y H:i')
            ?? __('assestme.dashboard.backup_never');

        return [
            Stat::make(
                __('assestme.dashboard.draft_assessments'),
                Assessment::query()->where('status', AssessmentStatus::Draft)->count(),
            ),
            Stat::make(
                __('assestme.dashboard.completed_assessments'),
                Assessment::query()->where('status', AssessmentStatus::Completed)->count(),
            ),
            Stat::make(
                __('assestme.dashboard.open_findings'),
                Finding::query()->where('status', FindingStatus::Open)->count(),
            ),
            Stat::make(
                __('assestme.dashboard.urgent_findings'),
                Finding::query()
                    ->where('include_in_report', true)
                    ->whereIn('priority', [FindingPriority::High, FindingPriority::Critical])
                    ->count(),
            ),
            Stat::make(
                __('assestme.dashboard.cleanup_failures'),
                DeletionOperation::query()->where('status', DeletionOperationStatus::CleanupFailed)->count(),
            ),
            Stat::make(__('assestme.dashboard.last_backup'), $backupValue),
        ];
    }
}
