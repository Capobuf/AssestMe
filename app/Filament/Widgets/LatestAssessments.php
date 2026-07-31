<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AssessmentStatus;
use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Models\Assessment;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

final class LatestAssessments extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('assestme.dashboard.latest_assessments'))
            ->query(fn (): Builder => Assessment::query()
                ->with('client')
                ->withCount([
                    'findings as open_findings_count' => fn (Builder $query): Builder => $query->where('status', FindingStatus::Open),
                ])
                ->latest('assessment_date')
                ->latest('id')
                ->limit(5))
            ->columns([
                TextColumn::make('client.legal_name')
                    ->label(__('assestme.dashboard.company'))
                    ->formatStateUsing(fn (mixed $state, Assessment $record): string => $record->client->displayName()),
                TextColumn::make('scope_type')
                    ->label(__('assestme.dashboard.scope'))
                    ->formatStateUsing(fn (mixed $state): string => __('assestme.scopes.'.($state instanceof ScopeType ? $state->value : (string) $state))),
                TextColumn::make('assessment_date')
                    ->label(__('assestme.assessments.fields.date'))
                    ->date('d/m/Y'),
                TextColumn::make('status')
                    ->label(__('assestme.assessments.fields.status'))
                    ->formatStateUsing(fn (mixed $state): string => __(
                        'assestme.assessments.status.'.($state instanceof AssessmentStatus ? $state->value : (string) $state),
                    ))
                    ->badge(),
                TextColumn::make('open_findings_count')
                    ->label(__('assestme.dashboard.open_findings_count')),
            ])
            ->recordActions([
                Action::make('workspace')
                    ->label(__('assestme.dashboard.open_workspace'))
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Assessment $record): string => WorkspaceAssessment::getUrl(['record' => $record])),
            ])
            ->recordUrl(fn (Assessment $record): string => WorkspaceAssessment::getUrl(['record' => $record]))
            ->paginated(false);
    }
}
