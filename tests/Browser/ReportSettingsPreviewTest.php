<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Filament\Pages\ReportSettingsPage;
use App\Models\GeneratedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class ReportSettingsPreviewTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_shared_report_preview_reacts_without_generating_a_report(): void
    {
        $administrator = User::factory()->create();
        $reportFilesBefore = Storage::disk('local')->allFiles('reports');
        sort($reportFilesBefore);

        $this->browse(function (Browser $browser) use ($administrator): void {
            $browser->loginAs($administrator)
                ->visit(ReportSettingsPage::getUrl())
                ->waitForText('Impostazioni report')
                ->waitFor('[data-dusk="report-preview-iframe"]');

            $initialSource = $browser->attribute('[data-dusk="report-preview-iframe"]', 'src');
            $initial = [];
            $browser->withinFrame('[data-dusk="report-preview-iframe"]', function (Browser $preview) use (&$initial): void {
                $preview->waitForText('Quadro generale');
                $initial = $preview->script(<<<'JS'
                    const overview = document.querySelector('.overview');
                    const summary = document.querySelector('.summary');

                    return {
                        overview: overview?.textContent?.includes('Quadro generale') ?? false,
                        summary: summary?.textContent?.includes('Riepilogo dei finding') ?? false,
                        finding: document.querySelector('[data-finding-page="1"]') !== null,
                        sharedCss: document.querySelector('style')?.textContent.includes('@page summary') ?? false,
                        portraitWidth: overview?.getBoundingClientRect().width ?? 0,
                        landscapeWidth: summary?.getBoundingClientRect().width ?? 0,
                    };
                    JS);
            });

            Assert::assertTrue($initial[0]['overview'] ?? false);
            Assert::assertTrue($initial[0]['summary'] ?? false);
            Assert::assertTrue($initial[0]['finding'] ?? false);
            Assert::assertTrue($initial[0]['sharedCss'] ?? false);
            Assert::assertGreaterThan(
                $initial[0]['portraitWidth'] ?? 0,
                $initial[0]['landscapeWidth'] ?? 0,
            );

            $browser->type('[data-dusk="report-preview-title-input"]', 'Assessment IT Dusk')
                ->pause(700)
                ->waitFor('[data-dusk="report-preview-iframe"]');

            $updatedSource = $browser->attribute('[data-dusk="report-preview-iframe"]', 'src');
            $updated = [];
            $browser->withinFrame('[data-dusk="report-preview-iframe"]', function (Browser $preview) use (&$updated): void {
                $preview->waitForText('Assessment IT Dusk');
                $updated = $preview->script(
                    "return document.querySelector('.cover__title')?.textContent?.trim() ?? null;",
                );
            });

            Assert::assertSame('Assessment IT Dusk', $updated[0] ?? null);
            Assert::assertNotSame($initialSource, $updatedSource);

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The browser console contains severe errors.');
        });

        $reportFilesAfter = Storage::disk('local')->allFiles('reports');
        sort($reportFilesAfter);

        Assert::assertSame(0, GeneratedReport::query()->count());
        Assert::assertSame($reportFilesBefore, $reportFilesAfter, 'The preview must not create report files.');
    }
}
