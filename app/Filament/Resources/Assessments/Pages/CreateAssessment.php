<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Pages;

use App\Filament\Resources\Assessments\AssessmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssessment extends CreateRecord
{
    protected static string $resource = AssessmentResource::class;

    protected function getRedirectUrl(): string
    {
        return AssessmentResource::getUrl('workspace', ['record' => $this->getRecord()]);
    }
}
