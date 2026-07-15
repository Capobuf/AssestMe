<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Tables;

use App\Enums\AssessmentStatus;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Support\StandardTableEnhancements;
use App\Models\Assessment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AssessmentsTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
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
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('workspace')
                    ->label(__('assestme.workspace.open'))
                    ->icon('heroicon-o-table-cells')
                    ->url(fn (Assessment $record): string => AssessmentResource::getUrl('workspace', ['record' => $record])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
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
