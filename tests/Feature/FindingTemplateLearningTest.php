<?php

declare(strict_types=1);

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Assessments\DuplicateFinding;
use App\Actions\Assessments\SaveFindingAsTemplate;
use App\Actions\Assessments\UpdateTemplateFromFinding;
use App\Actions\Templates\SaveFindingTemplate;
use App\Data\Templates\FindingTemplateSyncResult;
use App\Enums\AssessmentStatus;
use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Category;
use App\Models\ConsequenceLevel;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\FindingSolution;
use App\Models\FindingTemplate;
use App\Models\FindingTemplateSolution;
use App\Models\LikelihoodLevel;
use App\Models\PriorityLevel;
use App\Models\RiskMatrixEntry;
use App\Models\RiskProfile;
use App\Services\Templates\FindingTemplateContent;
use App\Services\Templates\FindRelatedFindingTemplates;
use Database\Seeders\MilestoneOneSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(MilestoneOneSeeder::class);
});

it('stores a canonical source fingerprint when copying and preserves it when duplicating', function (): void {
    $template = createLearningTemplate();
    $assessment = Assessment::factory()->create();

    $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
    $copy = app(DuplicateFinding::class)($finding);
    $expected = app(FindingTemplateContent::class)->fingerprint($template->fresh('solutions'));

    expect($finding->source_template_fingerprint)->toBe($expected)
        ->and($copy->source_template_id)->toBe($template->id)
        ->and($copy->source_template_fingerprint)->toBe($expected);
});

it('canonicalizes lineage content and separates it from semantic exact normalization', function (): void {
    $template = createLearningTemplate(['problem' => "Problema con accento e riga.\nSeconda riga"]);
    $content = app(FindingTemplateContent::class);
    $fingerprint = $content->fingerprint($template->fresh('solutions'));

    $template->update(['problem' => "Problema con accento e riga.\r\nSeconda riga"]);
    expect($content->fingerprint($template->fresh('solutions')))->toBe($fingerprint);

    $finding = app(CopyTemplateToAssessment::class)(Assessment::factory()->create(), $template->fresh('solutions'));
    $finding->update(['title' => mb_strtoupper($finding->title).'!!!']);
    expect($content->isSemanticallyIdentical($finding->fresh('solutions'), $template->fresh('solutions')))->toBeTrue();
});

it('creates a new template atomically from reusable Finding content and realigns lineage', function (): void {
    $assessment = Assessment::factory()->create();
    $finding = createManualLearningFinding($assessment, priorityOverride: true);
    Evidence::query()->create([
        'finding_id' => $finding->id,
        'type' => 'url',
        'title' => 'Evidenza cliente',
        'url' => 'https://example.test/customer',
        'include_in_report' => true,
        'sort_order' => 1,
    ]);
    $finding->update([
        'status' => 'in_progress',
        'include_in_report' => false,
        'resolution_notes' => 'Stato specifico cliente',
    ]);

    $result = app(SaveFindingAsTemplate::class)->create($finding, 0);
    $template = $result->template->fresh('solutions');
    $savedFinding = $result->finding->fresh(['solutions', 'evidences']);
    $matrixPriority = RiskMatrixEntry::query()
        ->where('consequence_level_id', $finding->consequence_level_id)
        ->where('likelihood_level_id', $finding->likelihood_level_id)
        ->value('priority_level_id');

    expect($result->outcome)->toBe(FindingTemplateSyncResult::CREATED)
        ->and($result->appliedVersion)->toBe(1)
        ->and($assessment->fresh()->lock_version)->toBe(1)
        ->and($savedFinding->source_template_id)->toBe($template->id)
        ->and($savedFinding->source_template_fingerprint)->toBe(app(FindingTemplateContent::class)->fingerprint($template))
        ->and($savedFinding->solutions->pluck('external_key')->all())->toBe($template->solutions->pluck('external_id')->all())
        ->and($template->default_priority_level_id)->toBe($matrixPriority)
        ->and($template->priority_rationale)->toBeNull()
        ->and($template->getAttributes())->not->toHaveKeys(['status', 'include_in_report', 'resolution_notes'])
        ->and($savedFinding->status->value)->toBe('in_progress')
        ->and($savedFinding->include_in_report)->toBeFalse()
        ->and($savedFinding->evidences)->toHaveCount(1);
});

