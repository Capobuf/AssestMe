<?php

declare(strict_types=1);

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Enums\FindingStatus;
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
use App\Models\WorkspaceSaveRequest;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MilestoneOneSeeder;
use Database\Seeders\MilestoneTwoSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(MilestoneOneSeeder::class);
});

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

it('shows the legal name in the assessment list when the trade name is absent', function (): void {
    $administrator = User::factory()->create();
    $client = Client::factory()->create([
        'legal_name' => 'Azienda Test S.r.l.',
        'trade_name' => null,
    ]);
    $assessment = Assessment::factory()->for($client)->create([
        'title' => 'Assessment con sola ragione sociale',
    ]);
    $this->actingAs($administrator);

    expect($assessment->client_id)->toBe($client->id)
        ->and($assessment->client->is($client))->toBeTrue()
        ->and($assessment->client->displayName())->toBe('Azienda Test S.r.l.');

    Livewire::test(ListAssessments::class)
        ->assertCanSeeTableRecords([$assessment])
        ->assertSee('Azienda Test S.r.l.');
});

it('shows the trade name in the assessment list when it is available', function (): void {
    $administrator = User::factory()->create();
    $client = Client::factory()->create([
        'legal_name' => 'Azienda Test S.r.l.',
        'trade_name' => 'Azienda Test',
    ]);
    $assessment = Assessment::factory()->for($client)->create([
        'title' => 'Assessment con nome commerciale',
    ]);
    $this->actingAs($administrator);

    Livewire::test(ListAssessments::class)
        ->assertCanSeeTableRecords([$assessment])
        ->assertSee('Azienda Test')
        ->assertDontSee('Azienda Test S.r.l.');
});

it('searches the assessment list by legal and trade company names', function (): void {
    $administrator = User::factory()->create();
    $legalClient = Client::factory()->create([
        'legal_name' => 'Legalneedle S.r.l.',
        'trade_name' => null,
    ]);
    $tradeClient = Client::factory()->create([
        'legal_name' => 'Seconda Ragione Sociale S.r.l.',
        'trade_name' => 'Tradeneedle',
    ]);
    $legalAssessment = Assessment::factory()->for($legalClient)->create();
    $tradeAssessment = Assessment::factory()->for($tradeClient)->create();
    $this->actingAs($administrator);

    Livewire::test(ListAssessments::class)
        ->searchTable('Legalneedle')
        ->assertCanSeeTableRecords([$legalAssessment])
        ->assertCountTableRecords(1);

    Livewire::test(ListAssessments::class)
        ->searchTable('Tradeneedle')
        ->assertCanSeeTableRecords([$tradeAssessment])
        ->assertCountTableRecords(1);
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
        ->assertDontSee('Anteprima riepilogo')
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

it('limits the finding solution repeater to three items with clear guidance', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $finding = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $this->actingAs($administrator);

    $component = Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $finding->id)
        ->assertSee(__('assestme.templates.solutions_help'));
    $repeater = $component->instance()->getSchema('findingEditor')?->getComponent(
        static fn (mixed $field): bool => $field instanceof Repeater && $field->getName() === 'solutions',
    );

    expect($repeater)->toBeInstanceOf(Repeater::class)
        ->and($repeater?->getMaxItems())->toBe(3);
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

it('uses the browser supplied finding request UUID for the signed save', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $finding = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $requestId = (string) Str::uuid();
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $finding->id)
        ->set('pendingFindingRequestId', $requestId)
        ->set('findingData.title', 'Bozza con UUID browser')
        ->call('saveFinding')
        ->assertHasNoErrors()
        ->assertSet('pendingFindingRequestId', null)
        ->assertSet('saveStatus', WorkspaceAssessment::STATUS_SAVED)
        ->assertDispatched(
            'assestme-server-save-confirmed',
            kind: 'finding',
            assessmentId: (int) $assessment->getKey(),
            findingId: (int) $finding->getKey(),
            requestId: $requestId,
            appliedVersion: 1,
        );

    expect(WorkspaceSaveRequest::query()->find($requestId))
        ->not->toBeNull()
        ->and($assessment->fresh()->lock_version)->toBe(1)
        ->and($finding->fresh()->title)->toBe('Bozza con UUID browser');
});

