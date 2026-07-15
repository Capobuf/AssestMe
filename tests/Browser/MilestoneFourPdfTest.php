<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Assessments\CopyTemplateToAssessment;
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

final class MilestoneFourPdfTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_pdf_generation_and_history_are_accessible_without_console_errors(): void
    {
        $this->seed(DatabaseSeeder::class);
        $administrator = User::factory()->create();
        $assessment = Assessment::factory()->create(['title' => 'Dusk report M4']);
        app(CopyTemplateToAssessment::class)->handle(
            $assessment,
            FindingTemplate::query()->where('default_scope_type', 'organization')->firstOrFail(),
        );

        $this->browse(function (Browser $browser) use ($administrator, $assessment): void {
            $browser->loginAs($administrator)
                ->visit("/admin/assessments/{$assessment->getKey()}/workspace")
                ->waitFor('[data-dusk="generate-pdf"]')
                ->assertSee('Genera PDF')
                ->click('[data-dusk="generate-pdf"]')
                ->waitUsing(15, 100, static fn (): bool => GeneratedReport::query()
                    ->where('assessment_id', $assessment->getKey())
                    ->exists());

            $report = GeneratedReport::query()->where('assessment_id', $assessment->getKey())->sole();
            $browser->visit("/admin/assessments/{$assessment->getKey()}/workspace")
                ->waitForText('File generati')
                ->press('File generati')
                ->waitForText($report->file_name)
                ->assertSee($report->file_name)
                ->assertSee('Scarica file')
                ->click('[data-dusk="download-generated-report"]')
                ->pause(500)
                ->assertPathIs("/admin/assessments/{$assessment->getKey()}/workspace");

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The browser console contains severe errors.');

            Storage::disk('local')->deleteDirectory("reports/{$assessment->getKey()}");
        });
    }
}
