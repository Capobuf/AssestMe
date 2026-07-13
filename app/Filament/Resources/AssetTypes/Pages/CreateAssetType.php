<?php

declare(strict_types=1);

namespace App\Filament\Resources\AssetTypes\Pages;

use App\Actions\AssetTypes\SaveAssetType;
use App\Filament\Resources\AssetTypes\AssetTypeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAssetType extends CreateRecord
{
    protected static string $resource = AssetTypeResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveAssetType::class)->handle(null, $data);
    }
}
