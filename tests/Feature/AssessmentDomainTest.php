<?php

declare(strict_types=1);

use App\Actions\Assessments\ArchiveAssessment;
use App\Actions\Assessments\CompleteAssessment;
use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Assessments\CreateAssessment;
use App\Actions\Assessments\CreateBlankFinding;
use App\Actions\Assessments\DeleteFindingSolution;
use App\Actions\Assessments\DuplicateFinding;
use App\Actions\Assessments\OverrideFindingPriority;
use App\Actions\Assessments\RecalculateFindingPriority;
use App\Actions\Assessments\ReopenAssessment;
use App\Actions\Assessments\SaveFindingDetails;
use App\Actions\Assessments\SetImplementedSolution;
use App\Actions\Assessments\SetRecommendedSolution;
use App\Actions\Assessments\TransitionFindingStatus;
use App\Enums\AssessmentStatus;
use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Client;
use App\Models\Finding;
use App\Models\FindingSolution;
use App\Models\FindingTemplate;
use App\Models\PriorityLevel;
use App\Models\RiskMatrixEntry;
use App\Models\RiskProfile;
use App\Models\Site;
use Database\Seeders\MilestoneOneSeeder;
use Database\Seeders\MilestoneTwoSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(MilestoneOneSeeder::class);
    $this->seed(MilestoneTwoSeeder::class);
});

it('creates a typed draft assessment and rejects sites owned by another client', function (): void {
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();

    $assessment = app(CreateAssessment::class)->handle([
        'client_id' => $client->getKey(),
        'title' => 'Assessment infrastruttura',
        'assessment_date' => '2026-07-13',
        'scope_type' => ScopeType::SelectedSites->value,
        'scope_description' => null,
        'site_ids' => [$site->getKey()],
    ]);

    expect($assessment->status)->toBe(AssessmentStatus::Draft)
        ->and($assessment->locale)->toBe('it')
        ->and($assessment->sites)->toHaveCount(1)
        ->and($assessment->sites->sole()->is($site))->toBeTrue();

    $otherSite = Site::factory()->create();
    expect(fn () => app(CreateAssessment::class)->handle([
        'client_id' => $client->getKey(),
        'title' => 'Assessment non valido',
        'assessment_date' => '2026-07-13',
        'scope_type' => ScopeType::SelectedSites->value,
        'site_ids' => [$otherSite->getKey()],
    ]))->toThrow(ValidationException::class);
});

it('persists blank findings immediately and copies detached template snapshots', function (): void {
    $assessment = Assessment::factory()->create();
    $blank = app(CreateBlankFinding::class)->handle($assessment);

    expect($blank->exists)->toBeTrue()
        ->and($blank->sort_order)->toBe(1)
        ->and($blank->status)->toBe(FindingStatus::Open);

    $template = FindingTemplate::query()->with('solutions')->firstOrFail();
    $originalTitle = $template->title;
    $finding = app(CopyTemplateToAssessment::class)->handle($assessment, $template);

    expect($finding->source_template_id)->toBe($template->id)
        ->and($finding->title)->toBe($originalTitle)
        ->and($finding->solutions)->toHaveCount($template->solutions->count())
        ->and($finding->recommendedSolution)->not->toBeNull()
        ->and($finding->recommendedSolution?->finding_id)->toBe($finding->id);

    $template->update(['title' => 'Titolo modificato successivamente']);
    $template->solutions->firstOrFail()->update(['description' => 'Soluzione modificata successivamente']);

    expect($finding->fresh()->title)->toBe($originalTitle)
        ->and($finding->fresh()->recommendedSolution?->description)->not->toBe('Soluzione modificata successivamente');
});

it('duplicates the complete editable finding aggregate without resolution state', function (): void {
    $assessment = Assessment::factory()->create();
    $source = app(CopyTemplateToAssessment::class)->handle($assessment, FindingTemplate::query()->firstOrFail());
    $source->update(['resolution_notes' => 'Risoluzione precedente', 'resolved_at' => now()]);

    $copy = app(DuplicateFinding::class)->handle($source);

    expect($copy->id)->not->toBe($source->id)
        ->and($copy->status)->toBe(FindingStatus::Open)
        ->and($copy->resolution_notes)->toBeNull()
        ->and($copy->resolved_at)->toBeNull()
        ->and($copy->solutions)->toHaveCount($source->solutions()->count())
        ->and($copy->recommendedSolution?->finding_id)->toBe($copy->id);
});

