<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('assestme.sites.sections.identity'))
                    ->schema([
                        Select::make('client_id')
                            ->label(__('assestme.sites.fields.client'))
                            ->relationship('client', 'legal_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->label(__('assestme.sites.fields.name'))
                            ->required()
                            ->maxLength(255),
                    ])->columns(['default' => 1, 'md' => 2])->columnSpanFull(),
                Section::make(__('assestme.address.section'))
                    ->schema([
                        TextInput::make('address')
                            ->label(__('assestme.address.fields.address'))
                            ->maxLength(255),
                        TextInput::make('city')
                            ->label(__('assestme.address.fields.city'))
                            ->maxLength(255),
                        TextInput::make('postal_code')
                            ->label(__('assestme.address.fields.postal_code'))
                            ->maxLength(20),
                        TextInput::make('province')
                            ->label(__('assestme.address.fields.province'))
                            ->maxLength(100),
                        TextInput::make('country')
                            ->label(__('assestme.address.fields.country'))
                            ->required()
                            ->default('IT')
                            ->length(2),
                    ])->columns(['default' => 1, 'md' => 2])->columnSpanFull(),
                Section::make(__('assestme.sites.sections.details'))
                    ->schema([
                        Textarea::make('description')
                            ->label(__('assestme.sites.fields.description'))
                            ->rows(4)
                            ->maxLength(20000),
                        Textarea::make('notes')
                            ->label(__('assestme.sites.fields.notes'))
                            ->rows(4)
                            ->maxLength(20000),
                    ])->columns(1)->columnSpanFull(),
            ]);
    }
}
