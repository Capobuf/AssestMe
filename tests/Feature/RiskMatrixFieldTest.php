<?php

declare(strict_types=1);

use App\Actions\Risk\SaveRiskProfileConfiguration;
use App\Filament\Components\RiskMatrixField;
use App\Filament\Resources\RiskProfiles\Pages\EditRiskProfile;
use App\Models\ConsequenceLevel;
use App\Models\Finding;
use App\Models\LikelihoodLevel;
use App\Models\PriorityLevel;
use App\Models\RiskMatrixEntry;
use App\Models\RiskProfile;
use App\Models\User;
use Database\Seeders\MilestoneOneSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(MilestoneOneSeeder::class);
});

it('mounts the dedicated field as an accessible semantic four by four table', function (): void {
    $this->actingAs(User::factory()->create());
    $profile = defaultRiskMatrixProfile();
    $component = Livewire::test(EditRiskProfile::class, ['record' => $profile->getRouteKey()]);
    $html = $component->html();

    expect($component->instance()->form->getComponentByStatePath('matrix'))->toBeInstanceOf(RiskMatrixField::class)
        ->and(substr_count($html, 'data-dusk="risk-matrix-cell-'))->toBe(16)
        ->and(substr_count($html, 'scope="row"'))->toBe(4)
        ->and(substr_count($html, 'scope="col"'))->toBe(5)
        ->and(substr_count($html, '<select'))->toBeGreaterThanOrEqual(16);

    $component
        ->assertSeeInOrder(['Matrice', 'Conseguenze', 'Probabilità', 'Priorità', 'Profilo'])
        ->assertSee('Improbabile')
        ->assertSee('Possibile')
        ->assertSee('Probabile')
        ->assertSee('Attuale o imminente')
        ->assertSee('Limitata')
        ->assertSee('Significativa')
        ->assertSee('Seria')
        ->assertSee('Critica')
        ->assertSee(__('assestme.risk.matrix_cell_label', [
            'consequence' => 'Seria',
            'likelihood' => 'Possibile',
        ]));
});

it('loads all existing entry values into nested id keyed Livewire state', function (): void {
    $this->actingAs(User::factory()->create());
    $profile = defaultRiskMatrixProfile();
    $component = Livewire::test(EditRiskProfile::class, ['record' => $profile->getRouteKey()]);

    foreach ($profile->matrixEntries as $entry) {
        $component->assertSet(
            "data.matrix.{$entry->consequence_level_id}.{$entry->likelihood_level_id}",
            (string) $entry->priority_level_id,
        );
    }
});

it('persists one changed cell without duplicating matrix entries', function (): void {
    $this->actingAs(User::factory()->create());
    $profile = defaultRiskMatrixProfile();
    $entry = $profile->matrixEntries()->firstOrFail();
    $replacement = $profile->priorityLevels()->whereKeyNot($entry->priority_level_id)->firstOrFail();

    Livewire::test(EditRiskProfile::class, ['record' => $profile->getRouteKey()])
        ->set("data.matrix.{$entry->consequence_level_id}.{$entry->likelihood_level_id}", (string) $replacement->getKey())
        ->call('save', false)
        ->assertHasNoErrors();

    expect($entry->fresh()->priority_level_id)->toBe($replacement->getKey())
        ->and($profile->matrixEntries()->count())->toBe(16)
        ->and($profile->matrixEntries()
            ->get(['consequence_level_id', 'likelihood_level_id'])
            ->unique(static fn (RiskMatrixEntry $matrixEntry): string => "{$matrixEntry->consequence_level_id}|{$matrixEntry->likelihood_level_id}")
            ->count())->toBe(16);
});

