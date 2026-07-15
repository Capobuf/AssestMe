<?php

declare(strict_types=1);

namespace App\Filament\Resources\FindingTemplates;

use App\Filament\Resources\FindingTemplates\Pages\CreateFindingTemplate;
use App\Filament\Resources\FindingTemplates\Pages\EditFindingTemplate;
use App\Filament\Resources\FindingTemplates\Pages\ListFindingTemplates;
use App\Filament\Resources\FindingTemplates\Schemas\FindingTemplateForm;
use App\Filament\Resources\FindingTemplates\Tables\FindingTemplatesTable;
use App\Models\FindingTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

final class FindingTemplateResource extends Resource
{
    protected static ?string $model = FindingTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('assestme.templates.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('assestme.templates.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('assestme.templates.plural');
    }

    public static function getNavigationGroup(): string
    {
        return __('assestme.navigation.library');
    }

    public static function form(Schema $schema): Schema
    {
        return FindingTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FindingTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFindingTemplates::route('/'),
            'create' => CreateFindingTemplate::route('/create'),
            'edit' => EditFindingTemplate::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
