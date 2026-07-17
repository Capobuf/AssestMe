<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Data\Operations\OperationalCheckResult;
use App\Enums\AssessmentStatus;
use App\Enums\DeletionOperationStatus;
use App\Enums\FindingStatus;
use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use App\Models\Assessment;
use App\Models\DeletionOperation;
use App\Models\Finding;
use App\Services\Backups\LatestBackupStatus;
use App\Services\Operations\OperationalCheckStore;
use Carbon\CarbonImmutable;
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
        $operationalChecks = app(OperationalCheckStore::class);
        $backupCheck = $operationalChecks->find(OperationalCheckType::Backup);
        $integrityCheck = $operationalChecks->find(OperationalCheckType::DatabaseIntegrity);
        $backupStat = Stat::make(__('assestme.dashboard.last_backup'), $backupValue);

        if ($backupCheck?->status === OperationalCheckStatus::Failed) {
            $backupStat
                ->description(__('assestme.dashboard.backup_failed', [
                    'date' => $this->formatRome($backupCheck->lastFailedAt),
                ]))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger');
        }

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
                    ->whereHas('priorityLevel', fn ($query) => $query->whereIn('code', ['high', 'critical']))
                    ->count(),
            ),
            Stat::make(
                __('assestme.dashboard.cleanup_failures'),
                DeletionOperation::query()->where('status', DeletionOperationStatus::CleanupFailed)->count(),
            ),
            $backupStat,
            $this->integrityStat($integrityCheck),
        ];
    }

    private function integrityStat(?OperationalCheckResult $check): Stat
    {
        if ($check === null) {
            return Stat::make(
                __('assestme.dashboard.database_integrity'),
                __('assestme.dashboard.integrity_never'),
            );
        }

        if ($check->status === OperationalCheckStatus::Failed) {
            return Stat::make(
                __('assestme.dashboard.database_integrity'),
                __('assestme.dashboard.integrity_failed', [
                    'date' => $this->formatRome($check->lastFailedAt),
                ]),
            )
                ->description(__('assestme.dashboard.integrity_failure_help'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger');
        }

        return Stat::make(
            __('assestme.dashboard.database_integrity'),
            __('assestme.dashboard.integrity_ok', [
                'date' => $this->formatRome($check->lastSucceededAt),
            ]),
        )->color('success');
    }

    private function formatRome(?CarbonImmutable $date): string
    {
        return $date?->setTimezone('Europe/Rome')->format('d/m/Y H:i') ?? '—';
    }
}