it('persists multiple cell changes and still stores exactly sixteen combinations', function (): void {
    $this->actingAs(User::factory()->create());
    $profile = defaultRiskMatrixProfile();
    $entries = $profile->matrixEntries()->limit(3)->get();
    $replacement = $profile->priorityLevels()->orderByDesc('sort_order')->firstOrFail();
    $component = Livewire::test(EditRiskProfile::class, ['record' => $profile->getRouteKey()]);

    foreach ($entries as $entry) {
        $component->set(
            "data.matrix.{$entry->consequence_level_id}.{$entry->likelihood_level_id}",
            (string) $replacement->getKey(),
        );
    }

    $component->call('save', false)->assertHasNoErrors();

    foreach ($entries as $entry) {
        expect($entry->fresh()->priority_level_id)->toBe($replacement->getKey());
    }
    expect($profile->matrixEntries()->count())->toBe(16);
});

it('does not recalculate existing findings when a matrix cell changes', function (): void {
    $profile = defaultRiskMatrixProfile();
    $entry = $profile->matrixEntries()->firstOrFail();
    $replacement = $profile->priorityLevels()->whereKeyNot($entry->priority_level_id)->firstOrFail();
    $finding = Finding::factory()->create([
        'consequence_level_id' => $entry->consequence_level_id,
        'likelihood_level_id' => $entry->likelihood_level_id,
        'priority_level_id' => $entry->priority_level_id,
    ]);
    $originalFindingPriorityId = $finding->priority_level_id;
    $payload = riskMatrixPayload($profile);
    $payload['matrix'][(string) $entry->consequence_level_id][(string) $entry->likelihood_level_id] = (string) $replacement->getKey();

    app(SaveRiskProfileConfiguration::class)->handle($profile, $payload);

    expect($entry->fresh()->priority_level_id)->toBe($replacement->getKey())
        ->and($finding->fresh()->priority_level_id)->toBe($originalFindingPriorityId);
});

it('keeps associations while labels order and colors change', function (): void {
    $this->actingAs(User::factory()->create());
    $profile = defaultRiskMatrixProfile();
    $before = matrixPriorityMap($profile);
    $codes = [
        'profile' => $profile->code,
        'consequences' => $profile->consequenceLevels()->pluck('code', 'id')->sortKeys()->all(),
        'likelihoods' => $profile->likelihoodLevels()->pluck('code', 'id')->sortKeys()->all(),
        'priorities' => $profile->priorityLevels()->pluck('code', 'id')->sortKeys()->all(),
    ];
    $component = Livewire::test(EditRiskProfile::class, ['record' => $profile->getRouteKey()]);
    $consequenceState = $component->get('data.consequences');
    $priorityState = $component->get('data.priorities');
    $consequenceKey = repeaterKeyForId($consequenceState, (int) $profile->consequenceLevels->first()->getKey());
    $priorityKey = repeaterKeyForId($priorityState, (int) $profile->priorityLevels->first()->getKey());

    $component
        ->set("data.consequences.{$consequenceKey}.label", 'Conseguenza rinominata')
        ->set("data.consequences.{$consequenceKey}.sort_order", 99)
        ->set("data.priorities.{$priorityKey}.color", '#123ABC')
        ->assertSee('Conseguenza rinominata')
        ->assertSeeHtml('--assestme-risk-color: #123ABC');

    $matrixHtml = (string) str($component->html())->after('data-dusk="risk-matrix-grid"')->before('</table>');
    expect(strpos($matrixHtml, 'Significativa'))->toBeLessThan(strpos($matrixHtml, 'Conseguenza rinominata'));

    $component->call('save', false)
        ->assertHasNoErrors();

    $profile->refresh();
    expect(matrixPriorityMap($profile))->toBe($before)
        ->and($profile->code)->toBe($codes['profile'])
        ->and($profile->consequenceLevels()->pluck('code', 'id')->sortKeys()->all())->toBe($codes['consequences'])
        ->and($profile->likelihoodLevels()->pluck('code', 'id')->sortKeys()->all())->toBe($codes['likelihoods'])
        ->and($profile->priorityLevels()->pluck('code', 'id')->sortKeys()->all())->toBe($codes['priorities'])
        ->and($profile->consequenceLevels()->orderBy('sort_order')->get()->last()->label)->toBe('Conseguenza rinominata')
        ->and($profile->priorityLevels()->findOrFail($profile->priorityLevels->first()->getKey())->color)->toBe('#123ABC');
});

