<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\WorkspaceSaveData;
use App\Data\Assessments\WorkspaceSaveResult;
use App\Enums\AssessmentStatus;
use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Exceptions\AssessmentVersionConflict;
use App\Exceptions\IdempotencyKeyMismatch;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\WorkspaceSaveRequest;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class SaveAssessmentWorkspace
{
    public function __construct(
        private SaveAssessmentPatch $saveAssessmentPatch,
        private ReorderFindings $reorderFindings,
    ) {}

    /**
     * @throws AssessmentVersionConflict
     * @throws IdempotencyKeyMismatch
     * @throws ValidationException
     */
    public function __invoke(Assessment $assessment, WorkspaceSaveData $request): WorkspaceSaveResult
    {
        $this->validateRequest($assessment, $request);

        if ($replay = $this->findReplay($assessment, $request)) {
            return $replay;
        }

        return Cache::lock("assessment:{$assessment->getKey()}:save", 10)->block(
            5,
            fn (): WorkspaceSaveResult => DB::transaction(
                fn (): WorkspaceSaveResult => $this->persist($assessment, $request),
                attempts: 1,
            ),
        );
    }

    /** @throws ValidationException */
    private function validateRequest(Assessment $assessment, WorkspaceSaveData $request): void
    {
        if ($assessment->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages([
                'assessment_id' => __('assestme.assessments.errors.read_only'),
            ]);
        }

        Validator::make([
            'request_id' => $request->requestId,
            'tab_id' => $request->tabId,
            'expected_version' => $request->expectedVersion,
            'payload_sha256' => $request->payloadSha256,
            'payload' => $request->payload,
        ], [
            'request_id' => ['required', 'uuid'],
            'tab_id' => ['required', 'uuid'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'payload_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'payload.assessment.title' => ['required', 'string', 'max:255'],
            'payload.assessment.assessment_date' => ['required', 'date_format:Y-m-d'],
            'payload.assessment.report_title_override' => ['nullable', 'string', 'max:255'],
            'payload.assessment.scope_type' => ['required', Rule::in([
                ScopeType::Organization->value,
                ScopeType::SelectedSites->value,
                ScopeType::Custom->value,
            ])],
            'payload.assessment.scope_description' => ['nullable', 'string', 'max:20000'],
            'payload.assessment.introduction' => ['nullable', 'string', 'max:20000'],
            'payload.assessment.executive_summary' => ['nullable', 'string', 'max:20000'],
            'payload.assessment.methodology_notes' => ['nullable', 'string', 'max:20000'],
            'payload.assessment.site_ids' => ['array'],
            'payload.assessment.site_ids.*' => ['integer', 'distinct', 'exists:sites,id'],
            'payload.findings' => ['present', 'array', 'max:100'],
            'payload.findings.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'payload.findings.*._temporary_uuid' => ['required', 'uuid', 'distinct'],
            'payload.findings.*.title' => ['nullable', 'string', 'max:255'],
            'payload.findings.*.problem' => ['nullable', 'string', 'max:20000'],
            'payload.findings.*.entrepreneur_notes' => ['nullable', 'string', 'max:20000'],
            'payload.findings.*.status' => ['required', Rule::enum(FindingStatus::class)],
            'payload.findings.*.include_in_report' => ['required', 'boolean'],
        ])->validate();

        if (! Str::isUuid($request->requestId) || ! Str::isUuid($request->tabId)) {
            throw ValidationException::withMessages([
                'request_id' => __('assestme.workspace.errors.invalid_request'),
            ]);
        }

        if (! hash_equals(WorkspaceSaveData::hashPayload($request->payload), $request->payloadSha256)) {
            throw ValidationException::withMessages([
                'payload_sha256' => __('assestme.workspace.errors.payload_hash'),
            ]);
        }

        if (! $assessment->exists) {
            throw ValidationException::withMessages([
                'assessment_id' => __('assestme.workspace.errors.assessment_missing'),
            ]);
        }

        $siteIds = $request->payload['assessment']['site_ids'] ?? [];
        if ($assessment->client->sites()->whereIn('id', $siteIds)->count() !== count($siteIds)) {
            throw ValidationException::withMessages([
                'assessment.site_ids' => __('assestme.assessments.errors.site_ownership'),
            ]);
        }

        $scope = ScopeType::from((string) $request->payload['assessment']['scope_type']);
        if ($scope === ScopeType::SelectedSites && $siteIds === []) {
            throw ValidationException::withMessages([
                'assessment.site_ids' => __('assestme.assessments.errors.site_required'),
            ]);
        }
        if ($scope !== ScopeType::SelectedSites && $siteIds !== []) {
            throw ValidationException::withMessages([
                'assessment.site_ids' => __('assestme.assessments.errors.site_scope'),
            ]);
        }
        if ($scope === ScopeType::Custom && blank($request->payload['assessment']['scope_description'] ?? null)) {
            throw ValidationException::withMessages([
                'assessment.scope_description' => __('validation.required', [
                    'attribute' => __('assestme.assessments.fields.scope_description'),
                ]),
            ]);
        }
    }

    private function findReplay(Assessment $assessment, WorkspaceSaveData $request): ?WorkspaceSaveResult
    {
        $stored = WorkspaceSaveRequest::query()->find($request->requestId);

        if (! $stored) {
            return null;
        }

        if ((int) $stored->assessment_id !== (int) $assessment->getKey() ||
            ! hash_equals((string) $stored->payload_hash, $request->payloadSha256)) {
            throw new IdempotencyKeyMismatch;
        }

        /** @var array{applied_version: int, id_map: array<string, int>} $response */
        $response = $stored->response;

        return WorkspaceSaveResult::fromStoredResponse($response);
    }

    private function persist(Assessment $assessment, WorkspaceSaveData $request): WorkspaceSaveResult
    {
        if ($replay = $this->findReplay($assessment, $request)) {
            return $replay;
        }

        /** @var Collection<int, Finding> $existingFindings */
        $existingFindings = $assessment->findings()->get()->keyBy('id');
        $requestedIds = collect($request->payload['findings'])
            ->pluck('id')
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id);

        if ($requestedIds->diff($existingFindings->keys())->isNotEmpty()) {
            throw ValidationException::withMessages([
                'findings' => __('assestme.workspace.errors.finding_ownership'),
            ]);
        }

        $appliedVersion = ($this->saveAssessmentPatch)($assessment, $request);

        /** @var array<string, int> $idMap */
        $idMap = [];
        $keptIds = [];

        foreach ($request->payload['findings'] as $index => $findingData) {
            $finding = isset($findingData['id'])
                ? $existingFindings->get((int) $findingData['id'])
                : new Finding;

            if (! $finding instanceof Finding) {
                throw ValidationException::withMessages([
                    "findings.{$index}.id" => __('assestme.workspace.errors.finding_ownership'),
                ]);
            }

            $finding->fill([
                'title' => $findingData['title'] ?? null,
                'problem' => $findingData['problem'] ?? null,
                'entrepreneur_notes' => $findingData['entrepreneur_notes'] ?? null,
                'status' => $findingData['status'],
                'include_in_report' => $findingData['include_in_report'],
            ]);

            if (! $finding->exists) {
                $finding->assessment()->associate($assessment);
            }

            $finding->save();
            $keptIds[] = (int) $finding->getKey();
            $idMap[(string) $findingData['_temporary_uuid']] = (int) $finding->getKey();
        }

        $assessment->findings()->whereNotIn('id', $keptIds)->delete();
        ($this->reorderFindings)($assessment, $keptIds);

        $result = new WorkspaceSaveResult($appliedVersion, $idMap);

        WorkspaceSaveRequest::query()->create([
            'request_id' => $request->requestId,
            'assessment_id' => $assessment->getKey(),
            'expected_version' => $request->expectedVersion,
            'applied_version' => $appliedVersion,
            'payload_hash' => $request->payloadSha256,
            'response' => $result->toArray(),
            'created_at' => now(),
        ]);

        return $result;
    }
}