it('rolls back template creation lineage keys fingerprint and version when authoritative save fails', function (): void {
    $assessment = Assessment::factory()->create();
    $finding = createManualLearningFinding($assessment);
    foreach (range(2, 4) as $number) {
        createLearningSolution($finding, "Soluzione {$number}", $number - 1, false);
    }
    $beforeKeys = $finding->solutions()->pluck('external_key', 'id')->all();

    expect(fn () => app(SaveFindingAsTemplate::class)->create($finding, 0))
        ->toThrow(ValidationException::class)
        ->and(FindingTemplate::query()->count())->toBe(0)
        ->and($finding->fresh()->source_template_id)->toBeNull()
        ->and($finding->fresh()->source_template_fingerprint)->toBeNull()
        ->and($finding->solutions()->pluck('external_key', 'id')->all())->toBe($beforeKeys)
        ->and($assessment->fresh()->lock_version)->toBe(0);
});

it('detects and links an exact template without creating a duplicate', function (): void {
    $template = createLearningTemplate();
    $assessment = Assessment::factory()->create();
    $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
    $finding->update(['source_template_id' => null, 'source_template_fingerprint' => null]);
    foreach ($finding->solutions as $solution) {
        $solution->update(['external_key' => "manual-{$solution->id}"]);
    }

    $related = app(FindRelatedFindingTemplates::class)->forFinding($finding->fresh('solutions'));
    $count = FindingTemplate::query()->count();
    $result = app(SaveFindingAsTemplate::class)->linkExact($finding, $related['exact'], 1);

    expect($related['exact']?->is($template))->toBeTrue()
        ->and($related['similar'])->toBe([])
        ->and(FindingTemplate::query()->count())->toBe($count)
        ->and($result->outcome)->toBe(FindingTemplateSyncResult::LINKED)
        ->and($result->finding->source_template_id)->toBe($template->id)
        ->and($result->finding->solutions()->pluck('external_key')->all())
        ->toBe($template->solutions()->pluck('external_id')->all());
});

it('maps semantically indistinguishable exact solutions with a deterministic tie breaker', function (): void {
    $template = createLearningTemplate(solutionCount: 3);
    $templateSolutions = $template->solutions()->orderBy('id')->get();
    $reference = $templateSolutions->get(1);
    $indistinguishable = $templateSolutions->get(2);
    $indistinguishable->update([
        'title' => $reference->title,
        'description' => $reference->description,
        'comparison_notes' => $reference->comparison_notes,
        'effort_level_id' => $reference->effort_level_id,
        'effort_notes' => $reference->effort_notes,
        'estimate_type' => $reference->estimate_type,
        'amount_min' => $reference->amount_min,
        'amount_max' => $reference->amount_max,
        'currency_code' => $reference->currency_code,
        'billing_frequency' => $reference->billing_frequency,
        'custom_billing_frequency' => $reference->custom_billing_frequency,
        'estimate_notes' => $reference->estimate_notes,
        'is_recommended' => false,
        'sort_order' => $reference->sort_order,
    ]);
    $assessment = Assessment::factory()->create();
    $finding = app(CopyTemplateToAssessment::class)($assessment, $template->fresh('solutions'));
    $finding->update(['source_template_id' => null, 'source_template_fingerprint' => null]);
    foreach ($finding->solutions as $solution) {
        $solution->update(['external_key' => "manual-{$solution->id}"]);
    }

    $exact = app(FindRelatedFindingTemplates::class)->forFinding($finding->fresh('solutions'))['exact'];
    $result = app(SaveFindingAsTemplate::class)->linkExact($finding, $exact, 1);
    $expected = $template->solutions()
        ->where('is_recommended', false)
        ->orderBy('external_id')
        ->pluck('external_id')
        ->all();
    $actual = $result->finding->solutions()
        ->whereKeyNot($result->finding->recommended_solution_id)
        ->orderBy('id')
        ->pluck('external_key')
        ->all();

    expect($actual)->toBe($expected);
});

