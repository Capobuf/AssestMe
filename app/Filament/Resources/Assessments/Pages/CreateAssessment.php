<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Pages;

use App\Actions\Assessments\CreateAssessment as CreateAssessmentAction;
use App\Filament\Resources\Assessments\AssessmentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAssessment extends CreateRecord
{
    protected static string $resource = AssessmentResource::class;

    protected function getRedirectUrl(): string
    {
        return AssessmentResource::getUrl('workspace', ['record' => $this->getRecord()]);
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->url(AssessmentResource::getUrl('index'));
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateAssessmentAction::class)($data);
    }
}