it('preserves matrix associations when a priority is disabled', function (): void {
    $profile = defaultRiskMatrixProfile();
    $payload = riskMatrixPayload($profile);
    $before = matrixPriorityMap($profile);
    $priorityId = (int) reset($before);
    $priorityIndex = array_search($priorityId, array_column($payload['priorities'], 'id'), true);
    if ($priorityIndex === false) {
        throw new RuntimeException('The priority referenced by the matrix was not found in the form payload.');
    }
    $payload['priorities'][$priorityIndex]['is_enabled'] = false;

    $saved = app(SaveRiskProfileConfiguration::class)->handle($profile, $payload);

    expect(matrixPriorityMap($saved))->toBe($before)
        ->and($saved->priorityLevels()->findOrFail($priorityId)->is_enabled)->toBeFalse()
        ->and($saved->matrixEntries()->count())->toBe(16);
});

it('does not delete associations when disabling a required dimension is rejected', function (): void {
    $profile = defaultRiskMatrixProfile();
    $payload = riskMatrixPayload($profile);
    $payload['consequences'][0]['is_enabled'] = false;
    $before = matrixPriorityMap($profile);

    expect(fn () => app(SaveRiskProfileConfiguration::class)->handle($profile, $payload))
        ->toThrow(ValidationException::class, __('assestme.risk.errors.four_enabled_consequences'));

    expect(matrixPriorityMap($profile->fresh()))->toBe($before)
        ->and($profile->consequenceLevels()->firstOrFail()->is_enabled)->toBeTrue();
});

it('rejects foreign consequence likelihood and priority identifiers', function (string $dimension): void {
    $profile = defaultRiskMatrixProfile();
    [$foreignConsequence, $foreignLikelihood, $foreignPriority] = foreignRiskLevels();
    $payload = riskMatrixPayload($profile);
    $before = matrixPriorityMap($profile);

    if ($dimension === 'consequence') {
        $payload['consequences'][0]['id'] = $foreignConsequence->getKey();
    } elseif ($dimension === 'likelihood') {
        $payload['likelihoods'][0]['id'] = $foreignLikelihood->getKey();
    } else {
        $firstConsequence = (string) array_key_first($payload['matrix']);
        $firstLikelihood = (string) array_key_first($payload['matrix'][$firstConsequence]);
        $payload['matrix'][$firstConsequence][$firstLikelihood] = (string) $foreignPriority->getKey();
    }

    expect(fn () => app(SaveRiskProfileConfiguration::class)->handle($profile, $payload))
        ->toThrow(ValidationException::class);

    expect(matrixPriorityMap($profile->fresh()))->toBe($before)
        ->and($profile->matrixEntries()->count())->toBe(16);
})->with(['consequence', 'likelihood', 'priority']);

it('rejects matrices with fifteen or seventeen cells', function (int $cellCount): void {
    $profile = defaultRiskMatrixProfile();
    $payload = riskMatrixPayload($profile);
    $firstConsequence = (string) array_key_first($payload['matrix']);

    if ($cellCount === 15) {
        $firstLikelihood = (string) array_key_first($payload['matrix'][$firstConsequence]);
        unset($payload['matrix'][$firstConsequence][$firstLikelihood]);
    } else {
        $payload['matrix'][$firstConsequence]['unknown-likelihood'] = (string) $profile->priorityLevels->first()->getKey();
    }

    expect(fn () => app(SaveRiskProfileConfiguration::class)->handle($profile, $payload))
        ->toThrow(ValidationException::class, __('assestme.risk.errors.invalid_matrix'));

    expect($profile->matrixEntries()->count())->toBe(16);
})->with([15, 17]);

