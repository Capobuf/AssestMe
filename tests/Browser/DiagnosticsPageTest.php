<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Filament\Pages\DiagnosticsPage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class DiagnosticsPageTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_diagnostics_are_structured_and_responsive_without_page_overflow(): void
    {
        $this->seed(DatabaseSeeder::class);
        $administrator = User::factory()->create();

        $this->browse(function (Browser $browser) use ($administrator): void {
            $browser->loginAs($administrator)
                ->resize(1440, 900)
                ->visit(DiagnosticsPage::getUrl())
                ->waitFor('[data-dusk="application-diagnostics"]')
                ->assertSee('Controlli diagnostici')
                ->assertSee('VERSIONE PHP')
                ->assertSee('MEMORIA DISPONIBILE');

            $desktop = $browser->script(<<<'JS'
                const summary = document.querySelector('.assestme-diagnostics__summary');
                const table = document.querySelector('.assestme-diagnostics__table');
                const scroll = document.querySelector('.assestme-diagnostics__table-scroll');

                return {
                    summaryColumns: getComputedStyle(summary).gridTemplateColumns.split(' ').length,
                    summaryItems: summary.children.length,
                    rows: table.querySelectorAll('tbody tr').length,
                    statusBadges: document.querySelectorAll('.assestme-diagnostics .fi-badge').length,
                    tableContained: table.getBoundingClientRect().right <= scroll.getBoundingClientRect().right + 1,
                    documentOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
                };
                JS)[0];

            Assert::assertSame(3, $desktop['summaryColumns']);
            Assert::assertSame(3, $desktop['summaryItems']);
            Assert::assertGreaterThan(10, $desktop['rows']);
            Assert::assertSame($desktop['rows'] + 1, $desktop['statusBadges']);
            Assert::assertTrue($desktop['tableContained']);
            Assert::assertLessThanOrEqual(1, $desktop['documentOverflow']);

            $browser->resize(390, 844)->pause(250);
            $mobile = $browser->script(<<<'JS'
                const summary = document.querySelector('.assestme-diagnostics__summary');
                const scroll = document.querySelector('.assestme-diagnostics__table-scroll');

                return {
                    summaryColumns: getComputedStyle(summary).gridTemplateColumns.split(' ').length,
                    tableOverflow: getComputedStyle(scroll).overflowX,
                    tableScrollsInternally: scroll.scrollWidth > scroll.clientWidth,
                    documentOverflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
                };
                JS)[0];

            Assert::assertSame(1, $mobile['summaryColumns']);
            Assert::assertSame('auto', $mobile['tableOverflow']);
            Assert::assertTrue($mobile['tableScrollsInternally']);
            Assert::assertLessThanOrEqual(1, $mobile['documentOverflow']);

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The diagnostics page contains severe console errors.');
        });
    }
}
