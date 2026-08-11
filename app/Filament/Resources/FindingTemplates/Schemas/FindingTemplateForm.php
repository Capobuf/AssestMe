<?php

declare(strict_types=1);

namespace App\Filament\Resources\FindingTemplates\Schemas;

use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\ScopeType;
use App\Models\Category;
use App\Models\EffortLevel;
use App\Services\Reporting\EditorialLimits;
use App\Services\Risk\ActiveRiskProfileResolver;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

final class FindingTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('assestme.templates.sections.finding'))->schema([
                TextInput::make('title')->label(__('assestme.templates.fields.title'))->required()->maxLength(EditorialLimits::FINDING_TITLE)
                    ->helperText(fn (?string $state): string => self::remaining($state, EditorialLimits::FINDING_TITLE)),
                Select::make('category_id')->label(__('assestme.templates.fields.category'))->options(fn (): array => Category::query()->where('is_enabled', true)->orderBy('name')->pluck('name', 'id')->all())->searchable()->required(),
                Toggle::make('is_enabled')->label(__('assestme.common.enabled'))->required()->default(true),
                Textarea::make('problem')->label(__('assestme.findings.fields.problem'))->required()->rows(5)->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('solutions')))['problem'])
                    ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('solutions')))['problem']))->columnSpanFull(),
                Textarea::make('entrepreneur_notes')->label(__('assestme.findings.fields.entrepreneur_notes'))->rows(4)->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('solutions')))['entrepreneur_notes'])
                    ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('solutions')))['entrepreneur_notes']))->columnSpanFull(),
                Textarea::make('technical_notes')->label(__('assestme.templates.fields.technical_notes'))->rows(4)->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('solutions')))['technical_notes'])
                    ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('solutions')))['technical_notes']))->columnSpanFull(),
            ])->columns(['default' => 1, 'md' => 2])->columnSpanFull(),
            Section::make(__('assestme.templates.sections.defaults'))->schema([
                Select::make('default_scope_type')->label(__('assestme.templates.fields.scope'))->options(self::scopeOptions())->required(),
                Textarea::make('default_scope_description')->label(__('assestme.templates.fields.scope_description'))->rows(3)->maxLength(20000)->columnSpanFull(),
                Select::make('default_consequence_level_id')->label(__('assestme.templates.fields.consequence'))->options(fn (Get $get): array => app(ActiveRiskProfileResolver::class)->consequenceOptions(self::nullableId($get('default_consequence_level_id'))))->searchable(),
                Select::make('default_likelihood_level_id')->label(__('assestme.templates.fields.likelihood'))->options(fn (Get $get): array => app(ActiveRiskProfileResolver::class)->likelihoodOptions(self::nullableId($get('default_likelihood_level_id'))))->searchable(),
                Select::make('default_priority_level_id')->label(__('assestme.findings.fields.priority'))->options(fn (Get $get): array => app(ActiveRiskProfileResolver::class)->priorityOptions(self::nullableId($get('default_priority_level_id'))))->searchable(),
                Textarea::make('priority_rationale')->label(__('assestme.templates.fields.priority_rationale'))->rows(3)->maxLength(20000)->columnSpanFull(),
            ])->columns(['default' => 1, 'md' => 2])->columnSpanFull(),
            Section::make(__('assestme.templates.sections.solutions'))->schema([
                Repeater::make('solutions')
                    ->label(__('assestme.templates.sections.solutions'))
                    ->schema([
                        TextInput::make('title')->label(__('assestme.templates.fields.title'))->required()->maxLength(EditorialLimits::SOLUTION_TITLE)
                            ->helperText(fn (?string $state): string => self::remaining($state, EditorialLimits::SOLUTION_TITLE)),
                        Toggle::make('is_recommended')->label(__('assestme.templates.fields.recommended'))->required(),
                        Textarea::make('description')->label(__('assestme.common.description'))->required()->rows(4)->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['solution_description'])
                            ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['solution_description']))->columnSpanFull(),
                        Select::make('effort_level_id')->label(__('assestme.findings.fields.effort'))->options(fn (): array => EffortLevel::query()->where('is_enabled', true)->orderBy('sort_order')->pluck('label', 'id')->all())->searchable(),
                        Textarea::make('effort_notes')->label(__('assestme.templates.fields.effort_notes'))->rows(3)->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['effort_notes'])
                            ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['effort_notes'])),
                        Textarea::make('comparison_notes')->label(__('assestme.templates.fields.comparison_notes'))->rows(3)->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['comparison_notes'])
                            ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['comparison_notes'])),
                        Select::make('estimate_type')->label(__('assestme.findings.fields.estimate_type'))->options(EstimateType::options())->required()->live(),
                        Select::make('billing_frequency')->label(__('assestme.templates.fields.billing_frequency'))->options(self::billingOptions())->required()->default(BillingFrequency::OneOff->value)->live(),
                        TextInput::make('amount_min')->label(__('assestme.templates.fields.amount_min'))->numeric()->minValue(0)
                            ->visible(fn (Get $get): bool => self::isMonetary($get('estimate_type'))),
                        TextInput::make('amount_max')->label(__('assestme.templates.fields.amount_max'))->numeric()->minValue(0)
                            ->visible(fn (Get $get): bool => $get('estimate_type') === EstimateType::Range->value),
                        TextInput::make('currency_code')->label(__('assestme.templates.fields.currency'))->length(3)->default('EUR')
                            ->visible(fn (Get $get): bool => self::isMonetary($get('estimate_type'))),
                        TextInput::make('custom_billing_frequency')->label(__('assestme.templates.fields.custom_billing'))->maxLength(120)
                            ->visible(fn (Get $get): bool => $get('billing_frequency') === BillingFrequency::Custom->value),
                        Textarea::make('estimate_notes')->label(__('assestme.findings.fields.estimate_notes'))->rows(3)->maxLength(fn (Get $get): int => EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['estimate_notes'])
                            ->helperText(fn (Get $get, ?string $state): string => self::remaining($state, EditorialLimits::forSolutionCount(count((array) $get('../../solutions')))['estimate_notes']))->columnSpanFull(),
                        TextInput::make('sort_order')->label(__('assestme.common.sort_order'))->numeric()->minValue(0)->required()->default(0),
                        TextInput::make('external_id')->label(__('assestme.templates.fields.external_id'))->disabled()->dehydrated()->maxLength(160),
                    ])
                    ->minItems(1)
                    ->maxItems(3)
                    ->defaultItems(1)
                    ->reorderable()
                    ->columns(['default' => 1, 'md' => 2])
                    ->helperText(__('assestme.templates.solutions_help'))
                    ->required(),
            ])->columnSpanFull(),
            Section::make(__('assestme.templates.sections.advanced'))
                ->collapsed()
                ->schema([
                    TextInput::make('external_id')
                        ->label(__('assestme.templates.fields.external_id'))
                        ->disabled()
                        ->dehydrated(),
                ])
                ->columnSpanFull(),
        ]);
    }

    /** @return array<string, string> */
    private static function scopeOptions(): array
    {
        return collect(ScopeType::cases())->mapWithKeys(fn (ScopeType $scope): array => [$scope->value => __("assestme.scopes.{$scope->value}")])->all();
    }

    /** @return array<string, string> */
    private static function billingOptions(): array
    {
        return collect(BillingFrequency::cases())->mapWithKeys(fn (BillingFrequency $frequency): array => [$frequency->value => __("assestme.billing.{$frequency->value}")])->all();
    }

    private static function isMonetary(mixed $estimateType): bool
    {
        return in_array($estimateType, [EstimateType::Exact->value, EstimateType::Range->value], true);
    }

    private static function remaining(?string $state, int $limit): string
    {
        return __('assestme.common.characters_remaining', [
            'count' => max(0, $limit - mb_strlen((string) $state)),
        ]);
    }

    private static function nullableId(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
