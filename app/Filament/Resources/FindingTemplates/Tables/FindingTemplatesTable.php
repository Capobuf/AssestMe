<?php

declare(strict_types=1);

namespace App\Filament\Resources\FindingTemplates\Tables;

use App\Filament\Support\StandardTableEnhancements;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

final class FindingTemplatesTable
{
    public static function configure(Table $table): Table
    {
        $table = $table->columns([
            TextColumn::make('external_id')->label(__('assestme.templates.fields.external_id'))->searchable()->sortable(),
            TextColumn::make('title')->label(__('assestme.templates.fields.title'))->searchable()->sortable()->wrap(),
            TextColumn::make('category.name')->label(__('assestme.templates.fields.category'))->sortable(),
            TextColumn::make('solutions_count')->label(__('assestme.templates.fields.solution_count'))->counts('solutions'),
            IconColumn::make('is_enabled')->label(__('assestme.common.enabled'))->boolean(),
        ])->filters([TrashedFilter::make()])->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()]);

        return StandardTableEnhancements::apply($table, StandardTableEnhancements::editableArchivables());
    }
}
