<?php

declare(strict_types=1);

use App\Actions\Assessments\SaveFindingDetails;
use App\Actions\Risk\CalculateFindingPriority;
use App\Actions\Risk\SaveRiskProfileConfiguration;
use App\Actions\Templates\ImportFindingTemplates;
use App\Data\Assessments\FindingSaveData;
use App\Filament\Resources\Assessments\Schemas\FindingEditorSchema;
use App\Filament\Widgets\UrgentFindings;
use App\Models\Assessment;
use App\Models\ConsequenceLevel;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Models\LikelihoodLevel;
use App\Models\PriorityLevel;
use App\Models\RiskMatrixEntry;
use App\Models\RiskProfile;
use App\Services\Risk\ActiveRiskProfileResolver;
use App\Services\Risk\UrgentPriorityResolver;
use App\Settings\GeneralSettings;
use Database\Seeders\MilestoneOneSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(MilestoneOneSeeder::class);
});

it('offers only active risk levels while retaining a selected historical label', function (): void {
    $historical = ConsequenceLevel::query()->where('code', 'limited')->firstOrFail();
    $profile = createCustomActiveRiskProfile();
    $resolver = app(ActiveRiskProfileResolver::class);

    expect($resolver->resolve()->is($profile))->toBeTrue()
        ->and(array_keys($resolver->consequenceOptions()))
        ->toBe($profile->consequenceLevels()->pluck('id')->all())
        ->and($resolver->consequenceOptions((int) $historical->getKey()))
        ->toHaveKey((int) $historical->getKey(), $historical->label)
        ->and($resolver->priorityOptions())->not->toContain('Alta', 'Critica');
});

it('preserves historical finding risk ids but requires active levels for reclassification', function (): void {
    $default = RiskProfile::query()->where('is_default', true)->firstOrFail();
    $consequence = $default->consequenceLevels()->firstOrFail();
    $likelihood = $default->likelihoodLevels()->firstOrFail();
    $priority = app(CalculateFindingPriority::class)($consequence, $likelihood);
    $assessment = Assessment::factory()->create();
    $finding = Finding::factory()->for($assessment)->create([
        'scope_type' => 'organization',
        'status' => 'open',
        'include_in_report' => true,
        'priority_is_overridden' => false,
        'consequence_level_id' => $consequence->getKey(),
        'likelihood_level_id' => $likelihood->getKey(),
        'priority_level_id' => $priority->getKey(),
    ]);
    $active = createCustomActiveRiskProfile();

    $payload = FindingEditorSchema::data($finding);
    $payload['title'] = 'Modifica non legata al rischio';
    app(SaveFindingDetails::class)($finding, activeRiskFindingRequest($assessment, $payload));

    expect($finding->fresh()->consequence_level_id)->toBe($consequence->getKey())
        ->and($finding->fresh()->likelihood_level_id)->toBe($likelihood->getKey())
        ->and($finding->fresh()->priority_level_id)->toBe($priority->getKey());

    $payload = FindingEditorSchema::data($finding->fresh());
    $payload['consequence_level_id'] = $active->consequenceLevels()->firstOrFail()->getKey();
    expect(fn () => app(SaveFindingDetails::class)(
        $finding->fresh(),
        activeRiskFindingRequest($assessment->fresh(), $payload),
    ))->toThrow(ValidationException::class);

    $activeConsequence = $active->consequenceLevels()->firstOrFail();
    $activeLikelihood = $active->likelihoodLevels()->firstOrFail();
    $activePriority = app(CalculateFindingPriority::class)($activeConsequence, $activeLikelihood);
    $payload['likelihood_level_id'] = $activeLikelihood->getKey();
    $payload['priority_level_id'] = $activePriority->getKey();
    app(SaveFindingDetails::class)(
        $finding->fresh(),
        activeRiskFindingRequest($assessment->fresh(), $payload),
    );

    expect($finding->fresh()->consequence_level_id)->toBe($activeConsequence->getKey())
        ->and($finding->fresh()->likelihood_level_id)->toBe($activeLikelihood->getKey())
        ->and($finding->fresh()->priority_level_id)->toBe($activePriority->getKey());
});

