<?php

declare(strict_types=1);

use App\Actions\Templates\SaveFindingTemplate;
use App\Enums\EstimateType;
use App\Filament\Resources\FindingTemplates\FindingTemplateResource;
use App\Filament\Resources\FindingTemplates\Pages\CreateFindingTemplate;
use App\Models\Category;
use App\Models\FindingTemplate;
use App\Models\FindingTemplateSolution;
use App\Models\User;
use App\Settings\GeneralSettings;
use Database\Seeders\MilestoneOneSeeder;
use Filament\Forms\Components\Repeater;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(MilestoneOneSeeder::class);
});

it('creates a complete template and its recommended solution through the application action', function (): void {
    $template = app(SaveFindingTemplate::class)->handle(null, findingTemplateData());

    expect($template->external_id)->toBe('template-di-test')
        ->and($template->solutions()->count())->toBe(1)
        ->and($template->solutions()->firstOrFail()->external_id)->toBe('soluzione-raccomandata')
        ->and($template->solutions()->firstOrFail()->is_recommended)->toBeTrue();
});

it('returns authoritative ordered solution identifiers without changing the existing save contract', function (): void {
    $saved = app(SaveFindingTemplate::class)->handleWithSolutionIds(null, findingTemplateData());

    expect($saved->template)->toBeInstanceOf(FindingTemplate::class)
        ->and($saved->solutionExternalIds)->toBe(['soluzione-raccomandata'])
        ->and($saved->template->solutions()->pluck('external_id')->all())->toBe($saved->solutionExternalIds);
});

it('stores approximate template estimates with the configured global currency', function (): void {
    $settings = app(GeneralSettings::class);
    $settings->currency = 'CHF';
    $settings->save();
    $data = findingTemplateData();
    $data['solutions'][0]['estimate_type'] = 'approximate';
    $data['solutions'][0]['currency_code'] = 'JPY';

    $template = app(SaveFindingTemplate::class)->handle(null, $data);
    $solution = $template->solutions()->sole();

    expect($solution->estimate_type)->toBe(EstimateType::Approximate)
        ->and($solution->currency_code)->toBe('CHF');
});

it('generates collision-safe stable external identifiers and rejects later mutations', function (): void {
    $first = app(SaveFindingTemplate::class)->handle(null, findingTemplateData());
    $second = app(SaveFindingTemplate::class)->handle(null, findingTemplateData());
    $payload = findingTemplateData();
    $payload['title'] = 'Titolo modificato';
    $payload['external_id'] = 'payload-manipolato';
    $payload['solutions'][0]['id'] = $first->solutions()->firstOrFail()->getKey();
    $payload['solutions'][0]['external_id'] = 'soluzione-manipolata';

    expect($first->external_id)->toBe('template-di-test')
        ->and($second->external_id)->toBe('template-di-test-2')
        ->and($second->solutions()->firstOrFail()->external_id)->toBe('soluzione-raccomandata')
        ->and(fn () => app(SaveFindingTemplate::class)->handle($first, $payload))
        ->toThrow(ValidationException::class);

    $payload['external_id'] = $first->external_id;
    $payload['solutions'][0]['external_id'] = $first->solutions()->firstOrFail()->external_id;
    $updated = app(SaveFindingTemplate::class)->handle($first, $payload);

    expect($updated->title)->toBe('Titolo modificato')
        ->and($updated->external_id)->toBe('template-di-test')
        ->and($updated->solutions()->firstOrFail()->external_id)->toBe('soluzione-raccomandata')
        ->and(fn () => $updated->update(['external_id' => 'mutazione-diretta']))
        ->toThrow(LogicException::class);
});

it('generates deterministic solution collisions inside one template', function (): void {
    $payload = findingTemplateData();
    $payload['solutions'][] = [
        ...$payload['solutions'][0],
        'title' => 'Soluzione raccomandata',
        'is_recommended' => false,
        'sort_order' => 1,
    ];

    $template = app(SaveFindingTemplate::class)->handle(null, $payload);

    expect($template->solutions()->orderBy('sort_order')->pluck('external_id')->all())
        ->toBe(['soluzione-raccomandata', 'soluzione-raccomandata-2']);
});

