<?php

declare(strict_types=1);

namespace App\Filament\Resources\AssetTypes\Tables;

use App\Filament\Support\StandardTableEnhancements;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class AssetTypesTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label(__('assestme.common.sort_order'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('assestme.asset_types.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label(__('assestme.asset_types.fields.slug'))
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_enabled')
                    ->label(__('assestme.common.enabled'))
                    ->boolean(),
                TextColumn::make('assets_count')
                    ->label(__('assestme.assets.plural'))
                    ->counts('assets'),
            ])
            ->recordActions([EditAction::make()]);

        return StandardTableEnhancements::apply($table, StandardTableEnhancements::editable());
    }
}
