<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Filament\Pages\ReportSettingsPage;
use App\Models\GeneratedReport;
use App\Models\User;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class ReportSettingsPreviewTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_weasyprint_preview_reacts_and_expands_without_generating_a_report(): void
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
                ->assertSee('Espandi anteprima')
                ->assertDontSee('Soluzioni nel riepilogo')
                ->assertDontSee('Note tecniche nel report')
                ->assertDontSee('Stime economiche nel report')
                ->assertDontSee('Inizia ogni Finding su una nuova pagina')
                ->assertSee('Dopo una generazione PDF riuscita, completa l’Assessment se è ancora in bozza; non modifica l’anteprima.')
                ->assertSee('Imposta il valore predefinito dell’opzione che include nel solo XLSX i Finding esclusi dal report; non modifica PDF o anteprima.');

            $initialSource = $browser->attribute('[data-dusk="report-preview-iframe"]', 'src');
            Assert::assertIsString($initialSource);
            $this->probePdf($browser);

            $normalGeometry = $browser->script(<<<'JS'
                const preview = document.querySelector('[data-dusk="report-style-preview"]');
                const iframe = document.querySelector('[data-dusk="report-preview-iframe"]');
                const previewRect = preview?.getBoundingClientRect();
                const iframeRect = iframe?.getBoundingClientRect();

                return {
                    position: preview ? getComputedStyle(preview).position : null,
                    previewWidth: previewRect?.width ?? 0,
                    previewHeight: previewRect?.height ?? 0,
                    iframeWidth: iframeRect?.width ?? 0,
                    iframeHeight: iframeRect?.height ?? 0,
                };
                JS)[0];
            Assert::assertSame('static', $normalGeometry['position'] ?? null);
            Assert::assertGreaterThan(380, $normalGeometry['iframeWidth'] ?? 0);
            Assert::assertEqualsWithDelta(
                210 / 297,
                ($normalGeometry['iframeWidth'] ?? 0) / ($normalGeometry['iframeHeight'] ?? 1),
                0.002,
            );

            $browser->type('[data-dusk="report-preview-title-input"]', 'Assessment IT Dusk')
                ->pause(700)
                ->waitUntil(
                    'return document.querySelector(\'[data-dusk="report-preview-iframe"]\')?.getAttribute("src") !== '.json_encode($initialSource).';',
                    10,
                );
            $updatedSource = $browser->attribute('[data-dusk="report-preview-iframe"]', 'src');
            Assert::assertNotSame($initialSource, $updatedSource);
            $this->probePdf($browser);

            $browser->scrollIntoView('[data-dusk="report-style-preview"]')
                ->script('window.scrollBy(0, -80)');
            $browser->click('[data-dusk="report-preview-expand"]')
                ->waitForText('Chiudi schermo intero');
            $expandedGeometry = $browser->script(<<<'JS'
                const preview = document.querySelector('[data-dusk="report-style-preview"]');
                const iframe = document.querySelector('[data-dusk="report-preview-iframe"]');
                const previewRect = preview?.getBoundingClientRect();
                const iframeRect = iframe?.getBoundingClientRect();

                return {
                    expanded: preview?.classList.contains('is-expanded') ?? false,
                    ariaExpanded: document.querySelector('[data-dusk="report-preview-expand"]')?.getAttribute('aria-expanded'),
                    position: preview ? getComputedStyle(preview).position : null,
                    top: previewRect?.top ?? -1,
                    left: previewRect?.left ?? -1,
                    width: previewRect?.width ?? 0,
                    height: previewRect?.height ?? 0,
                    iframeHeight: iframeRect?.height ?? 0,
                    viewportWidth: document.documentElement.clientWidth,
                    viewportHeight: document.documentElement.clientHeight,
                };
                JS)[0];
            Assert::assertTrue($expandedGeometry['expanded'] ?? false);
            Assert::assertSame('true', $expandedGeometry['ariaExpanded'] ?? null);
            Assert::assertSame('fixed', $expandedGeometry['position'] ?? null);
            Assert::assertEqualsWithDelta(0, $expandedGeometry['top'] ?? -1, 1);
            Assert::assertEqualsWithDelta(0, $expandedGeometry['left'] ?? -1, 1);
            Assert::assertEqualsWithDelta(
                $expandedGeometry['viewportWidth'] ?? 0,
                $expandedGeometry['width'] ?? 0,
                1,
            );
            Assert::assertEqualsWithDelta(
                $expandedGeometry['viewportHeight'] ?? 0,
                $expandedGeometry['height'] ?? 0,
                1,
            );
            Assert::assertGreaterThan($normalGeometry['iframeHeight'] ?? 0, $expandedGeometry['iframeHeight'] ?? 0);
            $browser->driver->takeScreenshot("{$artifactRoot}/report-settings-weasyprint-preview-fullscreen-1440x900.png");

            $browser->keys('[data-dusk="report-preview-expand"]', [WebDriverKeys::ESCAPE])
                ->waitForText('Espandi anteprima');
            $collapsed = $browser->script(<<<'JS'
                const preview = document.querySelector('[data-dusk="report-style-preview"]');
                const button = document.querySelector('[data-dusk="report-preview-expand"]');

                return {
                    expanded: preview?.classList.contains('is-expanded') ?? false,
                    ariaExpanded: button?.getAttribute('aria-expanded'),
                };
                JS)[0];
            Assert::assertFalse($collapsed['expanded'] ?? true);
            Assert::assertSame('false', $collapsed['ariaExpanded'] ?? null);

            $browser->resize(390, 844)
                ->visit(ReportSettingsPage::getUrl())
                ->waitFor('[data-dusk="report-preview-iframe"]')
                ->scrollIntoView('[data-dusk="report-style-preview"]')
                ->pause(250);
            $narrow = $browser->script(<<<'JS'
                const iframe = document.querySelector('[data-dusk="report-preview-iframe"]');
                const rect = iframe?.getBoundingClientRect();

                return {
                    width: rect?.width ?? 0,
                    height: rect?.height ?? 0,
                    documentOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
                };
                JS)[0];
            Assert::assertEqualsWithDelta(210 / 297, ($narrow['width'] ?? 0) / ($narrow['height'] ?? 1), 0.002);
            Assert::assertLessThanOrEqual(1, $narrow['documentOverflow'] ?? 0);
            $this->probePdf($browser);

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

    private function probePdf(Browser $browser): void
    {
        $browser->script(<<<'JS'
            window.__assestmePreviewProbe = null;
            const source = document.querySelector('[data-dusk="report-preview-iframe"]')?.src;

            fetch(source, { credentials: 'same-origin', cache: 'no-store' })
                .then(async (response) => {
                    const bytes = new Uint8Array(await response.arrayBuffer());
                    window.__assestmePreviewProbe = {
                        status: response.status,
                        contentType: response.headers.get('content-type'),
                        cacheControl: response.headers.get('cache-control'),
                        signature: String.fromCharCode(...bytes.slice(0, 5)),
                        size: bytes.length,
                    };
                })
                .catch((error) => {
                    window.__assestmePreviewProbe = { error: String(error) };
                });
            JS);
        $browser->waitUntil('return window.__assestmePreviewProbe !== null;', 15);
        $probe = $browser->script('return window.__assestmePreviewProbe;')[0];

        Assert::assertArrayNotHasKey('error', $probe);
        Assert::assertSame(200, $probe['status'] ?? null);
        Assert::assertSame('application/pdf', $probe['contentType'] ?? null);
        Assert::assertSame('no-store, private', $probe['cacheControl'] ?? null);
        Assert::assertSame('%PDF-', $probe['signature'] ?? null);
        Assert::assertGreaterThan(10_000, $probe['size'] ?? 0);
    }
}
