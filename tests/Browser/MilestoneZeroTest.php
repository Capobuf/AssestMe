<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Assessment;
use App\Models\Client;
use App\Models\Finding;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\File;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class MilestoneZeroTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_unsaved_warning_is_scoped_to_the_workspace_and_clears_after_save(): void
    {
        $administrator = User::factory()->create();
        $client = Client::factory()->create([
            'legal_name' => 'Cliente dirty-state browser S.r.l.',
            'phone' => null,
        ]);
        $assessment = Assessment::factory()->create(['title' => 'Dirty-state workspace browser']);
        Finding::factory()->for($assessment)->create();

        $this->browse(function (Browser $browser) use ($administrator, $assessment, $client): void {
            $browser->loginAs($administrator)
                ->visit("/admin/clients/{$client->getKey()}/edit")
                ->waitForText('Ragione sociale');

            $browser->script(<<<'JS'
                const input = Array.from(document.querySelectorAll('input'))
                    .find((element) => element.value === 'Cliente dirty-state browser S.r.l.');

                if (!input) {
                    throw new Error('The client legal-name input was not found.');
                }

                input.value = 'Cliente dirty-state salvato S.r.l.';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                JS);

            $browser->press('Salva')
                ->waitForText('Salvato');

            $ordinaryFormUnloadWasPrevented = $browser->script(<<<'JS'
                const event = new Event('beforeunload', { cancelable: true });
                window.dispatchEvent(event);

                return event.defaultPrevented;
                JS)[0];

            Assert::assertFalse(
                $ordinaryFormUnloadWasPrevented,
                'A saved ordinary Filament form must not inherit the workspace unsaved-change warning.',
            );

            $browser->visit("/admin/assessments/{$assessment->getKey()}/workspace")
                ->waitForText('Dettagli assessment')
                ->press('Dettagli assessment')
                ->waitFor('[data-dusk="save-assessment"]')
                ->waitUntil('return document.documentElement.dataset.assestmeWorkspaceAsset === "loaded"');

            $browser->script(<<<'JS'
                window.assestmePendingLivewireRequests = 0;
                window.assestmeLastLivewireRequestCompletedAt = performance.now();
                window.Livewire.hook('request', ({ respond, succeed, fail }) => {
                    let completed = false;
                    window.assestmePendingLivewireRequests++;

                    const complete = () => {
                        if (completed) {
                            return;
                        }

                        completed = true;
                        window.assestmePendingLivewireRequests--;
                        window.assestmeLastLivewireRequestCompletedAt = performance.now();
                    };

                    respond(complete);
                    succeed(complete);
                    fail(complete);
                });

                const input = Array.from(document.querySelectorAll('input'))
                    .find((element) => element.value === 'Dirty-state workspace browser');

                if (!input) {
                    throw new Error('The assessment title input was not found.');
                }

                input.value = 'Dirty-state workspace salvato';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                JS);

            $workspaceUnloadWasPrevented = $browser->script(<<<'JS'
                const event = new Event('beforeunload', { cancelable: true });
                window.dispatchEvent(event);

                return event.defaultPrevented;
                JS)[0];

            Assert::assertTrue(
                $workspaceUnloadWasPrevented,
                'An edited assessment workspace must retain its unsaved-change warning.',
            );

            $browser->click('[data-dusk="save-assessment"]')
                ->waitUntil('return document.querySelector(\'[data-assestme-save-status]\').dataset.status === "saved"')
                ->waitUntil(<<<'JS'
                    return window.assestmePendingLivewireRequests === 0
                        && performance.now() - window.assestmeLastLivewireRequestCompletedAt >= 250;
                    JS);

            $savedWorkspaceUnloadWasPrevented = $browser->script(<<<'JS'
                const event = new Event('beforeunload', { cancelable: true });
                window.dispatchEvent(event);

                return event.defaultPrevented;
                JS)[0];

            Assert::assertFalse(
                $savedWorkspaceUnloadWasPrevented,
                'A successfully saved assessment workspace must clear its unsaved-change warning.',
            );

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The dirty-state browser regression contains severe console errors.');
        });

        self::assertSame('Cliente dirty-state salvato S.r.l.', $client->fresh()->legal_name);
        self::assertSame('Dirty-state workspace salvato', $assessment->fresh()->title);
    }

    public function test_admin_login_and_native_workspace_actions_have_no_console_errors(): void
    {
        $this->seed(DatabaseSeeder::class);
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
            'title' => 'Finding ricerca Dusk',
            'problem' => str_repeat('Long multiline content must stay inside a compact row. ', 30),
        ]);

        $artifactRoot = base_path('storage/app/qa-artifacts');
        File::ensureDirectoryExists($artifactRoot);

        $this->browse(function (Browser $browser) use ($administrator, $password, $assessment, $artifactRoot): void {
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
                ->assertPresent('[data-dusk="add-template"]')
                ->assertPresent('[data-dusk="finding-actions"]')
                ->assertMissing('[data-dusk="open-finding"]')
                ->assertMissing('[data-dusk="move-up-finding"]')
                ->assertMissing('[data-dusk="move-down-finding"]')
                ->assertMissing('.fi-fo-table-repeater')
                ->assertPresent('.assestme-findings-workspace')
                ->waitUntil('return document.documentElement.dataset.assestmeWorkspaceAsset === "loaded"');

            $browser->type('input[type="search"]', 'ricerca Dusk')
                ->waitUntil('return document.querySelectorAll(\'.assestme-finding-row\').length === 1');
            $browser->script(<<<'JS'
                const search = document.querySelector('input[type="search"]');
                search.value = '';
                search.dispatchEvent(new Event('input', { bubbles: true }));
                JS);
            $browser->waitUntil('return document.querySelectorAll(\'.assestme-finding-row\').length === 10');

            $browser->click('.assestme-finding-row:first-of-type')
                ->waitFor('[data-assestme-finding-inspector]')
                ->select('[data-dusk="finding-property-status"] select', 'planned')
                ->click('[data-dusk="finding-property-report"]')
                ->click('[data-dusk="save-finding"]')
                ->waitUntil('return document.querySelector(\'[data-assestme-save-status]\').dataset.status === "saved"')
                ->click('[data-dusk="finding-close"]')
                ->waitUntilMissing('[data-assestme-finding-inspector]');

            $browser->script(<<<'JS'
                const root = document.querySelector('.assestme-findings-workspace').closest('[wire\\:id]');
                Livewire.find(root.getAttribute('wire:id')).$set('tableFilters.incomplete', { isActive: true });
                JS);
            $browser->waitUntil('return document.querySelectorAll(\'.assestme-finding-row\').length === 9');
            $browser->script(<<<'JS'
                const root = document.querySelector('.assestme-findings-workspace').closest('[wire\\:id]');
                Livewire.find(root.getAttribute('wire:id')).$set('tableFilters.incomplete', { isActive: false });
                JS);
            $browser->waitUntil('return document.querySelectorAll(\'.assestme-finding-row\').length === 10');

            $browser->resize(1440, 1000)->pause(400);
            $desktopLayout = $browser->script(<<<'JS'
                const workspace = document.querySelector('.assestme-findings-workspace');
                const list = document.querySelector('.assestme-findings-list');
                const firstRow = document.querySelector('.assestme-finding-row');

                return {
                    documentClientWidth: document.documentElement.clientWidth,
                    documentScrollWidth: document.documentElement.scrollWidth,
                    firstRowHeight: firstRow.getBoundingClientRect().height,
                    workspaceWidth: workspace.getBoundingClientRect().width,
                    listClientWidth: list.clientWidth,
                    listScrollWidth: list.scrollWidth,
                    selected: firstRow.getAttribute('aria-selected'),
                    tabIndex: firstRow.tabIndex,
                };
                JS)[0];

            Assert::assertGreaterThanOrEqual(72, $desktopLayout['firstRowHeight']);
            Assert::assertLessThanOrEqual(120, $desktopLayout['firstRowHeight']);
            Assert::assertLessThanOrEqual($desktopLayout['listClientWidth'] + 1, $desktopLayout['listScrollWidth']);
            Assert::assertLessThanOrEqual($desktopLayout['documentClientWidth'] + 1, $desktopLayout['documentScrollWidth']);
            Assert::assertSame('false', $desktopLayout['selected']);
            Assert::assertSame(0, $desktopLayout['tabIndex']);
            $browser->driver->takeScreenshot("{$artifactRoot}/workspace-list-1440-light.png");

            $browser->click('.assestme-finding-row:first-of-type')
                ->waitFor('[data-assestme-finding-inspector]')
                ->assertSee('Descrizione')
                ->assertSee('Soluzioni')
                ->assertSee('Evidenze')
                ->assertSee('Proprietà');
            $propertyText = $browser->script("return document.querySelector('.assestme-workbench-properties__body').textContent")[0];
            Assert::assertStringContainsString('Si applica a', $propertyText);
            Assert::assertStringContainsString('Rischio', $propertyText);

            $selectedLayout = $browser->script(<<<'JS'
                const workspace = document.querySelector('.assestme-findings-workspace');
                const editor = document.querySelector('.assestme-workbench-editor');
                const properties = document.querySelector('.assestme-workbench-properties');
                const selected = document.querySelector('.assestme-finding-row.is-selected');

                return {
                    hasInspector: workspace.classList.contains('has-inspector'),
                    editorWidth: editor.getBoundingClientRect().width,
                    propertiesWidth: properties.getBoundingClientRect().width,
                    selected: selected?.getAttribute('aria-selected'),
                };
                JS)[0];
            Assert::assertTrue($selectedLayout['hasInspector']);
            Assert::assertGreaterThan(0, $selectedLayout['editorWidth']);
            Assert::assertGreaterThan(0, $selectedLayout['propertiesWidth']);
            Assert::assertSame('true', $selectedLayout['selected']);
            $browser->driver->takeScreenshot("{$artifactRoot}/workspace-inspector-1440-light.png");

            $browser->script(<<<'JS'
                const title = document.querySelector('[data-dusk="finding-editor-title"]');
                title.value = 'Finding salvato da inspector';
                title.dispatchEvent(new Event('input', { bubbles: true }));
                document.dispatchEvent(new KeyboardEvent('keydown', { key: 's', ctrlKey: true, bubbles: true }));
                JS);
            $browser->waitUntil('return document.querySelector(\'[data-assestme-save-status]\').dataset.status === "saved"')
                ->assertSee('Finding salvato da inspector');

            $browser->script("document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', ctrlKey: true, bubbles: true }))");
            $browser->waitFor('[data-assestme-finding-inspector]')->pause(250);
            $browser->script("document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))");
            $browser->waitUntilMissing('[data-assestme-finding-inspector]');

            $browser->script("document.dispatchEvent(new KeyboardEvent('keydown', { key: 'n', altKey: true, bubbles: true }))");
            $browser->waitFor('[data-assestme-finding-inspector]')
                ->waitUntil('return document.querySelectorAll(\'.assestme-finding-row\').length === 11');
            $browser->click('[data-dusk="finding-close"]')->waitUntilMissing('[data-assestme-finding-inspector]');

            $browser->script("document.dispatchEvent(new KeyboardEvent('keydown', { key: 't', altKey: true, bubbles: true }))");
            $browser->waitFor('.fi-modal-window')->assertSee('Template');
            $browser->script("document.querySelector('.fi-modal-window button[aria-label*=\"Chiudi\"]')?.click() || document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))");
            $browser->waitUntilMissing('.fi-modal-window');
            $preMutationLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $preMutationLogs, 'Inspector and keyboard flows produced severe console errors.');

            $browser->click('.assestme-finding-row:first-of-type [data-dusk="finding-actions"]')
                ->waitFor('[data-dusk="duplicate-finding"]')
                ->click('[data-dusk="duplicate-finding"]')
                ->waitUntil('return document.querySelectorAll(\'.assestme-finding-row\').length === 12')
                ->waitFor('[data-assestme-finding-inspector]');
            $browser->click('[data-dusk="finding-close"]')->waitUntilMissing('[data-assestme-finding-inspector]');
            $duplicateLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $duplicateLogs, 'Duplicating from the action menu produced severe console errors.');

            $browser->script(<<<'JS'
                const root = document.querySelector('.assestme-findings-workspace').closest('[wire\\:id]');
                const component = Livewire.find(root.getAttribute('wire:id'));
                component.$set('tableSearch', '');
                JS);
            $browser->pause(500)
                ->waitFor('button[aria-label="Riordina record"]')
                ->click('button[aria-label="Riordina record"]')
                ->waitFor('.fi-ta-reorder-handle');
            $firstReorderKey = $browser->attribute('[x-sortable-item]', 'wire:key');
            $browser->script(<<<'JS'
                const sortable = document.querySelector('[x-sortable-item]').parentElement;
                const records = Array.from(sortable.querySelectorAll(':scope > [x-sortable-item]'));
                const dragged = records[0];
                sortable.insertBefore(dragged, records[3].nextSibling);
                const event = new CustomEvent('end', { bubbles: true });
                Object.defineProperty(event, 'item', { value: dragged });
                sortable.dispatchEvent(event);
                JS);
            $browser->waitUntil("return document.querySelector('[x-sortable-item]').getAttribute('wire:key') !== ".json_encode($firstReorderKey))
                ->click('button[aria-label="Termina riordino record"]');

            $browser->refresh()->waitFor('[data-dusk="finding-actions"]');
            $beforeDeleteLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $beforeDeleteLogs, 'A finding action before deletion produced severe console errors.');
            $browser->click('.assestme-finding-row:first-of-type [data-dusk="finding-actions"]')
                ->waitFor('[data-dusk="delete-finding"]')
                ->click('[data-dusk="delete-finding"]');
            $browser->waitForText('Conferma')->press('Conferma')
                ->waitUntil('return document.querySelectorAll(\'.assestme-finding-row\').length === 11');
            $deleteLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $deleteLogs, 'Deleting a finding produced severe console errors.');

            $browser->click('.assestme-finding-row:first-of-type')->waitFor('[data-assestme-finding-inspector]');
            $browser->script('window.dispatchEvent(new Event("offline"))');
            $browser->waitForText('Offline');
            $browser->script('window.dispatchEvent(new Event("online"))');
            $browser->waitForText('Modifiche non salvate');
            $browser->click('[data-dusk="finding-close"]')->waitUntilMissing('[data-assestme-finding-inspector]');

            $browser->resize(390, 844)->pause(500);
            $mobileLayout = $browser->script(<<<'JS'
                const workspace = document.querySelector('.assestme-findings-workspace');
                const firstRow = document.querySelector('.assestme-finding-row');

                return {
                    documentClientWidth: document.documentElement.clientWidth,
                    documentScrollWidth: document.documentElement.scrollWidth,
                    firstRowDisplay: getComputedStyle(firstRow).display,
                    workspaceWidth: workspace.getBoundingClientRect().width,
                };
                JS)[0];

            Assert::assertLessThanOrEqual(
                $mobileLayout['documentClientWidth'] + 1,
                $mobileLayout['documentScrollWidth'],
                'The responsive findings workspace must not overflow the mobile viewport.',
            );
            $browser->driver->takeScreenshot("{$artifactRoot}/workspace-list-mobile-light.png");
            $browser->click('.assestme-finding-row:first-of-type')->waitFor('[data-assestme-finding-inspector]')->pause(250);
            $browser->driver->takeScreenshot("{$artifactRoot}/workspace-inspector-mobile-light.png");
            $browser->resize(1920, 1080)->pause(250);
            $browser->script("localStorage.setItem('theme', 'dark'); document.documentElement.classList.add('dark');");
            $browser->pause(250);
            $browser->driver->takeScreenshot("{$artifactRoot}/workspace-inspector-1920-dark.png");

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The browser console contains severe errors.');
        });

        $assessment->refresh();
        self::assertSame(11, $assessment->findings()->count());
        self::assertGreaterThan(1, Finding::query()->findOrFail($firstFindingId)->sort_order);
        self::assertSame('Finding salvato da inspector', Finding::query()->findOrFail($firstFindingId)->title);
        self::assertSame('planned', Finding::query()->findOrFail($firstFindingId)->status->value);
        self::assertFalse(Finding::query()->findOrFail($firstFindingId)->include_in_report);
    }
}
