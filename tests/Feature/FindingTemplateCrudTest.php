<?php

declare(strict_types=1);

use App\Actions\Templates\SaveFindingTemplate;
use App\Filament\Resources\FindingTemplates\Pages\CreateFindingTemplate;
use App\Models\Category;
use App\Models\FindingTemplate;
use App\Models\FindingTemplateSolution;
use App\Models\User;
use Database\Seeders\MilestoneOneSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(MilestoneOneSeeder::class);
});

it('creates a complete template and its recommended solution through the application action', function (): void {
    $template = app(SaveFindingTemplate::class)->handle(null, findingTemplateData());

    expect($template->external_id)->toBe('test.template')
        ->and($template->solutions()->count())->toBe(1)
        ->and($template->solutions()->firstOrFail()->is_recommended)->toBeTrue();
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

    Livewire::test(CreateFindingTemplate::class)
        ->fillForm(findingTemplateData())
        ->call('create')
        ->assertHasNoFormErrors();

    expect(FindingTemplate::query()->where('external_id', 'test.template')->exists())->toBeTrue();
});

it('requires authentication for the template library', function (): void {
    $this->get('/admin/finding-templates')->assertRedirect('/admin/login');
});

/** @return array<string, mixed> */
function findingTemplateData(): array
{
    return [
        'external_id' => 'test.template',
        'title' => 'Template di test',
        'category_id' => Category::query()->where('slug', 'sicurezza')->firstOrFail()->getKey(),
        'tag_ids' => [],
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
            'external_id' => 'recommended',
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
