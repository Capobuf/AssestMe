<?php

declare(strict_types=1);

namespace App\Filament\Resources\EffortLevels;

use App\Filament\Resources\EffortLevels\Pages\CreateEffortLevel;
use App\Filament\Resources\EffortLevels\Pages\EditEffortLevel;
use App\Filament\Resources\EffortLevels\Pages\ListEffortLevels;
use App\Filament\Resources\EffortLevels\Schemas\EffortLevelForm;
use App\Filament\Resources\EffortLevels\Tables\EffortLevelsTable;
use App\Models\EffortLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class EffortLevelResource extends Resource
{
    protected static ?string $model = EffortLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('assestme.effort_levels.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('assestme.effort_levels.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('assestme.effort_levels.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('assestme.navigation.configuration');
    }

    public static function form(Schema $schema): Schema
    {
        return EffortLevelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EffortLevelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEffortLevels::route('/'),
            'create' => CreateEffortLevel::route('/create'),
            'edit' => EditEffortLevel::route('/{record}/edit'),
        ];
    }
}
