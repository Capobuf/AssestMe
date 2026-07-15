<?php

declare(strict_types=1);

namespace App\Filament\Resources\EffortLevels\Tables;

use App\Filament\Support\StandardTableEnhancements;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class EffortLevelsTable
{
    public static function configure(Table $table): Table
    {
        $table = $table->defaultSort('sort_order')->columns([
            TextColumn::make('sort_order')->label(__('assestme.common.sort_order'))->sortable(),
            TextColumn::make('code')->label(__('assestme.common.code'))->searchable(),
            TextColumn::make('label')->label(__('assestme.common.label'))->searchable(),
            ColorColumn::make('color')->label(__('assestme.common.color')),
            IconColumn::make('is_enabled')->label(__('assestme.common.enabled'))->boolean(),
        ])->recordActions([EditAction::make()]);

        return StandardTableEnhancements::apply($table, StandardTableEnhancements::editable());
    }
}
