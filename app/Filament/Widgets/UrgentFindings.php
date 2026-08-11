<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\FindingStatus;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Models\Finding;
use App\Services\Risk\UrgentPriorityResolver;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

final class UrgentFindings extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('assestme.dashboard.urgent_findings_list'))
            ->query(fn (): Builder => Finding::query()
                ->with(['assessment.client', 'priorityLevel'])
                ->where('include_in_report', true)
                ->where('status', FindingStatus::Open)
                ->whereIn('priority_level_id', app(UrgentPriorityResolver::class)->ids())
                ->latest('updated_at')
                ->limit(5))
            ->columns([
                TextColumn::make('title')->label(__('assestme.findings.fields.title')),
                TextColumn::make('assessment.client.legal_name')
                    ->label(__('assestme.dashboard.company'))
                    ->formatStateUsing(fn (mixed $state, Finding $record): string => $record->assessment->client->displayName()),
                TextColumn::make('priorityLevel.label')
                    ->label(__('assestme.findings.fields.priority'))
                    ->badge()
                    ->icon('heroicon-m-exclamation-triangle'),
            ])
            ->recordActions([
                Action::make('workspace')
                    ->label(__('assestme.dashboard.open_workspace'))
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Finding $record): string => WorkspaceAssessment::getUrl(['record' => $record->assessment_id])),
            ])
            ->recordUrl(fn (Finding $record): string => WorkspaceAssessment::getUrl(['record' => $record->assessment_id]))
            ->paginated(false);
    }
}