it('rechecks exact duplication server side before creating a template', function (): void {
    $template = createLearningTemplate();
    $assessment = Assessment::factory()->create();
    $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
    $finding->update(['source_template_id' => null, 'source_template_fingerprint' => null]);
    $count = FindingTemplate::query()->count();

    expect(fn () => app(SaveFindingAsTemplate::class)->create($finding, 1))
        ->toThrow(ValidationException::class)
        ->and(FindingTemplate::query()->count())->toBe($count)
        ->and($finding->fresh()->source_template_id)->toBeNull()
        ->and($assessment->fresh()->lock_version)->toBe(1);
});

it('finds conservative similar candidates without noisy distinct matches', function (): void {
    createLearningTemplate([
        'title' => 'Blocco schermo Windows non configurato',
        'problem' => 'Il blocco automatico dello schermo Windows non è configurato dopo inattività.',
    ]);
    $assessment = Assessment::factory()->create();
    $similar = createManualLearningFinding($assessment, [
        'title' => 'Blocco schermo Windows non configurato',
        'problem' => 'Il blocco automatico dello schermo Windows non è configurato dopo inattività prolungata.',
    ]);
    $distinct = createManualLearningFinding(Assessment::factory()->create(), [
        'title' => 'Inventario licenze software assente',
        'problem' => 'Le licenze acquistate non sono censite in un inventario verificabile.',
    ]);

    $similarResult = app(FindRelatedFindingTemplates::class)->forFinding($similar);
    $distinctResult = app(FindRelatedFindingTemplates::class)->forFinding($distinct);

    expect($similarResult['exact'])->toBeNull()
        ->and($similarResult['similar'])->not->toBeEmpty()
        ->and(count($similarResult['similar']))->toBeLessThanOrEqual(3)
        ->and($distinctResult['exact'])->toBeNull()
        ->and($distinctResult['similar'])->toBe([]);
});

it('ignores deleted exact templates and detects disabled exact templates without enabling them', function (): void {
    $deleted = createLearningTemplate();
    $finding = app(CopyTemplateToAssessment::class)(Assessment::factory()->create(), $deleted);
    $finding->update(['source_template_id' => null, 'source_template_fingerprint' => null]);
    $deleted->delete();

    expect(app(FindRelatedFindingTemplates::class)->forFinding($finding->fresh('solutions'))['exact'])->toBeNull();

    $disabled = createLearningTemplate(['title' => 'Template disabilitato identico']);
    $disabledFinding = app(CopyTemplateToAssessment::class)(Assessment::factory()->create(), $disabled);
    $disabledFinding->update(['source_template_id' => null, 'source_template_fingerprint' => null]);
    $disabled->update(['is_enabled' => false]);
    $exact = app(FindRelatedFindingTemplates::class)->forFinding($disabledFinding->fresh('solutions'))['exact'];

    expect($exact?->is_enabled)->toBeFalse();
});

it('full replaces an unchanged source lineage while preserving identities and detached siblings', function (): void {
    $template = createLearningTemplate(solutionCount: 2);
    $firstAssessment = Assessment::factory()->create();
    $secondAssessment = Assessment::factory()->create();
    $first = app(CopyTemplateToAssessment::class)($firstAssessment, $template);
    $second = app(CopyTemplateToAssessment::class)($secondAssessment, $template);
    $originalExternalId = $template->solutions()->orderBy('sort_order')->firstOrFail()->external_id;
    $removedExternalId = $template->solutions()->orderBy('sort_order')->get()->last()->external_id;
    $firstSolution = $first->solutions()->orderBy('sort_order')->firstOrFail();
    $firstSolution->update(['description' => 'Descrizione migliorata dal campo.']);
    $first->solutions()->orderBy('sort_order')->get()->last()->delete();
    $newSolution = createLearningSolution($first, 'Nuova soluzione dal campo', 2, false);
    $first->update(['problem' => 'Problema migliorato nel Workspace.']);

    $result = app(UpdateTemplateFromFinding::class)->handle($first, 1);
    $updated = $result->template->fresh('solutions');

    expect($result->outcome)->toBe(FindingTemplateSyncResult::UPDATED)
        ->and($updated->problem)->toBe('Problema migliorato nel Workspace.')
        ->and($updated->solutions)->toHaveCount(2)
        ->and($updated->solutions->first()->external_id)->toBe($originalExternalId)
        ->and($newSolution->fresh()->external_key)->toBe($updated->solutions->last()->external_id)
        ->and(FindingTemplateSolution::withTrashed()->where('finding_template_id', $template->id)->where('external_id', $removedExternalId)->firstOrFail()->trashed())->toBeTrue()
        ->and($result->finding->source_template_fingerprint)->toBe(app(FindingTemplateContent::class)->fingerprint($updated))
        ->and($second->fresh()->problem)->not->toBe('Problema migliorato nel Workspace.')
        ->and($second->solutions()->count())->toBe(2);
});

