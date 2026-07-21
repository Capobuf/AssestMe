<?php

declare(strict_types=1);

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Enums\ScopeType;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assessments\Pages\CreateAssessment;
use App\Filament\Resources\Assessments\Pages\ListAssessments;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Models\Assessment;
use App\Models\Client;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
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

it('mounts the structured findings table workspace with fifty related findings', function (): void {
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

it('keeps assessment metadata on its separate signed persistence path', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $this->actingAs($administrator);

    $component = Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()]);
    $component->set('data.title', 'Autosave controllato')->assertHasNoErrors();
    expect($assessment->fresh()->title)->not->toBe('Autosave controllato')
        ->and($assessment->fresh()->lock_version)->toBe(0);

    $component->call('saveAssessmentDetails')->assertHasNoErrors();
    expect($assessment->fresh()->title)->toBe('Autosave controllato')
        ->and($assessment->fresh()->lock_version)->toBe(1);
});

it('keeps invalid assessment state visible after a validation failure', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->set('data.title', '')
        ->call('saveAssessmentDetails')
        ->assertSet('data.title', '')
        ->assertSet('saveStatus', WorkspaceAssessment::STATUS_ERROR)
        ->assertHasErrors(['data.assessment.title']);

    expect($assessment->fresh()->title)->not->toBe('');
});

it('selects one persisted finding and loads its complete inspector state', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $finding = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $finding->id)
        ->assertHasNoErrors()
        ->assertSet('selectedFindingId', $finding->id)
        ->assertSet('findingData.title', $finding->title)
        ->assertSee(__('assestme.workspace.inspector.description'));
});

it('persists main editor and contextual property changes through one signed finding save', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $finding = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $finding->id)
        ->set('findingData.title', 'Finding aggiornato dal workbench')
        ->set('findingData.status', 'planned')
        ->set('findingData.include_in_report', false)
        ->call('saveFinding')
        ->assertHasNoErrors()
        ->assertSet('saveStatus', WorkspaceAssessment::STATUS_SAVED)
        ->assertSee(__('assestme.workspace.properties.label'));

    $saved = $finding->fresh();
    expect($saved->title)->toBe('Finding aggiornato dal workbench')
        ->and($saved->status->value)->toBe('planned')
        ->and($saved->include_in_report)->toBeFalse()
        ->and($assessment->fresh()->lock_version)->toBe(1);
});

it('keeps an invalid contextual scope visible without persisting partial workbench changes', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $finding = Finding::factory()->for($assessment)->create([
        'title' => 'Finding invariato',
        'scope_type' => ScopeType::Organization,
        'sort_order' => 1,
    ]);
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $finding->id)
        ->set('findingData.title', 'Modifica non persistita')
        ->set('findingData.scope_type', ScopeType::SelectedAssets->value)
        ->set('findingData.asset_ids', [])
        ->call('saveFinding')
        ->assertSet('findingData.title', 'Modifica non persistita')
        ->assertSet('saveStatus', WorkspaceAssessment::STATUS_ERROR)
        ->assertHasErrors(['findingData.scope']);

    $persisted = $finding->fresh();
    expect($persisted->title)->toBe('Finding invariato')
        ->and($persisted->scope_type)->toBe(ScopeType::Organization)
        ->and($assessment->fresh()->lock_version)->toBe(0);
});

it('exposes one native reorder mode and one visible row action menu', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $finding = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $this->actingAs($administrator);

    $component = Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()]);

    expect($component->instance()->canReorderFindings())->toBeTrue()
        ->and($component->instance()->getTable()->isReorderable())->toBeTrue();

    $component
        ->assertTableActionVisible('open', $finding)
        ->assertTableActionDoesNotExist('move_up', record: $finding)
        ->assertTableActionDoesNotExist('move_down', record: $finding)
        ->assertSeeHtml('assestme-finding-row__open-action')
        ->assertSeeHtml('data-dusk="finding-actions"');
});

it('searches and filters the synthetic finding list without relationship query growth', function (): void {
    $this->seed(DatabaseSeeder::class);
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $complete = app(CopyTemplateToAssessment::class)(
        $assessment,
        FindingTemplate::query()->where('default_scope_type', ScopeType::Organization->value)->firstOrFail(),
    );
    $complete->update(['title' => 'Unico finding ricercabile']);
    $incomplete = Finding::factory()->for($assessment)->create([
        'title' => 'Finding privo di contenuto',
        'problem' => null,
        'sort_order' => 2,
        'include_in_report' => true,
    ]);
    $excluded = Finding::factory()->for($assessment)->create([
        'title' => 'Finding escluso',
        'sort_order' => 3,
        'include_in_report' => false,
    ]);
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->searchTable('ricercabile')
        ->assertCanSeeTableRecords([$complete])
        ->assertCanNotSeeTableRecords([$incomplete])
        ->searchTable()
        ->filterTable('include_in_report', 0)
        ->assertCanSeeTableRecords([$excluded])
        ->assertCanNotSeeTableRecords([$incomplete])
        ->assertCanNotSeeTableRecords([$complete])
        ->resetTableFilters()
        ->filterTable('incomplete')
        ->assertCanSeeTableRecords([$incomplete])
        ->assertCanNotSeeTableRecords([$complete]);

    Finding::factory()->count(48)->for($assessment)->sequence(
        fn ($sequence): array => ['sort_order' => $sequence->index + 4],
    )->create();
    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])->assertOk();
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(20);
    DB::disableQueryLog();
});
