<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Schemas;

use App\Enums\EffortLevel;
use App\Enums\EstimateType;
use App\Enums\FindingPriority;
use App\Enums\FindingStatus;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\VerticalAlignment;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class AssessmentWorkspaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('assestme.workspace.assessment_section'))
                    ->compact()
                    ->collapsible()
                    ->persistCollapsed()
                    ->columns(3)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('assestme.assessments.fields.title'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(self::autosaveCallback(...))
                            ->disabled(self::isConflict(...))
                            ->columnSpan(2),
                        DatePicker::make('assessment_date')
                            ->label(__('assestme.assessments.fields.date'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(self::autosaveCallback(...))
                            ->disabled(self::isConflict(...)),
                        Placeholder::make('workspace_save_status')
                            ->label(__('assestme.workspace.save_state'))
                            ->content(fn (WorkspaceAssessment $livewire): View => view('filament.workspace-save-status', [
                                'status' => $livewire->saveStatus,
                                'label' => $livewire->getSaveStatusLabel(),
                            ]))
                            ->columnSpanFull(),
                    ]),
                Repeater::make('findings')
                    ->label(__('assestme.workspace.findings'))
                    ->relationship(
                        name: 'findings',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('sort_order'),
                    )
                    ->table([
                        self::tableColumn('assestme.findings.fields.title', '11rem'),
                        self::tableColumn('assestme.findings.fields.problem', '17rem'),
                        self::tableColumn('assestme.findings.fields.entrepreneur_notes', '15rem'),
                        self::tableColumn('assestme.findings.fields.recommended_solution', '15rem'),
                        self::tableColumn('assestme.findings.fields.priority', '7rem'),
                        self::tableColumn('assestme.findings.fields.effort', '7rem'),
                        self::tableColumn('assestme.findings.fields.estimate_type', '10rem'),
                        self::tableColumn('assestme.findings.fields.estimate_notes', '12rem'),
                        self::tableColumn('assestme.findings.fields.status', '8rem'),
                        self::tableColumn('assestme.findings.fields.include', '6rem'),
                    ])
                    ->compact()
                    ->extraAttributes(['class' => 'assestme-workspace-findings'])
                    ->schema([
                        Hidden::make('id'),
                        Hidden::make('_temporary_uuid')
                            ->default(fn (): string => (string) Str::uuid())
                            ->afterStateHydrated(function (Hidden $component, mixed $state): void {
                                if (! is_string($state) || $state === '') {
                                    $component->state((string) Str::uuid());
                                }
                            }),
                        TextInput::make('title')
                            ->label(__('assestme.findings.fields.title'))
                            ->maxLength(255),
                        self::multiline('problem', 'assestme.findings.fields.problem'),
                        self::multiline('entrepreneur_notes', 'assestme.findings.fields.entrepreneur_notes'),
                        self::multiline('recommended_solution_summary', 'assestme.findings.fields.recommended_solution'),
                        Select::make('priority')
                            ->label(__('assestme.findings.fields.priority'))
                            ->options(FindingPriority::options()),
                        Select::make('effort')
                            ->label(__('assestme.findings.fields.effort'))
                            ->options(EffortLevel::options()),
                        Select::make('estimate_type')
                            ->label(__('assestme.findings.fields.estimate_type'))
                            ->options(EstimateType::options()),
                        Textarea::make('estimate_notes')
                            ->label(__('assestme.findings.fields.estimate_notes'))
                            ->rows(3)
                            ->maxLength(20000),
                        Select::make('status')
                            ->label(__('assestme.findings.fields.status'))
                            ->options(FindingStatus::options())
                            ->default(FindingStatus::Open->value)
                            ->required(),
                        Toggle::make('include_in_report')
                            ->label(__('assestme.findings.fields.include'))
                            ->default(true),
                    ])
                    ->orderColumn('sort_order')
                    ->cloneable()
                    ->reorderable()
                    ->reorderableWithButtons()
                    ->addActionLabel(__('assestme.workspace.add_finding'))
                    ->addAction(fn (Action $action): Action => $action->extraAttributes(['data-dusk' => 'add-finding']))
                    ->cloneAction(fn (Action $action): Action => $action->extraAttributes(['data-dusk' => 'clone-finding']))
                    ->deleteAction(fn (Action $action): Action => $action
                        ->requiresConfirmation()
                        ->extraAttributes(['data-dusk' => 'delete-finding']))
                    ->reorderAction(fn (Action $action): Action => $action->extraAttributes(['data-dusk' => 'reorder-findings']))
                    ->moveDownAction(fn (Action $action): Action => $action->extraAttributes(['data-dusk' => 'move-down-finding']))
                    ->live(onBlur: true)
                    ->afterStateUpdated(self::autosaveCallback(...))
                    ->disabled(self::isConflict(...))
                    ->columnSpanFull(),
            ]);
    }

    private static function multiline(string $name, string $labelKey): Textarea
    {
        return Textarea::make($name)
            ->label(__($labelKey))
            ->rows(3)
            ->maxLength(20000)
            ->extraInputAttributes([
                'data-dusk' => "finding-{$name}",
            ]);
    }

    private static function tableColumn(string $labelKey, string $width): TableColumn
    {
        return TableColumn::make(__($labelKey))
            ->width($width)
            ->wrapHeader()
            ->verticalAlignment(VerticalAlignment::Start);
    }

    private static function autosaveCallback(WorkspaceAssessment $livewire): void
    {
        $livewire->autosave();
    }

    private static function isConflict(WorkspaceAssessment $livewire): bool
    {
        return $livewire->saveStatus === WorkspaceAssessment::STATUS_CONFLICT;
    }
}
