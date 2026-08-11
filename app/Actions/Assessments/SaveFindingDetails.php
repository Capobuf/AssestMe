<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Actions\Evidence\PrepareEvidenceFiles;
use App\Data\Assessments\FindingSaveData;
use App\Data\Assessments\FindingSaveResult;
use App\Data\Evidence\PendingEvidenceFileData;
use App\Data\Evidence\PreparedEvidenceFileData;
use App\Enums\AssessmentStatus;
use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\EvidenceType;
use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Exceptions\AssessmentVersionConflict;
use App\Exceptions\IdempotencyKeyMismatch;
use App\Models\ConsequenceLevel;
use App\Models\Finding;
use App\Models\FindingSolution;
use App\Models\LikelihoodLevel;
use App\Models\PriorityLevel;
use App\Models\WorkspaceSaveRequest;
use App\Services\Reporting\EditorialLimits;
use App\Services\Risk\ActiveRiskProfileResolver;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SaveFindingDetails
{
    public function __construct(
        private readonly IncrementAssessmentVersion $incrementAssessmentVersion,
        private readonly PrepareEvidenceFiles $prepareEvidenceFiles,
        private readonly ActiveRiskProfileResolver $activeRiskProfileResolver,
    ) {}

    /**
     * @throws IdempotencyKeyMismatch
     * @throws AssessmentVersionConflict
     * @throws LockTimeoutException
     * @throws ValidationException
     */
    public function __invoke(Finding $finding, FindingSaveData $request): FindingSaveResult
    {
        $this->validateEnvelope($finding, $request);

        if ($replay = $this->findReplay($finding, $request)) {
            return $replay;
        }

        $current = Finding::query()
            ->with(['assessment.client', 'sites', 'assets', 'solutions', 'evidences'])
            ->findOrFail($finding->getKey());
        if ($current->assessment->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages(['assessment' => __('assestme.assessments.errors.read_only')]);
        }
        $validated = $this->validatePayload(Arr::except($request->payload, '_evidence'));
        $this->validateOwnershipAndAggregate($current, $validated);
        $this->validateEvidenceEnvelope($request);
        $preparedFiles = $this->prepareEvidenceFiles->handle($current, $request->evidenceFiles);

        try {
            $result = Cache::lock("assessment:{$finding->assessment_id}:save", 10)->block(
                5,
                fn (): FindingSaveResult => DB::transaction(
                    fn (): FindingSaveResult => $this->persistRequest($finding, $request, $preparedFiles),
                    attempts: 1,
                ),
            );
            if ($result->idempotentReplay) {
                $this->prepareEvidenceFiles->compensate($preparedFiles, $current, $request->requestId);
            }

            return $result;
        } catch (Throwable $exception) {
            $this->prepareEvidenceFiles->compensate($preparedFiles, $current, $request->requestId);

            throw $exception;
        }
    }

    private function validateEnvelope(Finding $finding, FindingSaveData $request): void
    {
        Validator::make([
            'request_id' => $request->requestId,
            'tab_id' => $request->tabId,
            'expected_version' => $request->expectedVersion,
            'payload_sha256' => $request->payloadSha256,
        ], [
            'request_id' => ['required', 'uuid'],
            'tab_id' => ['required', 'uuid'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'payload_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
        ])->validate();

        if (! $finding->exists || ! Str::isUuid($request->requestId) || ! Str::isUuid($request->tabId)) {
            throw ValidationException::withMessages([
                'request_id' => __('assestme.workspace.errors.invalid_request'),
            ]);
        }

        if (! hash_equals(FindingSaveData::hashPayload($request->payload), $request->payloadSha256)) {
            throw ValidationException::withMessages([
                'payload_sha256' => __('assestme.workspace.errors.payload_hash'),
            ]);
        }
    }

    private function findReplay(Finding $finding, FindingSaveData $request): ?FindingSaveResult
    {
        $stored = WorkspaceSaveRequest::query()->find($request->requestId);

        if (! $stored) {
            return null;
        }

        /** @var array<string, mixed> $response */
        $response = $stored->getAttribute('response');
        if ((int) $stored->assessment_id !== $finding->assessment_id
            || ! hash_equals((string) $stored->payload_hash, $request->payloadSha256)
            || ($response['operation'] ?? null) !== 'save_finding'
            || (int) ($response['finding_id'] ?? 0) !== (int) $finding->getKey()) {
            throw new IdempotencyKeyMismatch;
        }

        $saved = Finding::query()->findOrFail($finding->getKey());

        return new FindingSaveResult(
            finding: $saved,
            appliedVersion: (int) $stored->applied_version,
            idempotentReplay: true,
            evidenceIds: array_values(array_map('intval', (array) ($response['evidence_ids'] ?? []))),
        );
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validatePayload(array $data): array
    {
        $solutionCount = count(is_array($data['solutions'] ?? null) ? $data['solutions'] : []);
        $limits = EditorialLimits::forSolutionCount($solutionCount);

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'title' => ['nullable', 'string', 'max:'.EditorialLimits::FINDING_TITLE],
            'problem' => ['nullable', 'string', 'max:'.$limits['problem']],
            'entrepreneur_notes' => ['nullable', 'string', 'max:'.$limits['entrepreneur_notes']],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'technical_notes' => ['nullable', 'string', 'max:'.$limits['technical_notes']],
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
            'include_in_report' => ['required', 'boolean'],
            'resolution_notes' => ['nullable', 'string', 'max:'.$limits['resolution_notes']],
            'solutions' => ['array', 'max:3'],
            'solutions.*.id' => ['nullable', 'integer', 'distinct'],
            'solutions.*.external_key' => ['nullable', 'string', 'max:160'],
            'solutions.*.title' => ['required', 'string', 'max:'.EditorialLimits::SOLUTION_TITLE],
            'solutions.*.description' => ['required', 'string', 'max:'.$limits['solution_description']],
            'solutions.*.comparison_notes' => ['nullable', 'string', 'max:'.$limits['comparison_notes']],
            'solutions.*.effort_level_id' => ['nullable', 'integer', 'exists:effort_levels,id'],
            'solutions.*.effort_notes' => ['nullable', 'string', 'max:'.$limits['effort_notes']],
            'solutions.*.estimate_type' => ['required', Rule::enum(EstimateType::class)],
            'solutions.*.amount_min' => ['nullable', 'numeric', 'between:0,999999999.99'],
            'solutions.*.amount_max' => ['nullable', 'numeric', 'between:0,999999999.99'],
            'solutions.*.currency_code' => ['nullable', 'string', 'regex:/^[A-Z]{3}$/'],
            'solutions.*.billing_frequency' => ['required', Rule::enum(BillingFrequency::class)],
            'solutions.*.custom_billing_frequency' => ['nullable', 'string', 'max:120'],
            'solutions.*.estimate_notes' => ['nullable', 'string', 'max:'.$limits['estimate_notes']],
            'solutions.*.sort_order' => ['required', 'integer', 'min:0'],
            'solutions.*.is_recommended' => ['required', 'boolean'],
            'solutions.*.is_implemented' => ['required', 'boolean'],
        ])->validate();

        return $validated;
    }

    /** @param list<PreparedEvidenceFileData> $preparedFiles */
    private function persistRequest(
        Finding $finding,
        FindingSaveData $request,
        array $preparedFiles,
    ): FindingSaveResult {
        if ($replay = $this->findReplay($finding, $request)) {
            return $replay;
        }

        $current = Finding::query()
            ->with(['assessment.client', 'sites', 'assets', 'solutions'])
            ->where('assessment_id', $finding->assessment_id)
            ->findOrFail($finding->getKey());

        if ($current->assessment->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages(['assessment' => __('assestme.assessments.errors.read_only')]);
        }

        $validated = $this->validatePayload(Arr::except($request->payload, '_evidence'));
        $this->validateOwnershipAndAggregate($current, $validated);
        $saved = $this->persistAggregate($current, $validated);
        $evidenceIds = $this->persistEvidence($saved, $preparedFiles, $request);
        $appliedVersion = ($this->incrementAssessmentVersion)($current->assessment, $request->expectedVersion);

        WorkspaceSaveRequest::query()->create([
            'request_id' => $request->requestId,
            'assessment_id' => $current->assessment_id,
            'expected_version' => $request->expectedVersion,
            'applied_version' => $appliedVersion,
            'payload_hash' => $request->payloadSha256,
            'response' => [
                'operation' => 'save_finding',
                'finding_id' => (int) $saved->getKey(),
                'evidence_ids' => $evidenceIds,
                'applied_version' => $appliedVersion,
            ],
            'created_at' => now(),
        ]);

        return new FindingSaveResult($saved, $appliedVersion, evidenceIds: $evidenceIds);
    }

    private function validateEvidenceEnvelope(FindingSaveData $request): void
    {
        $expected = [
            'files' => array_map(
                static fn (PendingEvidenceFileData $file): array => $file->normalizedPayload(),
                $request->evidenceFiles,
            ),
            'url' => $request->evidenceUrl?->normalizedPayload(),
        ];
        $actual = $request->payload['_evidence'] ?? ['files' => [], 'url' => null];
        if ($actual !== $expected) {
            throw ValidationException::withMessages([
                'payload_sha256' => __('assestme.workspace.errors.payload_hash'),
            ]);
        }

        if ($request->evidenceUrl === null) {
            return;
        }

        Validator::make($request->evidenceUrl->normalizedPayload(), [
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'caption' => ['nullable', 'string', 'max:20000'],
            'internal_notes' => ['nullable', 'string', 'max:20000'],
            'include_in_report' => ['required', 'boolean'],
        ])->validate();

        $parts = parse_url($request->evidenceUrl->url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages(['evidence.url' => __('assestme.evidence.errors.url_credentials')]);
        }
    }

    /**
     * @param  list<PreparedEvidenceFileData>  $preparedFiles
     * @return list<int>
     */
    private function persistEvidence(
        Finding $finding,
        array $preparedFiles,
        FindingSaveData $request,
    ): array {
        $evidenceIds = [];
        $sortOrder = (int) $finding->evidences()->max('sort_order');

        foreach ($preparedFiles as $prepared) {
            $pending = $prepared->pending;
            $evidence = $finding->evidences()->create([
                'type' => EvidenceType::File,
                'title' => $pending->title,
                'file_path' => $prepared->finalPath,
                'original_filename' => $pending->file->getClientOriginalName(),
                'caption' => $pending->caption,
                'internal_notes' => $pending->internalNotes,
                'include_in_report' => $pending->includeInReport,
                'mime_type' => $pending->mimeType,
                'size_bytes' => $pending->sizeBytes,
                'sha256' => $pending->sha256,
                'sort_order' => ++$sortOrder,
            ]);
            $evidenceIds[] = (int) $evidence->getKey();
        }

        if ($request->evidenceUrl !== null) {
            $url = $request->evidenceUrl;
            $evidence = $finding->evidences()->create([
                'type' => EvidenceType::Url,
                'title' => $url->title,
                'url' => $url->url,
                'caption' => $url->caption,
                'internal_notes' => $url->internalNotes,
                'include_in_report' => $url->includeInReport,
                'sort_order' => ++$sortOrder,
            ]);
            $evidenceIds[] = (int) $evidence->getKey();
        }

        return $evidenceIds;
    }

    /** @param array<string, mixed> $validated */
    private function persistAggregate(Finding $finding, array $validated): Finding
    {
        $originalStatus = $finding->status;
        $targetStatus = FindingStatus::from((string) $validated['status']);
        $priorityIsOverridden = (bool) $validated['priority_is_overridden'];
        $priorityLevelId = isset($validated['priority_level_id']) ? (int) $validated['priority_level_id'] : null;
        $priorityRationale = isset($validated['priority_rationale']) ? (string) $validated['priority_rationale'] : null;
        $finding->fill(collect($validated)->except([
            'site_ids', 'asset_ids', 'solutions', 'status',
            'priority_level_id', 'priority_is_overridden', 'priority_rationale',
        ])->all());
        $finding->save();

        if ($priorityIsOverridden && $priorityLevelId !== null) {
            app(OverrideFindingPriority::class)(
                $finding,
                PriorityLevel::query()->findOrFail($priorityLevelId),
                (string) $priorityRationale,
            );
        } elseif (! $priorityIsOverridden
            && $finding->consequence_level_id !== null
            && $finding->likelihood_level_id !== null) {
            app(RecalculateFindingPriority::class)->handle($finding);
        } else {
            $finding->forceFill([
                'priority_level_id' => $priorityLevelId,
                'priority_is_overridden' => $priorityIsOverridden,
                'priority_rationale' => $priorityRationale,
            ])->save();
        }
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
            app(TransitionFindingStatus::class)($finding->refresh(), $targetStatus);
        }

        return $finding->refresh();
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

        $scope = $validated['scope_type'] instanceof ScopeType
            ? $validated['scope_type']
            : ScopeType::from((string) $validated['scope_type']);
        app(ValidateFindingScopeSelection::class)(
            $scope,
            count($siteIds),
            isset($validated['scope_description']) ? (string) $validated['scope_description'] : null,
        );

        if ($validated['priority_is_overridden'] && blank($validated['priority_rationale'] ?? null)) {
            throw ValidationException::withMessages(['priority_rationale' => __('assestme.findings.errors.override_reason_required')]);
        }

        $submittedClassification = [
            'consequence' => isset($validated['consequence_level_id']) ? (int) $validated['consequence_level_id'] : null,
            'likelihood' => isset($validated['likelihood_level_id']) ? (int) $validated['likelihood_level_id'] : null,
            'priority' => isset($validated['priority_level_id']) ? (int) $validated['priority_level_id'] : null,
        ];
        $persistedClassification = [
            'consequence' => $finding->consequence_level_id === null ? null : (int) $finding->consequence_level_id,
            'likelihood' => $finding->likelihood_level_id === null ? null : (int) $finding->likelihood_level_id,
            'priority' => $finding->priority_level_id === null ? null : (int) $finding->priority_level_id,
        ];
        if ($submittedClassification !== $persistedClassification) {
            $this->activeRiskProfileResolver->assertSelectableClassification($submittedClassification, 'priority_level_id');
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
