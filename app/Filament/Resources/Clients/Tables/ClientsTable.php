<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Tables;

use App\Filament\Support\StandardTableEnhancements;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

final class ClientsTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('legal_name')
                    ->label(__('assestme.clients.fields.legal_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('trade_name')
                    ->label(__('assestme.clients.fields.trade_name'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('vat_number')
                    ->label(__('assestme.clients.fields.vat_number'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('city')
                    ->label(__('assestme.address.fields.city'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sites_count')
                    ->label(__('assestme.clients.fields.sites'))
                    ->counts('sites'),
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

        return StandardTableEnhancements::apply($table, StandardTableEnhancements::editableArchivables());
    }
}
