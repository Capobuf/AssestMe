<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Enums\FindingStatus;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingSolution;
use App\Models\FindingTemplate;
use App\Models\Tag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CopyTemplateToAssessment
{
    public function __construct(private readonly IncrementAssessmentVersion $incrementAssessmentVersion) {}

    public function __invoke(Assessment $assessment, FindingTemplate $template): Finding
    {
        if ($assessment->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages(['assessment' => __('assestme.assessments.errors.read_only')]);
        }

        if ($template->trashed() || ! $template->is_enabled) {
            throw ValidationException::withMessages(['template' => __('assestme.templates.errors.disabled')]);
        }

        $template->loadMissing(['tags', 'solutions']);
        $activeSolutions = $template->solutions->whereNull('deleted_at')->values();
        if ($activeSolutions->isEmpty() || $activeSolutions->where('is_recommended', true)->count() !== 1) {
            throw ValidationException::withMessages(['template' => __('assestme.templates.errors.recommended_count')]);
        }

        $expectedVersion = (int) $assessment->lock_version;

        return Cache::lock("assessment:{$assessment->getKey()}:save", 10)->block(
            5,
            fn (): Finding => DB::transaction(function () use ($assessment, $template, $activeSolutions, $expectedVersion): Finding {
                $persistedAssessment = Assessment::query()->findOrFail($assessment->getKey());
                if ($persistedAssessment->status !== AssessmentStatus::Draft) {
                    throw ValidationException::withMessages(['assessment' => __('assestme.assessments.errors.read_only')]);
                }

                $finding = $persistedAssessment->findings()->create([
                    'source_template_id' => $template->getKey(),
                    'title' => $template->title,
                    'category_id' => $template->category_id,
                    'problem' => $template->problem,
                    'entrepreneur_notes' => $template->entrepreneur_notes,
                    'technical_notes' => $template->technical_notes,
                    'scope_type' => $template->default_scope_type,
                    'scope_description' => $template->default_scope_description,
                    'consequence_level_id' => $template->default_consequence_level_id,
                    'likelihood_level_id' => $template->default_likelihood_level_id,
                    'priority_level_id' => $template->default_priority_level_id,
                    'priority_rationale' => $template->priority_rationale,
                    'status' => FindingStatus::Open,
                    'include_in_report' => true,
                    'sort_order' => ((int) $persistedAssessment->findings()->max('sort_order')) + 1,
                ]);
                $finding->tags()->sync($template->tags->map(static fn (Tag $tag): int => $tag->id)->all());

                $recommended = null;
                foreach ($activeSolutions as $templateSolution) {
                    $solution = $finding->solutions()->create([
                        'external_key' => $templateSolution->external_id,
                        'title' => $templateSolution->title,
                        'description' => $templateSolution->description,
                        'comparison_notes' => $templateSolution->comparison_notes,
                        'effort_level_id' => $templateSolution->effort_level_id,
                        'effort_notes' => $templateSolution->effort_notes,
                        'estimate_type' => $templateSolution->estimate_type,
                        'amount_min' => $templateSolution->amount_min,
                        'amount_max' => $templateSolution->amount_max,
                        'currency_code' => $templateSolution->currency_code,
                        'billing_frequency' => $templateSolution->billing_frequency,
                        'custom_billing_frequency' => $templateSolution->custom_billing_frequency,
                        'estimate_notes' => $templateSolution->estimate_notes,
                        'sort_order' => $templateSolution->sort_order,
                    ]);

                    if ($templateSolution->is_recommended) {
                        $recommended = $solution;
                    }
                }

                if (! $recommended instanceof FindingSolution) {
                    throw ValidationException::withMessages(['template' => __('assestme.templates.errors.recommended_count')]);
                }

                $finding->recommendedSolution()->associate($recommended);
                $finding->save();
                ($this->incrementAssessmentVersion)($assessment, $expectedVersion);

                return $finding->refresh();
            }, attempts: 1),
        );
    }
}
