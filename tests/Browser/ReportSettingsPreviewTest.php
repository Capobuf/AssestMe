<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Filament\Pages\ReportSettingsPage;
use App\Models\GeneratedReport;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\File;
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
        $artifactRoot = base_path('storage/app/qa-artifacts');
        File::ensureDirectoryExists($artifactRoot);

        $this->browse(function (Browser $browser) use ($administrator, $artifactRoot): void {
            $browser->loginAs($administrator)
                ->resize(1440, 900)
                ->visit(ReportSettingsPage::getUrl())
                ->waitForText('Impostazioni report')
                ->waitFor('[data-dusk="report-preview-iframe"]')
                ->assertSee('Valori e output')
                ->assertSee('Anteprima')
                ->assertDontSee('Anteprima stile')
                ->assertDontSee('Anteprima del report reale con lo stesso layout usato dal PDF.')
                ->assertSee('Inizia ogni Finding su una nuova pagina')
                ->assertDontSee('assestme.settings.fields.new_page_per_finding');

            $initialSource = $browser->attribute('[data-dusk="report-preview-iframe"]', 'src');
            $frame = $browser->script(<<<'JS'
                const iframe = document.querySelector('[data-dusk="report-preview-iframe"]');
                const heading = document.querySelector('.assestme-report-preview__heading');
                const rect = iframe?.getBoundingClientRect();

                return {
                    width: rect?.width ?? 0,
                    height: rect?.height ?? 0,
                    scrolling: iframe?.getAttribute('scrolling'),
                    heading: heading?.textContent?.trim(),
                    headingWeight: heading ? getComputedStyle(heading).fontWeight : null,
                };
                JS)[0];
            $initial = [];
            $browser->withinFrame('[data-dusk="report-preview-iframe"]', function (Browser $preview) use (&$initial): void {
                $preview->waitUntil('return document.body.dataset.reportPreviewFitted === "true"');
                $initial = $preview->script(<<<'JS'
                    const overview = document.querySelector('.overview');
                    const summary = document.querySelector('.summary');
                    const slots = Array.from(document.querySelectorAll('.assestme-report-preview-sheet-slot'));
                    const sheets = slots.map((slot) => slot.firstElementChild);
                    const firstRect = sheets[0]?.getBoundingClientRect();
                    const text = document.body.textContent ?? '';

                    return {
                        overview: overview?.textContent?.includes('Quadro generale') ?? false,
                        summary: summary?.textContent?.includes('Riepilogo dei finding') ?? false,
                        finding: document.querySelector('[data-finding-page="1"]') !== null,
                        findingHierarchy: Boolean(
                            document.querySelector('[data-finding-page="1"] h1.finding-title')
                            && document.querySelector('[data-finding-page="1"] h2.finding-section__label--prominent')
                            && document.querySelector('[data-finding-page="1"] .solution-block h3')
                        ),
                        riskAxes: Array.from(document.querySelectorAll('.risk-axis'))
                            .map((axis) => axis.textContent?.trim())
                            .includes('Conseguenza')
                            && Array.from(document.querySelectorAll('.risk-axis'))
                                .map((axis) => axis.textContent?.trim())
                                .includes('Probabilità'),
                        sharedCss: document.querySelector('style')?.textContent.includes('@page summary') ?? false,
                        fitted: document.body.dataset.reportPreviewFitted === 'true',
                        pageCount: Number(document.body.dataset.reportPreviewPageCount ?? 0),
                        viewportWidth: document.documentElement.clientWidth,
                        viewportHeight: document.documentElement.clientHeight,
                        firstWidth: firstRect?.width ?? 0,
                        firstHeight: firstRect?.height ?? 0,
                        scrollWidth: document.documentElement.scrollWidth,
                        scrollHeight: document.documentElement.scrollHeight,
                        representativeContent: [
                            'Ripristino dei backup non verificato',
                            'Servizio RDP esposto direttamente su Internet',
                            'Notifiche del NAS non configurate',
                            'Da 1.800 a 2.600 € una tantum',
                            '390 € all’anno',
                            '29 € al mese per utente',
                            'Richiede analisi',
                            'Richiede preventivo',
                            'Compreso in altra attività',
                        ].every((value) => text.includes(value)),
                        htmlOverflowX: getComputedStyle(document.documentElement).overflowX,
                        htmlOverflowY: getComputedStyle(document.documentElement).overflowY,
                    };
                    JS);
            });

            Assert::assertNull($frame['scrolling'] ?? null);
            Assert::assertGreaterThan(380, $frame['width'] ?? 0);
            Assert::assertSame('Anteprima', $frame['heading'] ?? null);
            Assert::assertSame('700', $frame['headingWeight'] ?? null);
            Assert::assertEqualsWithDelta(210 / 297, ($frame['width'] ?? 0) / ($frame['height'] ?? 1), 0.002);
            Assert::assertTrue($initial[0]['overview'] ?? false);
            Assert::assertTrue($initial[0]['summary'] ?? false);
            Assert::assertTrue($initial[0]['finding'] ?? false);
            Assert::assertTrue($initial[0]['findingHierarchy'] ?? false);
            Assert::assertTrue($initial[0]['riskAxes'] ?? false);
            Assert::assertTrue($initial[0]['sharedCss'] ?? false);
            Assert::assertTrue($initial[0]['fitted'] ?? false);
            Assert::assertSame(8, $initial[0]['pageCount'] ?? 0);
            Assert::assertTrue($initial[0]['representativeContent'] ?? false);
            Assert::assertEqualsWithDelta(
                $initial[0]['viewportWidth'] ?? 0,
                $initial[0]['firstWidth'] ?? 0,
                1,
            );
            Assert::assertEqualsWithDelta(
                210 / 297,
                ($initial[0]['firstWidth'] ?? 0) / ($initial[0]['firstHeight'] ?? 1),
                0.002,
            );
            Assert::assertLessThanOrEqual(
                ($initial[0]['viewportWidth'] ?? 0) + 1,
                $initial[0]['scrollWidth'] ?? 0,
            );
            Assert::assertGreaterThan(
                $initial[0]['viewportHeight'] ?? 0,
                $initial[0]['scrollHeight'] ?? 0,
            );
            Assert::assertSame('hidden', $initial[0]['htmlOverflowX'] ?? null);
            Assert::assertSame('auto', $initial[0]['htmlOverflowY'] ?? null);

            $browser->type('[data-dusk="report-preview-title-input"]', 'Assessment IT Dusk')
                ->pause(700)
                ->waitFor('[data-dusk="report-preview-iframe"]');

            $updatedSource = $browser->attribute('[data-dusk="report-preview-iframe"]', 'src');
            $updated = [];
            $browser->withinFrame('[data-dusk="report-preview-iframe"]', function (Browser $preview) use (&$updated): void {
                $preview->waitForText('Assessment IT Dusk');
                $preview->waitUntil('return document.body.dataset.reportPreviewFitted === "true"');
                $updated = $preview->script(
                    "return document.querySelector('.cover__title')?.textContent?.trim() ?? null;",
                );
            });

            Assert::assertSame('Assessment IT Dusk', $updated[0] ?? null);
            Assert::assertNotSame($initialSource, $updatedSource);

            $browser->scrollIntoView('[data-dusk="report-style-preview"]')
                ->script('window.scrollBy(0, -80)');
            $browser->pause(250);
            $browser->driver->takeScreenshot("{$artifactRoot}/report-settings-a4-preview-1440x900.png");

            $scrolled = [];
            $browser->withinFrame('[data-dusk="report-preview-iframe"]', function (Browser $preview) use (&$scrolled): void {
                $preview->script(
                    "document.querySelector('[data-finding=\"2\"]')?.closest('.assestme-report-preview-sheet-slot')?.scrollIntoView();",
                );
                $preview->pause(200);
                $scrolled = $preview->script(<<<'JS'
                    const target = document.querySelector('[data-finding="2"]');
                    const rect = target?.getBoundingClientRect();

                    return {
                        scrollTop: document.documentElement.scrollTop,
                        targetVisible: (rect?.top ?? -1) >= -1
                            && (rect?.top ?? Number.MAX_SAFE_INTEGER) < document.documentElement.clientHeight,
                    };
                    JS);
            });
            Assert::assertGreaterThan(0, $scrolled[0]['scrollTop'] ?? 0);
            Assert::assertTrue($scrolled[0]['targetVisible'] ?? false);
            $browser->driver->takeScreenshot("{$artifactRoot}/report-settings-a4-preview-scrolled-1440x900.png");

            $browser->resize(390, 844)
                ->visit(ReportSettingsPage::getUrl())
                ->waitFor('[data-dusk="report-preview-iframe"]')
                ->scrollIntoView('[data-dusk="report-style-preview"]')
                ->pause(250);
            $narrowFrame = $browser->script(<<<'JS'
                const iframe = document.querySelector('[data-dusk="report-preview-iframe"]');
                const rect = iframe?.getBoundingClientRect();

                return {
                    width: rect?.width ?? 0,
                    height: rect?.height ?? 0,
                    scrolling: iframe?.getAttribute('scrolling'),
                };
                JS)[0];
            $narrowPreview = [];
            $browser->withinFrame('[data-dusk="report-preview-iframe"]', function (Browser $preview) use (&$narrowPreview): void {
                $preview->waitUntil('return document.body.dataset.reportPreviewFitted === "true"');
                $narrowPreview = $preview->script(<<<'JS'
                    const sheet = document.querySelector('.assestme-report-preview-sheet-slot > :first-child');
                    const rect = sheet?.getBoundingClientRect();

                    return {
                        viewportWidth: document.documentElement.clientWidth,
                        viewportHeight: document.documentElement.clientHeight,
                        sheetWidth: rect?.width ?? 0,
                        sheetHeight: rect?.height ?? 0,
                        scrollWidth: document.documentElement.scrollWidth,
                        scrollHeight: document.documentElement.scrollHeight,
                        pageCount: Number(document.body.dataset.reportPreviewPageCount ?? 0),
                    };
                    JS);
            });
            Assert::assertNull($narrowFrame['scrolling'] ?? null);
            Assert::assertEqualsWithDelta(210 / 297, ($narrowFrame['width'] ?? 0) / ($narrowFrame['height'] ?? 1), 0.002);
            Assert::assertEqualsWithDelta(
                $narrowPreview[0]['viewportWidth'] ?? 0,
                $narrowPreview[0]['sheetWidth'] ?? 0,
                1,
            );
            Assert::assertEqualsWithDelta(
                210 / 297,
                ($narrowPreview[0]['sheetWidth'] ?? 0) / ($narrowPreview[0]['sheetHeight'] ?? 1),
                0.002,
            );
            Assert::assertSame(8, $narrowPreview[0]['pageCount'] ?? 0);
            Assert::assertLessThanOrEqual(
                ($narrowPreview[0]['viewportWidth'] ?? 0) + 1,
                $narrowPreview[0]['scrollWidth'] ?? 0,
            );
            Assert::assertGreaterThan(
                $narrowPreview[0]['viewportHeight'] ?? 0,
                $narrowPreview[0]['scrollHeight'] ?? 0,
            );
            $browser->driver->takeScreenshot("{$artifactRoot}/report-settings-a4-preview-390x844.png");

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
