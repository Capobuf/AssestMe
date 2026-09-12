<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Filament\Pages\ReportSettingsPage;
use App\Models\GeneratedReport;
use App\Models\User;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class ReportSettingsPreviewTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_preview_loads_reacts_and_closes_fullscreen_without_generating_a_report(): void
    {
        $administrator = User::factory()->create();
        $reportFilesBefore = Storage::disk('local')->allFiles('reports');
        sort($reportFilesBefore);

        $this->browse(function (Browser $browser) use ($administrator): void {
            $browser->loginAs($administrator)
                ->visit(ReportSettingsPage::getUrl())
                ->waitFor('[data-dusk="report-preview-iframe"]');

            $initialSource = $browser->attribute('[data-dusk="report-preview-iframe"]', 'src');
            Assert::assertIsString($initialSource);
            Assert::assertNotSame('', $initialSource);
            $this->probePdf($browser);

            $browser->type('[data-dusk="report-preview-title-input"]', 'Assessment IT Dusk')
                ->waitUntil(
                    'return document.querySelector(\'[data-dusk="report-preview-iframe"]\')?.getAttribute("src") !== '.json_encode($initialSource).';',
                    10,
                );
            Assert::assertNotSame(
                $initialSource,
                $browser->attribute('[data-dusk="report-preview-iframe"]', 'src'),
            );
            $this->probePdf($browser);

            $browser->click('[data-dusk="report-preview-expand"]')
                ->waitForText('Chiudi schermo intero')
                ->assertAttribute('[data-dusk="report-preview-expand"]', 'aria-expanded', 'true')
                ->keys('[data-dusk="report-preview-expand"]', [WebDriverKeys::ESCAPE])
                ->waitForText('Espandi anteprima')
                ->assertAttribute('[data-dusk="report-preview-expand"]', 'aria-expanded', 'false');

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The report preview browser flow contains severe console errors.');
        });

        $reportFilesAfter = Storage::disk('local')->allFiles('reports');
        sort($reportFilesAfter);
        self::assertSame(0, GeneratedReport::query()->count());
        self::assertSame($reportFilesBefore, $reportFilesAfter);
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
                        signature: String.fromCharCode(...bytes.slice(0, 5)),
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
        Assert::assertSame('%PDF-', $probe['signature'] ?? null);
    }
}
