<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Pages;

use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Support\DeleteAccordingToPolicyAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditAssessment extends EditRecord
{
    protected static string $resource = AssessmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('workspace')
                ->label(__('assestme.workspace.open'))
                ->url(AssessmentResource::getUrl('workspace', ['record' => $this->getRecord()])),
            DeleteAccordingToPolicyAction::make(successRedirectUrl: AssessmentResource::getUrl('index')),
        ];
    }
}