it('rejects an invalid browser supplied finding request UUID without saving or confirming', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $finding = Finding::factory()->for($assessment)->create([
        'title' => 'Finding invariato',
        'sort_order' => 1,
    ]);
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $finding->id)
        ->set('pendingFindingRequestId', 'not-a-uuid')
        ->set('findingData.title', 'Modifica da non salvare')
        ->call('saveFinding')
        ->assertSet('pendingFindingRequestId', 'not-a-uuid')
        ->assertSet('saveStatus', WorkspaceAssessment::STATUS_ERROR)
        ->assertHasErrors(['findingData.request_id'])
        ->assertNotDispatched('assestme-server-save-confirmed');

    expect(WorkspaceSaveRequest::query()->count())->toBe(0)
        ->and($assessment->fresh()->lock_version)->toBe(0)
        ->and($finding->fresh()->title)->toBe('Finding invariato');
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

it('saves the current finding before creating and selecting a blank finding', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $current = Finding::factory()->for($assessment)->create([
        'title' => 'Titolo persistito prima della modifica',
        'sort_order' => 1,
    ]);
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $current->id)
        ->set('findingData.title', 'Titolo salvato prima del nuovo Finding')
        ->call('createBlankFinding')
        ->assertHasNoErrors()
        ->assertSet('saveStatus', WorkspaceAssessment::STATUS_SAVED)
        ->assertSet('selectedFindingId', fn (?int $id): bool => $id !== null && $id !== $current->id);

    expect($current->fresh()->title)->toBe('Titolo salvato prima del nuovo Finding')
        ->and($assessment->findings()->count())->toBe(2)
        ->and($assessment->fresh()->lock_version)->toBe(2);
});

it('does not create a blank finding when the current finding fails pre action validation', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $current = Finding::factory()->for($assessment)->create([
        'title' => 'Finding da mantenere',
        'scope_type' => ScopeType::Organization,
        'sort_order' => 1,
    ]);
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $current->id)
        ->set('findingData.title', 'Modifica non persistibile')
        ->set('findingData.scope_type', ScopeType::SelectedAssets->value)
        ->set('findingData.asset_ids', [])
        ->call('createBlankFinding')
        ->assertSet('selectedFindingId', $current->id)
        ->assertSet('saveStatus', WorkspaceAssessment::STATUS_ERROR)
        ->assertHasErrors(['findingData.scope']);

    expect($assessment->findings()->count())->toBe(1)
        ->and($current->fresh()->title)->toBe('Finding da mantenere')
        ->and($assessment->fresh()->lock_version)->toBe(0);
});

it('retains pending evidence when finding validation fails', function (): void {
    Storage::fake('local');
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $current = Finding::factory()->for($assessment)->create([
        'scope_type' => ScopeType::Organization,
        'sort_order' => 1,
    ]);
    $pendingPath = 'pending-evidence/retained.png';
    Storage::disk('local')->put($pendingPath, (string) file_get_contents(base_path('fixtures/evidence/valid-small.png')));
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $current->id)
        ->set('findingData.evidence_uploads', ['retained' => $pendingPath])
        ->set('findingData.evidence_original_names', ['retained' => 'retained.png'])
        ->set('findingData.scope_type', ScopeType::SelectedAssets->value)
        ->set('findingData.asset_ids', [])
        ->call('saveFinding')
        ->assertSet('saveStatus', WorkspaceAssessment::STATUS_ERROR)
        ->assertHasErrors(['findingData.scope']);

    Storage::disk('local')->assertExists($pendingPath);
    expect($current->evidences()->count())->toBe(0)
        ->and($assessment->fresh()->lock_version)->toBe(0);
});

