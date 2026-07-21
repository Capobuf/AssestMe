<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Enums\FindingStatus;
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
        File::ensureDirectoryExists($artifactRoot);

        $this->browse(function (Browser $browser) use ($administrator, $assessment, $firstFinding, $artifactRoot): void {
            $browser->loginAs($administrator)
                ->visit("/admin/assessments/{$assessment->getKey()}/workspace?finding={$firstFinding->getKey()}")
                ->waitFor('[data-assestme-finding-inspector]')
                ->waitUntil('return document.documentElement.dataset.assestmeWorkspaceAsset === "loaded"');

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
                Assert::assertSame('static', $measurements["{$width}x{$height}"]['inspector']['position']);
            }

            foreach (['2560x1440', '1920x1080', '1440x900'] as $viewport) {
                $geometry = $measurements[$viewport];
                Assert::assertSame('block', $geometry['list']['display']);
                Assert::assertSame('flex', $geometry['inspector']['display']);
                Assert::assertGreaterThanOrEqual(512, $geometry['list']['width']);
                Assert::assertGreaterThanOrEqual(448, $geometry['inspector']['width']);
                Assert::assertLessThanOrEqual($geometry['list']['clientWidth'] + 1, $geometry['list']['scrollWidth']);
                Assert::assertLessThanOrEqual($geometry['inspector']['clientWidth'] + 1, $geometry['inspector']['scrollWidth']);
                Assert::assertGreaterThanOrEqual(
                    $geometry['list']['right'] - 1,
                    $geometry['inspector']['left'],
                    "The inspector must be positioned to the right at {$viewport}.",
                );
                Assert::assertGreaterThanOrEqual(
                    min($geometry['list']['height'], $geometry['inspector']['height']) - 2,
                    $geometry['verticalOverlap'],
                    "List and inspector must overlap vertically for almost their full height at {$viewport}.",
                );
                Assert::assertLessThan($geometry['list']['bottom'], $geometry['inspector']['top'] + 1);
                Assert::assertLessThanOrEqual($geometry['viewport']['height'], $geometry['header']['bottom']);
                Assert::assertLessThanOrEqual($geometry['viewport']['height'], $geometry['footer']['bottom']);
                Assert::assertSame('auto', $geometry['body']['overflowY']);
                Assert::assertGreaterThanOrEqual($geometry['body']['clientHeight'], $geometry['body']['scrollHeight']);
                Assert::assertSame('true', $geometry['selected']);
            }

            foreach (['1280x800', '390x844'] as $viewport) {
                $geometry = $measurements[$viewport];
                Assert::assertSame('none', $geometry['list']['display']);
                Assert::assertSame('flex', $geometry['inspector']['display']);
                Assert::assertEqualsWithDelta(
                    $geometry['workspace']['width'],
                    $geometry['inspector']['width'],
                    2,
                    "The sequential inspector must use the workspace width at {$viewport}.",
                );
            }

            Assert::assertSame('loaded', $measurements['1920x1080']['asset']['marker']);
            Assert::assertTrue($measurements['1920x1080']['asset']['cssLoaded']);
            Assert::assertTrue($measurements['1920x1080']['asset']['jsLoaded']);
            Assert::assertStringContainsString('/css/app/assestme-workspace.css', $measurements['1920x1080']['asset']['cssUrl']);
            Assert::assertStringContainsString('/js/app/assestme-workspace.js', $measurements['1920x1080']['asset']['jsUrl']);

            File::put(
                "{$artifactRoot}/workspace-final-geometry.json",
                json_encode($measurements, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            );

            self::resizeViewport($browser, 1280, 800);
            $browser->click('[data-dusk="finding-close"]')->waitUntilMissing('[data-assestme-finding-inspector]');
            Assert::assertSame('block', $browser->script(
                "return getComputedStyle(document.querySelector('.assestme-findings-list')).display",
            )[0]);
            $browser->click('.assestme-finding-row:first-of-type')->waitFor('[data-assestme-finding-inspector]');
            Assert::assertSame('none', self::measureWorkspace($browser)['list']['display']);

            self::resizeViewport($browser, 1920, 1080);
            $browser->driver->takeScreenshot("{$artifactRoot}/workspace-desktop-split-1920x1080.png");
            $browser->script(<<<'JS'
                for (const label of ['Note tecniche', 'Ambito e rischio']) {
                    const heading = Array.from(document.querySelectorAll('.fi-section-header-heading'))
                        .find((element) => element.textContent.trim() === label);
                    heading?.closest('.fi-section-header')?.click();
                }
                JS);
            $browser->pause(250);
            $browser->script("document.querySelector('.assestme-finding-inspector__body').scrollTop = 240");
            $browser->driver->takeScreenshot("{$artifactRoot}/workspace-desktop-long-form-1920x1080.png");

            $browser->script(<<<'JS'
                const root = document.querySelector('.assestme-findings-workspace').closest('[wire\\:id]');
                Livewire.find(root.getAttribute('wire:id')).$set('findingData.scope_type', 'selected_assets');
                JS);
            $browser->pause(250)->click('[data-dusk="save-finding"]')
                ->waitUntil('return document.querySelector("[data-assestme-save-status]").dataset.status === "error"');
            $browser->driver->takeScreenshot("{$artifactRoot}/workspace-validation-error-1920x1080.png");

            $browser->refresh()
                ->waitFor('[data-assestme-finding-inspector]')
                ->waitUntil('return document.documentElement.dataset.assestmeWorkspaceAsset === "loaded"');
            self::resizeViewport($browser, 390, 844);
            $browser->click('[data-dusk="finding-close"]')->waitUntilMissing('[data-assestme-finding-inspector]');
            $browser->driver->takeScreenshot("{$artifactRoot}/workspace-narrow-list-390x844.png");

            $browser->type('input[type="search"]', 'Finding incompleto')
                ->waitUntil('return document.querySelectorAll(".assestme-finding-row").length === 5');
            $browser->script(<<<'JS'
                const root = document.querySelector('.assestme-findings-workspace').closest('[wire\\:id]');
                Livewire.find(root.getAttribute('wire:id')).$set('tableFilters.incomplete', { isActive: true });
                const list = document.querySelector('.assestme-findings-list');
                list.scrollTop = 80;
                JS);
            $browser->element('.assestme-finding-row:first-of-type')?->sendKeys(WebDriverKeys::ENTER);
            $browser->waitFor('[data-assestme-finding-inspector]')->assertQueryStringHas('finding');
            Assert::assertSame('none', self::measureWorkspace($browser)['list']['display']);
            $browser->driver->takeScreenshot("{$artifactRoot}/workspace-narrow-inspector-390x844.png");
            $browser->click('[data-dusk="finding-close"]')->waitUntilMissing('[data-assestme-finding-inspector]');
            $browser->assertInputValue('input[type="search"]', 'Finding incompleto')
                ->waitUntil('return document.querySelectorAll(".assestme-finding-row").length > 0');
            $filterIsStillActive = $browser->script(<<<'JS'
                const root = document.querySelector('.assestme-findings-workspace').closest('[wire\\:id]');

                return Livewire.find(root.getAttribute('wire:id')).$get('tableFilters').incomplete.isActive;
                JS)[0];
            Assert::assertTrue($filterIsStillActive);

            $browser->element('.assestme-finding-row:first-of-type')?->sendKeys(WebDriverKeys::SPACE);
            $browser->waitFor('[data-assestme-finding-inspector]')
                ->click('[data-dusk="finding-close"]')
                ->waitUntilMissing('[data-assestme-finding-inspector]');
            $browser->click('.assestme-finding-row:first-of-type')->waitFor('[data-assestme-finding-inspector]');
            Assert::assertSame('true', $browser->attribute('.assestme-finding-row.is-selected', 'aria-selected'));

            self::assertNoSevereBrowserLogs($browser, 'The responsive workspace produced severe console errors.');
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

        FindingTemplate::query()->where('is_enabled', true)->orderBy('id')->limit(5)->get()
            ->each(function (FindingTemplate $template, int $index) use ($assessment, $priorities): void {
                app(CopyTemplateToAssessment::class)($assessment, $template)->update([
                    'title' => $index === 1
                        ? 'Finding con un titolo volutamente molto lungo per verificare il limite massimo di due righe'
                        : "Finding completo {$index}",
                    'problem' => "Problema realistico sulla prima riga.\nDettaglio operativo sulla seconda riga.",
                    'technical_notes' => str_repeat("Nota tecnica articolata per la verifica del form lungo.\n", 12),
                    'priority_level_id' => $priorities[$index % $priorities->count()],
                    'status' => [FindingStatus::Open, FindingStatus::Planned, FindingStatus::InProgress][$index % 3],
                    'include_in_report' => $index % 2 === 0,
                ]);
            });

        Finding::factory()->count(5)->for($assessment)->sequence(
            fn ($sequence): array => [
                'title' => "Finding incompleto {$sequence->index}",
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
            const inspector = document.querySelector('.assestme-finding-inspector');
            const header = document.querySelector('.assestme-finding-inspector__header');
            const body = document.querySelector('.assestme-finding-inspector__body');
            const footer = document.querySelector('.assestme-finding-inspector__footer');
            const selected = document.querySelector('.assestme-finding-row.is-selected');
            const cssLink = document.querySelector('link[href*="assestme-workspace.css"]');
            const jsScript = document.querySelector('script[src*="assestme-workspace.js"]');
            const rect = (element) => {
                const value = element.getBoundingClientRect();

                return { top: value.top, right: value.right, bottom: value.bottom, left: value.left, width: value.width, height: value.height };
            };
            const listRect = rect(list);
            const inspectorRect = rect(inspector);

            return {
                viewport: { width: innerWidth, height: innerHeight },
                document: { clientWidth: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth },
                container: { ...rect(container), containerType: getComputedStyle(container).containerType },
                workspace: { ...rect(workspace), gridTemplateColumns: getComputedStyle(workspace).gridTemplateColumns },
                list: { ...listRect, display: getComputedStyle(list).display, scrollWidth: list.scrollWidth, clientWidth: list.clientWidth },
                inspector: {
                    ...inspectorRect,
                    display: getComputedStyle(inspector).display,
                    position: getComputedStyle(inspector).position,
                    clientWidth: inspector.clientWidth,
                    scrollWidth: inspector.scrollWidth,
                },
                header: rect(header),
                body: { ...rect(body), clientHeight: body.clientHeight, scrollHeight: body.scrollHeight, overflowY: getComputedStyle(body).overflowY },
                footer: rect(footer),
                verticalOverlap: Math.max(0, Math.min(listRect.bottom, inspectorRect.bottom) - Math.max(listRect.top, inspectorRect.top)),
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
