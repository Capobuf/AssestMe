<?php

declare(strict_types=1);

use App\Actions\Assessments\DeleteFinding;
use App\Actions\Assessments\PurgeExpiredWorkspaceSaveRequests;
use App\Actions\Assessments\ReorderFindings;
use App\Actions\Assessments\SaveAssessmentWorkspace;
use App\Actions\Assessments\SaveFindingDetails;
use App\Data\Assessments\FindingSaveData;
use App\Data\Assessments\WorkspaceSaveData;
use App\Enums\ScopeType;
use App\Exceptions\AssessmentVersionConflict;
use App\Exceptions\IdempotencyKeyMismatch;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\WorkspaceSaveRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** @return array{assessment: array<string, mixed>} */
function assessmentWorkspacePayload(Assessment $assessment): array
{
    return ['assessment' => [
        'title' => $assessment->title,
        'assessment_date' => $assessment->assessment_date->format('Y-m-d'),
        'report_title_override' => $assessment->report_title_override,
        'scope_type' => $assessment->scope_type->value,
        'scope_description' => $assessment->scope_description,
        'introduction' => $assessment->introduction,
        'executive_summary' => $assessment->executive_summary,
        'methodology_notes' => $assessment->methodology_notes,
        'site_ids' => $assessment->sites()->pluck('sites.id')->all(),
    ]];
}

/** @param array{assessment: array<string, mixed>} $payload */
function assessmentWorkspaceRequest(Assessment $assessment, array $payload, ?string $requestId = null): WorkspaceSaveData
{
    return new WorkspaceSaveData(
        requestId: $requestId ?? (string) Str::uuid(),
        expectedVersion: (int) $assessment->lock_version,
        tabId: (string) Str::uuid(),
        payload: $payload,
        payloadSha256: WorkspaceSaveData::hashPayload($payload),
    );
}

/** @param array<string, mixed> $payload */
function findingSaveRequest(Assessment $assessment, array $payload, ?string $requestId = null): FindingSaveData
{
    return new FindingSaveData(
        requestId: $requestId ?? (string) Str::uuid(),
        expectedVersion: (int) $assessment->lock_version,
        tabId: (string) Str::uuid(),
        payload: $payload,
        payloadSha256: FindingSaveData::hashPayload($payload),
    );
}

/** @return array<string, mixed> */
function minimalFindingPayload(Finding $finding): array
{
    return [
        'title' => $finding->title,
        'problem' => $finding->problem,
        'entrepreneur_notes' => $finding->entrepreneur_notes,
        'technical_notes' => $finding->technical_notes,
        'category_id' => $finding->category_id,
        'tag_ids' => [],
        'scope_type' => $finding->getRawOriginal('scope_type') ?: ScopeType::Organization->value,
        'scope_description' => $finding->scope_description,
        'site_ids' => [],
        'asset_ids' => [],
        'consequence_level_id' => null,
        'likelihood_level_id' => null,
        'priority_level_id' => null,
        'priority_is_overridden' => false,
        'priority_rationale' => null,
        'status' => $finding->status->value,
        'include_in_report' => $finding->include_in_report,
        'resolution_notes' => null,
        'solutions' => [],
    ];
}

it('purges signed save requests older than twenty four hours through the scheduler', function (): void {
    $assessment = Assessment::factory()->create();
    $request = static fn (Carbon $createdAt): array => [
        'request_id' => (string) Str::uuid(),
        'assessment_id' => $assessment->getKey(),
        'expected_version' => 0,
        'applied_version' => 1,
        'payload_hash' => str_repeat('a', 64),
        'response' => [],
        'created_at' => $createdAt,
    ];
    WorkspaceSaveRequest::query()->create($request(now('UTC')->subHours(25)));
    $retained = WorkspaceSaveRequest::query()->create($request(now('UTC')->subHours(23)));

    expect(app(PurgeExpiredWorkspaceSaveRequests::class)())->toBe(1)
        ->and(WorkspaceSaveRequest::query()->sole()->is($retained))->toBeTrue()
        ->and(collect(Schedule::events())->contains(
            fn ($event): bool => $event->description === 'assestme:purge-workspace-save-requests',
        ))->toBeTrue();
});

it('saves assessment metadata without serializing or replacing findings', function (): void {
    $assessment = Assessment::factory()->create(['title' => 'Originale']);
    $finding = Finding::factory()->for($assessment)->create(['sort_order' => 1, 'problem' => 'Intatto']);
    $payload = assessmentWorkspacePayload($assessment);
    $payload['assessment']['title'] = 'Aggiornato';

    $result = app(SaveAssessmentWorkspace::class)($assessment, assessmentWorkspaceRequest($assessment, $payload));

    expect($result->appliedVersion)->toBe(1)
        ->and($assessment->fresh()->title)->toBe('Aggiornato')
        ->and($finding->fresh()->problem)->toBe('Intatto')
        ->and($assessment->fresh()->findings)->toHaveCount(1);
});