it('prevents a stale sibling from regressing a template updated by another Finding', function (): void {
    $template = createLearningTemplate();
    $first = app(CopyTemplateToAssessment::class)(Assessment::factory()->create(), $template);
    $second = app(CopyTemplateToAssessment::class)(Assessment::factory()->create(), $template);
    $first->update(['problem' => 'Versione B migliorata.']);
    app(UpdateTemplateFromFinding::class)->handle($first, 1);
    $currentFingerprint = app(FindingTemplateContent::class)->fingerprint($template->fresh('solutions'));

    expect(fn () => app(UpdateTemplateFromFinding::class)->preview($second))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(UpdateTemplateFromFinding::class)->handle($second, 1))
        ->toThrow(ValidationException::class)
        ->and(app(FindingTemplateContent::class)->fingerprint($template->fresh('solutions')))->toBe($currentFingerprint)
        ->and($template->fresh()->problem)->toBe('Versione B migliorata.');
});

it('initializes identical legacy lineage and refuses differing legacy or deleted sources', function (): void {
    $template = createLearningTemplate();
    $legacyIdentical = app(CopyTemplateToAssessment::class)(Assessment::factory()->create(), $template);
    $legacyIdentical->update(['source_template_fingerprint' => null]);

    $aligned = app(UpdateTemplateFromFinding::class)->handle($legacyIdentical, 1);
    expect($aligned->outcome)->toBe(FindingTemplateSyncResult::ALREADY_ALIGNED)
        ->and($aligned->finding->source_template_fingerprint)->not->toBeNull();

    $legacyDifferent = app(CopyTemplateToAssessment::class)(Assessment::factory()->create(), $template);
    $legacyDifferent->update(['source_template_fingerprint' => null, 'problem' => 'Contenuto legacy diverso.']);
    expect(fn () => app(UpdateTemplateFromFinding::class)->handle($legacyDifferent, 1))
        ->toThrow(ValidationException::class)
        ->and($legacyDifferent->fresh()->source_template_fingerprint)->toBeNull();

    $deletedSource = app(CopyTemplateToAssessment::class)(Assessment::factory()->create(), $template);
    $template->delete();
    expect(fn () => app(UpdateTemplateFromFinding::class)->handle($deletedSource, 1))
        ->toThrow(ValidationException::class)
        ->and($template->fresh()->trashed())->toBeTrue();

    $newLineage = app(SaveFindingAsTemplate::class)->create($deletedSource, 1);
    expect($newLineage->outcome)->toBe(FindingTemplateSyncResult::CREATED)
        ->and($newLineage->finding->source_template_id)->not->toBe($template->id)
        ->and($template->fresh()->trashed())->toBeTrue();
});

it('treats a hard cleared source as unlinked and permits a new lineage after source deletion', function (): void {
    $template = createLearningTemplate();
    $assessment = Assessment::factory()->create();
    $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
    $oldFingerprint = $finding->source_template_fingerprint;
    $template->forceDelete();
    $finding = $finding->fresh('solutions');

    expect($finding->source_template_id)->toBeNull()
        ->and($finding->source_template_fingerprint)->toBe($oldFingerprint);

    $result = app(SaveFindingAsTemplate::class)->create($finding, 1);
    expect($result->outcome)->toBe(FindingTemplateSyncResult::CREATED)
        ->and($result->finding->source_template_id)->not->toBeNull()
        ->and($result->finding->source_template_fingerprint)
        ->toBe(app(FindingTemplateContent::class)->fingerprint($result->template->fresh('solutions')));
});

