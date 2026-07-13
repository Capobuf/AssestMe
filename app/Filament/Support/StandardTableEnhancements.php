<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Leek\FilamentRightClick\Contracts\ContextMenuEntry;
use Leek\FilamentRightClick\Macros\RegisterMacros;
use Leek\FilamentRightClick\Menu\ContextMenuItem;
use OccTherapist\AdvancedTableExportForFilament\Actions\TableExportHeaderAction;
use OccTherapist\AdvancedTableExportForFilament\Enums\ExportFormat;

final class StandardTableEnhancements
{
    /**
     * @param  list<ContextMenuEntry|Action>  $contextMenuEntries
     */
    public static function apply(Table $table, array $contextMenuEntries): Table
    {
        $entries = RegisterMacros::normalizeRecordEntries($contextMenuEntries);
        RegisterMacros::registerEntries($table, $entries);
        $encoded = RegisterMacros::encodeConfig($entries);
        $enhanced = RegisterMacros::applyContextMenuAttributes($table, [
            'data-filament-right-click-config' => $encoded,
            'data-filament-right-click-record-config' => $encoded,
        ]);

        return $enhanced->headerActions([self::exportAction()]);
    }

    /** @return list<ContextMenuEntry> */
    public static function editable(): array
    {
        return [self::editMenuItem()];
    }

    /** @return list<ContextMenuEntry> */
    public static function editableArchivables(): array
    {
        return [
            self::editMenuItem(),
            ContextMenuItem::for(
                DeleteAction::make('contextDelete')
                    ->label(__('filament-actions::delete.single.label')),
            )
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger'),
            ContextMenuItem::for(
                RestoreAction::make('contextRestore')
                    ->label(__('filament-actions::restore.single.label')),
            )
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('success'),
        ];
    }

    public static function exportAction(): TableExportHeaderAction
    {
        return TableExportHeaderAction::make()
            ->label(__('assestme.exports.action'))
            ->modalHeading(__('assestme.exports.heading'))
            ->modalSubmitActionLabel(__('assestme.exports.download'))
            ->formats([ExportFormat::Csv, ExportFormat::Xlsx])
            ->formatLabel(ExportFormat::Csv, __('assestme.exports.formats.csv'))
            ->formatLabel(ExportFormat::Xlsx, __('assestme.exports.formats.xlsx'))
            ->disablePreview()
            ->formatFieldLabel(__('assestme.exports.fields.format'))
            ->fileNameFieldLabel(__('assestme.exports.fields.filename'))
            ->filterColumnsFieldLabel(__('assestme.exports.fields.columns'));
    }

    private static function editMenuItem(): ContextMenuItem
    {
        return ContextMenuItem::for(
            EditAction::make('contextEdit')
                ->label(__('filament-actions::edit.single.label')),
        )->icon(Heroicon::OutlinedPencilSquare);
    }
}
