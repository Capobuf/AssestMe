<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Schemas;

use App\Actions\Assessments\GenerateAssessmentTitle;
use App\Enums\AssessmentStatus;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Client;
use Carbon\CarbonInterface;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class AssessmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('client_id')
                    ->label(__('assestme.assessments.fields.client'))
                    ->relationship('client', 'legal_name', modifyQueryUsing: fn ($query) => $query->whereNull('deleted_at'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                        $siteIds = array_values(array_filter(
                            is_array($get('site_ids')) ? $get('site_ids') : [],
                            static fn (mixed $siteId): bool => Client::query()
                                ->whereKey($state)
                                ->whereHas('sites', fn ($query) => $query->whereKey($siteId))
                                ->exists(),
                        ));
                        $set('site_ids', $siteIds);
                        self::refreshAutomaticTitle($get, $set);
                    })
                    ->required(),
                TextInput::make('title')
                    ->label(__('assestme.assessments.fields.title'))
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateHydrated(function (Set $set, ?Assessment $record): void {
                        $set('_title_manually_edited', $record !== null);
                    })
                    ->afterStateUpdated(function (Set $set): void {
                        $set('_title_manually_edited', true);
                    }),
                DatePicker::make('assessment_date')
                    ->label(__('assestme.assessments.fields.date'))
                    ->required()
                    ->default(today())
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshAutomaticTitle($get, $set)),
                Select::make('scope_type')
                    ->label(__('assestme.assessments.fields.scope'))
                    ->options([
                        ScopeType::Organization->value => __('assestme.scopes.organization'),
                        ScopeType::SelectedSites->value => __('assestme.scopes.site'),
                        ScopeType::Custom->value => __('assestme.scopes.custom'),
                    ])
                    ->default(ScopeType::Organization->value)
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                        if ($state !== ScopeType::SelectedSites->value) {
                            $set('site_ids', []);
                        }
                        self::refreshAutomaticTitle($get, $set);
                    })
                    ->required(),
                Select::make('site_ids')
                    ->label(__('assestme.assessments.fields.sites'))
                    ->options(fn (Get $get): array => Client::query()
                        ->find($get('client_id'))
                        ?->sites()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all() ?? [])
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->visible(fn (Get $get): bool => $get('scope_type') === ScopeType::SelectedSites->value)
                    ->required(fn (Get $get): bool => $get('scope_type') === ScopeType::SelectedSites->value)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshAutomaticTitle($get, $set)),
                Textarea::make('scope_description')
                    ->label(fn (Get $get): string => $get('scope_type') === ScopeType::Custom->value
                        ? __('assestme.assessments.fields.scope_description')
                        : __('assestme.assessments.fields.scope_notes'))
                    ->rows(3)
                    ->maxLength(20000)
                    ->required(fn (Get $get): bool => $get('scope_type') === ScopeType::Custom->value)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::refreshAutomaticTitle($get, $set)),
                Hidden::make('status')->default(AssessmentStatus::Draft->value),
                Hidden::make('_title_manually_edited')->default(false)->dehydrated(false),
            ]);
    }

    private static function refreshAutomaticTitle(Get $get, Set $set): void
    {
        if ((bool) $get('_title_manually_edited')) {
            return;
        }

        $client = Client::query()->find($get('client_id'));
        $scope = ScopeType::tryFrom((string) $get('scope_type'));
        $date = $get('assessment_date');
        if ($client === null || $scope === null || (! is_string($date) && ! $date instanceof CarbonInterface) || $date === '') {
            return;
        }

        $set('title', app(GenerateAssessmentTitle::class)(
            $client,
            $scope,
            $date,
            is_array($get('site_ids')) ? $get('site_ids') : [],
            is_string($get('scope_description')) ? $get('scope_description') : null,
        ));
    }
}
