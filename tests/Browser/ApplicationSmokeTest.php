<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Enums\GeneratedReportFormat;
use App\Models\Assessment;
use App\Models\FindingTemplate;
use App\Models\GeneratedReport;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class ApplicationSmokeTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_login_workspace_save_lifecycle_and_report_generation_reach_the_backend(): void
    {
        $this->seed(DatabaseSeeder::class);
        $password = 'AssestMe-Dusk!2026';
        $administrator = User::factory()->create([
            'email' => 'application-smoke@assestme.invalid',
            'password' => $password,
        ]);
        $assessment = Assessment::factory()->create(['title' => 'Application smoke']);
        $finding = app(CopyTemplateToAssessment::class)(
            $assessment,
            FindingTemplate::query()->where('default_scope_type', 'organization')->firstOrFail(),
        );
        $updatedTitle = 'Finding saved by the browser smoke';

        $this->browse(function (Browser $browser) use ($administrator, $assessment, $finding, $password, $updatedTitle): void {
            $browser->visit('/admin/login')
                ->waitFor('input[type="email"]');

            $emailInput = $browser->element('input[type="email"]');
            $passwordInput = $browser->element('input[type="password"]');
            Assert::assertNotNull($emailInput);
            Assert::assertNotNull($passwordInput);
            $emailInput->sendKeys($administrator->email);
            $passwordInput->sendKeys($password);

            $browser->press('Accedi')
                ->waitForLocation('/admin')
                ->assertPathIs('/admin')
                ->visit("/admin/assessments/{$assessment->getKey()}/workspace?finding={$finding->getKey()}")
                ->waitFor('[data-assestme-finding-inspector]')
                ->assertAttribute('.assestme-finding-row.is-selected', 'aria-selected', 'true')
                ->type('[data-dusk="finding-editor-title"]', $updatedTitle)
                ->click('[data-dusk="save-finding"]')
                ->waitUntil(
                    'return document.querySelector("[data-assestme-save-status]")?.dataset.status === "saved"',
                )
                ->waitUsing(10, 100, static fn (): bool => $finding->fresh()->title === $updatedTitle)
                ->click('[data-dusk="finding-close"]')
                ->waitUntilMissing('[data-assestme-finding-inspector]')
                ->click('[data-dusk="export-menu"]')
                ->waitFor('[data-dusk="generate-pdf"]')
                ->click('[data-dusk="generate-pdf"]')
                ->waitUsing(60, 100, static fn (): bool => GeneratedReport::query()
                    ->where('assessment_id', $assessment->getKey())
                    ->where('format', GeneratedReportFormat::Pdf)
                    ->exists());

            $browser->script("Array.from(document.querySelectorAll('button')).find((button) => button.textContent.includes('Completa assessment')).click()");
            $browser->waitForText('Conferma')
                ->press('Conferma')
                ->waitForText('Riapri assessment');
            $browser->script("Array.from(document.querySelectorAll('button')).find((button) => button.textContent.includes('Riapri assessment')).click()");
            $browser->waitForText('Conferma')
                ->press('Conferma')
                ->waitFor('[data-dusk="new-finding-menu"]');

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The application browser smoke contains severe console errors.');
        });

        self::assertSame($updatedTitle, $finding->fresh()->title);
        self::assertSame('draft', $assessment->fresh()->status->value);
        self::assertSame(1, GeneratedReport::query()
            ->where('assessment_id', $assessment->getKey())
            ->where('format', GeneratedReportFormat::Pdf)
            ->count());

        Storage::disk('local')->deleteDirectory("reports/{$assessment->getKey()}");
    }
}
