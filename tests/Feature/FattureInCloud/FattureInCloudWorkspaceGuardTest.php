<?php

declare(strict_types=1);

use App\Enums\AssessmentStatus;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Models\Assessment;
use App\Models\User;
use App\Settings\FattureInCloudSettings;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('saves dirty assessment data before opening the composer', function (): void {
    ficWorkspaceReadySettings();
    $this->actingAs(User::factory()->create());
    $assessment = Assessment::factory()->create();

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->set('activeWorkspaceTab', 'assessment-details')
        ->set('assessmentSaveStatus', WorkspaceAssessment::STATUS_UNSAVED)
        ->set('data.title', 'Titolo salvato prima del preventivo')
        ->call('openFattureInCloudQuoteComposer')
        ->assertRedirect(AssessmentResource::getUrl('create-fatture-in-cloud-quote', [
            'record' => $assessment,
        ]));

    expect($assessment->refresh()->title)->toBe('Titolo salvato prima del preventivo');
});

it('does not open the composer after validation failure or for a read-only assessment', function (): void {
    ficWorkspaceReadySettings();
    $this->actingAs(User::factory()->create());
    $draft = Assessment::factory()->create();

    Livewire::test(WorkspaceAssessment::class, ['record' => $draft->getRouteKey()])
        ->set('activeWorkspaceTab', 'assessment-details')
        ->set('assessmentSaveStatus', WorkspaceAssessment::STATUS_UNSAVED)
        ->set('data.title', '')
        ->call('openFattureInCloudQuoteComposer')
        ->assertNoRedirect()
        ->assertHasErrors()
        ->assertSet('assessmentSaveStatus', WorkspaceAssessment::STATUS_ERROR);

    $completed = Assessment::factory()->create(['status' => AssessmentStatus::Completed]);
    Livewire::test(WorkspaceAssessment::class, ['record' => $completed->getRouteKey()])
        ->call('openFattureInCloudQuoteComposer')
        ->assertNoRedirect();
});

function ficWorkspaceReadySettings(): void
{
    $settings = app(FattureInCloudSettings::class);
    $settings->client_id = 'client';
    $settings->encrypted_client_secret = 'secret';
    $settings->encrypted_access_token = 'access';
    $settings->access_token_expires_at = Carbon::now('UTC')->addHour()->toIso8601String();
    $settings->encrypted_refresh_token = 'refresh';
    $settings->company_id = '4321';
    $settings->company_name = 'Studio Demo';
    $settings->default_vat_type_id = '22';
    $settings->default_vat_type_label = '22%';
    $settings->scope_version = 1;
    $settings->save();
}
