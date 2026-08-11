<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\ReorderFindingsData;
use App\Enums\AssessmentStatus;
use App\Exceptions\IdempotencyKeyMismatch;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\WorkspaceSaveRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ReorderFindings
{
    public function __construct(private readonly IncrementAssessmentVersion $incrementAssessmentVersion) {}

    /** @throws ValidationException */
    public function __invoke(Assessment $assessment, ReorderFindingsData $request): int
    {
        $this->validateEnvelope($request);

        if ($replay = $this->findReplay($assessment, $request)) {
            return $replay;
        }

        return Cache::lock("assessment:{$assessment->getKey()}:save", 10)->block(
            5,
            fn (): int => DB::transaction(
                fn (): int => $this->persist($assessment, $request),
                attempts: 1,
            ),
        );
    }

    private function validateEnvelope(ReorderFindingsData $request): void
    {
        Validator::make([
            'request_id' => $request->requestId,
            'expected_version' => $request->expectedVersion,
            'payload_sha256' => $request->payloadSha256,
            'ordered_finding_ids' => $request->orderedFindingIds,
        ], [
            'request_id' => ['required', 'uuid'],
            'expected_version' => ['required', 'integer', 'min:0'],
            'payload_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'ordered_finding_ids' => ['required', 'array', 'min:1'],
            'ordered_finding_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
        ])->validate();

        if (! Str::isUuid($request->requestId)
            || ! hash_equals(ReorderFindingsData::hashPayload($request->orderedFindingIds), $request->payloadSha256)) {
            throw ValidationException::withMessages([
                'payload_sha256' => __('assestme.workspace.errors.payload_hash'),
            ]);
        }
    }

    private function findReplay(Assessment $assessment, ReorderFindingsData $request): ?int
    {
        $stored = WorkspaceSaveRequest::query()->find($request->requestId);
        if (! $stored) {
            return null;
        }

        /** @var array<string, mixed> $response */
        $response = $stored->getAttribute('response');
        if ((int) $stored->assessment_id !== (int) $assessment->getKey()
            || ! hash_equals((string) $stored->payload_hash, $request->payloadSha256)
            || ($response['operation'] ?? null) !== 'reorder_findings') {
            throw new IdempotencyKeyMismatch;
        }

        return (int) $stored->applied_version;
    }

    private function persist(Assessment $assessment, ReorderFindingsData $request): int
    {
        if ($replay = $this->findReplay($assessment, $request)) {
            return $replay;
        }

        $persisted = Assessment::query()->findOrFail($assessment->getKey());
        if ($persisted->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages(['assessment_id' => __('assestme.assessments.errors.read_only')]);
        }

        $currentIds = $persisted->findings()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $currentSet = $currentIds;
        $requestedSet = $request->orderedFindingIds;
        sort($currentSet);
        sort($requestedSet);
        if ($currentSet !== $requestedSet) {
            throw ValidationException::withMessages(['findings' => __('assestme.workspace.errors.finding_ownership')]);
        }

        foreach ($request->orderedFindingIds as $index => $findingId) {
            $updated = Finding::query()
                ->where('assessment_id', $persisted->getKey())
                ->whereKey($findingId)
                ->update(['sort_order' => $index + 1]);
            if ($updated !== 1) {
                throw ValidationException::withMessages([
                    "findings.{$index}.id" => __('assestme.workspace.errors.finding_ownership'),
                ]);
            }
        }

        $appliedVersion = ($this->incrementAssessmentVersion)($persisted, $request->expectedVersion);
        WorkspaceSaveRequest::query()->create([
            'request_id' => $request->requestId,
            'assessment_id' => $persisted->getKey(),
            'expected_version' => $request->expectedVersion,
            'applied_version' => $appliedVersion,
            'payload_hash' => $request->payloadSha256,
            'response' => [
                'operation' => 'reorder_findings',
                'applied_version' => $appliedVersion,
            ],
            'created_at' => now(),
        ]);

        return $appliedVersion;
    }
}
