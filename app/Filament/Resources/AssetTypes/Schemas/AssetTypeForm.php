<?php

declare(strict_types=1);

namespace App\Filament\Resources\AssetTypes\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class AssetTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assestme.asset_types.section'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('assestme.asset_types.fields.name'))
                        ->required()
                        ->maxLength(120),
                    TextInput::make('slug')
                        ->label(__('assestme.asset_types.fields.slug'))
                        ->helperText(__('assestme.asset_types.slug_help'))
                        ->maxLength(160),
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
