<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sites\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

final class SitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('assestme.sites.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.legal_name')
                    ->label(__('assestme.sites.fields.client'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->label(__('assestme.address.fields.city'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('province')
                    ->label(__('assestme.address.fields.province'))
                    ->toggleable(),
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
