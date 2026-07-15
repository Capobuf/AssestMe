<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Pages;

use App\Actions\Assets\SaveAsset;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Support\DeleteAccordingToPolicyAction;
use App\Models\Asset;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAccordingToPolicyAction::make(successRedirectUrl: AssetResource::getUrl('index'))];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Asset) {
            throw new LogicException('The asset resource received an invalid model.');
        }

        return app(SaveAsset::class)->handle($record, $data);
    }
}
