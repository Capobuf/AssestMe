<?php

declare(strict_types=1);

namespace App\Filament\Resources\EffortLevels\Pages;

use App\Filament\Resources\EffortLevels\EffortLevelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListEffortLevels extends ListRecords
{
    protected static string $resource = EffortLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
