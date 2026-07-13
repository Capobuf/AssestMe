<?php

declare(strict_types=1);

namespace App\Filament\Resources\RiskProfiles\Pages;

use App\Filament\Resources\RiskProfiles\RiskProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListRiskProfiles extends ListRecords
{
    protected static string $resource = RiskProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
