<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Pages;

use App\Actions\Assets\SaveAsset;
use App\Filament\Resources\Assets\AssetResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveAsset::class)->handle(null, $data);
    }
}
