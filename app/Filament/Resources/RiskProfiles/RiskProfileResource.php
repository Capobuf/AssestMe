<?php

declare(strict_types=1);

namespace App\Filament\Resources\RiskProfiles;

use App\Filament\Clusters\SettingsCluster;
use App\Filament\Resources\RiskProfiles\Pages\CreateRiskProfile;
use App\Filament\Resources\RiskProfiles\Pages\EditRiskProfile;
use App\Filament\Resources\RiskProfiles\Pages\ListRiskProfiles;
use App\Filament\Resources\RiskProfiles\Schemas\RiskProfileForm;
use App\Filament\Resources\RiskProfiles\Tables\RiskProfilesTable;
use App\Models\RiskProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

final class RiskProfileResource extends Resource
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $model = RiskProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 6;

    public static function getNavigationLabel(): string
    {
        return __('assestme.risk.navigation');
    }

    public static function getModelLabel(): string
    {
        return __('assestme.risk.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('assestme.risk.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return RiskProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RiskProfilesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRiskProfiles::route('/'),
            'create' => CreateRiskProfile::route('/create'),
            'edit' => EditRiskProfile::route('/{record}/edit'),
        ];
    }
}
