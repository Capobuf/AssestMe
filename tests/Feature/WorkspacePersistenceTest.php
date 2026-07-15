<?php

declare(strict_types=1);

use App\Actions\Assessments\PurgeExpiredWorkspaceSaveRequests;
use App\Actions\Assessments\SaveAssessmentWorkspace;
use App\Data\Assessments\WorkspaceSaveData;
use App\Enums\FindingStatus;
use App\Exceptions\AssessmentVersionConflict;
use App\Exceptions\IdempotencyKeyMismatch;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\WorkspaceSaveRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function workspacePayload(Assessment $assessment): array
{
    return [
        'assessment' => [
            'title' => $assessment->title,
            'assessment_date' => $assessment->assessment_date->format('Y-m-d'),
            'report_title_override' => $assessment->report_title_override,
            'scope_type' => $assessment->scope_type->value,
            'scope_description' => $assessment->scope_description,
            'introduction' => $assessment->introduction,
            'executive_summary' => $assessment->executive_summary,
            'methodology_notes' => $assessment->methodology_notes,
            'site_ids' => $assessment->sites()->pluck('sites.id')->all(),
        ],
        'findings' => $assessment->findings->map(fn (Finding $finding): array => [
            'id' => $finding->getKey(),
            '_temporary_uuid' => (string) Str::uuid(),
            'title' => $finding->title,
            'problem' => $finding->problem,
            'entrepreneur_notes' => $finding->entrepreneur_notes,
            'status' => $finding->status->value,
            'include_in_report' => $finding->include_in_report,
        ])->values()->all(),
    ];
}

function workspaceRequest(Assessment $assessment, array $payload, ?string $requestId = null): WorkspaceSaveData
{
    return new WorkspaceSaveData(
        requestId: $requestId ?? (string) Str::uuid(),
        expectedVersion: (int) $assessment->lock_version,
        tabId: (string) Str::uuid(),
        payload: $payload,
        payloadSha256: WorkspaceSaveData::hashPayload($payload),
    );
}

it('purges workspace save requests older than twenty four hours through the scheduler', function (): void {
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

it('atomically adds updates deletes and reorders multiline findings', function (): void {
    $assessment = Assessment::factory()->create();
    $first = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $removed = Finding::factory()->for($assessment)->create(['sort_order' => 2]);
    $assessment->refresh()->load('findings');

    $newUuid = (string) Str::uuid();
    $payload = workspacePayload($assessment);
    $payload['assessment']['title'] = 'Assessment aggiornato';
    $payload['findings'] = [
        [
            'id' => null,
            '_temporary_uuid' => $newUuid,
            'title' => 'Finding nuovo',
            'problem' => "Prima riga\nSeconda riga\nTerza riga",
            'entrepreneur_notes' => "Nota uno\nNota due",
            'status' => FindingStatus::Open->value,
            'include_in_report' => true,
        ],
        array_replace($payload['findings'][0], ['title' => 'Finding esistente aggiornato']),
    ];

    $result = app(SaveAssessmentWorkspace::class)($assessment, workspaceRequest($assessment, $payload));

    expect($result->appliedVersion)->toBe(1)
        ->and($result->idMap)->toHaveKey($newUuid)
        ->and($assessment->fresh()->title)->toBe('Assessment aggiornato')
        ->and($assessment->fresh()->findings)->toHaveCount(2)
        ->and($assessment->fresh()->findings[0]->problem)->toBe("Prima riga\nSeconda riga\nTerza riga")
        ->and($assessment->fresh()->findings[1]->is($first))->toBeTrue()
        ->and(Finding::withTrashed()->find($removed->getKey())?->trashed())->toBeTrue();
});

it('replays identical request ids without applying the save twice', function (): void {
    $assessment = Assessment::factory()->create();
    Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $assessment->refresh()->load('findings');
    $payload = workspacePayload($assessment);
    $requestId = (string) Str::uuid();
    $request = workspaceRequest($assessment, $payload, $requestId);

    $first = app(SaveAssessmentWorkspace::class)($assessment, $request);
    $replay = app(SaveAssessmentWorkspace::class)($assessment, $request);

    expect($first->appliedVersion)->toBe(1)
        ->and($replay->appliedVersion)->toBe(1)
        ->and($replay->idempotentReplay)->toBeTrue()
        ->and($assessment->fresh()->lock_version)->toBe(1);
});

it('rejects idempotency key reuse with another payload', function (): void {
    $assessment = Assessment::factory()->create();
    $assessment->load('findings');
    $payload = workspacePayload($assessment);
    $requestId = (string) Str::uuid();
    app(SaveAssessmentWorkspace::class)($assessment, workspaceRequest($assessment, $payload, $requestId));

    $changed = $payload;
    $changed['assessment']['title'] = 'Diverso';

    expect(fn () => app(SaveAssessmentWorkspace::class)(
        $assessment->fresh(),
        workspaceRequest($assessment->fresh(), $changed, $requestId),
    ))->toThrow(IdempotencyKeyMismatch::class);
});

it('rejects a stale version without overwriting persisted data', function (): void {
    $assessment = Assessment::factory()->create(['title' => 'Originale']);
    $assessment->load('findings');
    $payload = workspacePayload($assessment);
    $request = workspaceRequest($assessment, $payload);
    $assessment->update(['lock_version' => 1, 'title' => 'Modifica concorrente']);

    expect(fn () => app(SaveAssessmentWorkspace::class)(
        $assessment,
        $request,
    ))->toThrow(AssessmentVersionConflict::class)
        ->and($assessment->fresh()->title)->toBe('Modifica concorrente');
});

it('rolls back an invalid payload', function (): void {
    $assessment = Assessment::factory()->create(['title' => 'Originale']);
    $assessment->load('findings');
    $payload = workspacePayload($assessment);
    $payload['assessment']['title'] = '';

    expect(fn () => app(SaveAssessmentWorkspace::class)(
        $assessment,
        workspaceRequest($assessment, $payload),
    ))->toThrow(ValidationException::class)
        ->and($assessment->fresh()->title)->toBe('Originale')
        ->and($assessment->fresh()->lock_version)->toBe(0);
});

it('persists native relationship payload sizes', function (int $count): void {
    $assessment = Assessment::factory()->create();
    Finding::factory()->count($count)->for($assessment)->sequence(
        fn ($sequence): array => ['sort_order' => $sequence->index + 1],
    )->create();
    $assessment->refresh()->load('findings');

    $result = app(SaveAssessmentWorkspace::class)(
        $assessment,
        workspaceRequest($assessment, workspacePayload($assessment)),
    );

    expect($result->appliedVersion)->toBe(1)
        ->and($assessment->fresh()->findings)->toHaveCount($count);
})->with([10, 25, 50]);

it('preserves both short and long Unicode problem text', function (string $problem): void {
    $assessment = Assessment::factory()->create();
    $assessment->load('findings');
    $payload = workspacePayload($assessment);
    $payload['findings'][] = [
        'id' => null,
        '_temporary_uuid' => (string) Str::uuid(),
        'title' => 'Testo Unicode',
        'problem' => $problem,
        'entrepreneur_notes' => null,
        'status' => FindingStatus::Open->value,
        'include_in_report' => true,
    ];

    app(SaveAssessmentWorkspace::class)($assessment, workspaceRequest($assessment, $payload));

    expect($assessment->fresh()->findings->sole()->problem)->toBe($problem);
})->with([
    'short' => "Priorità\nAlta",
    'long' => str_repeat("Continuità operativa e qualità.\n", 500),
]);
