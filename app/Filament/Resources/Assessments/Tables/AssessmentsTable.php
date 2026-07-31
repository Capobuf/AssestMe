<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Tables;

use App\Enums\AssessmentStatus;
use App\Enums\FindingStatus;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Support\DeleteAccordingToPolicyAction;
use App\Filament\Support\StandardTableEnhancements;
use App\Models\Assessment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssessmentsTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->recordUrl(fn (Assessment $record): string => AssessmentResource::getUrl('workspace', ['record' => $record]))
            ->columns([
                TextColumn::make('client.legal_name')
                    ->label(__('assestme.assessments.fields.client'))
                    ->formatStateUsing(fn (mixed $state, Assessment $record): string => $record->client->displayName())
                    ->searchable(['legal_name', 'trade_name'])
                    ->sortable(),
                TextColumn::make('title')
                    ->label(__('assestme.assessments.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('assessment_date')
                    ->label(__('assestme.assessments.fields.date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('assestme.assessments.fields.status'))
                    ->formatStateUsing(fn (AssessmentStatus $state): string => AssessmentStatus::options()[$state->value])
                    ->badge(),
                TextColumn::make('findings_count')
                    ->label(__('assestme.assessments.fields.findings'))
                    ->counts('findings'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('assestme.assessments.fields.status'))
                    ->options(AssessmentStatus::options()),
                SelectFilter::make('finding_attention')
                    ->label(__('assestme.dashboard.finding_filter'))
                    ->options([
                        'open' => __('assestme.dashboard.open_findings'),
                        'urgent' => __('assestme.dashboard.urgent_findings'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'open' => $query->whereHas('findings', fn (Builder $findings): Builder => $findings->where('status', FindingStatus::Open)),
                            'urgent' => $query->whereHas('findings', fn (Builder $findings): Builder => $findings
                                ->where('status', FindingStatus::Open)
                                ->where('include_in_report', true)
                                ->whereHas('priorityLevel', fn (Builder $priority): Builder => $priority->whereIn('code', ['high', 'critical']))),
                            default => $query,
                        };
                    }),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('workspace')
                    ->label(__('assestme.workspace.open'))
                    ->icon('heroicon-o-table-cells')
                    ->url(fn (Assessment $record): string => AssessmentResource::getUrl('workspace', ['record' => $record])),
                EditAction::make(),
                DeleteAccordingToPolicyAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RestoreBulkAction::make(),
                ]),
            ]);

        return StandardTableEnhancements::apply($table, [
            Action::make('contextWorkspace')
                ->label(__('assestme.workspace.open'))
                ->icon('heroicon-o-table-cells')
                ->url(fn (Assessment $record): string => AssessmentResource::getUrl('workspace', ['record' => $record])),
            ...StandardTableEnhancements::editable(),
        ]);
    }
}
