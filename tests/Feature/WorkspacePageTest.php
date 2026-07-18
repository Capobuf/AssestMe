<?php

declare(strict_types=1);

use App\Enums\ScopeType;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assessments\Pages\CreateAssessment;
use App\Filament\Resources\Assessments\Pages\ListAssessments;
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

    $component = Livewire::test(CreateAssessment::class)
        ->fillForm([
            'client_id' => $client->getKey(),
            'title' => 'Assessment creato da Filament',
            'assessment_date' => '2026-07-13',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $assessment = Assessment::query()->where('title', 'Assessment creato da Filament')->sole();
    $component->assertRedirect(AssessmentResource::getUrl('workspace', ['record' => $assessment]));
});

it('keeps a manually edited automatic assessment title unchanged', function (): void {
    $administrator = User::factory()->create();
    $client = Client::factory()->create(['trade_name' => 'Azienda automatica']);
    $this->actingAs($administrator);

    Livewire::test(CreateAssessment::class)
        ->set('data.client_id', $client->id)
        ->set('data.assessment_date', '2026-07-18')
        ->set('data.scope_type', ScopeType::Organization->value)
        ->assertSet('data.title', 'Azienda automatica — Intera azienda — 18/07/2026')
        ->set('data.title', 'Titolo scelto dall’utente')
        ->set('data.assessment_date', '2026-07-19')
        ->assertSet('data.title', 'Titolo scelto dall’utente');
});

it('uses the Workspace as the primary assessment list destination', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $this->actingAs($administrator);

    Livewire::test(ListAssessments::class)
        ->assertSee($assessment->title)
        ->assertSeeHtml(AssessmentResource::getUrl('workspace', ['record' => $assessment]));
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
        ->assertSee('File Generati');
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
