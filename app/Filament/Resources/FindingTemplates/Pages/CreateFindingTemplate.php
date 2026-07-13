<?php

declare(strict_types=1);

namespace App\Filament\Resources\FindingTemplates\Pages;

use App\Actions\Templates\SaveFindingTemplate;
use App\Filament\Resources\FindingTemplates\FindingTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateFindingTemplate extends CreateRecord
{
    protected static string $resource = FindingTemplateResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveFindingTemplate::class)->handle(null, $data);
    }
}
