<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AssessmentStatus;
use App\Enums\FindingStatus;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Models\Assessment;
use App\Models\Finding;
use App\Services\Risk\UrgentPriorityResolver;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class AssessmentStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /** @return array<string, int> */
    protected function getColumns(): array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        return [
            Stat::make(
                __('assestme.dashboard.draft_assessments'),
                Assessment::query()->where('status', AssessmentStatus::Draft)->count(),
            )
                ->icon('heroicon-m-pencil-square')
                ->url($this->assessmentFilterUrl('status', AssessmentStatus::Draft->value)),
            Stat::make(
                __('assestme.dashboard.completed_assessments'),
                Assessment::query()->where('status', AssessmentStatus::Completed)->count(),
            )
                ->icon('heroicon-m-check-circle')
                ->url($this->assessmentFilterUrl('status', AssessmentStatus::Completed->value)),
            Stat::make(
                __('assestme.dashboard.open_findings'),
                Finding::query()->where('status', FindingStatus::Open)->count(),
            )
                ->icon('heroicon-m-clipboard-document-list')
                ->url($this->assessmentFilterUrl('finding_attention', 'open')),
            Stat::make(
                __('assestme.dashboard.urgent_findings'),
                Finding::query()
                    ->where('include_in_report', true)
                    ->where('status', FindingStatus::Open)
                    ->whereIn('priority_level_id', app(UrgentPriorityResolver::class)->ids())
                    ->count(),
            )
                ->description(__('assestme.dashboard.urgent_findings_help'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->url($this->assessmentFilterUrl('finding_attention', 'urgent')),
        ];
    }

    private function assessmentFilterUrl(string $filter, string $value): string
    {
        return AssessmentResource::getUrl('index', [
            'tableFilters' => [$filter => ['value' => $value]],
        ]);
    }
}
