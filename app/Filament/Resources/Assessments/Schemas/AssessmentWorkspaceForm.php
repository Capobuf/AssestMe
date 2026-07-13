<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Schemas;

use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Models\Assessment;
use App\Models\Asset;
use App\Models\Category;
use App\Models\ConsequenceLevel;
use App\Models\EffortLevel;
use App\Models\Finding;
use App\Models\FindingSolution;
use App\Models\LikelihoodLevel;
use App\Models\PriorityLevel;
use App\Models\Tag;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class AssessmentWorkspaceForm
{
    public static function configure(Schema $schema): Schema
    {
        $assessmentDetails = Section::make(__('assestme.workspace.assessment_section'))
            ->compact()
            ->collapsible()
            ->persistCollapsed()
            ->columns(3)
            ->schema([
                Placeholder::make('client_display')
                    ->label(__('assestme.assessments.fields.client'))
                    ->content(fn (WorkspaceAssessment $livewire): string => self::workspaceAssessment($livewire)->client->displayName()),
                TextInput::make('title')
                    ->label(__('assestme.assessments.fields.title'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(self::autosaveCallback(...))
                    ->disabled(self::isReadOnly(...))
                    ->columnSpan(2),
                DatePicker::make('assessment_date')
                    ->label(__('assestme.assessments.fields.date'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(self::autosaveCallback(...))
                    ->disabled(self::isReadOnly(...)),
                TextInput::make('report_title_override')
                    ->label(__('assestme.assessments.fields.report_title'))
                    ->maxLength(255)
                    ->disabled(self::isReadOnly(...))
                    ->columnSpan(2),
                Select::make('scope_type')
                    ->label(__('assestme.assessments.fields.scope'))
                    ->options(self::scopeOptions())
                    ->required()
                    ->disabled(self::isReadOnly(...)),
                Select::make('site_ids')
                    ->label(__('assestme.assessments.fields.sites'))
                    ->options(fn (WorkspaceAssessment $livewire): array => self::workspaceAssessment($livewire)->client->sites()->pluck('name', 'id')->all())
                    ->multiple()
                    ->searchable()
                    ->disabled(self::isReadOnly(...))
                    ->columnSpanFull(),
                Textarea::make('scope_description')
                    ->label(__('assestme.assessments.fields.scope_description'))
                    ->rows(3)
                    ->maxLength(20000)
                    ->disabled(self::isReadOnly(...))
                    ->columnSpanFull(),
                Textarea::make('introduction')
                    ->label(__('assestme.assessments.fields.introduction'))
                    ->rows(4)
                    ->maxLength(20000)
                    ->disabled(self::isReadOnly(...))
                    ->columnSpanFull(),
                Textarea::make('executive_summary')
                    ->label(__('assestme.assessments.fields.executive_summary'))
                    ->rows(4)
                    ->maxLength(20000)
                    ->disabled(self::isReadOnly(...))
                    ->columnSpanFull(),
                Textarea::make('methodology_notes')
                    ->label(__('assestme.assessments.fields.methodology_notes'))
                    ->rows(4)
                    ->maxLength(20000)
                    ->disabled(self::isReadOnly(...))
                    ->columnSpanFull(),
                Placeholder::make('workspace_save_status')
                    ->label(__('assestme.workspace.save_state'))
                    ->content(fn (WorkspaceAssessment $livewire): View => view('filament.workspace-save-status', [
                        'status' => $livewire->saveStatus,
                        'label' => $livewire->getSaveStatusLabel(),
                    ]))
                    ->columnSpanFull(),
            ]);

        $findings = Repeater::make('findings')
            ->label(__('assestme.workspace.findings'))
            ->relationship(
                name: 'findings',
                modifyQueryUsing: fn (Builder $query): Builder => $query
                    ->with(['priorityLevel', 'recommendedSolution.effortLevel'])
                    ->orderBy('sort_order'),
            )
            ->table([
                self::tableColumn('assestme.findings.fields.title', '11rem'),
                self::tableColumn('assestme.findings.fields.problem', '17rem'),
                self::tableColumn('assestme.findings.fields.entrepreneur_notes', '15rem'),
                self::tableColumn('assestme.findings.fields.recommended_solution', '15rem'),
                self::tableColumn('assestme.findings.fields.priority', '7rem'),
                self::tableColumn('assestme.findings.fields.effort', '7rem'),
                self::tableColumn('assestme.findings.fields.estimate_type', '10rem'),
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
                Placeholder::make('recommended_solution_display')
                    ->label(__('assestme.findings.fields.recommended_solution'))
                    ->content(fn (?Finding $record): string => $record?->recommendedSolutionDescription() ?? '—'),
                Placeholder::make('priority_display')
                    ->label(__('assestme.findings.fields.priority'))
                    ->content(fn (?Finding $record): string => $record?->priorityLabel() ?? '—'),
                Placeholder::make('effort_display')
                    ->label(__('assestme.findings.fields.effort'))
                    ->content(fn (?Finding $record): string => $record?->recommendedEffortLabel() ?? '—'),
                Placeholder::make('estimate_display')
                    ->label(__('assestme.findings.fields.estimate_type'))
                    ->content(fn (?Finding $record): string => $record?->recommendedEstimateLabel() ?? '—'),
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
            ->cloneable(false)
            ->reorderable()
            ->reorderableWithButtons()
            ->addable(false)
            ->deleteAction(fn (Action $action): Action => $action
                ->requiresConfirmation()
                ->extraAttributes(['data-dusk' => 'delete-finding']))
            ->extraItemActions([
                Action::make('details')
                    ->label(__('assestme.workspace.finding_details'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->extraAttributes(['data-dusk' => 'finding-details'])
                    ->slideOver()
                    ->modalWidth(Width::FiveExtraLarge)
                    ->fillForm(fn (array $arguments, Repeater $component): array => self::detailsData($component, (string) $arguments['item']))
                    ->schema(self::detailsSchema())
                    ->action(function (array $data, array $arguments, Repeater $component, WorkspaceAssessment $livewire): void {
                        $state = $component->getRawItemState((string) $arguments['item']);
                        $livewire->saveFindingDetails((int) $state['id'], $data);
                    }),
                Action::make('duplicate_finding')
                    ->label(__('assestme.workspace.duplicate_finding'))
                    ->icon('heroicon-o-square-2-stack')
                    ->extraAttributes(['data-dusk' => 'clone-finding'])
                    ->action(function (array $arguments, Repeater $component, WorkspaceAssessment $livewire): void {
                        $state = $component->getRawItemState((string) $arguments['item']);
                        $livewire->duplicateFinding((int) $state['id']);
                    }),
            ])
            ->reorderAction(fn (Action $action): Action => $action->extraAttributes(['data-dusk' => 'reorder-findings']))
            ->moveDownAction(fn (Action $action): Action => $action->extraAttributes(['data-dusk' => 'move-down-finding']))
            ->live(onBlur: true)
            ->afterStateUpdated(self::autosaveCallback(...))
            ->disabled(self::isReadOnly(...))
            ->columnSpanFull();

        return $schema
            ->columns(1)
            ->components([
                Tabs::make('workspace_tabs')
                    ->tabs([
                        Tab::make(__('assestme.workspace.tabs.findings'))
                            ->schema([$findings]),
                        Tab::make(__('assestme.workspace.tabs.assessment_details'))
                            ->schema([$assessmentDetails]),
                        Tab::make(__('assestme.workspace.tabs.summary_preview'))
                            ->schema([
                                Placeholder::make('summary_preview')
                                    ->hiddenLabel()
                                    ->content(fn (WorkspaceAssessment $livewire): string => self::summaryPreview($livewire)),
                            ]),
                        Tab::make(__('assestme.workspace.tabs.generated_files'))
                            ->schema([
                                Placeholder::make('generated_files_summary')
                                    ->hiddenLabel()
                                    ->content(__('assestme.workspace.no_generated_files')),
                            ]),
                    ])
                    ->persistTabInQueryString('workspace-tab')
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

    private static function isReadOnly(WorkspaceAssessment $livewire): bool
    {
        return $livewire->isWorkspaceReadOnly() || self::isConflict($livewire);
    }

    /** @return list<Component|Action> */
    private static function detailsSchema(): array
    {
        return [
            Select::make('category_id')
                ->label(__('assestme.templates.fields.category'))
                ->options(fn (): array => Category::query()->where('is_enabled', true)->orderBy('sort_order')->pluck('name', 'id')->all())
                ->searchable()
                ->preload(),
            Select::make('tag_ids')
                ->label(__('assestme.templates.fields.tags'))
                ->options(fn (): array => Tag::query()->orderBy('name')->pluck('name', 'id')->all())
                ->multiple()
                ->searchable()
                ->preload(),
            Select::make('scope_type')
                ->label(__('assestme.assessments.fields.scope'))
                ->options(self::scopeOptions())
                ->required(),
            Textarea::make('scope_description')
                ->label(__('assestme.assessments.fields.scope_description'))
                ->rows(3)
                ->maxLength(20000),
            Select::make('site_ids')
                ->label(__('assestme.assessments.fields.sites'))
                ->options(fn (WorkspaceAssessment $livewire): array => self::workspaceAssessment($livewire)->client->sites()->pluck('name', 'id')->all())
                ->multiple()
                ->searchable(),
            Select::make('asset_ids')
                ->label(__('assestme.findings.fields.assets'))
                ->options(fn (WorkspaceAssessment $livewire): array => self::workspaceAssessment($livewire)->client->assets()->get()->mapWithKeys(
                    static fn (Asset $asset): array => [$asset->id => $asset->name ?? $asset->hostname ?? "Asset {$asset->id}"],
                )->all())
                ->multiple()
                ->searchable(),
            Select::make('consequence_level_id')
                ->label(__('assestme.templates.fields.consequence'))
                ->options(fn (): array => ConsequenceLevel::query()->where('is_enabled', true)->orderBy('sort_order')->pluck('label', 'id')->all()),
            Select::make('likelihood_level_id')
                ->label(__('assestme.templates.fields.likelihood'))
                ->options(fn (): array => LikelihoodLevel::query()->where('is_enabled', true)->orderBy('sort_order')->pluck('label', 'id')->all()),
            Toggle::make('priority_is_overridden')
                ->label(__('assestme.findings.fields.priority_override'))
                ->live(),
            Select::make('priority_level_id')
                ->label(__('assestme.findings.fields.priority'))
                ->options(fn (): array => PriorityLevel::query()->where('is_enabled', true)->orderBy('sort_order')->pluck('label', 'id')->all()),
            Textarea::make('priority_rationale')
                ->label(__('assestme.templates.fields.priority_rationale'))
                ->rows(3)
                ->maxLength(20000),
            Textarea::make('technical_notes')
                ->label(__('assestme.templates.fields.technical_notes'))
                ->rows(4)
                ->maxLength(20000),
            Repeater::make('solutions')
                ->label(__('assestme.templates.sections.solutions'))
                ->schema([
                    Hidden::make('id'),
                    Hidden::make('external_key'),
                    TextInput::make('title')->label(__('assestme.findings.fields.solution_title'))->required()->maxLength(255),
                    Textarea::make('description')->label(__('assestme.common.description'))->required()->rows(4)->maxLength(20000),
                    Select::make('effort_level_id')
                        ->label(__('assestme.findings.fields.effort'))
                        ->options(fn (): array => EffortLevel::query()->where('is_enabled', true)->orderBy('sort_order')->pluck('label', 'id')->all()),
                    Select::make('estimate_type')->label(__('assestme.findings.fields.estimate_type'))->options(EstimateType::options())->required(),
                    TextInput::make('amount_min')->label(__('assestme.templates.fields.amount_min'))->numeric(),
                    TextInput::make('amount_max')->label(__('assestme.templates.fields.amount_max'))->numeric(),
                    TextInput::make('currency_code')->label(__('assestme.templates.fields.currency'))->maxLength(3),
                    Select::make('billing_frequency')->label(__('assestme.templates.fields.billing_frequency'))->options([
                        BillingFrequency::OneOff->value => __('assestme.billing.one_off'),
                        BillingFrequency::Monthly->value => __('assestme.billing.monthly'),
                        BillingFrequency::Yearly->value => __('assestme.billing.yearly'),
                        BillingFrequency::Custom->value => __('assestme.billing.custom'),
                    ])->default(BillingFrequency::OneOff->value)->required(),
                    Textarea::make('estimate_notes')->label(__('assestme.findings.fields.estimate_notes'))->maxLength(20000),
                    Toggle::make('is_recommended')->label(__('assestme.templates.fields.recommended')),
                    Toggle::make('is_implemented')->label(__('assestme.findings.fields.implemented')),
                    Hidden::make('sort_order')->default(1),
                ])
                ->reorderable()
                ->orderColumn('sort_order')
                ->defaultItems(0)
                ->columnSpanFull(),
            Select::make('status')
                ->label(__('assestme.findings.fields.status'))
                ->options(FindingStatus::options())
                ->required(),
            Textarea::make('resolution_notes')
                ->label(__('assestme.findings.fields.resolution_notes'))
                ->rows(3)
                ->maxLength(20000),
            TextInput::make('evidence_summary')
                ->label(__('assestme.findings.fields.evidence'))
                ->disabled()
                ->dehydrated(false),
            FileUpload::make('evidence_uploads')
                ->label(__('assestme.workspace.add_evidence_files'))
                ->disk('local')
                ->directory('pending-evidence')
                ->visibility('private')
                ->storeFileNamesIn('evidence_original_names')
                ->preventFilePathTampering()
                ->multiple()
                ->maxFiles(20)
                ->maxSize(25 * 1024)
                ->acceptedFileTypes([
                    'image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain', 'text/csv',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.oasis.opendocument.spreadsheet',
                ]),
            Hidden::make('evidence_original_names'),
            TextInput::make('evidence_title')->label(__('assestme.workspace.evidence_url_title'))->maxLength(255),
            TextInput::make('evidence_url')->label(__('assestme.workspace.evidence_url'))->url()->maxLength(2048),
        ];
    }

    /** @return array<string, mixed> */
    private static function detailsData(Repeater $component, string $item): array
    {
        $state = $component->getRawItemState($item);
        $finding = Finding::query()->with(['tags', 'sites', 'assets', 'solutions', 'evidences'])->findOrFail($state['id']);

        return [
            ...$finding->only([
                'category_id', 'technical_notes', 'scope_type', 'scope_description', 'consequence_level_id',
                'likelihood_level_id', 'priority_level_id', 'priority_is_overridden', 'priority_rationale', 'status', 'resolution_notes',
            ]),
            'tag_ids' => $finding->tags->pluck('id')->all(),
            'site_ids' => $finding->sites->pluck('id')->all(),
            'asset_ids' => $finding->assets->pluck('id')->all(),
            'solutions' => $finding->solutions->map(static fn (FindingSolution $solution): array => [
                ...$solution->only([
                    'id', 'external_key', 'title', 'description', 'comparison_notes', 'effort_level_id', 'effort_notes',
                    'estimate_type', 'amount_min', 'amount_max', 'currency_code', 'billing_frequency',
                    'custom_billing_frequency', 'estimate_notes', 'sort_order',
                ]),
                'is_recommended' => $finding->recommended_solution_id === $solution->id,
                'is_implemented' => $finding->implemented_solution_id === $solution->id,
            ])->all(),
            'evidence_summary' => $finding->evidences->pluck('title')->join(', '),
        ];
    }

    /** @return array<string, string> */
    private static function scopeOptions(): array
    {
        return [
            ScopeType::Organization->value => __('assestme.scopes.organization'),
            ScopeType::SelectedSites->value => __('assestme.scopes.selected_sites'),
            ScopeType::Network->value => __('assestme.scopes.network'),
            ScopeType::SelectedAssets->value => __('assestme.scopes.selected_assets'),
            ScopeType::Custom->value => __('assestme.scopes.custom'),
        ];
    }

    private static function workspaceAssessment(WorkspaceAssessment $livewire): Assessment
    {
        $record = $livewire->getRecord();

        if (! $record instanceof Assessment) {
            throw new \LogicException('The workspace record must be an assessment.');
        }

        return $record;
    }

    private static function summaryPreview(WorkspaceAssessment $livewire): string
    {
        $assessment = self::workspaceAssessment($livewire);
        $includedFindings = $assessment->findings()->where('include_in_report', true)->count();

        return __('assestme.workspace.summary_preview', [
            'client' => $assessment->client->displayName(),
            'title' => $assessment->report_title_override ?: $assessment->title,
            'findings' => $includedFindings,
        ]);
    }
}
