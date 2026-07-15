<?php

declare(strict_types=1);

use App\Filament\Resources\Assessments\Pages\CreateAssessment;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Models\Assessment;
use App\Models\Client;
use App\Models\Finding;
use App\Models\User;
use Livewire\Livewire;

it('creates an assessment through the Filament resource', function (): void {
    $administrator = User::factory()->create();
    $client = Client::factory()->create();
    $this->actingAs($administrator);

    Livewire::test(CreateAssessment::class)
        ->fillForm([
            'client_id' => $client->getKey(),
            'title' => 'Assessment creato da Filament',
            'assessment_date' => '2026-07-13',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Assessment::query()->where('title', 'Assessment creato da Filament')->exists())->toBeTrue();
});

it('mounts the native table repeater workspace with fifty related findings', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    Finding::factory()->count(50)->for($assessment)->sequence(
        fn ($sequence): array => ['sort_order' => $sequence->index + 1],
    )->create();

    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->assertOk()
        ->assertSet('expectedVersion', 0)
        ->assertSet('saveStatus', WorkspaceAssessment::STATUS_SAVED)
        ->assertSee('Finding')
        ->assertSee('Dettagli assessment')
        ->assertSee('Anteprima riepilogo')
        ->assertSee('File generati');
});

it('uses the same persistence path for explicit save and autosave', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $this->actingAs($administrator);

    $component = Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()]);
    $component->set('data.title', 'Autosave controllato')->assertHasNoErrors();
    expect($assessment->fresh()->title)->toBe('Autosave controllato')
        ->and($assessment->fresh()->lock_version)->toBe(1);

    $component->call('save')->assertHasNoErrors();
    expect($assessment->fresh()->title)->toBe('Autosave controllato')
        ->and($assessment->fresh()->lock_version)->toBe(2);
});

it('keeps invalid form state visible after an autosave validation failure', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->set('data.title', '')
        ->assertSet('data.title', '')
        ->assertSet('saveStatus', WorkspaceAssessment::STATUS_ERROR)
        ->assertHasErrors(['data.assessment.title']);

    expect($assessment->fresh()->title)->not->toBe('');
});

it('mounts the finding details slide-over for a persisted row', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $finding = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->mountFormComponentAction('findings', 'details', ['item' => "record-{$finding->id}"])
        ->assertHasNoErrors()
        ->assertSee(__('assestme.workspace.finding_details'));
});
