<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\WorkspaceSaveData;
use App\Data\Assessments\WorkspaceSaveResult;
use App\Enums\EffortLevel;
use App\Enums\EstimateType;
use App\Enums\FindingPriority;
use App\Enums\FindingStatus;
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

final class SaveAssessmentWorkspace
{
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
            'payload.findings' => ['present', 'array', 'max:100'],
            'payload.findings.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'payload.findings.*._temporary_uuid' => ['required', 'uuid', 'distinct'],
            'payload.findings.*.title' => ['nullable', 'string', 'max:255'],
            'payload.findings.*.problem' => ['nullable', 'string', 'max:20000'],
            'payload.findings.*.entrepreneur_notes' => ['nullable', 'string', 'max:20000'],
            'payload.findings.*.recommended_solution_summary' => ['nullable', 'string', 'max:20000'],
            'payload.findings.*.priority' => ['nullable', Rule::enum(FindingPriority::class)],
            'payload.findings.*.effort' => ['nullable', Rule::enum(EffortLevel::class)],
            'payload.findings.*.estimate_type' => ['nullable', Rule::enum(EstimateType::class)],
            'payload.findings.*.estimate_notes' => ['nullable', 'string', 'max:20000'],
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

        $appliedVersion = $request->expectedVersion + 1;
        $updated = Assessment::query()
            ->whereKey($assessment->getKey())
            ->where('lock_version', $request->expectedVersion)
            ->update([
                'title' => $request->payload['assessment']['title'],
                'assessment_date' => $request->payload['assessment']['assessment_date'],
                'lock_version' => $appliedVersion,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            $actualVersion = (int) Assessment::query()
                ->whereKey($assessment->getKey())
                ->value('lock_version');

            throw new AssessmentVersionConflict($request->expectedVersion, $actualVersion);
        }

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
                'recommended_solution_summary' => $findingData['recommended_solution_summary'] ?? null,
                'priority' => $findingData['priority'] ?? null,
                'effort' => $findingData['effort'] ?? null,
                'estimate_type' => $findingData['estimate_type'] ?? null,
                'estimate_notes' => $findingData['estimate_notes'] ?? null,
                'status' => $findingData['status'],
                'include_in_report' => $findingData['include_in_report'],
                'sort_order' => $index + 1,
            ]);

            if (! $finding->exists) {
                $finding->assessment()->associate($assessment);
            }

            $finding->save();
            $keptIds[] = (int) $finding->getKey();
            $idMap[(string) $findingData['_temporary_uuid']] = (int) $finding->getKey();
        }

        $assessment->findings()->whereNotIn('id', $keptIds)->delete();

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
