<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Actions\Templates\SaveFindingTemplate;
use App\Data\Templates\FindingTemplateSyncResult;
use App\Enums\AssessmentStatus;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Services\Templates\FindingTemplateContent;
use App\Services\Templates\FindRelatedFindingTemplates;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SaveFindingAsTemplate
{
    public function __construct(
        private readonly IncrementAssessmentVersion $incrementAssessmentVersion,
        private readonly SaveFindingTemplate $saveFindingTemplate,
        private readonly FindingTemplateContent $content,
        private readonly FindRelatedFindingTemplates $relatedTemplates,
    ) {}

    public function create(Finding $finding, int $expectedVersion): FindingTemplateSyncResult
    {
        return Cache::lock("assessment:{$finding->assessment_id}:save", 10)->block(
            5,
            fn (): FindingTemplateSyncResult => DB::transaction(function () use ($finding, $expectedVersion): FindingTemplateSyncResult {
                $current = $this->lockedFinding($finding);
                if ($this->relatedTemplates->forFinding($current)['exact'] instanceof FindingTemplate) {
                    throw ValidationException::withMessages([
                        'template' => __('assestme.template_learning.errors.exact_exists'),
                    ]);
                }
                $payload = $this->content->templatePayload($current);
                $saved = $this->saveFindingTemplate->handleWithSolutionIds(null, $payload);
                $map = $this->orderedExternalIdMap($current, $saved->solutionExternalIds);

                $this->realignSolutionKeys($current, $map);
                $template = $saved->template->load('solutions');
                $current->forceFill([
                    'source_template_id' => $template->getKey(),
                    'source_template_fingerprint' => $this->content->fingerprint($template),
                ])->save();
                $appliedVersion = ($this->incrementAssessmentVersion)($current->assessment, $expectedVersion);

                return new FindingTemplateSyncResult(
                    template: $template,
                    finding: $current->refresh(),
                    appliedVersion: $appliedVersion,
                    outcome: FindingTemplateSyncResult::CREATED,
                );
            }, attempts: 1),
        );
    }

    public function linkExact(
        Finding $finding,
        FindingTemplate $candidate,
        int $expectedVersion,
    ): FindingTemplateSyncResult {
        return Cache::lock("assessment:{$finding->assessment_id}:save", 10)->block(
            5,
            fn (): FindingTemplateSyncResult => DB::transaction(function () use ($finding, $candidate, $expectedVersion): FindingTemplateSyncResult {
                $current = $this->lockedFinding($finding);
                $template = FindingTemplate::query()
                    ->with('solutions')
                    ->lockForUpdate()
                    ->findOrFail($candidate->getKey());

                if (! $this->content->isSemanticallyIdentical($current, $template)) {
                    throw ValidationException::withMessages([
                        'template' => __('assestme.template_learning.errors.exact_changed'),
                    ]);
                }

                $this->realignSolutionKeys(
                    $current,
                    $this->content->matchSemanticallyIdenticalSolutions($current, $template),
                );
                $current->forceFill([
                    'source_template_id' => $template->getKey(),
                    'source_template_fingerprint' => $this->content->fingerprint($template),
                ])->save();
                $appliedVersion = ($this->incrementAssessmentVersion)($current->assessment, $expectedVersion);

                return new FindingTemplateSyncResult(
                    template: $template,
                    finding: $current->refresh(),
                    appliedVersion: $appliedVersion,
                    outcome: FindingTemplateSyncResult::LINKED,
                );
            }, attempts: 1),
        );
    }

    /**
     * @param  list<string>  $externalIds
     * @return array<int, string>
     */
    private function orderedExternalIdMap(Finding $finding, array $externalIds): array
    {
        $solutions = $this->content->orderedFindingSolutions($finding);
        if ($solutions->count() !== count($externalIds)) {
            throw new \RuntimeException('Authoritative template solution mapping is incomplete.');
        }

        $map = [];
        foreach ($solutions as $index => $solution) {
            $map[(int) $solution->getKey()] = $externalIds[$index];
        }

        return $map;
    }

    /** @param array<int, string> $externalIdsByFindingSolutionId */
    public function realignSolutionKeys(Finding $finding, array $externalIdsByFindingSolutionId): void
    {
        $solutions = $this->content->orderedFindingSolutions($finding);
        if ($solutions->pluck('id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all()
            !== collect(array_keys($externalIdsByFindingSolutionId))->sort()->values()->all()) {
            throw new \RuntimeException('Every active Finding solution must map to one template solution.');
        }

        $operation = Str::replace('-', '', (string) Str::uuid());
        foreach ($solutions as $solution) {
            $solution->forceFill([
                'external_key' => "template-sync-{$operation}-{$solution->getKey()}",
            ])->save();
        }
        foreach ($solutions as $solution) {
            $solution->forceFill([
                'external_key' => $externalIdsByFindingSolutionId[(int) $solution->getKey()],
            ])->save();
        }
    }

    private function lockedFinding(Finding $finding): Finding
    {
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

        return $current;
    }
}
