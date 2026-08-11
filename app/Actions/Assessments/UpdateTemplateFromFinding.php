<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Actions\Templates\SaveFindingTemplate;
use App\Data\Templates\FindingTemplateSyncResult;
use App\Enums\AssessmentStatus;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Services\Templates\FindingTemplateContent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateTemplateFromFinding
{
    public function __construct(
        private readonly IncrementAssessmentVersion $incrementAssessmentVersion,
        private readonly SaveFindingTemplate $saveFindingTemplate,
        private readonly SaveFindingAsTemplate $saveFindingAsTemplate,
        private readonly FindingTemplateContent $content,
    ) {}

    /** @return array{fields:array<string,bool>,solutions:array{added:int,changed:int,removed:int}} */
    public function preview(Finding $finding): array
    {
        $template = $this->sourceTemplate($finding, lock: false);
        $currentFingerprint = $this->content->fingerprint($template);
        if ($finding->source_template_fingerprint === null) {
            if (! $this->content->isSemanticallyIdentical($finding, $template)) {
                throw ValidationException::withMessages([
                    'template' => __('assestme.template_learning.errors.legacy_unverifiable'),
                ]);
            }
        } elseif (! hash_equals($finding->source_template_fingerprint, $currentFingerprint)) {
            throw ValidationException::withMessages([
                'template' => __('assestme.template_learning.errors.fingerprint_conflict'),
            ]);
        }

        return $this->content->diff($finding, $template);
    }

    public function handle(Finding $finding, int $expectedVersion): FindingTemplateSyncResult
    {
        return Cache::lock("assessment:{$finding->assessment_id}:save", 10)->block(
            5,
            fn (): FindingTemplateSyncResult => DB::transaction(function () use ($finding, $expectedVersion): FindingTemplateSyncResult {
                $current = Finding::query()
                    ->with(['assessment', 'solutions'])
                    ->where('assessment_id', $finding->assessment_id)
                    ->lockForUpdate()
                    ->findOrFail($finding->getKey());
                if ($current->assessment->status !== AssessmentStatus::Draft) {
                    throw ValidationException::withMessages([
                        'assessment' => __('assestme.assessments.errors.read_only'),
                    ]);
                }

                $template = $this->sourceTemplate($current, lock: true);
                $currentFingerprint = $this->content->fingerprint($template);
                $knownFingerprint = $current->source_template_fingerprint;

                if ($knownFingerprint === null) {
                    if (! $this->content->isSemanticallyIdentical($current, $template)) {
                        throw ValidationException::withMessages([
                            'template' => __('assestme.template_learning.errors.legacy_unverifiable'),
                        ]);
                    }

                    $this->saveFindingAsTemplate->realignSolutionKeys(
                        $current,
                        $this->content->matchSemanticallyIdenticalSolutions($current, $template),
                    );
                    $current->forceFill(['source_template_fingerprint' => $currentFingerprint])->save();
                    $appliedVersion = ($this->incrementAssessmentVersion)($current->assessment, $expectedVersion);

                    return new FindingTemplateSyncResult(
                        template: $template,
                        finding: $current->refresh(),
                        appliedVersion: $appliedVersion,
                        outcome: FindingTemplateSyncResult::ALREADY_ALIGNED,
                    );
                }

                if (! hash_equals($knownFingerprint, $currentFingerprint)) {
                    throw ValidationException::withMessages([
                        'template' => __('assestme.template_learning.errors.fingerprint_conflict'),
                    ]);
                }

                $diff = $this->content->diff($current, $template);
                if (! $this->content->hasDifferences($diff)) {
                    return new FindingTemplateSyncResult(
                        template: $template,
                        finding: $current,
                        appliedVersion: $expectedVersion,
                        outcome: FindingTemplateSyncResult::ALREADY_ALIGNED,
                    );
                }

                $payload = $this->content->templatePayload($current, $template);
                $saved = $this->saveFindingTemplate->handleWithSolutionIds($template, $payload);
                $solutionMap = [];
                foreach ($this->content->orderedFindingSolutions($current) as $index => $solution) {
                    $solutionMap[(int) $solution->getKey()] = $saved->solutionExternalIds[$index];
                }
                $this->saveFindingAsTemplate->realignSolutionKeys($current, $solutionMap);
                $template = $saved->template->load('solutions');
                $current->forceFill([
                    'source_template_fingerprint' => $this->content->fingerprint($template),
                ])->save();
                $appliedVersion = ($this->incrementAssessmentVersion)($current->assessment, $expectedVersion);

                return new FindingTemplateSyncResult(
                    template: $template,
                    finding: $current->refresh(),
                    appliedVersion: $appliedVersion,
                    outcome: FindingTemplateSyncResult::UPDATED,
                );
            }, attempts: 1),
        );
    }

    private function sourceTemplate(Finding $finding, bool $lock): FindingTemplate
    {
        if ($finding->source_template_id === null) {
            throw ValidationException::withMessages([
                'template' => __('assestme.template_learning.errors.source_missing'),
            ]);
        }

        $query = FindingTemplate::withTrashed()->with('solutions');
        if ($lock) {
            $query->lockForUpdate();
        }
        $template = $query->find($finding->source_template_id);
        if (! $template instanceof FindingTemplate || $template->trashed()) {
            throw ValidationException::withMessages([
                'template' => __('assestme.template_learning.errors.source_deleted'),
            ]);
        }

        return $template;
    }
}
