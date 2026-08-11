<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Models\FindingTemplate;
use App\Models\FindingTemplateSolution;
use JsonException;

final class ExportFindingTemplates
{
    /** @throws JsonException */
    public function __invoke(bool $includeInactive = false): string
    {
        $templates = FindingTemplate::query()
            ->with(['category', 'solutions.effortLevel', 'defaultConsequenceLevel', 'defaultLikelihoodLevel', 'defaultPriorityLevel'])
            ->when(! $includeInactive, fn ($query) => $query->where('is_enabled', true))
            ->orderBy('external_id')
            ->get()
            ->map(fn (FindingTemplate $template): array => [
                'external_id' => $template->external_id,
                'title' => $template->title,
                'category' => $template->category->name,
                'problem' => $template->problem,
                'entrepreneur_notes' => $template->entrepreneur_notes,
                'technical_notes' => $template->technical_notes,
                'default_scope_type' => $template->default_scope_type->value,
                'default_scope_description' => $template->default_scope_description,
                'consequence' => $template->defaultConsequenceLevel?->code,
                'likelihood' => $template->defaultLikelihoodLevel?->code,
                'priority' => $template->defaultPriorityLevel?->code,
                'priority_rationale' => $template->priority_rationale,
                'active' => $template->is_enabled,
                'solutions' => $template->solutions->map(fn (FindingTemplateSolution $solution): array => [
                    'external_id' => $solution->external_id,
                    'title' => $solution->title,
                    'description' => $solution->description,
                    'comparison_notes' => $solution->comparison_notes,
                    'effort' => $solution->effortLevel?->code,
                    'effort_notes' => $solution->effort_notes,
                    'estimate_type' => $solution->estimate_type->value,
                    'amount_min' => $solution->amount_min === null ? null : (float) $solution->amount_min,
                    'amount_max' => $solution->amount_max === null ? null : (float) $solution->amount_max,
                    'currency_code' => $solution->currency_code,
                    'billing_frequency' => $solution->billing_frequency->value,
                    'custom_billing_frequency' => $solution->custom_billing_frequency,
                    'estimate_notes' => $solution->estimate_notes,
                    'recommended' => $solution->is_recommended,
                    'sort_order' => $solution->sort_order,
                ])->all(),
            ])->all();

        return json_encode(
            ['schema_version' => 2, 'templates' => $templates],
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        )."\n";
    }
}