it('keeps disabled sources disabled and rejects server-side template mutation for read-only assessments', function (): void {
    $template = createLearningTemplate(['is_enabled' => false]);
    $assessment = Assessment::factory()->create();
    $template->update(['is_enabled' => true]);
    $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
    $template->update(['is_enabled' => false]);
    $finding->update(['problem' => 'Miglioria su template disabilitato.']);

    app(UpdateTemplateFromFinding::class)->handle($finding, 1);
    expect($template->fresh()->is_enabled)->toBeFalse();

    $assessment->update(['status' => AssessmentStatus::Completed]);
    expect(fn () => app(SaveFindingAsTemplate::class)->create($finding->fresh(), 2))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(UpdateTemplateFromFinding::class)->handle($finding->fresh(), 2))
        ->toThrow(ValidationException::class);

    $exactAssessment = Assessment::factory()->create();
    $template->update(['is_enabled' => true]);
    $exactFinding = app(CopyTemplateToAssessment::class)($exactAssessment, $template->fresh());
    $template->update(['is_enabled' => false]);
    $exactAssessment->update(['status' => AssessmentStatus::Archived]);
    expect(fn () => app(SaveFindingAsTemplate::class)->linkExact($exactFinding, $template->fresh(), 1))
        ->toThrow(ValidationException::class);
});

