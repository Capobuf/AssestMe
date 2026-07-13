<?php

declare(strict_types=1);

namespace App\Filament\Resources\EffortLevels\Pages;

use App\Actions\EffortLevels\SaveEffortLevel;
use App\Filament\Resources\EffortLevels\EffortLevelResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateEffortLevel extends CreateRecord
{
    protected static string $resource = EffortLevelResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveEffortLevel::class)->handle(null, $data);
    }
}
