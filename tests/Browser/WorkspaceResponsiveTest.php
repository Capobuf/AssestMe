<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Enums\EstimateType;
use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Models\PriorityLevel;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\File;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class WorkspaceResponsiveTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_workspace_uses_only_split_and_sequential_geometry_at_required_viewports(): void
    {
        [$administrator, $assessment, $firstFinding] = $this->responsiveFixture();
        $artifactRoot = base_path('storage/app/qa-artifacts');
        $clipboardPng = json_encode(
            base64_encode(File::get(base_path('fixtures/evidence/valid-small.png'))),
            JSON_THROW_ON_ERROR,
        );
        File::ensureDirectoryExists($artifactRoot);

        $this->browse(function (Browser $browser) use ($administrator, $assessment, $firstFinding, $artifactRoot, $clipboardPng): void {
            $browser->loginAs($administrator)
                ->visit("/admin/assessments/{$assessment->getKey()}/workspace?finding={$firstFinding->getKey()}")
                ->waitFor('[data-assestme-finding-inspector]')
                ->waitUntil('return document.documentElement.dataset.assestmeWorkspaceAsset === "loaded"');

            $rowPresentation = $browser->script(<<<'JS'
                return Array.from(document.querySelectorAll('.assestme-finding-row')).map((row) => ({
                    metadata: Boolean(row.querySelector('.assestme-finding-row__metadata')),
                    state: row.querySelector('.assestme-finding-row__state')?.textContent.trim() ?? null,
                    completion: row.querySelector('.assestme-finding-row__completion')?.textContent.trim() ?? null,
                    exclusion: row.querySelector('.assestme-finding-row__report-exclusion')?.getAttribute('aria-label') ?? null,
                }));
                JS)[0];
            Assert::assertFalse(collect($rowPresentation)->contains('metadata', true));
            Assert::assertNull($rowPresentation[0]['state']);
            Assert::assertNull($rowPresentation[0]['completion']);
            Assert::assertSame('Pianificato', $rowPresentation[1]['state']);
            Assert::assertSame('Escluso dal report', $rowPresentation[1]['exclusion']);
            Assert::assertStringContainsString('informazioni mancanti', (string) $rowPresentation[9]['completion']);

            $measurements = [];
            foreach ([[2560, 1440], [1920, 1080], [1440, 900], [1280, 800], [390, 844]] as [$width, $height]) {
                self::resizeViewport($browser, $width, $height);
                $measurements["{$width}x{$height}"] = self::measureWorkspace($browser);

                Assert::assertSame($width, $measurements["{$width}x{$height}"]['viewport']['width']);
                Assert::assertSame($height, $measurements["{$width}x{$height}"]['viewport']['height']);
                Assert::assertLessThanOrEqual(
                    $measurements["{$width}x{$height}"]['document']['clientWidth'] + 1,
                    $measurements["{$width}x{$height}"]['document']['scrollWidth'],
                    "The {$width}x{$height} document must not have horizontal overflow.",
                );
                Assert::assertSame('static', $measurements["{$width}x{$height}"]['form']['position']);
            }

            foreach (['2560x1440', '1920x1080', '1440x900'] as $viewport) {
                $geometry = $measurements[$viewport];
                Assert::assertSame('flex', $geometry['list']['display']);
                Assert::assertSame('grid', $geometry['form']['display']);
                Assert::assertEqualsWithDelta(288, $geometry['list']['width'], 2);
                Assert::assertEqualsWithDelta(288, $geometry['properties']['width'], 2);
                Assert::assertGreaterThan($geometry['list']['width'], $geometry['editor']['width']);
                Assert::assertGreaterThan($geometry['properties']['width'], $geometry['editor']['width']);
                Assert::assertLessThanOrEqual($geometry['list']['clientWidth'] + 1, $geometry['list']['scrollWidth']);
                Assert::assertLessThanOrEqual($geometry['form']['clientWidth'] + 1, $geometry['form']['scrollWidth']);
                Assert::assertGreaterThanOrEqual(
                    $geometry['list']['right'] - 1,
                    $geometry['editor']['left'],
                    "The editor must be positioned to the right of the navigator at {$viewport}.",
                );
                Assert::assertGreaterThanOrEqual(
                    $geometry['editor']['right'] - 1,
                    $geometry['properties']['left'],
                    "The properties panel must be positioned to the right of the editor at {$viewport}.",
                );
                Assert::assertGreaterThanOrEqual(
                    min($geometry['list']['height'], $geometry['form']['height']) - 2,
                    $geometry['verticalOverlap'],
                    "Navigator and editor must share the workbench height at {$viewport}.",
                );
                Assert::assertLessThanOrEqual($geometry['viewport']['height'], $geometry['header']['bottom']);
                Assert::assertLessThanOrEqual($geometry['viewport']['height'], $geometry['footer']['bottom']);
                Assert::assertSame('auto', $geometry['editor']['overflowY']);
                Assert::assertSame('auto', $geometry['propertiesBody']['overflowY']);
                Assert::assertSame('true', $geometry['selected']);
            }

            foreach (['1280x800', '390x844'] as $viewport) {
                $geometry = $measurements[$viewport];
                Assert::assertSame('none', $geometry['list']['display']);
                Assert::assertSame('grid', $geometry['form']['display']);
                Assert::assertEqualsWithDelta(
                    $geometry['workspace']['width'],
                    $geometry['form']['width'],
                    2,
                    "The sequential editor must use the workspace width at {$viewport}.",
                );
                Assert::assertEqualsWithDelta($geometry['form']['width'], $geometry['editor']['width'], 20);
            }

            Assert::assertSame('loaded', $measurements['1920x1080']['asset']['marker']);
            Assert::assertTrue($measurements['1920x1080']['asset']['cssLoaded']);
            Assert::assertTrue($measurements['1920x1080']['asset']['jsLoaded']);
            Assert::assertStringContainsString('/css/app/assestme-workspace.css', $measurements['1920x1080']['asset']['cssUrl']);
            Assert::assertStringContainsString('/js/app/assestme-workspace.js', $measurements['1920x1080']['asset']['jsUrl']);

            self::resizeViewport($browser, 1920, 1080);
            $propertySections = $browser->script(<<<'JS'
                return Array.from(document.querySelectorAll('.assestme-workbench-properties .fi-section'))
                    .map((section) => ({
                        heading: section.querySelector('.fi-section-header-heading')?.textContent.trim(),
                        expanded: section.querySelector('.fi-section-content-ctn')?.getAttribute('aria-expanded'),
                    }));
                JS)[0];
            Assert::assertSame(
                [
                    ['expanded' => 'false', 'heading' => 'Stato e report'],
                    ['expanded' => 'false', 'heading' => 'Classificazione'],
                    ['expanded' => 'false', 'heading' => 'Si applica a'],
                    ['expanded' => 'false', 'heading' => 'Rischio'],
                    ['expanded' => 'false', 'heading' => 'Risoluzione'],
                ],
                $propertySections,
            );
            self::resizeViewport($browser, 1440, 900);
            $browser->script(<<<'JS'
                const body = document.querySelector('.assestme-workbench-properties__body');
                for (const label of ['Rischio', 'Risoluzione']) {
                    Array.from(body.querySelectorAll('.fi-section-header-heading'))
                        .find((heading) => heading.textContent.trim() === label)
                        .closest('.fi-section-header')
                        .click();
                }
                JS);
            $browser->pause(600);
            $propertiesScroll = $browser->script(<<<'JS'
                const body = document.querySelector('.assestme-workbench-properties__body');
                const bodyRect = body.getBoundingClientRect();
                const resolution = Array.from(body.querySelectorAll('.fi-section-header-heading'))
                    .find((heading) => heading.textContent.trim() === 'Risoluzione')
                    .closest('.assestme-workbench-section');
                const resolutionRect = resolution.getBoundingClientRect();

                return {
                    clientHeight: body.clientHeight,
                    scrollHeight: body.scrollHeight,
                    scrollTop: body.scrollTop,
                    resolutionTop: resolutionRect.top,
                    resolutionBottom: resolutionRect.bottom,
                    bodyTop: bodyRect.top,
                    bodyBottom: bodyRect.bottom,
                    resolutionExpanded: resolution.querySelector('.fi-section-content-ctn').getAttribute('aria-expanded'),
                };
                JS)[0];
            Assert::assertGreaterThan($propertiesScroll['clientHeight'], $propertiesScroll['scrollHeight']);
            Assert::assertGreaterThan(0, $propertiesScroll['scrollTop']);
            Assert::assertGreaterThanOrEqual($propertiesScroll['bodyTop'], $propertiesScroll['resolutionTop']);
            Assert::assertLessThanOrEqual($propertiesScroll['bodyBottom'], $propertiesScroll['resolutionBottom']);
            Assert::assertSame('true', $propertiesScroll['resolutionExpanded']);

            File::put(
                "{$artifactRoot}/workbench-after-geometry.json",
                json_encode($measurements, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            );

            self::resizeViewport($browser, 1280, 800);
            $browser->click('[data-dusk="finding-close"]')->waitUntilMissing('[data-assestme-finding-inspector]');
            Assert::assertSame('flex', $browser->script(
                "return getComputedStyle(document.querySelector('.assestme-findings-list')).display",
            )[0]);
            $browser->click('.assestme-finding-row:first-of-type')->waitFor('[data-assestme-finding-inspector]');
            Assert::assertSame('none', self::measureWorkspace($browser)['list']['display']);

            self::resizeViewport($browser, 1920, 1080);
            $browser->script("localStorage.setItem('theme', 'dark'); document.documentElement.classList.add('dark');");
            $browser->pause(250);
            $browser->driver->takeScreenshot("{$artifactRoot}/palette-after-1920x1080-dark.png");
            self::resizeViewport($browser, 1440, 900);
            $browser->driver->takeScreenshot("{$artifactRoot}/palette-after-1440x900-dark.png");
            $browser->script("localStorage.setItem('theme', 'light'); document.documentElement.classList.remove('dark');");
            $browser->pause(250);
            $browser->driver->takeScreenshot("{$artifactRoot}/palette-after-1440x900-light.png");
            $browser->script("localStorage.setItem('theme', 'dark'); document.documentElement.classList.add('dark');");
            self::resizeViewport($browser, 1920, 1080);
            $navigator = $browser->element('.assestme-findings-list');
            Assert::assertNotNull($navigator);
            $navigator->takeElementScreenshot("{$artifactRoot}/palette-after-navigator-selected-dark.png");
            $browser->click('[data-dusk="new-finding-menu"]')
                ->waitFor('[data-dusk="add-finding"]');
            $browser->driver->takeScreenshot("{$artifactRoot}/palette-after-new-finding-menu-dark.png");
            $browser->click('[data-dusk="new-finding-menu"]')
                ->click('[data-dusk="export-menu"]')
                ->waitFor('[data-dusk="generate-pdf"]');
            $browser->driver->takeScreenshot("{$artifactRoot}/palette-after-export-menu-dark.png");
            $browser->click('[data-dusk="export-menu"]');
            $footer = $browser->element('.assestme-workbench-footer');
            Assert::assertNotNull($footer);
            $footer->takeElementScreenshot("{$artifactRoot}/palette-after-editor-footer-dark.png");
            $browser->driver->takeScreenshot("{$artifactRoot}/workbench-after-1920x1080.png");
            $pasteEnabled = $browser->script(<<<JS
                const editor = document.querySelector('[data-assestme-workbench-editor]');
                editor.scrollTop = editor.scrollHeight;
                const input = document.querySelector('.assestme-workbench-section--evidence input[type="file"]');
                document.activeElement?.blur();
                document.body.focus();
                const bytes = Uint8Array.from(atob({$clipboardPng}), (character) => character.charCodeAt(0));
                const transfer = new DataTransfer();
                transfer.items.add(new File([bytes], 'screenshot-incollato.png', { type: 'image/png' }));
                const paste = new Event('paste', { bubbles: true, cancelable: true });
                Object.defineProperty(paste, 'clipboardData', { value: transfer });
                document.dispatchEvent(paste);

                return {
                    input: Boolean(input),
                    status: document.documentElement.dataset.assestmeEvidencePaste ?? null,
                };
                JS)[0];
            Assert::assertSame(['input' => true, 'status' => 'queued'], $pasteEnabled);
            $browser->waitUntil('return document.documentElement.dataset.assestmeEvidencePaste !== "queued"');
            Assert::assertSame('added', $browser->script(
                'return document.documentElement.dataset.assestmeEvidencePaste',
            )[0]);
            $browser->waitUntil('return document.querySelectorAll(".filepond--item").length === 1')
                ->pause(1000)
                ->click('[data-dusk="save-finding"]')
                ->waitUntil('return document.querySelector("[data-assestme-save-status]").dataset.status === "saved"')
                ->assertSee('screenshot-incollato.png');
            $browser->script(<<<'JS'
                for (const label of ['Note tecniche']) {
                    const heading = Array.from(document.querySelectorAll('.fi-section-header-heading'))
                        .find((element) => element.textContent.trim() === label);
                    heading?.closest('.fi-section-header')?.click();
                }
                JS);
            $browser->pause(250);
            $browser->script("document.querySelector('[data-assestme-workbench-editor]').scrollTop = 240");
            $browser->driver->takeScreenshot("{$artifactRoot}/workbench-editor-scroll-1920x1080.png");

            $browser->script(<<<'JS'
                const root = document.querySelector('.assestme-findings-workspace').closest('[wire\\:id]');
                Livewire.find(root.getAttribute('wire:id')).$set('findingData.scope_type', 'selected_assets');
                JS);
            $browser->pause(250)->click('[data-dusk="save-finding"]')
                ->waitUntil('return document.querySelector("[data-assestme-save-status]").dataset.status === "error"');
            $browser->driver->takeScreenshot("{$artifactRoot}/workbench-validation-error-1920x1080.png");

            $browser->refresh()
                ->waitFor('[data-assestme-finding-inspector]')
                ->waitUntil('return document.documentElement.dataset.assestmeWorkspaceAsset === "loaded"');
            self::resizeViewport($browser, 390, 844);
            $browser->click('[data-dusk="finding-close"]')->waitUntilMissing('[data-assestme-finding-inspector]');
            $browser->driver->takeScreenshot("{$artifactRoot}/workbench-narrow-navigator-390x844.png");

            $browser->click('.fi-ta-search-field')
                ->waitUntil('return document.querySelector(".fi-ta-search-field").getBoundingClientRect().width > 100')
                ->type('input[type="search"]', 'Finding incompleto')
                ->waitUntil('return document.querySelectorAll(".assestme-finding-row").length === 5');
            $browser->script(<<<'JS'
                const list = document.querySelector('.assestme-findings-list .fi-ta-content-ctn');
                list.scrollTop = 80;
                JS);
            $browser->waitUntil('return document.querySelector(".assestme-finding-row:first-of-type")?.dataset.assestmeKeyboardReady === "true"');
            $browser->script(<<<'JS'
                const row = document.querySelector('.assestme-finding-row:first-of-type');
                row.focus();
                row.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
                JS);
            $browser->waitFor('[data-assestme-finding-inspector]')->assertQueryStringHas('finding');
            Assert::assertSame('none', self::measureWorkspace($browser)['list']['display']);
            $browser->driver->takeScreenshot("{$artifactRoot}/workbench-narrow-editor-390x844.png");
            $browser->click('[data-dusk="finding-close"]')->waitUntilMissing('[data-assestme-finding-inspector]');
            $browser->assertInputValue('input[type="search"]', 'Finding incompleto')
                ->waitUntil('return document.querySelectorAll(".assestme-finding-row").length > 0');
            $browser->assertMissing('.fi-ta-filters-dropdown');

            $browser->element('.assestme-finding-row:first-of-type')?->sendKeys(WebDriverKeys::SPACE);
            $browser->waitFor('[data-assestme-finding-inspector]')
                ->click('[data-dusk="finding-close"]')
                ->waitUntilMissing('[data-assestme-finding-inspector]');
            $browser->click('.assestme-finding-row:first-of-type')->waitFor('[data-assestme-finding-inspector]');
            Assert::assertSame('true', $browser->attribute('.assestme-finding-row.is-selected', 'aria-selected'));

            self::assertNoSevereBrowserLogs($browser, 'The responsive workspace produced severe console errors.');
        });
    }

    public function test_reactive_finding_fields_preserve_the_visible_scroll_position(): void
    {
        [$administrator, $assessment, $firstFinding] = $this->responsiveFixture();
        $firstFinding->solutions()->firstOrFail()->update([
            'estimate_type' => EstimateType::RequiresQuote,
        ]);

        $this->browse(function (Browser $browser) use ($administrator, $assessment, $firstFinding): void {
            $browser->loginAs($administrator)
                ->visit("/admin/assessments/{$assessment->getKey()}/workspace?finding={$firstFinding->getKey()}")
                ->waitFor('[data-dusk="finding-estimate-type"] select')
                ->waitUntil('return document.documentElement.dataset.assestmeWorkspaceAsset === "loaded"');

            self::resizeViewport($browser, 1440, 900);
            $desktopBefore = $browser->script(<<<'JS'
                const editor = document.querySelector('[data-assestme-workbench-editor]');
                const field = document.querySelector('[data-dusk="finding-estimate-type"]');
                editor.scrollTop += field.getBoundingClientRect().top - editor.getBoundingClientRect().top - 120;

                return editor.scrollTop;
                JS)[0];
            Assert::assertGreaterThan(0, $desktopBefore);

            $browser->select('[data-dusk="finding-estimate-type"] select', EstimateType::Exact->value)
                ->waitUntil(<<<'JS'
                    return document.querySelector('[data-dusk="finding-estimate-type"] select')?.value === 'exact'
                        && Array.from(document.querySelectorAll('label')).some((label) => label.textContent.includes('Importo minimo'));
                    JS)
                ->pause(250);
            $desktopAfter = $browser->script(
                "return document.querySelector('[data-assestme-workbench-editor]').scrollTop",
            )[0];
            Assert::assertEqualsWithDelta($desktopBefore, $desktopAfter, 2);

            self::resizeViewport($browser, 1280, 800);
            $narrowBefore = $browser->script(<<<'JS'
                const form = document.querySelector('.assestme-workbench-form');
                const field = document.querySelector('[data-dusk="finding-estimate-type"]');
                form.scrollTop += field.getBoundingClientRect().top - form.getBoundingClientRect().top - 120;

                return form.scrollTop;
                JS)[0];
            Assert::assertGreaterThan(0, $narrowBefore);

            $browser->select('[data-dusk="finding-estimate-type"] select', EstimateType::Range->value)
                ->waitUntil(<<<'JS'
                    return document.querySelector('[data-dusk="finding-estimate-type"] select')?.value === 'range'
                        && Array.from(document.querySelectorAll('label')).some((label) => label.textContent.includes('Importo massimo'));
                    JS)
                ->pause(250);
            $narrowAfter = $browser->script(
                "return document.querySelector('.assestme-workbench-form').scrollTop",
            )[0];
            Assert::assertEqualsWithDelta($narrowBefore, $narrowAfter, 2);

            self::assertNoSevereBrowserLogs($browser, 'Reactive Finding fields produced severe console errors.');
        });
    }

    public function test_workspace_renders_native_table_counts_of_10_25_and_50_findings(): void
    {
        $administrator = User::factory()->create();
        $assessments = collect([10, 25, 50])->mapWithKeys(function (int $count): array {
            $assessment = Assessment::factory()->create(['title' => "Workspace {$count} finding"]);
            Finding::factory()->count($count)->for($assessment)->sequence(
                fn ($sequence): array => ['sort_order' => $sequence->index + 1],
            )->create();

            return [$count => $assessment];
        });

        $this->browse(function (Browser $browser) use ($administrator, $assessments): void {
            $browser->loginAs($administrator);
            self::resizeViewport($browser, 1920, 1080);

            foreach ([10, 25, 50] as $count) {
                $assessment = $assessments[$count];
                $browser->visit("/admin/assessments/{$assessment->getKey()}/workspace")
                    ->waitFor('.assestme-finding-row');
                $browser->script(<<<'JS'
                    const root = document.querySelector('.assestme-findings-workspace').closest('[wire\\:id]');
                    Livewire.find(root.getAttribute('wire:id')).$set('tableRecordsPerPage', 50);
                    JS);
                $browser
                    ->waitUntil("return document.querySelectorAll('.assestme-finding-row').length === {$count}");
                Assert::assertCount($count, $browser->elements('.assestme-finding-row'));

                if ($count === 25) {
                    $browser->script(<<<'JS'
                        const root = document.querySelector('.assestme-findings-workspace').closest('[wire\\:id]');
                        const component = Livewire.find(root.getAttribute('wire:id'));
                        component.$set('tableRecordsPerPage', 10);
                        component.setPage(2);
                        JS);
                    $browser->waitUntil('return document.querySelectorAll(".assestme-finding-row").length === 10');
                    $pageTwoKeys = $browser->script(<<<'JS'
                        return Array.from(document.querySelectorAll('.assestme-finding-row'))
                            .map((row) => row.getAttribute('wire:key'));
                        JS)[0];
                    $browser->click('.assestme-finding-row:first-of-type')
                        ->waitFor('[data-assestme-finding-inspector]')
                        ->click('[data-dusk="finding-close"]')
                        ->waitUntilMissing('[data-assestme-finding-inspector]');
                    $stateAfterClose = $browser->script(<<<'JS'
                        const root = document.querySelector('.assestme-findings-workspace').closest('[wire\\:id]');

                        return {
                            keys: Array.from(document.querySelectorAll('.assestme-finding-row'))
                                .map((row) => row.getAttribute('wire:key')),
                            page: Livewire.find(root.getAttribute('wire:id')).$get('paginators').page,
                        };
                        JS)[0];
                    Assert::assertSame(2, $stateAfterClose['page']);
                    Assert::assertSame($pageTwoKeys, $stateAfterClose['keys']);
                }
            }

            self::assertNoSevereBrowserLogs($browser, 'The 10/25/50 finding table checks produced severe console errors.');
        });
    }

    public function test_dirty_selection_save_next_and_version_conflict_use_the_signed_workspace_protocol(): void
    {
        $administrator = User::factory()->create();
        $assessment = Assessment::factory()->create(['title' => 'Workspace persistence browser']);
        $findings = Finding::factory()->count(2)->for($assessment)->sequence(
            ['title' => 'Finding persistence first', 'sort_order' => 1],
            ['title' => 'Finding persistence second', 'sort_order' => 2],
        )->create();
        $first = $findings[0];
        $second = $findings[1];

        $this->browse(function (Browser $browser) use ($administrator, $assessment, $first, $second): void {
            $browser->loginAs($administrator)
                ->visit("/admin/assessments/{$assessment->getKey()}/workspace?finding={$first->getKey()}")
                ->waitFor('[data-dusk="finding-editor-title"]');
            self::resizeViewport($browser, 1920, 1080);
            $browser
                ->type('[data-dusk="finding-editor-title"]', 'Finding modificato e salvato')
                ->waitUntil('return document.querySelector("[data-assestme-save-status]").dataset.status === "unsaved"')
                ->click('.assestme-finding-row + .assestme-finding-row')
                ->pause(300)
                ->assertAttribute('.assestme-findings-workspace', 'data-selected-finding', (string) $first->getKey())
                ->click('[data-dusk="save-finding-next"]')
                ->waitUntil("return document.querySelector('.assestme-findings-workspace').dataset.selectedFinding === '{$second->getKey()}'")
                ->assertInputValue('[data-dusk="finding-editor-title"]', 'Finding persistence second');

            Assert::assertSame('Finding modificato e salvato', $first->fresh()->title);
            $assessment->refresh()->increment('lock_version');

            $browser->type('[data-dusk="finding-editor-title"]', 'Aggiornamento concorrente rifiutato')
                ->click('[data-dusk="save-finding"]')
                ->waitUntil('return document.querySelector("[data-assestme-save-status]").dataset.status === "conflict"')
                ->assertSee('Conflitto');

            $navigatorOverflow = $browser->script(<<<'JS'
                const content = document.querySelector('.assestme-findings-list .fi-ta-content-ctn');
                const table = content.querySelector('.fi-ta-table');
                const row = content.querySelector('.assestme-finding-row');
                const actionCell = row.querySelector('.fi-ta-cell:last-child');
                const action = actionCell.querySelector('[data-dusk="finding-actions"]');
                const widths = (element) => ({
                    client: element.clientWidth,
                    offset: element.offsetWidth,
                    scroll: element.scrollWidth,
                    rect: element.getBoundingClientRect().width,
                });

                return {
                    content: widths(content),
                    table: widths(table),
                    row: widths(row),
                    actionCell: widths(actionCell),
                    action: widths(action),
                };
                JS)[0];
            Assert::assertLessThanOrEqual(
                $navigatorOverflow['content']['client'] + 1,
                $navigatorOverflow['content']['scroll'],
                'The navigator table must not overflow horizontally in the conflict state: '.json_encode($navigatorOverflow),
            );

            $browser->driver->takeScreenshot(base_path('storage/app/qa-artifacts/palette-after-conflict-1920x1080-dark.png'));

            Assert::assertSame('Finding persistence second', $second->fresh()->title);
            self::assertNoSevereBrowserLogs($browser, 'Dirty-state and conflict flows produced severe console errors.');
        });
    }

    /** @return array{User, Assessment, Finding} */
    private function responsiveFixture(): array
    {
        $this->seed(DatabaseSeeder::class);
        $administrator = User::factory()->create();
        $assessment = Assessment::factory()->create(['title' => 'Workspace responsive diagnostics']);
        $priorities = PriorityLevel::query()->orderBy('sort_order')->pluck('id')->values();
        $realisticTitles = [
            'Backup del NAS non verificato',
            'NAS appoggiato sopra l’UPS senza staffaggio e protezione dagli urti',
            'Accessi amministrativi non tracciati',
            'Patch critiche non installate sui server',
            'Continuità elettrica del rack non documentata',
        ];

        FindingTemplate::query()->where('is_enabled', true)->orderBy('id')->limit(5)->get()
            ->each(function (FindingTemplate $template, int $index) use ($assessment, $priorities, $realisticTitles): void {
                $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
                $finding->update([
                    'title' => $realisticTitles[$index],
                    'problem' => "Problema realistico sulla prima riga.\nDettaglio operativo sulla seconda riga.",
                    'technical_notes' => str_repeat("Nota tecnica articolata per la verifica del form lungo.\n", 3),
                    'priority_level_id' => $priorities[$index % $priorities->count()],
                    'status' => [FindingStatus::Open, FindingStatus::Planned, FindingStatus::InProgress][$index % 3],
                    'include_in_report' => $index % 2 === 0,
                    ...($index === 0 ? ['scope_type' => ScopeType::Organization] : []),
                ]);
            });

        Finding::factory()->count(5)->for($assessment)->sequence(
            fn ($sequence): array => [
                'title' => 'Finding incompleto: '.[
                    'inventario dei dispositivi di rete',
                    'responsabile del ripristino non assegnato',
                    'test di restore non documentato',
                    'protezione fisica del locale tecnico',
                    'classificazione degli asset da completare',
                ][$sequence->index],
                'problem' => $sequence->index === 4 ? null : "Problema incompleto {$sequence->index}\nSeconda riga descrittiva",
                'priority_level_id' => $sequence->index === 4 ? null : $priorities[$sequence->index % $priorities->count()],
                'status' => FindingStatus::Open,
                'include_in_report' => $sequence->index % 2 === 0,
                'sort_order' => 6 + $sequence->index,
            ],
        )->create();

        return [$administrator, $assessment, $assessment->findings()->firstOrFail()];
    }

    /** @return array<string, mixed> */
    private static function measureWorkspace(Browser $browser): array
    {
        return $browser->script(<<<'JS'
            const workspace = document.querySelector('.assestme-findings-workspace');
            const container = document.querySelector('.assestme-findings-container');
            const list = document.querySelector('.assestme-findings-list');
            const form = document.querySelector('.assestme-workbench-form');
            const header = document.querySelector('.assestme-workbench-editor__header');
            const editor = document.querySelector('[data-assestme-workbench-editor]');
            const properties = document.querySelector('[data-assestme-workbench-properties]');
            const propertiesBody = document.querySelector('.assestme-workbench-properties__body');
            const footer = document.querySelector('.assestme-workbench-footer');
            const selected = document.querySelector('.assestme-finding-row.is-selected');
            const cssLink = document.querySelector('link[href*="assestme-workspace.css"]');
            const jsScript = document.querySelector('script[src*="assestme-workspace.js"]');
            const rect = (element) => {
                const value = element.getBoundingClientRect();

                return { top: value.top, right: value.right, bottom: value.bottom, left: value.left, width: value.width, height: value.height };
            };
            const listRect = rect(list);
            const formRect = rect(form);

            return {
                viewport: { width: innerWidth, height: innerHeight },
                document: { clientWidth: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth },
                container: { ...rect(container), containerType: getComputedStyle(container).containerType },
                workspace: { ...rect(workspace), gridTemplateColumns: getComputedStyle(workspace).gridTemplateColumns },
                list: { ...listRect, display: getComputedStyle(list).display, scrollWidth: list.scrollWidth, clientWidth: list.clientWidth },
                form: {
                    ...formRect,
                    display: getComputedStyle(form).display,
                    position: getComputedStyle(form).position,
                    clientWidth: form.clientWidth,
                    scrollWidth: form.scrollWidth,
                },
                editor: {
                    ...rect(editor),
                    clientHeight: editor.clientHeight,
                    scrollHeight: editor.scrollHeight,
                    overflowY: getComputedStyle(editor).overflowY,
                },
                properties: rect(properties),
                propertiesBody: {
                    ...rect(propertiesBody),
                    clientHeight: propertiesBody.clientHeight,
                    scrollHeight: propertiesBody.scrollHeight,
                    overflowY: getComputedStyle(propertiesBody).overflowY,
                },
                header: rect(header),
                footer: rect(footer),
                verticalOverlap: Math.max(0, Math.min(listRect.bottom, formRect.bottom) - Math.max(listRect.top, formRect.top)),
                selected: selected?.getAttribute('aria-selected') ?? null,
                asset: {
                    marker: document.documentElement.dataset.assestmeWorkspaceAsset ?? null,
                    cssUrl: cssLink?.href ?? null,
                    jsUrl: jsScript?.src ?? null,
                    cssLoaded: performance.getEntriesByType('resource').some((entry) => entry.name.includes('assestme-workspace.css')),
                    jsLoaded: performance.getEntriesByType('resource').some((entry) => entry.name.includes('assestme-workspace.js')),
                },
            };
            JS)[0];
    }

    private static function resizeViewport(Browser $browser, int $width, int $height): void
    {
        (new ChromeDevToolsDriver($browser->driver))->execute('Emulation.setDeviceMetricsOverride', [
            'width' => $width,
            'height' => $height,
            'deviceScaleFactor' => 1,
            'mobile' => false,
        ]);
        $browser->pause(300);
    }

    private static function assertNoSevereBrowserLogs(Browser $browser, string $message): void
    {
        $severeLogs = array_values(array_filter(
            $browser->driver->manage()->getLog('browser'),
            static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
        ));
        Assert::assertSame([], $severeLogs, $message);
    }
}
