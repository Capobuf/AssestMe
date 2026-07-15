<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class TagForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assestme.tags.singular'))
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
                ])->columns(2),
        ]);
    }
}
