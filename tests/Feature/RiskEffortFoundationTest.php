<?php

declare(strict_types=1);

use App\Actions\EffortLevels\SaveEffortLevel;
use App\Actions\Risk\CalculateFindingPriority;
use App\Actions\Risk\SaveRiskProfileConfiguration;
use App\Filament\Resources\RiskProfiles\Pages\EditRiskProfile;
use App\Models\ConsequenceLevel;
use App\Models\EffortLevel;
use App\Models\LikelihoodLevel;
use App\Models\RiskMatrixEntry;
use App\Models\RiskProfile;
use App\Models\User;
use Database\Seeders\MilestoneOneSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('seeds the approved default risk matrix and global effort levels', function (): void {
    $this->seed(MilestoneOneSeeder::class);

    $profile = RiskProfile::query()->where('is_default', true)->firstOrFail();

    expect(RiskProfile::query()->where('is_default', true)->count())->toBe(1)
        ->and($profile->consequenceLevels()->pluck('label')->all())->toBe(['Limitata', 'Significativa', 'Seria', 'Critica'])
        ->and($profile->likelihoodLevels()->pluck('label')->all())->toBe(['Improbabile', 'Possibile', 'Probabile', 'Attuale o imminente'])
        ->and($profile->priorityLevels()->pluck('label')->all())->toBe(['Bassa', 'Moderata', 'Alta', 'Critica'])
        ->and($profile->matrixEntries()->count())->toBe(16)
        ->and(EffortLevel::query()->orderBy('sort_order')->pluck('code')->all())->toBe(['low', 'moderate', 'high', 'very_high']);
});

it('calculates priority from levels owned by the same risk profile', function (): void {
    $this->seed(MilestoneOneSeeder::class);

    $profile = RiskProfile::query()->where('code', 'default')->firstOrFail();
    $consequence = $profile->consequenceLevels()->where('code', 'serious')->firstOrFail();
    $likelihood = $profile->likelihoodLevels()->where('code', 'possible')->firstOrFail();

    expect(app(CalculateFindingPriority::class)($consequence, $likelihood)->code)->toBe('high');
});

it('rejects cross-profile risk calculation without changing the matrix', function (): void {
    $this->seed(MilestoneOneSeeder::class);

    $original = RiskProfile::query()->where('code', 'default')->firstOrFail();
    $other = RiskProfile::query()->create([
        'code' => 'other',
        'label' => 'Altro profilo',
        'is_default' => false,
        'is_enabled' => false,
    ]);
    $foreignLikelihood = LikelihoodLevel::query()->create([
        'risk_profile_id' => $other->getKey(),
        'code' => 'foreign',
        'label' => 'Esterna',
        'score' => 1,
        'color' => '#123456',
        'sort_order' => 1,
        'is_enabled' => false,
    ]);

    expect(fn () => app(CalculateFindingPriority::class)(
        $original->consequenceLevels()->firstOrFail(),
        $foreignLikelihood,
    ))->toThrow(DomainException::class)
        ->and(RiskMatrixEntry::query()->count())->toBe(16);
});

it('rejects disabling the default profile and malformed matrix updates', function (): void {
    $this->seed(MilestoneOneSeeder::class);
    $profile = RiskProfile::query()->where('code', 'default')->firstOrFail();

    $payload = riskProfilePayload($profile);
    $payload['is_enabled'] = false;

    expect(fn () => app(SaveRiskProfileConfiguration::class)->handle($profile, $payload))
        ->toThrow(ValidationException::class);

    $payload = riskProfilePayload($profile);
    $payload['matrix'][15] = $payload['matrix'][0];

    expect(fn () => app(SaveRiskProfileConfiguration::class)->handle($profile, $payload))
        ->toThrow(ValidationException::class)
        ->and($profile->fresh()->is_enabled)->toBeTrue()
        ->and($profile->matrixEntries()->count())->toBe(16);
});

it('preserves risk level identities and rejects technical code mutations server side', function (): void {
    $this->seed(MilestoneOneSeeder::class);
    $profile = RiskProfile::query()->where('code', 'default')->firstOrFail();
    $consequence = $profile->consequenceLevels()->firstOrFail();
    $payload = riskProfilePayload($profile);
    $payload['consequences'][0]['label'] = 'Etichetta aggiornata';

    $saved = app(SaveRiskProfileConfiguration::class)->handle($profile, $payload);
    expect($saved->consequenceLevels()->whereKey($consequence)->firstOrFail()->label)->toBe('Etichetta aggiornata')
        ->and($saved->consequenceLevels()->count())->toBe(4)
        ->and($saved->matrixEntries()->count())->toBe(16);

    $payload = riskProfilePayload($saved);
    $payload['consequences'][0]['code'] = 'payload_manipolato';
    expect(fn () => app(SaveRiskProfileConfiguration::class)->handle($saved, $payload))
        ->toThrow(ValidationException::class)
        ->and(fn () => $consequence->update(['code' => 'mutazione_diretta']))
        ->toThrow(LogicException::class);
});

it('mounts a real four by four risk matrix instead of a vertical matrix repeater', function (): void {
    $this->seed(MilestoneOneSeeder::class);
    $this->actingAs(User::factory()->create());
    $profile = RiskProfile::query()->where('code', 'default')->firstOrFail();

    Livewire::test(EditRiskProfile::class, ['record' => $profile->getRouteKey()])
        ->assertOk()
        ->assertSeeHtml('data-dusk="risk-matrix-grid"')
        ->assertSee(__('assestme.risk.matrix_help'));
});

it('normalizes effort colors and rejects duplicate stable codes', function (): void {
    $first = app(SaveEffortLevel::class)->handle(null, [
        'code' => 'custom',
        'label' => 'Personalizzato',
        'color' => '#a1b2c3',
        'sort_order' => 1,
        'is_enabled' => true,
    ]);

    expect($first->color)->toBe('#A1B2C3');

    expect(fn () => app(SaveEffortLevel::class)->handle(null, [
        'code' => 'custom',
        'label' => 'Duplicato',
        'color' => '#A1B2C3',
        'sort_order' => 2,
        'is_enabled' => true,
    ]))->toThrow(ValidationException::class);
});

/** @return array<string, mixed> */
function riskProfilePayload(RiskProfile $profile): array
{
    $mapScored = static fn (ConsequenceLevel|LikelihoodLevel $level): array => [
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
        'consequences' => $profile->consequenceLevels->map($mapScored)->all(),
        'likelihoods' => $profile->likelihoodLevels->map($mapScored)->all(),
        'priorities' => $profile->priorityLevels->map(static fn ($level): array => [
            'id' => $level->getKey(),
            'code' => $level->code,
            'label' => $level->label,
            'description' => $level->description,
            'color' => $level->color,
            'sort_order' => $level->sort_order,
            'is_enabled' => $level->is_enabled,
        ])->all(),
        'matrix' => $profile->matrixEntries()
            ->with(['consequenceLevel', 'likelihoodLevel', 'priorityLevel'])
            ->get()
            ->map(static fn (RiskMatrixEntry $entry): array => [
                'consequence_code' => $entry->consequenceLevel->code,
                'likelihood_code' => $entry->likelihoodLevel->code,
                'priority_code' => $entry->priorityLevel->code,
            ])->all(),
    ];
}
