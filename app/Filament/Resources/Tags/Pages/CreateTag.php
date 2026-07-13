<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Pages;

use App\Actions\Tags\SaveTag;
use App\Filament\Resources\Tags\TagResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveTag::class)->handle(null, $data);
    }
}
