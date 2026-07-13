<?php

declare(strict_types=1);

namespace App\Filament\Resources\EffortLevels\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class EffortLevelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('assestme.effort_levels.singular'))->schema([
                TextInput::make('code')->label(__('assestme.common.code'))->required()->maxLength(40)->regex('/^[a-z0-9_]+$/'),
                TextInput::make('label')->label(__('assestme.common.label'))->required()->maxLength(120),
                ColorPicker::make('color')->label(__('assestme.common.color'))->required()->regex('/^#[0-9A-Fa-f]{6}$/'),
                TextInput::make('sort_order')->label(__('assestme.common.sort_order'))->numeric()->minValue(0)->required(),
                Toggle::make('is_enabled')->label(__('assestme.common.enabled'))->required()->default(true),
                Textarea::make('description')->label(__('assestme.common.description'))->rows(4)->maxLength(20000)->columnSpanFull(),
            ])->columns(2),
        ]);
    }
}
