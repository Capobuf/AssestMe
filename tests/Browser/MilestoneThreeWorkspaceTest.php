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

    public function test_finding_inspector_and_lifecycle_are_accessible_without_console_errors(): void
    {
        $this->seed(DatabaseSeeder::class);
        $administrator = User::factory()->create();
        $assessment = Assessment::factory()->create(['title' => 'Dusk workspace M3']);
        app(CopyTemplateToAssessment::class)($assessment, FindingTemplate::query()->where('default_scope_type', 'organization')->firstOrFail());

        $this->browse(function (Browser $browser) use ($administrator, $assessment): void {
            $browser->loginAs($administrator)
                ->visit("/admin/assessments/{$assessment->getKey()}/workspace")
                ->waitFor('.assestme-finding-row')
                ->assertSee('Nuovo finding')
                ->assertSee('Completa assessment')
                ->assertDontSee('Anteprima riepilogo')
                ->assertSee('File Generati')
                ->click('.assestme-finding-row:first-of-type')
                ->waitFor('[data-assestme-finding-inspector]')
                ->assertAttribute('.assestme-finding-row.is-selected', 'aria-selected', 'true')
                ->assertSee('Descrizione')
                ->assertSee('Soluzioni')
                ->assertSee('Evidenze');
            $browser->click('[data-dusk="finding-close"]')->waitUntilMissing('[data-assestme-finding-inspector]');
            $browser->script("Array.from(document.querySelectorAll('button')).find((button) => button.textContent.includes('Completa assessment')).click()");
            $browser->waitForText('Conferma')
                ->press('Conferma')
                ->waitForText('Riapri assessment')
                ->assertMissing('[data-dusk="new-finding-menu"]')
                ->click('.assestme-finding-row:first-of-type')
                ->waitFor('[data-assestme-finding-inspector]')
                ->assertMissing('[data-dusk="save-finding"]')
                ->assertPresent('[data-dusk="finding-close"]');
            $browser->click('[data-dusk="finding-close"]')->waitUntilMissing('[data-assestme-finding-inspector]');
            $browser->script("Array.from(document.querySelectorAll('button')).find((button) => button.textContent.includes('Riapri assessment')).click()");
            $browser->waitForText('Conferma')
                ->press('Conferma')
                ->waitFor('[data-dusk="new-finding-menu"]');

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The browser console contains severe errors.');
        });
    }
}
