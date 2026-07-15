<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

use App\Actions\Categories\SaveCategory;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Support\DeleteAccordingToPolicyAction;
use App\Models\Category;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAccordingToPolicyAction::make(successRedirectUrl: CategoryResource::getUrl('index'))];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Category) {
            throw new LogicException('The category resource received an invalid model.');
        }

        return app(SaveCategory::class)->handle($record, $data);
    }
}