it('saves only one complete finding aggregate and increments lock version once', function (): void {
    $assessment = Assessment::factory()->create();
    $first = Finding::factory()->for($assessment)->create(['sort_order' => 1, 'title' => 'Primo']);
    $second = Finding::factory()->for($assessment)->create(['sort_order' => 2, 'title' => 'Secondo']);
    $payload = minimalFindingPayload($first);
    $payload['title'] = 'Primo aggiornato';
    $payload['problem'] = "Prima riga\nSeconda riga";

    $result = app(SaveFindingDetails::class)($first, findingSaveRequest($assessment, $payload));

    expect($result->appliedVersion)->toBe(1)
        ->and($result->finding->title)->toBe('Primo aggiornato')
        ->and($result->finding->problem)->toBe("Prima riga\nSeconda riga")
        ->and($second->fresh()->title)->toBe('Secondo')
        ->and($assessment->fresh()->lock_version)->toBe(1);
});

it('replays an identical finding request without applying it twice', function (): void {
    $assessment = Assessment::factory()->create();
    $finding = Finding::factory()->for($assessment)->create();
    $payload = minimalFindingPayload($finding);
    $requestId = (string) Str::uuid();
    $request = findingSaveRequest($assessment, $payload, $requestId);

    $first = app(SaveFindingDetails::class)($finding, $request);
    $replay = app(SaveFindingDetails::class)($finding, $request);

    expect($first->appliedVersion)->toBe(1)
        ->and($replay->appliedVersion)->toBe(1)
        ->and($replay->idempotentReplay)->toBeTrue()
        ->and($assessment->fresh()->lock_version)->toBe(1);
});

it('rejects finding request id reuse with another payload', function (): void {
    $assessment = Assessment::factory()->create();
    $finding = Finding::factory()->for($assessment)->create();
    $payload = minimalFindingPayload($finding);
    $requestId = (string) Str::uuid();
    app(SaveFindingDetails::class)($finding, findingSaveRequest($assessment, $payload, $requestId));
    $payload['title'] = 'Diverso';

    expect(fn () => app(SaveFindingDetails::class)(
        $finding,
        findingSaveRequest($assessment->fresh(), $payload, $requestId),
    ))->toThrow(IdempotencyKeyMismatch::class);
});

it('rejects a stale finding save without overwriting persisted data', function (): void {
    $assessment = Assessment::factory()->create();
    $finding = Finding::factory()->for($assessment)->create(['title' => 'Originale']);
    $payload = minimalFindingPayload($finding);
    $payload['title'] = 'Tentativo obsoleto';
    $request = findingSaveRequest($assessment, $payload);
    $assessment->update(['lock_version' => 1]);
    $finding->update(['title' => 'Modifica concorrente']);

    expect(fn () => app(SaveFindingDetails::class)($finding, $request))
        ->toThrow(AssessmentVersionConflict::class)
        ->and($finding->fresh()->title)->toBe('Modifica concorrente');
});

it('keeps data and version unchanged after finding validation failure', function (): void {
    $assessment = Assessment::factory()->create();
    $finding = Finding::factory()->for($assessment)->create(['title' => 'Originale']);
    $payload = minimalFindingPayload($finding);
    $payload['scope_type'] = 'selected_assets';

    expect(fn () => app(SaveFindingDetails::class)($finding, findingSaveRequest($assessment, $payload)))
        ->toThrow(ValidationException::class)
        ->and($finding->fresh()->title)->toBe('Originale')
        ->and($assessment->fresh()->lock_version)->toBe(0);
});

it('reorders the complete finding set deterministically and increments version', function (): void {
    $assessment = Assessment::factory()->create();
    $first = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $second = Finding::factory()->for($assessment)->create(['sort_order' => 2]);

    $version = app(ReorderFindings::class)($assessment, [$second->id, $first->id]);

    expect($version)->toBe(1)
        ->and($assessment->fresh()->findings->pluck('id')->all())->toBe([$second->id, $first->id]);
});

it('rejects an incomplete reorder set without partial updates or version change', function (): void {
    $assessment = Assessment::factory()->create();
    $first = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $second = Finding::factory()->for($assessment)->create(['sort_order' => 2]);

    expect(fn () => app(ReorderFindings::class)($assessment, [$second->id]))
        ->toThrow(ValidationException::class)
        ->and($assessment->fresh()->findings->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($assessment->fresh()->lock_version)->toBe(0);
});

it('soft deletes a finding, compacts order, and increments version', function (): void {
    $assessment = Assessment::factory()->create();
    $first = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $second = Finding::factory()->for($assessment)->create(['sort_order' => 2]);

    $version = app(DeleteFinding::class)($first);

    expect($version)->toBe(1)
        ->and($first->fresh()->trashed())->toBeTrue()
        ->and($second->fresh()->sort_order)->toBe(1)
        ->and($assessment->fresh()->lock_version)->toBe(1);
});