it('calculates matrix priority', function (): void {
    $entry = RiskMatrixEntry::query()->firstOrFail();
    $finding = Finding::factory()->create([
        'consequence_level_id' => $entry->consequence_level_id,
        'likelihood_level_id' => $entry->likelihood_level_id,
        'priority_is_overridden' => true,
        'priority_rationale' => 'Valutazione manuale precedente.',
    ]);

    $finding = app(RecalculateFindingPriority::class)->handle($finding);
    expect($finding->priority_level_id)->toBe($entry->priority_level_id)
        ->and($finding->priority_is_overridden)->toBeFalse()
        ->and($finding->priority_rationale)->toBeNull();
});

it('overrides finding priority with a reason and rejects invalid overrides', function (): void {
    $entry = RiskMatrixEntry::query()->with('priorityLevel')->firstOrFail();
    $finding = Finding::factory()->create([
        'consequence_level_id' => $entry->consequence_level_id,
        'likelihood_level_id' => $entry->likelihood_level_id,
    ]);

    $overridden = app(OverrideFindingPriority::class)(
        $finding,
        $entry->priorityLevel,
        '  Valutazione contestuale documentata.  ',
    );

    expect($overridden->priority_level_id)->toBe($entry->priority_level_id)
        ->and($overridden->priority_is_overridden)->toBeTrue()
        ->and($overridden->priority_rationale)->toBe('Valutazione contestuale documentata.')
        ->and(method_exists(OverrideFindingPriority::class, '__invoke'))->toBeTrue();

    expect(fn () => app(OverrideFindingPriority::class)($overridden, $entry->priorityLevel, '  '))
        ->toThrow(ValidationException::class);

    $otherProfile = RiskProfile::query()->create([
        'code' => 'override-other',
        'label' => 'Profilo override esterno',
        'is_default' => false,
        'is_enabled' => false,
    ]);
    $foreignPriority = PriorityLevel::query()->create([
        'risk_profile_id' => $otherProfile->getKey(),
        'code' => 'foreign',
        'label' => 'Esterna',
        'color' => '#123456',
        'sort_order' => 1,
        'is_enabled' => false,
    ]);

    expect(fn () => app(OverrideFindingPriority::class)($overridden, $foreignPriority, 'Motivo non valido.'))
        ->toThrow(ValidationException::class);

    $overridden->assessment()->update(['status' => AssessmentStatus::Completed]);
    expect(fn () => app(OverrideFindingPriority::class)($overridden, $entry->priorityLevel, 'Stato obsoleto.'))
        ->toThrow(ValidationException::class)
        ->and($overridden->fresh()->priority_rationale)->toBe('Valutazione contestuale documentata.');
});

it('sets and clears solution references while rejecting invalid assignments', function (): void {
    $finding = Finding::factory()->create();
    $solution = createFindingSolution($finding, 'manuale');

    $recommended = app(SetRecommendedSolution::class)($finding, $solution);
    $implemented = app(SetImplementedSolution::class)($recommended, $solution);

    expect($implemented->recommended_solution_id)->toBe($solution->id)
        ->and($implemented->implemented_solution_id)->toBe($solution->id)
        ->and(method_exists(SetRecommendedSolution::class, '__invoke'))->toBeTrue()
        ->and(method_exists(SetImplementedSolution::class, '__invoke'))->toBeTrue();
    expect(fn () => app(DeleteFindingSolution::class)->handle($solution))->toThrow(ValidationException::class);

    $foreign = createFindingSolution(Finding::factory()->create(), 'altra');
    expect(fn () => app(SetRecommendedSolution::class)($implemented, $foreign))->toThrow(ValidationException::class)
        ->and(fn () => app(SetImplementedSolution::class)($implemented, $foreign))->toThrow(ValidationException::class);

    $deleted = createFindingSolution($finding, 'eliminata');
    $deleted->delete();
    expect(fn () => app(SetRecommendedSolution::class)($implemented, $deleted))->toThrow(ValidationException::class)
        ->and(fn () => app(SetImplementedSolution::class)($implemented, $deleted))->toThrow(ValidationException::class);

    $clearedRecommended = app(SetRecommendedSolution::class)($implemented, null);
    $cleared = app(SetImplementedSolution::class)($clearedRecommended, null);
    expect($cleared->recommended_solution_id)->toBeNull()
        ->and($cleared->implemented_solution_id)->toBeNull();

    app(DeleteFindingSolution::class)->handle($solution);
    expect($solution->fresh()->trashed())->toBeTrue();

    $readOnlyFinding = Finding::factory()->create();
    $readOnlySolution = createFindingSolution($readOnlyFinding, 'sola lettura');
    $readOnlyFinding->assessment()->update(['status' => AssessmentStatus::Completed]);
    expect(fn () => app(SetRecommendedSolution::class)($readOnlyFinding->fresh(), $readOnlySolution))->toThrow(ValidationException::class)
        ->and(fn () => app(SetImplementedSolution::class)($readOnlyFinding->fresh(), $readOnlySolution))->toThrow(ValidationException::class);
});

