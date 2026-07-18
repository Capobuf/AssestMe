<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assessments\Pages;

use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Support\DeleteAccordingToPolicyAction;
use App\Models\Assessment;
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

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if (! $record instanceof Assessment) {
            throw new \LogicException('The edited record must be an assessment.');
        }

        $data['site_ids'] = $record->sites()->pluck('sites.id')->all();

        return $data;
    }
}