it('rolls back the complete aggregate when relational level persistence fails', function (): void {
    $profile = defaultRiskMatrixProfile();
    $payload = riskMatrixPayload($profile);
    $payload['label'] = 'Etichetta che deve essere annullata';
    $payload['consequences'][0]['label'] = 'Conseguenza da annullare';
    [$payload['consequences'][0]['score'], $payload['consequences'][1]['score']] = [
        $payload['consequences'][1]['score'],
        $payload['consequences'][0]['score'],
    ];
    $before = matrixPriorityMap($profile);
    $originalProfileLabel = $profile->label;
    $originalConsequenceLabel = $profile->consequenceLevels->first()->label;

    expect(fn () => app(SaveRiskProfileConfiguration::class)->handle($profile, $payload))
        ->toThrow(QueryException::class);

    $profile->refresh();
    expect($profile->label)->toBe($originalProfileLabel)
        ->and($profile->consequenceLevels->first()->label)->toBe($originalConsequenceLabel)
        ->and(matrixPriorityMap($profile))->toBe($before)
        ->and($profile->matrixEntries()->count())->toBe(16);
});

it('maps stable repeater UUIDs to integer ids when creating a profile atomically', function (): void {
    $payload = newRiskMatrixPayload();
    $temporaryIdentities = [
        ...array_column($payload['consequences'], '_form_key'),
        ...array_column($payload['likelihoods'], '_form_key'),
        ...array_column($payload['priorities'], '_form_key'),
    ];

    $created = app(SaveRiskProfileConfiguration::class)->handle(null, $payload);

    expect($created->getKey())->toBeInt()
        ->and($created->consequenceLevels()->count())->toBe(4)
        ->and($created->likelihoodLevels()->count())->toBe(4)
        ->and($created->priorityLevels()->count())->toBe(2)
        ->and($created->matrixEntries()->count())->toBe(16);

    foreach ($temporaryIdentities as $temporaryIdentity) {
        expect(Str::isUuid($temporaryIdentity))->toBeTrue();
    }
});

it('shows a server error without losing manipulated form state or sending success', function (): void {
    $this->actingAs(User::factory()->create());
    $profile = defaultRiskMatrixProfile();
    [, , $foreignPriority] = foreignRiskLevels();
    $entry = $profile->matrixEntries->first();
    $path = "data.matrix.{$entry->consequence_level_id}.{$entry->likelihood_level_id}";

    Livewire::test(EditRiskProfile::class, ['record' => $profile->getRouteKey()])
        ->set($path, (string) $foreignPriority->getKey())
        ->call('save', false)
        ->assertHasErrors("matrix.{$entry->consequence_level_id}.{$entry->likelihood_level_id}")
        ->assertSet($path, (string) $foreignPriority->getKey())
        ->assertSee(__('assestme.risk.errors.foreign_priority'))
        ->assertNotNotified();

    expect($entry->fresh()->priority_level_id)->not->toBe($foreignPriority->getKey());
});

function defaultRiskMatrixProfile(): RiskProfile
{
    return RiskProfile::query()
        ->where('code', 'default')
        ->with(['consequenceLevels', 'likelihoodLevels', 'priorityLevels', 'matrixEntries'])
        ->firstOrFail();
}

/** @return array<string, mixed> */
function riskMatrixPayload(RiskProfile $profile): array
{
    $profile->load(['consequenceLevels', 'likelihoodLevels', 'priorityLevels', 'matrixEntries']);
    $scored = static fn (ConsequenceLevel|LikelihoodLevel $level): array => [
        'id' => $level->getKey(),
        'code' => $level->code,
        'label' => $level->label,
        'description' => $level->description,
        'score' => $level->score,
        'color' => $level->color,
        'sort_order' => $level->sort_order,
        'is_enabled' => $level->is_enabled,
    ];

    return [
        'code' => $profile->code,
        'label' => $profile->label,
        'description' => $profile->description,
        'is_default' => $profile->is_default,
        'is_enabled' => $profile->is_enabled,
        'consequences' => $profile->consequenceLevels->map($scored)->all(),
        'likelihoods' => $profile->likelihoodLevels->map($scored)->all(),
        'priorities' => $profile->priorityLevels->map(static fn (PriorityLevel $level): array => [
            'id' => $level->getKey(),
            'code' => $level->code,
            'label' => $level->label,
            'description' => $level->description,
            'color' => $level->color,
            'sort_order' => $level->sort_order,
            'is_enabled' => $level->is_enabled,
        ])->all(),
        'matrix' => $profile->matrixEntries->reduce(static function (array $matrix, RiskMatrixEntry $entry): array {
            $matrix[(string) $entry->consequence_level_id][(string) $entry->likelihood_level_id] = (string) $entry->priority_level_id;

            return $matrix;
        }, []),
    ];
}

