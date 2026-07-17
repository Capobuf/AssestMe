<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Models\ConsequenceLevel;
use App\Models\Finding;
use App\Models\FindingSolution;
use App\Models\LikelihoodLevel;
use App\Models\PriorityLevel;
use App\Models\RiskMatrixEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveFindingDetails
{
    /** @param array<string, mixed> $data */
    public function handle(Finding $finding, array $data): Finding
    {
        $finding->loadMissing('assessment');
        if ($finding->assessment->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages(['assessment' => __('assestme.assessments.errors.read_only')]);
        }

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('tags', 'id')->whereNull('deleted_at')],
            'technical_notes' => ['nullable', 'string', 'max:20000'],
            'scope_type' => ['required', Rule::enum(ScopeType::class)],
            'scope_description' => ['nullable', 'string', 'max:20000'],
            'site_ids' => ['array'],
            'site_ids.*' => ['integer', 'distinct', 'exists:sites,id'],
            'asset_ids' => ['array'],
            'asset_ids.*' => ['integer', 'distinct', 'exists:assets,id'],
            'consequence_level_id' => ['nullable', 'integer', 'exists:consequence_levels,id'],
            'likelihood_level_id' => ['nullable', 'integer', 'exists:likelihood_levels,id'],
            'priority_level_id' => ['nullable', 'integer', 'exists:priority_levels,id'],
            'priority_is_overridden' => ['required', 'boolean'],
            'priority_rationale' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', Rule::enum(FindingStatus::class)],
            'resolution_notes' => ['nullable', 'string', 'max:20000'],
            'solutions' => ['array'],
            'solutions.*.id' => ['nullable', 'integer', 'distinct'],
            'solutions.*.external_key' => ['nullable', 'string', 'max:160'],
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
            'solutions.*.sort_order' => ['required', 'integer', 'min:0'],
            'solutions.*.is_recommended' => ['required', 'boolean'],
            'solutions.*.is_implemented' => ['required', 'boolean'],
        ])->validate();

        $this->validateOwnershipAndAggregate($finding, $validated);

        return DB::transaction(function () use ($finding, $validated): Finding {
            $originalStatus = $finding->status;
            $targetStatus = FindingStatus::from((string) $validated['status']);
            $finding->fill(collect($validated)->except(['tag_ids', 'site_ids', 'asset_ids', 'solutions', 'status'])->all());

            if (! $finding->priority_is_overridden
                && $finding->consequence_level_id !== null
                && $finding->likelihood_level_id !== null) {
                $finding->priority_level_id = RiskMatrixEntry::query()
                    ->where('consequence_level_id', $finding->consequence_level_id)
                    ->where('likelihood_level_id', $finding->likelihood_level_id)
                    ->value('priority_level_id');
                $finding->priority_rationale = null;
            }
            $finding->save();
            $finding->tags()->sync($validated['tag_ids'] ?? []);
            $finding->sites()->sync($validated['site_ids'] ?? []);
            $finding->assets()->sync($validated['asset_ids'] ?? []);

            $keptIds = [];
            $recommended = null;
            $implemented = null;
            foreach ($validated['solutions'] ?? [] as $row) {
                /** @var array<string, mixed> $row */
                $solution = isset($row['id'])
                    ? $finding->solutions()->withTrashed()->findOrFail($row['id'])
                    : new FindingSolution;
                $solution->fill(collect($row)->except(['id', 'is_recommended', 'is_implemented'])->all());
                $solution->finding()->associate($finding);
                $solution->save();
                if ($solution->external_key === null) {
                    $solution->external_key = "manual-{$solution->id}";
                    $solution->save();
                }
                $solution->restore();
                $keptIds[] = (int) $solution->getKey();
                $recommended = $row['is_recommended'] ? $solution : $recommended;
                $implemented = $row['is_implemented'] ? $solution : $implemented;
            }

            $referencedOmitted = collect([$finding->recommended_solution_id, $finding->implemented_solution_id])
                ->filter()
                ->diff($keptIds);
            if ($referencedOmitted->isNotEmpty()) {
                throw ValidationException::withMessages(['solutions' => __('assestme.findings.errors.referenced_solution')]);
            }
            $finding->solutions()->whereNotIn('id', $keptIds)->delete();
            app(SetRecommendedSolution::class)($finding, $recommended);
            app(SetImplementedSolution::class)($finding, $implemented);

            if ($targetStatus !== $originalStatus) {
                app(TransitionFinding::class)->handle($finding->refresh(), $targetStatus);
            }

            return $finding->refresh();
        });
    }

    /** @param array<string, mixed> $validated */
    private function validateOwnershipAndAggregate(Finding $finding, array $validated): void
    {
        $siteIds = $validated['site_ids'] ?? [];
        $assetIds = $validated['asset_ids'] ?? [];
        if ($finding->assessment->client->sites()->whereIn('id', $siteIds)->count() !== count($siteIds)
            || $finding->assessment->client->assets()->whereIn('id', $assetIds)->count() !== count($assetIds)) {
            throw ValidationException::withMessages(['scope' => __('assestme.findings.errors.scope_ownership')]);
        }

        if ($validated['priority_is_overridden'] && blank($validated['priority_rationale'] ?? null)) {
            throw ValidationException::withMessages(['priority_rationale' => __('assestme.findings.errors.override_reason_required')]);
        }

        $profileIds = collect([
            isset($validated['consequence_level_id']) ? ConsequenceLevel::find($validated['consequence_level_id'])?->risk_profile_id : null,
            isset($validated['likelihood_level_id']) ? LikelihoodLevel::find($validated['likelihood_level_id'])?->risk_profile_id : null,
            isset($validated['priority_level_id']) ? PriorityLevel::find($validated['priority_level_id'])?->risk_profile_id : null,
        ])->filter()->unique();
        if ($profileIds->count() > 1) {
            throw ValidationException::withMessages(['priority_level_id' => __('assestme.templates.errors.risk_profile')]);
        }

        $solutions = $validated['solutions'] ?? [];
        $recommendedCount = 0;
        $implementedCount = 0;

        foreach ($solutions as $index => $solution) {
            /** @var array<string, mixed> $solution */
            $recommendedCount += ($solution['is_recommended'] ?? false) === true ? 1 : 0;
            $implementedCount += ($solution['is_implemented'] ?? false) === true ? 1 : 0;
            if (isset($solution['id']) && $finding->solutions()->withTrashed()->whereKey($solution['id'])->doesntExist()) {
                throw ValidationException::withMessages(["solutions.{$index}.id" => __('assestme.findings.errors.solution_ownership')]);
            }
            $type = $solution['estimate_type'] instanceof EstimateType
                ? $solution['estimate_type']->value
                : (string) $solution['estimate_type'];
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
            $billing = $solution['billing_frequency'] instanceof BillingFrequency
                ? $solution['billing_frequency']->value
                : (string) $solution['billing_frequency'];
            if ($billing === BillingFrequency::Custom->value && blank($solution['custom_billing_frequency'] ?? null)) {
                throw ValidationException::withMessages(["solutions.{$index}.custom_billing_frequency" => __('assestme.templates.errors.custom_billing')]);
            }
        }

        if ($solutions !== [] && ($recommendedCount !== 1 || $implementedCount > 1)) {
            throw ValidationException::withMessages(['solutions' => __('assestme.findings.errors.solution_selection')]);
        }
    }
}
