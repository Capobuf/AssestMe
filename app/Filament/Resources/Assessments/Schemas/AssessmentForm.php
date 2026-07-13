<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Schemas;

use App\Enums\AssessmentStatus;
use App\Enums\ScopeType;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                    ->required(),
                TextInput::make('title')
                    ->label(__('assestme.assessments.fields.title'))
                    ->required()
                    ->maxLength(255),
                DatePicker::make('assessment_date')
                    ->label(__('assestme.assessments.fields.date'))
                    ->required()
                    ->default(today()),
                Select::make('scope_type')
                    ->label(__('assestme.assessments.fields.scope'))
                    ->options([
                        ScopeType::Organization->value => __('assestme.scopes.organization'),
                        ScopeType::SelectedSites->value => __('assestme.scopes.selected_sites'),
                        ScopeType::Network->value => __('assestme.scopes.network'),
                        ScopeType::SelectedAssets->value => __('assestme.scopes.selected_assets'),
                        ScopeType::Custom->value => __('assestme.scopes.custom'),
                    ])
                    ->default(ScopeType::Organization->value)
                    ->required(),
                Textarea::make('scope_description')
                    ->label(__('assestme.assessments.fields.scope_description'))
                    ->rows(3)
                    ->maxLength(20000),
                Hidden::make('status')->default(AssessmentStatus::Draft->value),
            ]);
    }
}
