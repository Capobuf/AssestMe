<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Schemas;

use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Models\Asset;
use App\Models\Category;
use App\Models\ConsequenceLevel;
use App\Models\EffortLevel;
use App\Models\Finding;
use App\Models\FindingSolution;
use App\Models\LikelihoodLevel;
use App\Models\PriorityLevel;
use App\Services\Reporting\EditorialLimits;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;

final class FindingEditorSchema
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('assestme.workspace.inspector.description'))
                    ->compact()
                    ->contained(false)
                    ->divided()
                    ->extraAttributes(['class' => 'assestme-workbench-section assestme-workbench-section--primary'])
                    ->schema([
                        TextInput::make('title')
                            ->label(__('assestme.findings.fields.title'))
                            ->maxLength(EditorialLimits::FINDING_TITLE)
                            ->helperText(fn (?string $state): string => self::remaining($state, EditorialLimits::FINDING_TITLE))
                            ->disabled(self::isReadOnly(...))
                            ->extraInputAttributes(['data-dusk' => 'finding-editor-title']),
                        Textarea::make('problem')
                            ->label(__('assestme.findings.fields.problem'))
                            ->rows(4)
                            ->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('solutions')))['problem'])
                            ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('solutions')))['problem']))
                            ->disabled(self::isReadOnly(...)),
                        Textarea::make('entrepreneur_notes')
                            ->label(__('assestme.findings.fields.entrepreneur_notes'))
                            ->rows(3)
                            ->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('solutions')))['entrepreneur_notes'])
                            ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('solutions')))['entrepreneur_notes']))
                            ->disabled(self::isReadOnly(...)),
                    ]),
                Section::make(__('assestme.templates.sections.solutions'))
                    ->contained(false)
                    ->divided()
                    ->extraAttributes(['class' => 'assestme-workbench-section assestme-workbench-section--solutions'])
                    ->schema([
                        Repeater::make('solutions')
                            ->hiddenLabel()
                            ->extraAttributes(['data-assestme-draft-collection' => 'solutions'])
                            ->addAction(self::draftStructuralAction(...))
                            ->deleteAction(self::draftStructuralAction(...))
                            ->moveDownAction(self::draftStructuralAction(...))
                            ->moveUpAction(self::draftStructuralAction(...))
                            ->reorderAction(self::draftStructuralAction(...))
                            ->itemLabel(fn (array $state): string => (string) ($state['title'] ?? __('assestme.workspace.inspector.new_solution')))
                            ->schema([
                                Hidden::make('id'),
                                Hidden::make('external_key'),
                                TextInput::make('title')
                                    ->label(__('assestme.findings.fields.solution_title'))
                                    ->required()
                                    ->maxLength(EditorialLimits::SOLUTION_TITLE)
                                    ->extraInputAttributes(['data-dusk' => 'finding-solution-title'])
                                    ->helperText(fn (?string $state): string => self::remaining($state, EditorialLimits::SOLUTION_TITLE)),
                                Textarea::make('description')
                                    ->label(__('assestme.common.description'))
                                    ->required()
                                    ->rows(5)
                                    ->extraInputAttributes(['data-dusk' => 'finding-solution-description'])
                                    ->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['solution_description'])
                                    ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['solution_description']))
                                    ->columnSpanFull(),
                                Textarea::make('comparison_notes')
                                    ->label(__('assestme.templates.fields.comparison_notes'))
                                    ->rows(3)
                                    ->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['comparison_notes'])
                                    ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['comparison_notes']))
                                    ->columnSpanFull(),
                                Select::make('effort_level_id')
                                    ->label(__('assestme.findings.fields.effort'))
                                    ->options(fn (): array => EffortLevel::query()->where('is_enabled', true)->orderBy('sort_order')->pluck('label', 'id')->all()),
                                Select::make('estimate_type')
                                    ->label(__('assestme.findings.fields.estimate_type'))
                                    ->options(EstimateType::options())
                                    ->extraAttributes(['data-dusk' => 'finding-estimate-type'])
                                    ->live()
                                    ->required(),
                                TextInput::make('amount_min')
                                    ->label(__('assestme.templates.fields.amount_min'))
                                    ->numeric()
                                    ->visible(fn (Get $get): bool => in_array($get('estimate_type'), [EstimateType::Exact->value, EstimateType::Range->value], true)),
                                TextInput::make('amount_max')
                                    ->label(__('assestme.templates.fields.amount_max'))
                                    ->numeric()
                                    ->visible(fn (Get $get): bool => $get('estimate_type') === EstimateType::Range->value),
                                TextInput::make('currency_code')
                                    ->label(__('assestme.templates.fields.currency'))
                                    ->maxLength(3)
                                    ->visible(fn (Get $get): bool => in_array($get('estimate_type'), [EstimateType::Exact->value, EstimateType::Range->value], true)),
                                Select::make('billing_frequency')
                                    ->label(__('assestme.templates.fields.billing_frequency'))
                                    ->options([
                                        BillingFrequency::OneOff->value => __('assestme.billing.one_off'),
                                        BillingFrequency::Monthly->value => __('assestme.billing.monthly'),
                                        BillingFrequency::Yearly->value => __('assestme.billing.yearly'),
                                        BillingFrequency::Custom->value => __('assestme.billing.custom'),
                                    ])
                                    ->live()
                                    ->default(BillingFrequency::OneOff->value)
                                    ->required(),
                                TextInput::make('custom_billing_frequency')
                                    ->label(__('assestme.templates.fields.custom_billing'))
                                    ->maxLength(120)
                                    ->visible(fn (Get $get): bool => $get('billing_frequency') === BillingFrequency::Custom->value),
                                Textarea::make('effort_notes')
                                    ->label(__('assestme.templates.fields.effort_notes'))
                                    ->rows(3)
                                    ->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['effort_notes'])
                                    ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['effort_notes'])),
                                Textarea::make('estimate_notes')
                                    ->label(__('assestme.findings.fields.estimate_notes'))
                                    ->rows(3)
                                    ->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['estimate_notes'])
                                    ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['estimate_notes'])),
                                Toggle::make('is_recommended')->label(__('assestme.templates.fields.recommended')),
                                Toggle::make('is_implemented')->label(__('assestme.findings.fields.implemented')),
                                Hidden::make('sort_order')->default(1),
                            ])
                            ->columns(2)
                            ->reorderable()
                            ->orderColumn('sort_order')
                            ->defaultItems(0)
                            ->maxItems(3)
                            ->helperText(__('assestme.templates.solutions_help'))
                            ->disabled(self::isReadOnly(...))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('assestme.workspace.inspector.evidence'))
                    ->contained(false)
                    ->divided()
                    ->extraAttributes(['class' => 'assestme-workbench-section assestme-workbench-section--evidence'])
                    ->schema([
                        Placeholder::make('existing_evidence')
                            ->hiddenLabel()
                            ->content(function (WorkspaceAssessment $livewire): View {
                                $finding = $livewire->selectedFinding();

                                return view('filament.resources.assessments.finding-evidence-list', [
                                    'evidences' => $finding instanceof Finding ? $finding->evidences : collect(),
                                ]);
                            }),
                        FileUpload::make('evidence_uploads')
                            ->label(__('assestme.workspace.add_evidence_files'))
                            ->disk('local')
                            ->directory('pending-evidence')
                            ->visibility('private')
                            ->storeFileNamesIn('evidence_original_names')
                            ->preventFilePathTampering()
                            ->multiple()
                            ->pasteable()
                            ->maxFiles(20)
                            ->maxSize(25 * 1024)
                            ->acceptedFileTypes([
                                'image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain', 'text/csv',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.oasis.opendocument.spreadsheet',
                            ])
                            ->helperText(__('assestme.workspace.evidence_upload_hint'))
                            ->extraInputAttributes(['data-dusk' => 'finding-evidence-upload'])
                            ->disabled(self::isReadOnly(...)),
                        Hidden::make('evidence_original_names'),
                        TextInput::make('evidence_title')
                            ->label(__('assestme.workspace.evidence_url_title'))
                            ->maxLength(255)
                            ->disabled(self::isReadOnly(...)),
                        TextInput::make('evidence_url')
                            ->label(__('assestme.workspace.evidence_url'))
                            ->url()
                            ->maxLength(2048)
                            ->disabled(self::isReadOnly(...)),
                    ]),
                Section::make(__('assestme.templates.fields.technical_notes'))
                    ->collapsible()
                    ->collapsed()
                    ->compact()
                    ->contained(false)
                    ->extraAttributes(['class' => 'assestme-workbench-section assestme-workbench-section--secondary'])
                    ->schema([
                        Textarea::make('technical_notes')
                            ->label(__('assestme.templates.fields.technical_notes'))
                            ->rows(4)
                            ->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('solutions')))['technical_notes'])
                            ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('solutions')))['technical_notes']))
                            ->disabled(self::isReadOnly(...)),
                    ]),
            ]);
    }

    private static function draftStructuralAction(Action $action): Action
    {
        return $action->extraAttributes([
            'data-assestme-draft-structural-action' => 'solutions',
            'data-dusk' => 'finding-solution-'.$action->getName(),
        ]);
    }

    public static function properties(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('assestme.workspace.inspector.status_report'))
                    ->collapsible()
                    ->collapsed()
                    ->compact()
                    ->contained(false)
                    ->divided()
                    ->extraAttributes(['class' => 'assestme-workbench-section assestme-workbench-section--properties'])
                    ->schema([
                        Select::make('status')
                            ->label(__('assestme.findings.fields.status'))
                            ->options(FindingStatus::options())
                            ->required()
                            ->extraAttributes(['data-dusk' => 'finding-property-status'])
                            ->disabled(self::isReadOnly(...)),
                        Toggle::make('include_in_report')
                            ->label(__('assestme.findings.fields.include'))
                            ->extraAttributes(['data-dusk' => 'finding-property-report'])
                            ->disabled(self::isReadOnly(...)),
                    ]),
                Section::make(__('assestme.workspace.properties.classification'))
                    ->collapsible()
                    ->collapsed()
                    ->compact()
                    ->contained(false)
                    ->divided()
                    ->extraAttributes(['class' => 'assestme-workbench-section assestme-workbench-section--properties'])
                    ->schema([
                        Select::make('category_id')
                            ->label(__('assestme.templates.fields.category'))
                            ->options(fn (): array => Category::query()->where('is_enabled', true)->orderBy('sort_order')->pluck('name', 'id')->all())
                            ->searchable()
                            ->preload()
                            ->disabled(self::isReadOnly(...)),
                    ]),
                Section::make(__('assestme.workspace.properties.scope'))
                    ->collapsible()
                    ->collapsed()
                    ->compact()
                    ->contained(false)
                    ->divided()
                    ->extraAttributes(['class' => 'assestme-workbench-section assestme-workbench-section--properties'])
                    ->schema([
                        Select::make('scope_type')
                            ->label(__('assestme.assessments.fields.scope'))
                            ->options(self::scopeOptions())
                            ->live()
                            ->required()
                            ->disabled(self::isReadOnly(...)),
                        Textarea::make('scope_description')
                            ->label(__('assestme.assessments.fields.scope_description'))
                            ->rows(3)
                            ->maxLength(20000)
                            ->disabled(self::isReadOnly(...)),
                        Select::make('site_ids')
                            ->label(__('assestme.assessments.fields.sites'))
                            ->options(fn (WorkspaceAssessment $livewire): array => $livewire->assessmentRecord()->client->sites()->pluck('name', 'id')->all())
                            ->multiple()
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('scope_type') === ScopeType::SelectedSites->value)
                            ->required(fn (Get $get): bool => $get('scope_type') === ScopeType::SelectedSites->value)
                            ->disabled(self::isReadOnly(...)),
                        Select::make('asset_ids')
                            ->label(__('assestme.findings.fields.assets'))
                            ->options(fn (WorkspaceAssessment $livewire): array => $livewire->assessmentRecord()->client->assets()->get()->mapWithKeys(
                                static fn (Asset $asset): array => [$asset->id => $asset->name ?? $asset->hostname ?? "Asset {$asset->id}"],
                            )->all())
                            ->multiple()
                            ->searchable()
                            ->visible(fn (Get $get): bool => $get('scope_type') === ScopeType::SelectedAssets->value)
                            ->required(fn (Get $get): bool => $get('scope_type') === ScopeType::SelectedAssets->value)
                            ->disabled(self::isReadOnly(...)),
                    ]),
                Section::make(__('assestme.workspace.properties.risk'))
                    ->collapsible()
                    ->collapsed()
                    ->compact()
                    ->contained(false)
                    ->divided()
                    ->extraAttributes(['class' => 'assestme-workbench-section assestme-workbench-section--properties'])
                    ->schema([
                        Select::make('consequence_level_id')
                            ->label(__('assestme.templates.fields.consequence'))
                            ->options(fn (): array => ConsequenceLevel::query()->where('is_enabled', true)->orderBy('sort_order')->pluck('label', 'id')->all())
                            ->disabled(self::isReadOnly(...)),
                        Select::make('likelihood_level_id')
                            ->label(__('assestme.templates.fields.likelihood'))
                            ->options(fn (): array => LikelihoodLevel::query()->where('is_enabled', true)->orderBy('sort_order')->pluck('label', 'id')->all())
                            ->disabled(self::isReadOnly(...)),
                        Toggle::make('priority_is_overridden')
                            ->label(__('assestme.findings.fields.priority_override'))
                            ->live()
                            ->disabled(self::isReadOnly(...)),
                        Select::make('priority_level_id')
                            ->label(__('assestme.findings.fields.priority'))
                            ->options(fn (): array => PriorityLevel::query()->where('is_enabled', true)->orderBy('sort_order')->pluck('label', 'id')->all())
                            ->disabled(self::isReadOnly(...)),
                        Textarea::make('priority_rationale')
                            ->label(__('assestme.templates.fields.priority_rationale'))
                            ->rows(3)
                            ->maxLength(20000)
                            ->required(fn (Get $get): bool => (bool) $get('priority_is_overridden'))
                            ->disabled(self::isReadOnly(...)),
                    ]),
                Section::make(__('assestme.workspace.properties.resolution'))
                    ->collapsible()
                    ->collapsed()
                    ->compact()
                    ->contained(false)
                    ->divided()
                    ->extraAttributes(['class' => 'assestme-workbench-section assestme-workbench-section--properties'])
                    ->schema([
                        Textarea::make('resolution_notes')
                            ->label(__('assestme.findings.fields.resolution_notes'))
                            ->rows(4)
                            ->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('solutions')))['resolution_notes'])
                            ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('solutions')))['resolution_notes']))
                            ->disabled(self::isReadOnly(...)),
                    ]),
            ]);
    }

    /** @return array<string, mixed> */
    public static function data(Finding $finding): array
    {
        $finding->load(['sites', 'assets', 'solutions', 'evidences']);

        return [
            ...$finding->only([
                'title', 'problem', 'entrepreneur_notes', 'technical_notes', 'category_id', 'scope_type',
                'scope_description', 'consequence_level_id', 'likelihood_level_id', 'priority_level_id',
                'priority_is_overridden', 'priority_rationale', 'status', 'include_in_report', 'resolution_notes',
            ]),
            'scope_type' => $finding->scope_type->value,
            'status' => $finding->status->value,
            'site_ids' => $finding->sites->pluck('id')->all(),
            'asset_ids' => $finding->assets->pluck('id')->all(),
            'solutions' => $finding->solutions->map(static fn (FindingSolution $solution): array => [
                ...$solution->only([
                    'id', 'external_key', 'title', 'description', 'comparison_notes', 'effort_level_id', 'effort_notes',
                    'estimate_type', 'amount_min', 'amount_max', 'currency_code', 'billing_frequency',
                    'custom_billing_frequency', 'estimate_notes', 'sort_order',
                ]),
                'estimate_type' => $solution->estimate_type->value,
                'billing_frequency' => $solution->billing_frequency->value,
                'is_recommended' => $finding->recommended_solution_id === $solution->id,
                'is_implemented' => $finding->implemented_solution_id === $solution->id,
            ])->all(),
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

    private static function isReadOnly(WorkspaceAssessment $livewire): bool
    {
        return $livewire->isWorkspaceReadOnly() || $livewire->saveStatus === WorkspaceAssessment::STATUS_CONFLICT;
    }

    private static function remaining(?string $state, int $limit): string
    {
        return __('assestme.common.characters_remaining', [
            'count' => max(0, $limit - mb_strlen((string) $state)),
        ]);
    }
}
