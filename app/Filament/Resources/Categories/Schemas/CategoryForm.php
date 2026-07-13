<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assestme.categories.singular'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('assestme.common.name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('slug')
                        ->label(__('assestme.common.slug'))
                        ->helperText(__('assestme.common.slug_help'))
                        ->maxLength(160),
                    ColorPicker::make('color')
                        ->label(__('assestme.common.color'))
                        ->regex('/^#[0-9A-Fa-f]{6}$/'),
                    TextInput::make('sort_order')
                        ->label(__('assestme.common.sort_order'))
                        ->numeric()
                        ->minValue(0)
                        ->required()
                        ->default(0),
                    Toggle::make('is_enabled')
                        ->label(__('assestme.common.enabled'))
                        ->required()
                        ->default(true),
                    Textarea::make('description')
                        ->label(__('assestme.common.description'))
                        ->maxLength(20000)
                        ->rows(4)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }
}