it('avoids a template write and version increment when source content is already aligned', function (): void {
    $template = createLearningTemplate();
    $assessment = Assessment::factory()->create();
    $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
    $updatedAt = $template->updated_at;

    $result = app(UpdateTemplateFromFinding::class)->handle($finding, 1);

    expect($result->outcome)->toBe(FindingTemplateSyncResult::ALREADY_ALIGNED)
        ->and($result->appliedVersion)->toBe(1)
        ->and($assessment->fresh()->lock_version)->toBe(1)
        ->and($template->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('rejects historical non selectable risk classification for a new template without remapping it', function (): void {
    $profile = RiskProfile::query()->create([
        'code' => 'historical-learning',
        'label' => 'Profilo storico learning',
        'is_default' => false,
        'is_enabled' => false,
    ]);
    $consequence = ConsequenceLevel::query()->create([
        'risk_profile_id' => $profile->id,
        'code' => 'historical-consequence',
        'label' => 'Conseguenza storica',
        'score' => 1,
        'color' => '#111111',
        'sort_order' => 1,
        'is_enabled' => false,
    ]);
    $likelihood = LikelihoodLevel::query()->create([
        'risk_profile_id' => $profile->id,
        'code' => 'historical-likelihood',
        'label' => 'Probabilità storica',
        'score' => 1,
        'color' => '#222222',
        'sort_order' => 1,
        'is_enabled' => false,
    ]);
    $priority = PriorityLevel::query()->create([
        'risk_profile_id' => $profile->id,
        'code' => 'historical-priority',
        'label' => 'Priorità storica',
        'color' => '#333333',
        'sort_order' => 1,
        'is_enabled' => false,
    ]);
    RiskMatrixEntry::query()->create([
        'risk_profile_id' => $profile->id,
        'consequence_level_id' => $consequence->id,
        'likelihood_level_id' => $likelihood->id,
        'priority_level_id' => $priority->id,
    ]);
    $assessment = Assessment::factory()->create();
    $finding = createManualLearningFinding($assessment, [
        'consequence_level_id' => $consequence->id,
        'likelihood_level_id' => $likelihood->id,
        'priority_level_id' => $priority->id,
    ]);

    expect(fn () => app(SaveFindingAsTemplate::class)->create($finding, 0))
        ->toThrow(ValidationException::class)
        ->and(FindingTemplate::query()->count())->toBe(0)
        ->and($assessment->fresh()->lock_version)->toBe(0);
});

/** @param array<string, mixed> $overrides */
function createLearningTemplate(array $overrides = [], int $solutionCount = 1): FindingTemplate
{
    $entry = RiskMatrixEntry::query()->firstOrFail();
    $solutions = [];
    foreach (range(1, $solutionCount) as $number) {
        $solutions[] = [
            'title' => "Soluzione riutilizzabile {$number}",
            'description' => "Descrizione completa soluzione {$number}.",
            'comparison_notes' => $number === 1 ? 'Confronto riutilizzabile.' : null,
            'effort_level_id' => null,
            'effort_notes' => null,
            'estimate_type' => EstimateType::NotApplicable->value,
            'amount_min' => null,
            'amount_max' => null,
            'currency_code' => null,
            'billing_frequency' => BillingFrequency::OneOff->value,
            'custom_billing_frequency' => null,
            'estimate_notes' => null,
            'is_recommended' => $number === 1,
            'sort_order' => $number - 1,
        ];
    }

    return app(SaveFindingTemplate::class)->handle(null, array_replace([
        'title' => 'Template apprendimento dal campo',
        'category_id' => Category::query()->where('slug', 'sicurezza')->firstOrFail()->id,
        'problem' => 'Problema riutilizzabile descritto in modo completo.',
        'entrepreneur_notes' => 'Nota riutilizzabile per imprenditore.',
        'technical_notes' => 'Nota tecnica riutilizzabile.',
        'default_scope_type' => ScopeType::Organization->value,
        'default_scope_description' => null,
        'default_consequence_level_id' => $entry->consequence_level_id,
        'default_likelihood_level_id' => $entry->likelihood_level_id,
        'default_priority_level_id' => $entry->priority_level_id,
        'priority_rationale' => 'Motivazione riutilizzabile.',
        'is_enabled' => true,
        'solutions' => $solutions,
    ], $overrides));
}

/** @param array<string, mixed> $overrides */
function createManualLearningFinding(
    Assessment $assessment,
    array $overrides = [],
    bool $priorityOverride = false,
): Finding {
    $entry = RiskMatrixEntry::query()->firstOrFail();
    $finding = Finding::factory()->for($assessment)->create(array_replace([
        'title' => 'Finding appreso sul campo',
        'category_id' => Category::query()->where('slug', 'sicurezza')->firstOrFail()->id,
        'problem' => 'Problema rilevato e descritto sul campo.',
        'entrepreneur_notes' => 'Nota utile e riutilizzabile.',
        'technical_notes' => 'Dettaglio tecnico riutilizzabile.',
        'scope_type' => ScopeType::Organization->value,
        'scope_description' => null,
        'consequence_level_id' => $entry->consequence_level_id,
        'likelihood_level_id' => $entry->likelihood_level_id,
        'priority_level_id' => $entry->priority_level_id,
        'priority_is_overridden' => $priorityOverride,
        'priority_rationale' => $priorityOverride ? 'Motivo specifico cliente.' : 'Motivazione riutilizzabile.',
    ], $overrides));
    $solution = createLearningSolution($finding, 'Soluzione manuale raccomandata', 0, true);
    $finding->update(['recommended_solution_id' => $solution->id]);

    return $finding->fresh('solutions');
}

function createLearningSolution(
    Finding $finding,
    string $title,
    int $sortOrder,
    bool $recommended,
): FindingSolution {
    $solution = $finding->solutions()->create([
        'external_key' => null,
        'title' => $title,
        'description' => "Descrizione completa per {$title}.",
        'comparison_notes' => null,
        'effort_level_id' => null,
        'effort_notes' => null,
        'estimate_type' => EstimateType::NotApplicable,
        'amount_min' => null,
        'amount_max' => null,
        'currency_code' => null,
        'billing_frequency' => BillingFrequency::OneOff,
        'custom_billing_frequency' => null,
        'estimate_notes' => null,
        'sort_order' => $sortOrder,
    ]);
    $solution->update(['external_key' => "manual-{$solution->id}"]);
    if ($recommended) {
        $finding->update(['recommended_solution_id' => $solution->id]);
    }

    return $solution;
}
