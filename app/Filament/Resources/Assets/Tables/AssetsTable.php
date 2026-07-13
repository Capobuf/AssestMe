<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

final class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('assestme.assets.fields.name'))
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('assetType.name')
                    ->label(__('assestme.assets.fields.asset_type'))
                    ->sortable(),
                TextColumn::make('client.legal_name')
                    ->label(__('assestme.assets.fields.client'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('site.name')
                    ->label(__('assestme.assets.fields.site'))
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('hostname')
                    ->label(__('assestme.assets.fields.hostname'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('ip_address')
                    ->label(__('assestme.assets.fields.ip_address'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('serial_number')
                    ->label(__('assestme.assets.fields.serial_number'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
