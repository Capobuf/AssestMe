<?php

declare(strict_types=1);

namespace App\Filament\Resources\FindingTemplates\Pages;

use App\Actions\Templates\SaveFindingTemplate;
use App\Filament\Resources\FindingTemplates\FindingTemplateResource;
use App\Models\FindingTemplate;
use App\Models\FindingTemplateSolution;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class EditFindingTemplate extends EditRecord
{
    protected static string $resource = FindingTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $template = $this->getRecord();
        if (! $template instanceof FindingTemplate) {
            throw new LogicException('The finding-template resource received an invalid model.');
        }

        $data['tag_ids'] = $template->tags()->pluck('tags.id')->all();
        $data['solutions'] = $template->solutions()->get()->map(static fn (FindingTemplateSolution $solution): array => [
            'id' => $solution->getKey(),
            'external_id' => $solution->external_id,
            'title' => $solution->title,
            'description' => $solution->description,
            'comparison_notes' => $solution->comparison_notes,
            'effort_level_id' => $solution->effort_level_id,
            'effort_notes' => $solution->effort_notes,
            'estimate_type' => $solution->estimate_type->value,
            'amount_min' => $solution->amount_min,
            'amount_max' => $solution->amount_max,
            'currency_code' => $solution->currency_code,
            'billing_frequency' => $solution->billing_frequency->value,
            'custom_billing_frequency' => $solution->custom_billing_frequency,
            'estimate_notes' => $solution->estimate_notes,
            'is_recommended' => $solution->is_recommended,
            'sort_order' => $solution->sort_order,
        ])->all();

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof FindingTemplate) {
            throw new LogicException('The finding-template resource received an invalid model.');
        }

        return app(SaveFindingTemplate::class)->handle($record, $data);
    }
}
