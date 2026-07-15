<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

use App\Actions\Categories\SaveCategory;
use App\Filament\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveCategory::class)->handle(null, $data);
    }
}