it('saves the current finding before selecting another finding', function (): void {
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $current = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $next = Finding::factory()->for($assessment)->create(['sort_order' => 2]);
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $current->id)
        ->set('findingData.title', 'Persistito prima della selezione')
        ->call('selectFinding', $next->id)
        ->assertHasNoErrors()
        ->assertSet('selectedFindingId', $next->id)
        ->assertSet('findingData.title', $next->title)
        ->assertSet('saveStatus', WorkspaceAssessment::STATUS_SAVED);

    expect($current->fresh()->title)->toBe('Persistito prima della selezione')
        ->and($assessment->fresh()->lock_version)->toBe(1);
});

it('saves the current finding before previous next close and tab navigation', function (): void {
    $administrator = User::factory()->create();
    $this->actingAs($administrator);

    $assessment = Assessment::factory()->create();
    $first = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $middle = Finding::factory()->for($assessment)->create(['sort_order' => 2]);
    $last = Finding::factory()->for($assessment)->create(['sort_order' => 3]);
    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $middle->id)
        ->set('findingData.title', 'Persistito prima di precedente')
        ->call('selectPreviousFinding')
        ->assertSet('selectedFindingId', $first->id);
    expect($middle->fresh()->title)->toBe('Persistito prima di precedente');

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $middle->id)
        ->set('findingData.title', 'Persistito prima di successivo')
        ->call('selectNextFinding')
        ->assertSet('selectedFindingId', $last->id);
    expect($middle->fresh()->title)->toBe('Persistito prima di successivo');

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $middle->id)
        ->set('findingData.title', 'Persistito prima della chiusura')
        ->call('closeInspector')
        ->assertSet('selectedFindingId', null);
    expect($middle->fresh()->title)->toBe('Persistito prima della chiusura');

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $middle->id)
        ->set('findingData.title', 'Persistito prima del cambio tab')
        ->call('setWorkspaceTab', 'assessment-details')
        ->assertSet('activeWorkspaceTab', 'assessment-details');
    expect($middle->fresh()->title)->toBe('Persistito prima del cambio tab');
});

it('saves the current finding before template copy duplication and deletion mutations', function (): void {
    $this->seed(MilestoneTwoSeeder::class);
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $current = Finding::factory()->for($assessment)->create(['sort_order' => 1]);
    $template = FindingTemplate::query()->firstOrFail();
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $current->id)
        ->set('findingData.title', 'Salvato prima della copia template')
        ->call('createFromTemplate', $template->id)
        ->assertHasNoErrors();
    expect($current->fresh()->title)->toBe('Salvato prima della copia template')
        ->and($assessment->findings()->count())->toBe(2);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $current->id)
        ->set('findingData.title', 'Salvato prima della duplicazione')
        ->call('duplicateFinding', $current->id)
        ->assertHasNoErrors();
    expect($current->fresh()->title)->toBe('Salvato prima della duplicazione')
        ->and($assessment->findings()->count())->toBe(3);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $current->id)
        ->set('findingData.title', 'Salvato prima della cancellazione')
        ->call('deleteFinding', $current->id)
        ->assertHasNoErrors();
    expect($current->fresh()->trashed())->toBeTrue()
        ->and($current->fresh()->title)->toBe('Salvato prima della cancellazione')
        ->and($assessment->findings()->count())->toBe(2);
});

it('persists the selected finding before completing the assessment', function (): void {
    $this->seed(MilestoneTwoSeeder::class);
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $finding = app(CopyTemplateToAssessment::class)(
        $assessment,
        FindingTemplate::query()->where('default_scope_type', ScopeType::Organization->value)->firstOrFail(),
    );
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $finding->id)
        ->set('findingData.title', 'Persistito prima del completamento')
        ->callAction(TestAction::make('complete'))
        ->assertHasNoErrors();

    expect($finding->fresh()->title)->toBe('Persistito prima del completamento')
        ->and($assessment->fresh()->status->value)->toBe('completed');
});

