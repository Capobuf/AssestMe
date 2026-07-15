<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AssessmentStatus;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Models\Assessment;
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
            ->query(fn (): Builder => Assessment::query()->latest('updated_at')->limit(5))
            ->columns([
                TextColumn::make('title')
                    ->label(__('assestme.assessments.fields.title')),
                TextColumn::make('assessment_date')
                    ->label(__('assestme.assessments.fields.date'))
                    ->date('d/m/Y'),
                TextColumn::make('status')
                    ->label(__('assestme.assessments.fields.status'))
                    ->formatStateUsing(fn (mixed $state): string => __(
                        'assestme.assessments.status.'.($state instanceof AssessmentStatus ? $state->value : (string) $state),
                    )),
            ])
            ->recordUrl(fn (Assessment $record): string => WorkspaceAssessment::getUrl(['record' => $record]))
            ->paginated(false);
    }
}
