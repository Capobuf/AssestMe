<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\ScopeType;
use App\Models\ConsequenceLevel;
use App\Models\FindingTemplate;
use App\Models\FindingTemplateSolution;
use App\Models\LikelihoodLevel;
use App\Models\PriorityLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveFindingTemplate
{
    /** @param array<string, mixed> $data */
    public function handle(?FindingTemplate $template, array $data): FindingTemplate
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'external_id' => ['required', 'string', 'min:3', 'max:160', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', Rule::unique('finding_templates', 'external_id')->ignore($template?->getKey())],
            'title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')->whereNull('deleted_at')],
            'problem' => ['required', 'string', 'max:20000'],
            'entrepreneur_notes' => ['nullable', 'string', 'max:20000'],
            'technical_notes' => ['nullable', 'string', 'max:20000'],
            'default_scope_type' => ['required', Rule::enum(ScopeType::class)],
            'default_scope_description' => ['nullable', 'string', 'max:20000'],
            'default_consequence_level_id' => ['nullable', 'integer', 'exists:consequence_levels,id'],
            'default_likelihood_level_id' => ['nullable', 'integer', 'exists:likelihood_levels,id'],
            'default_priority_level_id' => ['nullable', 'integer', 'exists:priority_levels,id'],
            'priority_rationale' => ['nullable', 'string', 'max:20000'],
            'is_enabled' => ['required', 'boolean'],
            'solutions' => ['required', 'array', 'min:1'],
            'solutions.*.id' => ['nullable', 'integer'],
            'solutions.*.external_id' => ['required', 'string', 'min:3', 'max:160', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', 'distinct'],
            'solutions.*.title' => ['required', 'string', 'max:255'],
            'solutions.*.description' => ['required', 'string', 'max:20000'],
            'solutions.*.comparison_notes' => ['nullable', 'string', 'max:20000'],
            'solutions.*.effort_level_id' => ['nullable', 'integer', 'exists:effort_levels,id'],
            'solutions.*.effort_notes' => ['nullable', 'string', 'max:20000'],
            'solutions.*.estimate_type' => ['required', Rule::enum(EstimateType::class)],
            'solutions.*.amount_min' => ['nullable', 'numeric', 'between:0,999999999.99'],
            'solutions.*.amount_max' => ['nullable', 'numeric', 'between:0,999999999.99'],
            'solutions.*.currency_code' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/'],
            'solutions.*.billing_frequency' => ['required', Rule::enum(BillingFrequency::class)],
            'solutions.*.custom_billing_frequency' => ['nullable', 'string', 'max:120'],
            'solutions.*.estimate_notes' => ['nullable', 'string', 'max:20000'],
            'solutions.*.is_recommended' => ['required', 'boolean'],
            'solutions.*.sort_order' => ['required', 'integer', 'between:0,100000'],
        ])->validate();

        $this->validateAggregate($template, $validated);

        return DB::transaction(function () use ($template, $validated): FindingTemplate {
            $record = $template ?? new FindingTemplate;
            $record->fill(collect($validated)->except(['tag_ids', 'solutions'])->all());
            $record->save();
            $record->tags()->sync($validated['tag_ids'] ?? []);

            $keptIds = [];
            foreach ($validated['solutions'] as $row) {
                /** @var array<string, mixed> $row */
                $solution = isset($row['id'])
                    ? FindingTemplateSolution::withTrashed()->where('finding_template_id', $record->getKey())->findOrFail($row['id'])
                    : FindingTemplateSolution::withTrashed()->firstOrNew([
                        'finding_template_id' => $record->getKey(),
                        'external_id' => $row['external_id'],
                    ]);
                $solution->fill($row);
                $solution->finding_template_id = $record->getKey();
                $solution->save();
                $solution->restore();
                $keptIds[] = $solution->getKey();
            }

            // Missing rows are soft-deleted so detached assessment snapshots remain traceable.
            $record->solutions()->whereNotIn('id', $keptIds)->delete();

            return $record->refresh();
        });
    }

    /** @param array<string, mixed> $validated */
    private function validateAggregate(?FindingTemplate $template, array $validated): void
    {
        $recommended = 0;
        foreach ($validated['solutions'] as $solution) {
            /** @var array<string, mixed> $solution */
            $recommended += ($solution['is_recommended'] ?? false) === true ? 1 : 0;
        }
        if ($recommended !== 1) {
            throw ValidationException::withMessages(['solutions' => __('assestme.templates.errors.recommended_count')]);
        }

        $profileIds = collect([
            isset($validated['default_consequence_level_id']) ? ConsequenceLevel::find($validated['default_consequence_level_id'])?->risk_profile_id : null,
            isset($validated['default_likelihood_level_id']) ? LikelihoodLevel::find($validated['default_likelihood_level_id'])?->risk_profile_id : null,
            isset($validated['default_priority_level_id']) ? PriorityLevel::find($validated['default_priority_level_id'])?->risk_profile_id : null,
        ])->filter()->unique();
        if ($profileIds->count() > 1) {
            throw ValidationException::withMessages(['default_priority_level_id' => __('assestme.templates.errors.risk_profile')]);
        }

        foreach ($validated['solutions'] as $index => $solution) {
            /** @var array<string, mixed> $solution */
            $type = (string) $solution['estimate_type'];
            $monetary = in_array($type, [EstimateType::Exact->value, EstimateType::Range->value], true);
            if ($monetary && (($solution['amount_min'] ?? null) === null || ($solution['currency_code'] ?? null) === null)) {
                throw ValidationException::withMessages(["solutions.{$index}.amount_min" => __('assestme.templates.errors.monetary_amount')]);
            }
            if (! $monetary && (($solution['amount_min'] ?? null) !== null || ($solution['amount_max'] ?? null) !== null || ($solution['currency_code'] ?? null) !== null)) {
                throw ValidationException::withMessages(["solutions.{$index}.amount_min" => __('assestme.templates.errors.non_monetary_amount')]);
            }
            if ($type === EstimateType::Range->value && (float) $solution['amount_min'] > (float) ($solution['amount_max'] ?? -1)) {
                throw ValidationException::withMessages(["solutions.{$index}.amount_max" => __('assestme.templates.errors.invalid_range')]);
            }
            if ($type === EstimateType::Exact->value && ($solution['amount_max'] ?? null) !== null) {
                throw ValidationException::withMessages(["solutions.{$index}.amount_max" => __('assestme.templates.errors.exact_max')]);
            }
            if ($solution['billing_frequency'] === BillingFrequency::Custom->value && blank($solution['custom_billing_frequency'] ?? null)) {
                throw ValidationException::withMessages(["solutions.{$index}.custom_billing_frequency" => __('assestme.templates.errors.custom_billing')]);
            }
            if (isset($solution['id']) && $template?->solutions()->withTrashed()->whereKey($solution['id'])->doesntExist()) {
                throw ValidationException::withMessages(["solutions.{$index}.id" => __('assestme.templates.errors.solution_ownership')]);
            }
        }
    }
}
