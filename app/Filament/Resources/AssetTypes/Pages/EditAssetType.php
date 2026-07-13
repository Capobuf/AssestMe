<?php

declare(strict_types=1);

namespace App\Filament\Resources\AssetTypes\Pages;

use App\Actions\AssetTypes\SaveAssetType;
use App\Filament\Resources\AssetTypes\AssetTypeResource;
use App\Models\AssetType;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class EditAssetType extends EditRecord
{
    protected static string $resource = AssetTypeResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof AssetType) {
            throw new LogicException('The asset type resource received an invalid model.');
        }

        return app(SaveAssetType::class)->handle($record, $data);
    }
}
