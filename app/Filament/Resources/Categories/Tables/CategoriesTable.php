<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Tables;

use App\Filament\Support\StandardTableEnhancements;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

final class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')->label(__('assestme.common.sort_order'))->sortable(),
                TextColumn::make('name')->label(__('assestme.common.name'))->searchable()->sortable(),
                TextColumn::make('slug')->label(__('assestme.common.slug'))->searchable()->toggleable(),
                ColorColumn::make('color')->label(__('assestme.common.color')),
                IconColumn::make('is_enabled')->label(__('assestme.common.enabled'))->boolean(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make(), RestoreBulkAction::make()]),
            ]);

        return StandardTableEnhancements::apply($table, StandardTableEnhancements::editableArchivables());
    }
}
