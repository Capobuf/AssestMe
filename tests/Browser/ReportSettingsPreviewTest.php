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

    public function test_report_settings_preview_reacts_without_generating_a_report(): void
    {
        $administrator = User::factory()->create();
        $reportFilesBefore = Storage::disk('local')->allFiles('reports');
        sort($reportFilesBefore);

        $this->browse(function (Browser $browser) use ($administrator): void {
            $browser->loginAs($administrator)
                ->visit(ReportSettingsPage::getUrl())
                ->waitForText('Impostazioni report')
                ->waitFor('[data-dusk="report-style-preview"]')
                ->assertVisible('[data-dusk="report-preview-cover"]')
                ->assertSeeIn('[data-dusk="report-style-preview"]', 'Anteprima stile');

            $browser->waitForLivewire()
                ->type('[data-dusk="report-preview-title-input"]', 'Assessment IT Dusk')
                ->waitForTextIn('[data-dusk="report-preview-title"]', 'Assessment IT Dusk');

            $colorInputValue = [];
            $browser->waitForLivewire(function (Browser $browser) use (&$colorInputValue): void {
                $colorInputValue = $browser->script(<<<'JS'
                    const input = document.querySelector('[data-dusk="report-preview-color-input"]');

                    if (! (input instanceof HTMLInputElement)) {
                        return null;
                    }

                    const panel = input.closest('[x-data]')?.querySelector('.fi-fo-color-picker-panel');

                    if (! (panel instanceof HTMLElement)) {
                        return null;
                    }

                    input.value = '#B42318';
                    panel.dispatchEvent(new CustomEvent('color-changed', {
                        bubbles: true,
                        detail: { value: '#B42318' },
                    }));

                    return input.value;
                    JS);
            });

            Assert::assertSame('#B42318', $colorInputValue[0] ?? null);

            $browser->waitUntil(<<<'JS'
                return document.querySelector('[data-dusk="report-preview-accent-scope"]')
                    ?.getAttribute('style')
                    ?.includes('--preview-accent: #B42318') === true;
                JS)
                ->assertAttributeContains(
                    '[data-dusk="report-preview-accent-scope"]',
                    'style',
                    '--preview-accent: #B42318',
                )
                ->click('[data-dusk="report-preview-internal-tab"]');

            $previewState = $browser->script(<<<'JS'
                const root = document.querySelector('[data-dusk="report-style-preview"]');
                const preview = document.querySelector('[data-dusk="report-preview-internal"]');

                return {
                    page: root?.dataset.previewPage ?? null,
                    display: preview === null ? null : window.getComputedStyle(preview).display,
                };
                JS);
            Assert::assertSame('internal', $previewState[0]['page'] ?? null, json_encode($previewState, JSON_THROW_ON_ERROR));
            Assert::assertNotSame('none', $previewState[0]['display'] ?? null, json_encode($previewState, JSON_THROW_ON_ERROR));

            $browser->pause(1000);
            $settledPreviewState = $browser->script(<<<'JS'
                const root = document.querySelector('[data-dusk="report-style-preview"]');
                const preview = document.querySelector('[data-dusk="report-preview-internal"]');

                return {
                    page: root?.dataset.previewPage ?? null,
                    display: preview === null ? null : window.getComputedStyle(preview).display,
                };
                JS);
            Assert::assertSame('internal', $settledPreviewState[0]['page'] ?? null, json_encode($settledPreviewState, JSON_THROW_ON_ERROR));
            Assert::assertNotSame('none', $settledPreviewState[0]['display'] ?? null, json_encode($settledPreviewState, JSON_THROW_ON_ERROR));

            $browser->waitForLivewire()
                ->type('[data-dusk="report-preview-header-input"]', 'Header interno Dusk')
                ->waitForTextIn('[data-dusk="report-preview-header"]', 'Header interno Dusk')
                ->waitForLivewire()
                ->type('[data-dusk="report-preview-footer-input"]', 'Footer interno Dusk')
                ->waitForTextIn('[data-dusk="report-preview-footer"]', 'Footer interno Dusk')
                ->click('[data-dusk="report-preview-cover-tab"]');

            $coverState = $browser->script(<<<'JS'
                const root = document.querySelector('[data-dusk="report-style-preview"]');
                const preview = document.querySelector('[data-dusk="report-preview-cover"]');

                return {
                    page: root?.dataset.previewPage ?? null,
                    display: preview === null ? null : window.getComputedStyle(preview).display,
                    aria: document.querySelector('[data-dusk="report-preview-cover-tab"]')?.getAttribute('aria-selected') ?? null,
                };
                JS);
            Assert::assertSame('cover', $coverState[0]['page'] ?? null, json_encode($coverState, JSON_THROW_ON_ERROR));
            Assert::assertNotSame('none', $coverState[0]['display'] ?? null, json_encode($coverState, JSON_THROW_ON_ERROR));
            Assert::assertSame('true', $coverState[0]['aria'] ?? null, json_encode($coverState, JSON_THROW_ON_ERROR));

            $browser->click('[data-dusk="report-preview-internal-tab"]');
            $finalInternalState = $browser->script(<<<'JS'
                const root = document.querySelector('[data-dusk="report-style-preview"]');
                const preview = document.querySelector('[data-dusk="report-preview-internal"]');

                return {
                    page: root?.dataset.previewPage ?? null,
                    display: preview === null ? null : window.getComputedStyle(preview).display,
                    aria: document.querySelector('[data-dusk="report-preview-internal-tab"]')?.getAttribute('aria-selected') ?? null,
                };
                JS);
            Assert::assertSame('internal', $finalInternalState[0]['page'] ?? null, json_encode($finalInternalState, JSON_THROW_ON_ERROR));
            Assert::assertNotSame('none', $finalInternalState[0]['display'] ?? null, json_encode($finalInternalState, JSON_THROW_ON_ERROR));
            Assert::assertSame('true', $finalInternalState[0]['aria'] ?? null, json_encode($finalInternalState, JSON_THROW_ON_ERROR));

            $browser
                ->assertSeeIn('[data-dusk="report-preview-internal"]', 'Header interno Dusk')
                ->assertSeeIn('[data-dusk="report-preview-internal"]', 'Footer interno Dusk');

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