it('rejects a fourth template solution server side without persisting partial data', function (): void {
    $payload = findingTemplateData();
    foreach (range(2, 4) as $number) {
        $payload['solutions'][] = [
            ...$payload['solutions'][0],
            'title' => "Soluzione alternativa {$number}",
            'is_recommended' => false,
            'sort_order' => $number - 1,
        ];
    }

    expect(fn () => app(SaveFindingTemplate::class)->handle(null, $payload))
        ->toThrow(ValidationException::class)
        ->and(FindingTemplate::query()->count())->toBe(0)
        ->and(FindingTemplateSolution::query()->count())->toBe(0);
});

it('rolls back a template when recommendations or estimates are invalid', function (array $changes): void {
    $data = array_replace_recursive(findingTemplateData(), $changes);

    expect(fn () => app(SaveFindingTemplate::class)->handle(null, $data))->toThrow(ValidationException::class)
        ->and(FindingTemplate::query()->count())->toBe(0)
        ->and(FindingTemplateSolution::query()->count())->toBe(0);
})->with([
    'no recommended solution' => [['solutions' => [0 => ['is_recommended' => false]]]],
    'non monetary amount' => [['solutions' => [0 => ['estimate_type' => 'bundled', 'amount_min' => 10, 'currency_code' => 'EUR']]]],
    'inverted range' => [['solutions' => [0 => ['estimate_type' => 'range', 'amount_min' => 20, 'amount_max' => 10]]]],
]);

it('creates a template through its Filament resource', function (): void {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(CreateFindingTemplate::class)
        ->fillForm(findingTemplateData())
        ->call('create')
        ->assertHasNoFormErrors();

    $created = FindingTemplate::query()->where('external_id', 'template-di-test')->firstOrFail();
    $component->assertRedirect(FindingTemplateResource::getUrl('edit', ['record' => $created]));

    expect($created->exists)->toBeTrue();
});

it('limits the template solution repeater to three items with clear guidance', function (): void {
    $this->actingAs(User::factory()->create());
    $component = Livewire::test(CreateFindingTemplate::class)
        ->assertSee(__('assestme.templates.solutions_help'))
        ->assertSee('180 caratteri rimanenti')
        ->assertSee('1400 caratteri rimanenti');
    $repeater = $component->instance()->getSchema('form')?->getComponent(
        static fn (mixed $field): bool => $field instanceof Repeater && $field->getName() === 'solutions',
    );

    expect($repeater)->toBeInstanceOf(Repeater::class)
        ->and($repeater?->getMaxItems())->toBe(3);
});

it('requires authentication for the template library', function (): void {
    $this->get(FindingTemplateResource::getUrl('index'))->assertRedirect('/admin/login');
});

/** @return array<string, mixed> */
function findingTemplateData(): array
{
    return [
        'title' => 'Template di test',
        'category_id' => Category::query()->where('slug', 'sicurezza')->firstOrFail()->getKey(),
        'problem' => 'Problema sufficientemente descritto.',
        'entrepreneur_notes' => null,
        'technical_notes' => null,
        'default_scope_type' => 'organization',
        'default_scope_description' => null,
        'default_consequence_level_id' => null,
        'default_likelihood_level_id' => null,
        'default_priority_level_id' => null,
        'priority_rationale' => null,
        'is_enabled' => true,
        'solutions' => [[
            'title' => 'Soluzione raccomandata',
            'description' => 'Descrizione della soluzione.',
            'comparison_notes' => null,
            'effort_level_id' => null,
            'effort_notes' => null,
            'estimate_type' => 'exact',
            'amount_min' => 100,
            'amount_max' => null,
            'currency_code' => 'EUR',
            'billing_frequency' => 'one_off',
            'custom_billing_frequency' => null,
            'estimate_notes' => null,
            'is_recommended' => true,
            'sort_order' => 0,
        ]],
    ];
}
