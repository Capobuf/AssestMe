<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Data\Operations\OperationalCheckResult;
use App\Enums\DeletionOperationStatus;
use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use App\Filament\Pages\BackupSettingsPage;
use App\Models\DeletionOperation;
use App\Services\Backups\LatestBackupStatus;
use App\Services\Operations\OperationalCheckStore;
use App\Settings\GeneralSettings;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;

final class ApplicationStatus extends Widget
{
    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.application-status';

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $checks = app(OperationalCheckStore::class);
        $backup = $checks->find(OperationalCheckType::Backup);
        $integrity = $checks->find(OperationalCheckType::DatabaseIntegrity);
        $latestBackup = app(LatestBackupStatus::class)->latestSuccessfulAt();
        $cleanupCount = DeletionOperation::query()
            ->where('status', DeletionOperationStatus::CleanupFailed)
            ->count();
        $latestCleanupEvent = DeletionOperation::query()->latest('updated_at')->first()?->updated_at;

        return ['rows' => [
            [
                'label' => __('assestme.dashboard.last_backup'),
                'status' => $backup?->status === OperationalCheckStatus::Failed ? 'failed' : ($latestBackup === null ? 'unknown' : 'ok'),
                'status_label' => $backup?->status === OperationalCheckStatus::Failed
                    ? __('assestme.dashboard.status_failed')
                    : ($latestBackup === null ? __('assestme.dashboard.status_unknown') : __('assestme.dashboard.status_ok')),
                'event' => $this->formatDate($backup !== null ? $backup->lastAttemptedAt : $latestBackup),
                'message' => $backup?->status === OperationalCheckStatus::Failed
                    ? __('assestme.dashboard.backup_failed_short')
                    : ($latestBackup === null ? __('assestme.dashboard.backup_never') : __('assestme.dashboard.backup_available')),
                'action_label' => __('assestme.backups.actions.manage'),
                'action_url' => BackupSettingsPage::getUrl(),
            ],
            [
                'label' => __('assestme.dashboard.database_integrity'),
                'status' => $this->checkStatus($integrity),
                'status_label' => $this->checkStatusLabel($integrity),
                'event' => $this->formatDate($integrity?->lastAttemptedAt),
                'message' => $integrity?->status === OperationalCheckStatus::Failed
                    ? __('assestme.dashboard.integrity_failure_help')
                    : ($integrity === null ? __('assestme.dashboard.integrity_never') : __('assestme.dashboard.integrity_verified')),
                'action_label' => null,
                'action_url' => null,
            ],
            [
                'label' => __('assestme.dashboard.cleanup_failures'),
                'status' => $cleanupCount > 0 ? 'warning' : 'ok',
                'status_label' => $cleanupCount > 0 ? __('assestme.dashboard.status_attention') : __('assestme.dashboard.status_ok'),
                'event' => $this->formatDate($latestCleanupEvent instanceof CarbonInterface ? $latestCleanupEvent : null),
                'message' => $cleanupCount > 0
                    ? __('assestme.dashboard.cleanup_pending_count', ['count' => $cleanupCount])
                    : __('assestme.dashboard.cleanup_none'),
                'action_label' => null,
                'action_url' => null,
            ],
        ]];
    }

    private function checkStatus(?OperationalCheckResult $check): string
    {
        return match ($check?->status) {
            OperationalCheckStatus::Succeeded => 'ok',
            OperationalCheckStatus::Failed => 'failed',
            default => 'unknown',
        };
    }

    private function checkStatusLabel(?OperationalCheckResult $check): string
    {
        return match ($check?->status) {
            OperationalCheckStatus::Succeeded => __('assestme.dashboard.status_ok'),
            OperationalCheckStatus::Failed => __('assestme.dashboard.status_failed'),
            default => __('assestme.dashboard.status_unknown'),
        };
    }

    private function formatDate(?CarbonInterface $date): string
    {
        return $date?->setTimezone(app(GeneralSettings::class)->timezone)->format('d/m/Y H:i') ?? '—';
    }
}