/** @return array<string, int> */
function matrixPriorityMap(RiskProfile $profile): array
{
    return $profile->matrixEntries()
        ->orderBy('consequence_level_id')
        ->orderBy('likelihood_level_id')
        ->get()
        ->mapWithKeys(static fn (RiskMatrixEntry $entry): array => [
            "{$entry->consequence_level_id}|{$entry->likelihood_level_id}" => $entry->priority_level_id,
        ])
        ->all();
}

/** @param array<int|string, mixed> $state */
function repeaterKeyForId(array $state, int $id): int|string
{
    foreach ($state as $key => $row) {
        if (is_array($row) && (int) ($row['id'] ?? 0) === $id) {
            return $key;
        }
    }

    throw new RuntimeException("Repeater row for ID {$id} was not found.");
}

/** @return array{ConsequenceLevel, LikelihoodLevel, PriorityLevel} */
function foreignRiskLevels(): array
{
    $profile = RiskProfile::query()->create([
        'code' => 'foreign_'.Str::lower(Str::random(8)),
        'label' => 'Profilo estraneo',
        'is_default' => false,
        'is_enabled' => false,
    ]);
    $consequence = ConsequenceLevel::query()->create([
        'risk_profile_id' => $profile->getKey(),
        'code' => 'foreign_consequence',
        'label' => 'Conseguenza estranea',
        'score' => 1,
        'color' => '#111111',
        'sort_order' => 1,
        'is_enabled' => true,
    ]);
    $likelihood = LikelihoodLevel::query()->create([
        'risk_profile_id' => $profile->getKey(),
        'code' => 'foreign_likelihood',
        'label' => 'Probabilità estranea',
        'score' => 1,
        'color' => '#222222',
        'sort_order' => 1,
        'is_enabled' => true,
    ]);
    $priority = PriorityLevel::query()->create([
        'risk_profile_id' => $profile->getKey(),
        'code' => 'foreign_priority',
        'label' => 'Priorità estranea',
        'color' => '#333333',
        'sort_order' => 1,
        'is_enabled' => true,
    ]);

    return [$consequence, $likelihood, $priority];
}

/** @return array<string, mixed> */
function newRiskMatrixPayload(): array
{
    $makeScored = static function (string $prefix, int $index): array {
        return [
            '_form_key' => (string) Str::uuid(),
            'code' => "{$prefix}_{$index}",
            'label' => ucfirst($prefix)." {$index}",
            'description' => null,
            'score' => $index,
            'color' => '#2563EB',
            'sort_order' => $index,
            'is_enabled' => true,
        ];
    };
    $consequences = array_map(static fn (int $index): array => $makeScored('consequence', $index), range(1, 4));
    $likelihoods = array_map(static fn (int $index): array => $makeScored('likelihood', $index), range(1, 4));
    $priorities = array_map(static fn (int $index): array => [
        '_form_key' => (string) Str::uuid(),
        'code' => "priority_{$index}",
        'label' => "Priorità {$index}",
        'description' => null,
        'color' => $index === 1 ? '#15803D' : '#DC2626',
        'sort_order' => $index,
        'is_enabled' => true,
    ], range(1, 2));
    $matrix = [];
    foreach ($consequences as $consequenceIndex => $consequence) {
        foreach ($likelihoods as $likelihoodIndex => $likelihood) {
            $matrix[$consequence['_form_key']][$likelihood['_form_key']] = $priorities[($consequenceIndex + $likelihoodIndex) % 2]['_form_key'];
        }
    }

    return [
        'code' => 'custom_profile',
        'label' => 'Profilo personalizzato',
        'description' => null,
        'is_default' => false,
        'is_enabled' => true,
        'consequences' => $consequences,
        'likelihoods' => $likelihoods,
        'priorities' => $priorities,
        'matrix' => $matrix,
    ];
}