it('returns row-numbered completion errors and enforces the assessment lifecycle', function (): void {
    $assessment = Assessment::factory()->create();
    app(CreateBlankFinding::class)->handle($assessment);

    try {
        app(CompleteAssessment::class)($assessment);
        test()->fail('Completion must reject an included incomplete finding.');
    } catch (ValidationException $exception) {
        expect(collect($exception->errors())->flatten()->join(' '))->toContain('Riga 1');
    }

    $assessment->findings()->delete();
    $completeFinding = app(CopyTemplateToAssessment::class)->handle($assessment, FindingTemplate::query()->firstOrFail());
    $completeFinding->update(['scope_type' => ScopeType::Organization, 'scope_description' => null]);
    expect($completeFinding->priority_level_id)->not->toBeNull();

    $completed = app(CompleteAssessment::class)($assessment->fresh());
    expect($completed->status)->toBe(AssessmentStatus::Completed)
        ->and($completed->completed_at)->not->toBeNull()
        ->and($completed->lock_version)->toBe(1)
        ->and(fn () => app(CreateBlankFinding::class)->handle($completed))->toThrow(ValidationException::class);

    expect(fn () => app(CompleteAssessment::class)($completed))
        ->toThrow(ValidationException::class);

    $completedAt = $completed->completed_at?->toISOString();
    $archived = app(ArchiveAssessment::class)($completed);
    expect($archived->status)->toBe(AssessmentStatus::Archived)
        ->and($archived->completed_at?->toISOString())->toBe($completedAt)
        ->and($archived->lock_version)->toBe(2)
        ->and(fn () => app(ArchiveAssessment::class)($archived))
        ->toThrow(ValidationException::class);

    $reopened = app(ReopenAssessment::class)($archived);
    expect($reopened->status)->toBe(AssessmentStatus::Draft)
        ->and($reopened->completed_at)->toBeNull()
        ->and($reopened->lock_version)->toBe(3)
        ->and(fn () => app(ReopenAssessment::class)($reopened))
        ->toThrow(ValidationException::class);
});

it('rejects a lifecycle transition when the caller holds stale assessment state', function (): void {
    $staleDraft = Assessment::factory()->create();
    Assessment::query()->whereKey($staleDraft)->update(['status' => AssessmentStatus::Archived]);

    expect(fn () => app(CompleteAssessment::class)($staleDraft))
        ->toThrow(ValidationException::class)
        ->and($staleDraft->fresh()->status)->toBe(AssessmentStatus::Archived)
        ->and($staleDraft->fresh()->lock_version)->toBe(0);
});

it('enforces finding state transitions and resolved requirements', function (): void {
    $finding = Finding::factory()->create(['status' => FindingStatus::Open]);

    expect(fn () => app(TransitionFindingStatus::class)($finding, FindingStatus::Resolved))
        ->toThrow(ValidationException::class);

    $planned = app(TransitionFindingStatus::class)($finding, FindingStatus::Planned);
    $planned->update(['resolution_notes' => 'Rischio eliminato mediante modifica configurativa.']);
    $resolved = app(TransitionFindingStatus::class)($planned->fresh(), FindingStatus::Resolved);

    expect($resolved->status)->toBe(FindingStatus::Resolved)
        ->and($resolved->resolved_at)->not->toBeNull()
        ->and(method_exists(TransitionFindingStatus::class, '__invoke'))->toBeTrue();

    expect(fn () => app(TransitionFindingStatus::class)($resolved, FindingStatus::Accepted))
        ->toThrow(ValidationException::class);
});

