<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Tables;

use App\Filament\Support\DeleteAccordingToPolicyAction;
use App\Filament\Support\StandardTableEnhancements;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

final class TagsTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('name')->label(__('assestme.common.name'))->searchable()->sortable(),
                TextColumn::make('slug')->label(__('assestme.common.slug'))->searchable()->toggleable(),
                ColorColumn::make('color')->label(__('assestme.common.color')),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make(), DeleteAccordingToPolicyAction::make(), RestoreAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([RestoreBulkAction::make()]),
            ]);

        return StandardTableEnhancements::apply($table, StandardTableEnhancements::editableArchivables());
    }
}
