<?php

declare(strict_types=1);

namespace App\Filament\Resources\EffortLevels\Pages;

use App\Actions\EffortLevels\SaveEffortLevel;
use App\Filament\Resources\EffortLevels\EffortLevelResource;
use App\Models\EffortLevel;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EditEffortLevel extends EditRecord
{
    protected static string $resource = EffortLevelResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof EffortLevel) {
            throw new LogicException('The effort-level resource received an invalid model.');
        }

        return app(SaveEffortLevel::class)->handle($record, $data);
    }
}