it('rejects finding transitions from stale or read-only state', function (): void {
    $stale = Finding::factory()->create(['status' => FindingStatus::Open]);
    Finding::query()->whereKey($stale)->update(['status' => FindingStatus::Accepted]);

    expect(fn () => app(TransitionFindingStatus::class)($stale, FindingStatus::InProgress))
        ->toThrow(ValidationException::class)
        ->and($stale->fresh()->status)->toBe(FindingStatus::Accepted);

    $readOnly = Finding::factory()->create(['status' => FindingStatus::Open]);
    $readOnly->assessment()->update(['status' => AssessmentStatus::Completed]);

    expect(fn () => app(TransitionFindingStatus::class)($readOnly, FindingStatus::Planned))
        ->toThrow(ValidationException::class)
        ->and($readOnly->fresh()->status)->toBe(FindingStatus::Open);
});

it('saves the row slide-over aggregate and rejects cross-client scope relations', function (): void {
    $assessment = Assessment::factory()->create();
    $finding = app(CopyTemplateToAssessment::class)->handle($assessment, FindingTemplate::query()->firstOrFail());
    $site = Site::factory()->for($assessment->client)->create();
    $payload = findingDetailsPayload($finding);
    $payload['technical_notes'] = "Dettaglio tecnico\ncon più righe.";
    $payload['scope_type'] = ScopeType::SelectedSites->value;
    $payload['site_ids'] = [$site->id];
    $payload['priority_is_overridden'] = true;
    $payload['priority_rationale'] = 'Override salvato dallo slide-over.';

    $saved = app(SaveFindingDetails::class)->handle($finding, $payload);
    expect($saved->technical_notes)->toBe("Dettaglio tecnico\ncon più righe.")
        ->and($saved->sites)->toHaveCount(1)
        ->and($saved->recommendedSolution)->not->toBeNull()
        ->and($saved->priority_is_overridden)->toBeTrue()
        ->and($saved->priority_rationale)->toBe('Override salvato dallo slide-over.');

    $foreignSite = Site::factory()->create();
    $payload['site_ids'] = [$foreignSite->id];
    expect(fn () => app(SaveFindingDetails::class)->handle($saved, $payload))
        ->toThrow(ValidationException::class);
});

function createFindingSolution(Finding $finding, string $suffix): FindingSolution
{
    $solution = $finding->solutions()->create([
        'title' => "Soluzione {$suffix}",
        'description' => 'Descrizione completa della soluzione.',
        'estimate_type' => EstimateType::RequiresQuote,
        'billing_frequency' => BillingFrequency::OneOff,
        'sort_order' => 1,
    ]);
    $solution->update(['external_key' => "manual-{$solution->id}"]);

    return $solution->refresh();
}

/** @return array<string, mixed> */
function findingDetailsPayload(Finding $finding): array
{
    $finding->load(['tags', 'sites', 'assets', 'solutions']);

    return [
        'category_id' => $finding->category_id,
        'tag_ids' => $finding->tags->pluck('id')->all(),
        'technical_notes' => $finding->technical_notes,
        'scope_type' => $finding->scope_type->value,
        'scope_description' => $finding->scope_description,
        'site_ids' => $finding->sites->pluck('id')->all(),
        'asset_ids' => $finding->assets->pluck('id')->all(),
        'consequence_level_id' => $finding->consequence_level_id,
        'likelihood_level_id' => $finding->likelihood_level_id,
        'priority_level_id' => $finding->priority_level_id,
        'priority_is_overridden' => $finding->priority_is_overridden,
        'priority_rationale' => $finding->priority_rationale,
        'status' => $finding->status->value,
        'resolution_notes' => $finding->resolution_notes,
        'solutions' => $finding->solutions->map(fn (FindingSolution $solution): array => [
            ...$solution->only([
                'id', 'external_key', 'title', 'description', 'comparison_notes', 'effort_level_id', 'effort_notes',
                'estimate_type', 'amount_min', 'amount_max', 'currency_code', 'billing_frequency',
                'custom_billing_frequency', 'estimate_notes', 'sort_order',
            ]),
            'is_recommended' => $finding->recommended_solution_id === $solution->id,
            'is_implemented' => $finding->implemented_solution_id === $solution->id,
        ])->all(),
    ];
}
