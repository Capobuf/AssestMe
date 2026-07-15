<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Models\Assessment;
use App\Models\FindingTemplate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class MilestoneThreeWorkspaceTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_slide_over_and_lifecycle_are_accessible_without_console_errors(): void
    {
        $this->seed(DatabaseSeeder::class);
        $administrator = User::factory()->create();
        $assessment = Assessment::factory()->create(['title' => 'Dusk workspace M3']);
        app(CopyTemplateToAssessment::class)->handle($assessment, FindingTemplate::query()->where('default_scope_type', 'organization')->firstOrFail());

        $this->browse(function (Browser $browser) use ($administrator, $assessment): void {
            $browser->loginAs($administrator)
                ->visit("/admin/assessments/{$assessment->getKey()}/workspace")
                ->waitFor('[data-dusk="finding-details"]')
                ->assertSee('Aggiungi da template')
                ->assertSee('Completa assessment')
                ->assertSee('Anteprima riepilogo')
                ->assertSee('File generati')
                ->click('tbody tr:first-child [data-dusk="finding-details"]')
                ->waitForText('Dettagli finding')
                ->assertSee('Soluzioni')
                ->assertSee('Evidenze');
            // The edit page also has an "Annulla" button, so scope cancellation to the active slide-over.
            $browser->script("Array.from(document.querySelectorAll('.fi-modal-window button')).find((button) => button.textContent.trim() === 'Annulla').click()");
            $browser->waitUntilMissing('.fi-modal-window');
            $browser->script("Array.from(document.querySelectorAll('button')).find((button) => button.textContent.includes('Completa assessment')).click()");
            $browser->waitForText('Conferma')
                ->press('Conferma')
                ->waitForText('Riapri assessment')
                ->assertMissing('[data-dusk="add-finding"]');
            $browser->script("Array.from(document.querySelectorAll('button')).find((button) => button.textContent.includes('Riapri assessment')).click()");
            $browser->waitForText('Conferma')
                ->press('Conferma')
                ->waitFor('[data-dusk="add-finding"]');

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The browser console contains severe errors.');
        });
    }
}
