<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Tables;

use App\Actions\Assessments\AssessFindingCompleteness;
use App\Enums\FindingStatus;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Models\Category;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Models\PriorityLevel;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class AssessmentFindingsTable
{
    public static function configure(Table $table, WorkspaceAssessment $page): Table
    {
        return $table
            ->query($page->findingsQuery())
            ->columns([
                ViewColumn::make('workbench_summary')
                    ->label(__('assestme.workspace.tabs.findings'))
                    ->view('filament.resources.assessments.tables.finding-workbench-state'),
            ])
            ->searchable()
            ->searchPlaceholder(__('assestme.workspace.list.search'))
            ->searchUsing(function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $term = '%'.$search.'%';
                    $query->where('title', 'like', $term)
                        ->orWhere('problem', 'like', $term)
                        ->orWhere('entrepreneur_notes', 'like', $term)
                        ->orWhereHas('solutions', fn (Builder $query): Builder => $query->where('description', 'like', $term))
                        ->orWhereHas('assets', fn (Builder $query): Builder => $query
                            ->where('name', 'like', $term)
                            ->orWhere('hostname', 'like', $term)
                            ->orWhere('description', 'like', $term));
                });
            })
            ->filters([
                SelectFilter::make('priority_level_id')
                    ->label(__('assestme.findings.fields.priority'))
                    ->options(fn (): array => PriorityLevel::query()->orderBy('sort_order')->pluck('label', 'id')->all()),
                SelectFilter::make('status')
                    ->label(__('assestme.findings.fields.status'))
                    ->options(FindingStatus::options()),
                SelectFilter::make('category_id')
                    ->label(__('assestme.templates.fields.category'))
                    ->options(fn (): array => Category::query()->orderBy('sort_order')->pluck('name', 'id')->all()),
                SelectFilter::make('include_in_report')
                    ->label(__('assestme.findings.fields.include'))
                    ->options([
                        1 => __('assestme.workspace.list.included'),
                        0 => __('assestme.workspace.list.excluded'),
                    ]),
                Filter::make('incomplete')
                    ->label(__('assestme.workspace.list.incomplete_filter'))
                    ->query(fn (Builder $query): Builder => app(AssessFindingCompleteness::class)->applyIncompleteFilter($query)),
            ])
            ->headerActions([
                ActionGroup::make([
                    Action::make('add_blank')
                        ->label(__('assestme.workspace.add_blank_finding'))
                        ->icon('heroicon-o-document-plus')
                        ->keyBindings(['alt+n'])
                        ->extraAttributes(['data-dusk' => 'add-finding'])
                        ->action(function () use ($page): void {
                            $page->createBlankFinding();
                        }),
                    Action::make('add_template')
                        ->label(__('assestme.workspace.add_from_template'))
                        ->icon('heroicon-o-book-open')
                        ->keyBindings(['alt+t'])
                        ->extraAttributes(['data-dusk' => 'add-template'])
                        ->schema([
                            Select::make('template_id')
                                ->label(__('assestme.workspace.template'))
                                ->options(fn (): array => FindingTemplate::query()->where('is_enabled', true)->orderBy('title')->pluck('title', 'id')->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (array $data) use ($page): void {
                            $page->createFromTemplate((int) $data['template_id']);
                        }),
                ])
                    ->label(__('assestme.workspace.new_finding'))
                    ->icon('heroicon-o-plus')
                    ->color('gray')
                    ->outlined()
                    ->button()
                    ->extraAttributes([
                        'class' => 'assestme-new-finding-action',
                        'data-dusk' => 'new-finding-menu',
                    ])
                    ->visible(fn (): bool => ! $page->isWorkspaceReadOnly()),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('assestme.workspace.list.open'))
                    ->extraAttributes([
                        'class' => 'assestme-finding-row__open-action',
                        'aria-hidden' => 'true',
                        'tabindex' => '-1',
                    ])
                    ->action(function (Finding $record) use ($page): void {
                        $page->selectFinding((int) $record->getKey());
                    }),
                ActionGroup::make([
                    Action::make('duplicate')
                        ->label(__('assestme.workspace.duplicate_finding'))
                        ->icon('heroicon-o-square-2-stack')
                        ->extraAttributes(['data-dusk' => 'duplicate-finding'])
                        ->visible(fn (): bool => ! $page->isWorkspaceReadOnly())
                        ->action(function (Finding $record) use ($page): void {
                            $page->duplicateFinding((int) $record->getKey());
                        }),
                    Action::make('delete')
                        ->label(__('filament-actions::delete.single.label'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->extraAttributes(['data-dusk' => 'delete-finding'])
                        ->visible(fn (): bool => ! $page->isWorkspaceReadOnly())
                        ->action(function (Finding $record) use ($page): void {
                            $page->deleteFinding((int) $record->getKey());
                        }),
                ])
                    ->label(__('assestme.workspace.list.actions'))
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->extraAttributes(['data-dusk' => 'finding-actions'])
                    ->iconButton(),
            ])
            ->recordAction('open')
            ->recordClasses(fn (Finding $record): string => $page->selectedFindingId === (int) $record->getKey() ? 'assestme-finding-row is-selected' : 'assestme-finding-row')
            ->reorderable('sort_order', fn (): bool => $page->canReorderFindings())
            ->reorderRecordsTriggerAction(fn (Action $action, bool $isReordering): Action => $action
                ->label($isReordering
                    ? __('assestme.workspace.list.finish_reordering')
                    : __('assestme.workspace.list.reorder'))
                ->button()
                ->outlined()
                ->size('xs'))
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading(__('assestme.workspace.list.empty'))
            ->emptyStateDescription(__('assestme.workspace.list.empty_description'))
            ->striped(false);
    }
}