it('imports schema v2 against the active profile rather than the default profile', function (): void {
    $profile = createCustomActiveRiskProfile();
    $payload = customRiskTemplateDocument();

    app(ImportFindingTemplates::class)->preview(json_encode($payload, JSON_THROW_ON_ERROR));
    $result = app(ImportFindingTemplates::class)(json_encode($payload, JSON_THROW_ON_ERROR), 'replace');
    $template = FindingTemplate::query()->where('external_id', 'fixture.exact.monthly')->firstOrFail();

    expect($result['created'])->toBe(1)
        ->and($template->defaultConsequenceLevel?->risk_profile_id)->toBe($profile->getKey())
        ->and($template->defaultLikelihoodLevel?->risk_profile_id)->toBe($profile->getKey())
        ->and($template->defaultPriorityLevel?->risk_profile_id)->toBe($profile->getKey())
        ->and(RiskProfile::query()->where('is_default', true)->firstOrFail()->is($profile))->toBeFalse();
});

it('rejects unknown active codes and matrix-incoherent schema v2 documents with context', function (): void {
    createCustomActiveRiskProfile();
    $unknown = customRiskTemplateDocument();
    $unknown['templates'][0]['priority'] = 'missing_code';

    try {
        app(ImportFindingTemplates::class)->preview(json_encode($unknown, JSON_THROW_ON_ERROR));
        $this->fail('Unknown risk code should fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('templates.0.priority')
            ->and($exception->getMessage())->toContain('fixture.exact.monthly', 'missing_code');
    }

    $mismatch = customRiskTemplateDocument();
    $mismatch['templates'][0]['priority'] = 'emergency';
    expect(fn () => app(ImportFindingTemplates::class)(
        json_encode($mismatch, JSON_THROW_ON_ERROR),
        'replace',
    ))->toThrow(ValidationException::class)
        ->and(FindingTemplate::query()->count())->toBe(0);
});

it('prevents disabling the active risk profile independently of the default flag', function (): void {
    $profile = createCustomActiveRiskProfile();
    $payload = persistedRiskProfilePayload($profile);
    $payload['is_enabled'] = false;

    expect(fn () => app(SaveRiskProfileConfiguration::class)->handle($profile, $payload))
        ->toThrow(ValidationException::class)
        ->and($profile->fresh()->is_enabled)->toBeTrue()
        ->and($profile->fresh()->is_default)->toBeFalse();
});

it('derives urgent findings from the two highest levels of every profile without code hardcodes', function (): void {
    $profile = createCustomActiveRiskProfile();
    $assessment = Assessment::factory()->create();
    $priorities = $profile->priorityLevels()->orderBy('sort_order')->get();
    foreach ($priorities as $priority) {
        Finding::factory()->for($assessment)->create([
            'title' => 'Finding '.$priority->code,
            'priority_level_id' => $priority->getKey(),
            'include_in_report' => true,
            'status' => 'open',
        ]);
    }

    $urgentIds = app(UrgentPriorityResolver::class)->ids();
    expect($urgentIds)->toContain(
        (int) $priorities[2]->getKey(),
        (int) $priorities[3]->getKey(),
    )->not->toContain((int) $priorities[0]->getKey(), (int) $priorities[1]->getKey());

    Livewire::test(UrgentFindings::class)
        ->assertSee('Finding urgent_now')
        ->assertSee('Finding emergency')
        ->assertDontSee('Finding watch')
        ->assertDontSee('Finding attention');
});

function createCustomActiveRiskProfile(): RiskProfile
{
    $profile = RiskProfile::query()->create([
        'code' => 'custom_operational',
        'label' => 'Profilo operativo personalizzato',
        'is_default' => false,
        'is_enabled' => true,
    ]);
    $consequenceCodes = ['contained', 'material', 'major', 'catastrophic'];
    $likelihoodCodes = ['remote', 'plausible', 'expected', 'ongoing'];
    $priorityCodes = ['watch', 'attention', 'urgent_now', 'emergency'];
    $consequences = [];
    $likelihoods = [];
    $priorities = [];
    foreach (range(0, 3) as $index) {
        $consequences[] = ConsequenceLevel::query()->create([
            'risk_profile_id' => $profile->getKey(), 'code' => $consequenceCodes[$index],
            'label' => ucfirst($consequenceCodes[$index]), 'score' => $index + 1,
            'color' => '#2563EB', 'sort_order' => $index + 1, 'is_enabled' => true,
        ]);
        $likelihoods[] = LikelihoodLevel::query()->create([
            'risk_profile_id' => $profile->getKey(), 'code' => $likelihoodCodes[$index],
            'label' => ucfirst($likelihoodCodes[$index]), 'score' => $index + 1,
            'color' => '#D97706', 'sort_order' => $index + 1, 'is_enabled' => true,
        ]);
        $priorities[] = PriorityLevel::query()->create([
            'risk_profile_id' => $profile->getKey(), 'code' => $priorityCodes[$index],
            'label' => ucfirst(str_replace('_', ' ', $priorityCodes[$index])),
            'color' => '#DC2626', 'sort_order' => $index + 1, 'is_enabled' => true,
        ]);
    }
    foreach ($consequences as $consequenceIndex => $consequence) {
        foreach ($likelihoods as $likelihoodIndex => $likelihood) {
            RiskMatrixEntry::query()->create([
                'risk_profile_id' => $profile->getKey(),
                'consequence_level_id' => $consequence->getKey(),
                'likelihood_level_id' => $likelihood->getKey(),
                'priority_level_id' => $priorities[min(3, intdiv($consequenceIndex + $likelihoodIndex, 2))]->getKey(),
            ]);
        }
    }

    $settings = app(GeneralSettings::class);
    $settings->active_risk_profile_id = (int) $profile->getKey();
    $settings->save();

    return $profile->refresh();
}

/** @param array<string, mixed> $payload */
function activeRiskFindingRequest(Assessment $assessment, array $payload): FindingSaveData
{
    return new FindingSaveData(
        requestId: (string) Str::uuid(),
        expectedVersion: (int) $assessment->lock_version,
        tabId: (string) Str::uuid(),
        payload: $payload,
        payloadSha256: FindingSaveData::hashPayload($payload),
    );
}

/** @return array<string, mixed> */
function customRiskTemplateDocument(): array
{
    $payload = json_decode(
        (string) file_get_contents(base_path('fixtures/imports/valid-all-branches.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $payload['schema_version'] = 2;
    $payload['templates'] = [$payload['templates'][0]];
    $payload['templates'][0]['consequence'] = 'contained';
    $payload['templates'][0]['likelihood'] = 'remote';
    $payload['templates'][0]['priority'] = 'watch';

    return $payload;
}

/** @return array<string, mixed> */
function persistedRiskProfilePayload(RiskProfile $profile): array
{
    $profile->load(['consequenceLevels', 'likelihoodLevels', 'priorityLevels', 'matrixEntries']);
    $scored = static fn (ConsequenceLevel|LikelihoodLevel $level): array => [
        'id' => $level->getKey(), 'code' => $level->code, 'label' => $level->label,
        'description' => $level->description, 'score' => $level->score, 'color' => $level->color,
        'sort_order' => $level->sort_order, 'is_enabled' => $level->is_enabled,
    ];

    return [
        'code' => $profile->code, 'label' => $profile->label, 'description' => $profile->description,
        'is_default' => $profile->is_default, 'is_enabled' => $profile->is_enabled,
        'consequences' => $profile->consequenceLevels->map($scored)->all(),
        'likelihoods' => $profile->likelihoodLevels->map($scored)->all(),
        'priorities' => $profile->priorityLevels->map(static fn (PriorityLevel $level): array => [
            'id' => $level->getKey(), 'code' => $level->code, 'label' => $level->label,
            'description' => $level->description, 'color' => $level->color,
            'sort_order' => $level->sort_order, 'is_enabled' => $level->is_enabled,
        ])->all(),
        'matrix' => $profile->matrixEntries->reduce(static function (array $matrix, RiskMatrixEntry $entry): array {
            $matrix[(string) $entry->consequence_level_id][(string) $entry->likelihood_level_id] = (string) $entry->priority_level_id;

            return $matrix;
        }, []),
    ];
}
