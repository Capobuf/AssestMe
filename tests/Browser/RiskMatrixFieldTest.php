<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\RiskProfile;
use App\Models\User;
use Database\Seeders\MilestoneOneSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\File;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class RiskMatrixFieldTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_risk_matrix_opens_first_persists_one_of_sixteen_cells_and_stays_clean_after_save(): void
    {
        $this->seed(MilestoneOneSeeder::class);
        $administrator = User::factory()->create();
        $profile = RiskProfile::query()
            ->where('code', 'default')
            ->with(['priorityLevels', 'matrixEntries'])
            ->firstOrFail();
        $entry = $profile->matrixEntries->first();
        $replacement = $profile->priorityLevels->firstWhere('id', '!=', $entry->priority_level_id)
            ?? $profile->priorityLevels->last();
        $selector = sprintf(
            '[data-dusk="risk-matrix-cell-%d-%d"]',
            $entry->consequence_level_id,
            $entry->likelihood_level_id,
        );
        $artifactRoot = base_path('storage/app/qa-artifacts');
        File::ensureDirectoryExists($artifactRoot);

        $this->browse(function (Browser $browser) use ($administrator, $artifactRoot, $profile, $replacement, $selector): void {
            $browser->loginAs($administrator)
                ->resize(1366, 768)
                ->visit("/admin/risk-profiles/{$profile->getKey()}/edit")
                ->waitFor('[data-dusk="risk-matrix-grid"]')
                ->waitUntil('return document.querySelectorAll(\'[data-dusk^="risk-matrix-cell-"]\').length === 16')
                ->script("localStorage.setItem('theme', 'dark'); document.documentElement.classList.add('dark');");

            $initialState = $browser->script(<<<'JS'
                const activeTab = document.querySelector('.fi-sc-tabs .fi-tabs-item.fi-active');
                const matrix = document.querySelector('[data-dusk="risk-matrix-grid"]');
                const table = matrix.querySelector('table');
                const firstCell = matrix.querySelector('td');
                const firstSelect = matrix.querySelector('select');
                firstSelect.focus();

                return {
                    activeTab: activeTab?.textContent.trim(),
                    cells: matrix.querySelectorAll('tbody td').length,
                    selects: matrix.querySelectorAll('tbody select').length,
                    colHeaders: matrix.querySelectorAll('thead th[scope="col"]').length,
                    rowHeaders: matrix.querySelectorAll('tbody th[scope="row"]').length,
                    pageOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
                    matrixClientWidth: matrix.clientWidth,
                    matrixScrollWidth: matrix.scrollWidth,
                    tableMinWidth: table.getBoundingClientRect().width,
                    cellWidth: firstCell.getBoundingClientRect().width,
                    focusOutline: getComputedStyle(firstCell).outlineStyle,
                    accessibleLabel: document.querySelector(`label[for="${firstSelect.id}"]`)?.textContent.trim(),
                    priorityLabels: Array.from(firstCell.querySelectorAll('option')).map((option) => option.textContent.trim()),
                };
                JS)[0];

            Assert::assertSame('Matrice', $initialState['activeTab']);
            Assert::assertSame(16, $initialState['cells']);
            Assert::assertSame(16, $initialState['selects']);
            Assert::assertSame(5, $initialState['colHeaders']);
            Assert::assertSame(4, $initialState['rowHeaders']);
            Assert::assertLessThanOrEqual(1, $initialState['pageOverflow']);
            Assert::assertGreaterThanOrEqual(928, $initialState['tableMinWidth']);
            Assert::assertGreaterThanOrEqual(150, $initialState['cellWidth']);
            Assert::assertNotSame('none', $initialState['focusOutline']);
            Assert::assertStringContainsString('Priorità per conseguenza', $initialState['accessibleLabel']);
            Assert::assertContains('Bassa', $initialState['priorityLabels']);
            Assert::assertContains('Critica', $initialState['priorityLabels']);

            $backgroundBeforeHover = $browser->script("return getComputedStyle(document.querySelector('{$selector}').closest('td')).backgroundColor;")[0];
            $browser->mouseover($selector);
            $backgroundAfterHover = $browser->script("return getComputedStyle(document.querySelector('{$selector}').closest('td')).backgroundColor;")[0];
            Assert::assertNotSame($backgroundBeforeHover, $backgroundAfterHover, 'The matrix cell hover state was not visible.');

            $browser->select($selector, (string) $replacement->getKey())
                ->waitUntil("return document.querySelector('{$selector}').value === '{$replacement->getKey()}'")
                ->press('Salva')
                ->waitForText('Salvato')
                ->pause(300)
                ->refresh()
                ->waitFor($selector)
                ->assertSelected($selector, (string) $replacement->getKey());

            $browser->select($selector, '')
                ->press('Salva')
                ->waitForText(__('assestme.risk.errors.invalid_matrix'))
                ->assertSelected($selector, '');
            $browser->driver->takeScreenshot("{$artifactRoot}/risk-matrix-field-validation-1366x768-dark.png");
            $browser->select($selector, (string) $replacement->getKey())
                ->press('Salva')
                ->waitForText('Salvato')
                ->pause(300);

            $unloadWasPrevented = $browser->script(<<<'JS'
                const event = new Event('beforeunload', { cancelable: true });
                window.dispatchEvent(event);

                return event.defaultPrevented;
                JS)[0];
            Assert::assertFalse($unloadWasPrevented, 'A successful risk-matrix save left a false beforeunload warning.');

            foreach ([[1366, 768], [1920, 1080]] as [$width, $height]) {
                $browser->resize($width, $height)
                    ->script("localStorage.setItem('theme', 'dark'); document.documentElement.classList.add('dark');");
                $browser->pause(200);
                $matrixWidths = $browser->script(<<<'JS'
                    const matrix = document.querySelector('[data-dusk="risk-matrix-grid"]');

                    return { client: matrix.clientWidth, scroll: matrix.scrollWidth };
                    JS)[0];
                if ($width === 1920) {
                    Assert::assertLessThanOrEqual($matrixWidths['client'] + 1, $matrixWidths['scroll']);
                }
                $browser->driver->takeScreenshot("{$artifactRoot}/risk-matrix-field-{$width}x{$height}-dark.png");
            }

            $browser->resize(1366, 768)
                ->script("localStorage.setItem('theme', 'light'); document.documentElement.classList.remove('dark');");
            $browser->pause(200);
            $browser->driver->takeScreenshot("{$artifactRoot}/risk-matrix-field-1366x768-light.png");

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $logEntry): bool => ($logEntry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The risk-matrix browser flow contains severe console errors.');
        });

        $entry->refresh();
        self::assertSame($replacement->getKey(), $entry->priority_level_id);
        self::assertSame(16, $profile->matrixEntries()->count());
        foreach ([
            'risk-matrix-field-1366x768-dark.png',
            'risk-matrix-field-1920x1080-dark.png',
            'risk-matrix-field-1366x768-light.png',
            'risk-matrix-field-validation-1366x768-dark.png',
        ] as $artifact) {
            self::assertFileExists("{$artifactRoot}/{$artifact}");
            self::assertGreaterThan(0, (int) filesize("{$artifactRoot}/{$artifact}"));
        }
    }
}
