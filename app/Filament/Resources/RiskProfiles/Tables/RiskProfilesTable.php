<?php

declare(strict_types=1);

namespace App\Filament\Resources\RiskProfiles\Tables;

use App\Filament\Support\StandardTableEnhancements;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class RiskProfilesTable
{
    public static function configure(Table $table): Table
    {
        $table = $table->columns([
            TextColumn::make('code')->label(__('assestme.common.code'))->searchable()->sortable(),
            TextColumn::make('label')->label(__('assestme.common.label'))->searchable()->sortable(),
            IconColumn::make('is_default')->label(__('assestme.risk.fields.default'))->boolean(),
            IconColumn::make('is_enabled')->label(__('assestme.common.enabled'))->boolean(),
        ])->recordActions([EditAction::make()]);

        return StandardTableEnhancements::apply($table, StandardTableEnhancements::editable());
    }
}
