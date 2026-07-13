<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Assessment;
use App\Models\Finding;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class MilestoneZeroTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_admin_login_and_native_workspace_actions_have_no_console_errors(): void
    {
        $password = 'AssestMe-Dusk!2026';
        $administrator = User::factory()->create([
            'email' => 'dusk@assestme.local',
            'password' => $password,
        ]);
        $assessment = Assessment::factory()->create(['title' => 'Dusk workspace M0']);
        Finding::factory()->count(10)->for($assessment)->sequence(
            fn ($sequence): array => ['sort_order' => $sequence->index + 1],
        )->create();
        $firstFindingId = (int) $assessment->findings()->firstOrFail()->getKey();
        Finding::query()->findOrFail($firstFindingId)->update([
            'problem' => str_repeat('Long multiline content must stay inside a compact row. ', 30),
        ]);

        $multilineProblem = "Prima riga Dusk\nSeconda riga con priorità";

        $this->browse(function (Browser $browser) use ($administrator, $password, $assessment): void {
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
                ->visit("/admin/assessments/{$assessment->getKey()}/workspace")
                ->waitFor('[data-dusk="add-finding"]')
                ->assertSee('Finding')
                ->assertPresent('[data-dusk="add-finding"]')
                ->assertPresent('[data-dusk="clone-finding"]')
                ->assertPresent('[data-dusk="delete-finding"]')
                ->assertPresent('[data-dusk="reorder-findings"]')
                ->assertPresent('[data-dusk="move-down-finding"]')
                ->assertPresent('.fi-fo-table-repeater.fi-compact')
                ->waitUntil('return document.documentElement.dataset.assestmeWorkspaceAsset === "loaded"');

            $desktopLayout = $browser->script(<<<'JS'
                const wrapper = document.querySelector('.fi-fo-table-repeater');
                const firstRow = wrapper.querySelector('tbody tr');
                const problem = firstRow.querySelector('[data-dusk="finding-problem"]');
                const actionCell = firstRow.lastElementChild;
                const cloneAction = actionCell.querySelector('[data-dusk="clone-finding"]');
                const deleteAction = actionCell.querySelector('[data-dusk="delete-finding"]');
                const titleCell = firstRow.querySelector('td:nth-of-type(2)');
                const firstHeader = wrapper.querySelector('thead th:nth-of-type(2)');
                const table = wrapper.querySelector('table');

                return {
                    actionCellLeft: actionCell.getBoundingClientRect().left,
                    actionCellRight: actionCell.getBoundingClientRect().right,
                    actionCellWidth: actionCell.getBoundingClientRect().width,
                    cloneActionLeft: cloneAction.getBoundingClientRect().left,
                    deleteActionRight: deleteAction.getBoundingClientRect().right,
                    documentClientWidth: document.documentElement.clientWidth,
                    documentScrollWidth: document.documentElement.scrollWidth,
                    firstRowHeight: firstRow.getBoundingClientRect().height,
                    headerPosition: getComputedStyle(firstHeader).position,
                    problemHeight: problem.getBoundingClientRect().height,
                    tableWidth: table.getBoundingClientRect().width,
                    titlePosition: getComputedStyle(titleCell).position,
                    actionPosition: getComputedStyle(actionCell).position,
                    wrapperClientHeight: wrapper.clientHeight,
                    wrapperClientWidth: wrapper.clientWidth,
                    wrapperScrollHeight: wrapper.scrollHeight,
                    wrapperScrollWidth: wrapper.scrollWidth,
                    wrappedHeaderCount: wrapper.querySelectorAll('thead th.fi-wrapped').length,
                };
                JS)[0];

            Assert::assertLessThanOrEqual(120, $desktopLayout['firstRowHeight']);
            Assert::assertLessThanOrEqual(192, $desktopLayout['problemHeight']);
            Assert::assertGreaterThanOrEqual(96, $desktopLayout['actionCellWidth']);
            Assert::assertGreaterThanOrEqual($desktopLayout['actionCellLeft'], $desktopLayout['cloneActionLeft']);
            Assert::assertLessThanOrEqual($desktopLayout['actionCellRight'], $desktopLayout['deleteActionRight']);
            Assert::assertGreaterThanOrEqual(1880, $desktopLayout['tableWidth']);
            Assert::assertGreaterThan($desktopLayout['wrapperClientWidth'], $desktopLayout['wrapperScrollWidth']);
            Assert::assertGreaterThan($desktopLayout['wrapperClientHeight'], $desktopLayout['wrapperScrollHeight']);
            Assert::assertSame('sticky', $desktopLayout['headerPosition']);
            Assert::assertSame('sticky', $desktopLayout['titlePosition']);
            Assert::assertSame('sticky', $desktopLayout['actionPosition']);
            Assert::assertSame(9, $desktopLayout['wrappedHeaderCount']);
            Assert::assertLessThanOrEqual(
                $desktopLayout['documentClientWidth'],
                $desktopLayout['documentScrollWidth'],
                'The table repeater must not overflow the document viewport.',
            );

            $pinnedLayout = $browser->script(<<<'JS'
                const wrapper = document.querySelector('.fi-fo-table-repeater');
                const firstRow = wrapper.querySelector('tbody tr');
                const cells = firstRow.querySelectorAll(':scope > td');
                const header = wrapper.querySelector('thead th:nth-of-type(2)');

                wrapper.scrollLeft = 600;
                wrapper.scrollTop = 300;

                const result = {
                    actionRight: cells[cells.length - 1].getBoundingClientRect().right,
                    headerTop: header.getBoundingClientRect().top,
                    reorderLeft: cells[0].getBoundingClientRect().left,
                    titleLeft: cells[1].getBoundingClientRect().left,
                    wrapperLeft: wrapper.getBoundingClientRect().left,
                    wrapperRight: wrapper.getBoundingClientRect().right,
                    wrapperTop: wrapper.getBoundingClientRect().top,
                };

                wrapper.scrollLeft = 0;
                wrapper.scrollTop = 0;

                return result;
                JS)[0];

            Assert::assertEqualsWithDelta($pinnedLayout['wrapperLeft'], $pinnedLayout['reorderLeft'], 1);
            Assert::assertEqualsWithDelta($pinnedLayout['wrapperLeft'] + 112, $pinnedLayout['titleLeft'], 1);
            Assert::assertEqualsWithDelta($pinnedLayout['wrapperRight'], $pinnedLayout['actionRight'], 16);
            Assert::assertEqualsWithDelta($pinnedLayout['wrapperTop'], $pinnedLayout['headerTop'], 1);

            $problem = $browser->element('tbody tr:first-child [data-dusk="finding-problem"]');
            Assert::assertNotNull($problem);
            $problem->click();
            $browser->script(<<<'JS'
                const problem = document.querySelector('[data-dusk="finding-problem"]');
                problem.value = "Prima riga Dusk\nSeconda riga con priorità";
                problem.dispatchEvent(new Event('input', { bubbles: true }));
                JS);

            $browser->click('h1')
                ->waitUntil('return document.querySelector(\'[data-assestme-save-status]\').dataset.status === "saved"')
                ->click('tbody tr:first-child [data-dusk="clone-finding"]')
                ->waitUntil('return document.querySelectorAll(\'[data-dusk="clone-finding"]\').length === 11')
                ->waitUntil('return document.querySelector(\'[data-assestme-save-status]\').dataset.status === "saved"')
                ->click('[data-dusk="add-finding"]')
                ->waitUntil('return document.querySelectorAll(\'[data-dusk="clone-finding"]\').length === 12')
                ->waitUntil('return document.querySelector(\'[data-assestme-save-status]\').dataset.status === "saved"');

            $browser->script('document.querySelector(\'tbody tr:first-child [data-dusk="move-down-finding"]\').click()');
            $browser->waitUntil('return document.querySelectorAll(\'tbody tr[x-sortable-item]\')[1].getAttribute(\'x-sortable-item\') === "record-1"')
                ->waitUntil('return document.querySelector(\'[data-assestme-save-status]\').dataset.status === "saved"')
                ->pause(750);

            $browser->script('document.querySelector(\'tbody tr:last-child [data-dusk="delete-finding"]\').click()');
            $browser->waitForText('Conferma')
                ->press('Conferma')
                ->waitUntil('return document.querySelectorAll(\'[data-dusk="clone-finding"]\').length === 11')
                ->waitUntil('return document.querySelector(\'[data-assestme-save-status]\').dataset.status === "saved"')
                ->assertSee('Salva assessment')
                ->assertSee('Salvato');

            $browser->script('window.dispatchEvent(new Event("offline"))');
            $browser->waitForText('Offline');
            $browser->script('window.dispatchEvent(new Event("online"))');
            $browser->waitForText('Modifiche non salvate');

            $browser->resize(390, 844)->pause(500);
            $mobileLayout = $browser->script(<<<'JS'
                const wrapper = document.querySelector('.fi-fo-table-repeater');
                const table = wrapper.querySelector('table');
                const firstRow = table.querySelector('tbody tr');

                return {
                    documentClientWidth: document.documentElement.clientWidth,
                    documentScrollWidth: document.documentElement.scrollWidth,
                    firstRowDisplay: getComputedStyle(firstRow).display,
                    tableDisplay: getComputedStyle(table).display,
                };
                JS)[0];

            Assert::assertSame('block', $mobileLayout['tableDisplay']);
            Assert::assertSame('grid', $mobileLayout['firstRowDisplay']);
            Assert::assertLessThanOrEqual(
                $mobileLayout['documentClientWidth'],
                $mobileLayout['documentScrollWidth'],
                'The responsive repeater must not overflow the mobile viewport.',
            );
            $browser->resize(1920, 1080)->pause(250);

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The browser console contains severe errors.');
        });

        $assessment->refresh();
        self::assertSame(11, $assessment->findings()->count());
        self::assertSame(2, Finding::query()->findOrFail($firstFindingId)->sort_order);
        self::assertSame($multilineProblem, Finding::query()->findOrFail($firstFindingId)->problem);
    }
}
