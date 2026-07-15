<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Enums\DeletionOperationStatus;
use App\Enums\GeneratedReportFormat;
use App\Models\Assessment;
use App\Models\DeletionOperation;
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

    public function test_pdf_and_xlsx_generation_history_are_accessible_without_console_errors(): void
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
                    ->where('format', GeneratedReportFormat::Pdf)
                    ->exists())
                ->waitFor('[data-dusk="generate-xlsx"]')
                ->click('[data-dusk="generate-xlsx"]')
                ->waitForText('Includi i finding esclusi dal report')
                ->assertSee('Genera file XLSX')
                ->press('Genera file XLSX')
                ->waitUsing(15, 100, static fn (): bool => GeneratedReport::query()
                    ->where('assessment_id', $assessment->getKey())
                    ->where('format', GeneratedReportFormat::Xlsx)
                    ->exists());

            $pdfReport = GeneratedReport::query()
                ->where('assessment_id', $assessment->getKey())
                ->where('format', GeneratedReportFormat::Pdf)
                ->sole();
            $workbook = GeneratedReport::query()
                ->where('assessment_id', $assessment->getKey())
                ->where('format', GeneratedReportFormat::Xlsx)
                ->sole();
            $browser->visit("/admin/assessments/{$assessment->getKey()}/workspace")
                ->waitForText('File generati')
                ->press('File generati')
                ->waitForText($workbook->file_name)
                ->assertSee($pdfReport->file_name)
                ->assertSee($workbook->file_name)
                ->assertSee('Scarica file')
                ->click('[data-dusk="download-generated-report"][data-report-id="'.$workbook->getKey().'"]')
                ->pause(500)
                ->assertPathIs("/admin/assessments/{$assessment->getKey()}/workspace")
                ->click('[data-report-id="'.$workbook->getKey().'"] [data-dusk="delete-generated-report"]')
                ->waitForText('Eliminare definitivamente il file generato?')
                ->assertSee('Questa operazione non può essere annullata.')
                ->press('Elimina definitivamente')
                ->waitUsing(10, 100, static fn (): bool => ! GeneratedReport::query()->whereKey($workbook->getKey())->exists())
                ->waitForText('File generato eliminato definitivamente')
                ->assertDontSee($workbook->file_name)
                ->assertSee($pdfReport->file_name);

            $operation = DeletionOperation::query()
                ->where('entity_type', GeneratedReport::class)
                ->where('entity_id', $workbook->getKey())
                ->sole();

            Assert::assertSame(DeletionOperationStatus::Cleaned, $operation->status);
            Assert::assertFalse(Storage::disk('local')->exists($workbook->file_path));
            Assert::assertTrue(Storage::disk('local')->exists($pdfReport->file_path));

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The browser console contains severe errors.');

            Storage::disk('local')->deleteDirectory("reports/{$assessment->getKey()}");
        });
    }
}