it('does not complete the assessment when the selected finding pre action save fails', function (): void {
    $this->seed(MilestoneTwoSeeder::class);
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $finding = app(CopyTemplateToAssessment::class)(
        $assessment,
        FindingTemplate::query()->where('default_scope_type', ScopeType::Organization->value)->firstOrFail(),
    );
    $originalTitle = $finding->title;
    $this->actingAs($administrator);

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $finding->id)
        ->set('findingData.title', 'Titolo che non deve essere salvato')
        ->set('findingData.scope_type', ScopeType::SelectedAssets->value)
        ->set('findingData.asset_ids', [])
        ->callAction(TestAction::make('complete'))
        ->assertSet('saveStatus', WorkspaceAssessment::STATUS_ERROR)
        ->assertHasErrors(['findingData.scope']);

    expect($finding->fresh()->title)->toBe($originalTitle)
        ->and($assessment->fresh()->status->value)->toBe('draft');
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

it('keeps navigator search without cramped filters or relationship query growth', function (): void {
    $this->seed(DatabaseSeeder::class);
    $administrator = User::factory()->create();
    $assessment = Assessment::factory()->create();
    $complete = app(CopyTemplateToAssessment::class)(
        $assessment,
        FindingTemplate::query()->where('default_scope_type', ScopeType::Organization->value)->firstOrFail(),
    );
    $complete->update(['title' => 'Unico finding ricercabile']);
    Finding::factory()->for($assessment)->create([
        'title' => 'Finding privo di contenuto',
        'problem' => null,
        'sort_order' => 2,
        'include_in_report' => true,
    ]);
    Finding::factory()->for($assessment)->create([
        'title' => 'Finding escluso',
        'sort_order' => 3,
        'include_in_report' => false,
    ]);
    $this->actingAs($administrator);

    $component = Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->searchTable('ricercabile')
        ->assertCanSeeTableRecords([$complete])
        ->assertCountTableRecords(1)
        ->assertDontSeeHtml('fi-ta-filters-dropdown');

    expect($component->instance()->getTable()->getFilters())->toBe([]);

    Finding::factory()->count(48)->for($assessment)->sequence(
        fn ($sequence): array => ['sort_order' => $sequence->index + 4],
    )->create();
    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])->assertOk();
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(20);
    DB::disableQueryLog();
});

it('renders only selection-critical and exceptional information in navigator rows', function (): void {
    $this->seed(DatabaseSeeder::class);
    $assessment = Assessment::factory()->create();
    $finding = app(CopyTemplateToAssessment::class)(
        $assessment,
        FindingTemplate::query()->where('default_scope_type', ScopeType::Organization->value)->firstOrFail(),
    );
    $finding->update([
        'title' => 'NAS appoggiato sopra l’UPS',
        'status' => FindingStatus::Open,
        'include_in_report' => true,
    ]);

    $completeHtml = view('filament.resources.assessments.tables.finding-workbench-state', [
        'getRecord' => static fn (): Finding => $finding->fresh(),
    ])->render();

    expect($completeHtml)
        ->toContain('NAS appoggiato sopra l’UPS')
        ->toContain((string) $finding->priorityLevel?->label)
        ->not->toContain('assestme-finding-row__metadata')
        ->not->toContain('assestme-finding-row__state')
        ->not->toContain('assestme-finding-row__completion')
        ->not->toContain((string) $finding->category?->name);

    $finding->update([
        'problem' => null,
        'status' => FindingStatus::InProgress,
        'include_in_report' => false,
    ]);
    $exceptionHtml = view('filament.resources.assessments.tables.finding-workbench-state', [
        'getRecord' => static fn (): Finding => $finding->fresh(),
    ])->render();

    expect($exceptionHtml)
        ->toContain(FindingStatus::options()[FindingStatus::InProgress->value])
        ->toContain('mancant')
        ->toContain('aria-label="Escluso dal report"')
        ->not->toContain('>Aperto<')
        ->not->toContain('>Completo<');
});
