<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Models\Assessment;
use App\Models\Category;
use App\Models\Finding;
use App\Models\PriorityLevel;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class WorkspaceLocalDraftTest extends DuskTestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_local_finding_draft_survives_reload_and_is_removed_after_confirmed_save(): void
    {
        $administrator = User::factory()->create();
        $assessment = Assessment::factory()->create();
        $finding = Finding::factory()->for($assessment)->create([
            'include_in_report' => true,
            'title' => 'Titolo server originale',
            'sort_order' => 1,
        ]);
        $solution = $finding->solutions()->create([
            'title' => 'Soluzione server originale',
            'description' => 'Descrizione server originale',
            'estimate_type' => EstimateType::RequiresQuote,
            'billing_frequency' => BillingFrequency::OneOff,
            'sort_order' => 1,
        ]);
        $finding->update([
            'category_id' => Category::query()->value('id'),
            'priority_level_id' => PriorityLevel::query()->value('id'),
            'recommended_solution_id' => $solution->getKey(),
        ]);

        $url = "/admin/assessments/{$assessment->getKey()}/workspace?finding={$finding->getKey()}";

        $this->browse(function (Browser $browser) use ($administrator, $assessment, $finding, $solution, $url): void {
            $browser->loginAs($administrator)
                ->visit($url)
                ->waitFor('[data-assestme-finding-inspector]')
                ->waitUntil(<<<'JS'
                    return document.documentElement.dataset.assestmeWorkspaceAsset === 'loaded'
                        && document.documentElement.dataset.assestmeWorkspaceDraftsAsset === 'loaded';
                    JS);

            $connectionState = $browser->script(<<<'JS'
                window.dispatchEvent(new Event('offline'));
                const event = new Event('beforeunload', { cancelable: true });
                window.dispatchEvent(event);
                window.dispatchEvent(new Event('online'));
                const status = document.querySelector('[data-assestme-save-status]');
                const savedWasRestored = status?.dataset.status === 'saved';

                status.dataset.status = 'conflict';
                status.textContent = status.dataset.conflictLabel;
                window.dispatchEvent(new Event('offline'));
                window.dispatchEvent(new Event('online'));
                const conflictWasRestored = status.dataset.status === 'conflict';
                status.dataset.status = 'saved';
                status.textContent = status.dataset.savedLabel;

                return { conflictWasRestored, pristineWarned: event.defaultPrevented, savedWasRestored };
                JS)[0];
            Assert::assertFalse(
                $connectionState['pristineWarned'] ?? true,
                'Going offline must not make a pristine form dirty.',
            );
            Assert::assertTrue($connectionState['savedWasRestored'] ?? false);
            Assert::assertTrue($connectionState['conflictWasRestored'] ?? false);

            $browser->waitFor('[data-dusk="finding-solution-title"]')
                ->type('[data-dusk="finding-solution-title"]', 'Soluzione conservata')
                ->type('[data-dusk="finding-solution-description"]', 'Descrizione della soluzione locale')
                ->select('[data-dusk="finding-estimate-type"] select', EstimateType::NotApplicable->value)
                ->click('[data-dusk="finding-property-report"]');

            $browser->script(<<<'JS'
                const input = document.querySelector('[data-dusk="finding-editor-title"]');

                if (! input) {
                    throw new Error('The Finding title input was not found.');
                }

                input.value = 'Titolo conservato in IndexedDB';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                JS);

            $browser->script(<<<'JS'
                const inspectDraft = () => {
                    const request = indexedDB.open('assestme-workspace');

                    request.onerror = () => {
                        document.documentElement.dataset.assestmeDraftTestPayload = 'error';
                    };
                    request.onsuccess = () => {
                        const database = request.result;
                        const transaction = database.transaction('drafts', 'readonly');
                        const draftsRequest = transaction.objectStore('drafts').getAll();

                        draftsRequest.onsuccess = () => {
                            const matching = draftsRequest.result.some((draft) => {
                                const solutions = Object.values(draft.payload?.solutions ?? {});

                                return draft.kind === 'finding'
                                    && draft.payload?.title === 'Titolo conservato in IndexedDB'
                                    && draft.payload?.include_in_report === false
                                    && solutions.some((solution) => (
                                        solution.title === 'Soluzione conservata'
                                        && solution.description === 'Descrizione della soluzione locale'
                                    ))
                                    && ! Object.hasOwn(draft.payload, 'evidence_uploads')
                                    && ! Object.hasOwn(draft.payload, 'evidence_original_names')
                                    && ! Object.hasOwn(draft.payload, 'existing_evidence')
                                    && ! Object.hasOwn(draft.payload, 'evidence_url')
                                    && ! Object.hasOwn(draft.payload, 'evidence_title');
                            });

                            database.close();
                            if (matching) {
                                document.documentElement.dataset.assestmeDraftTestPayload = 'matched';
                            } else {
                                window.setTimeout(inspectDraft, 100);
                            }
                        };
                    };
                };

                inspectDraft();
                JS);

            $browser->waitUntil(
                'return document.documentElement.dataset.assestmeDraftTestPayload === "matched"
                    && document.querySelector(\'[data-assestme-save-status]\')?.dataset.status === "local";',
            );

            Assert::assertSame('Titolo server originale', $finding->fresh()->title);
            Assert::assertTrue($finding->fresh()->include_in_report);
            Assert::assertSame('Soluzione server originale', $solution->fresh()->title);
            Assert::assertSame(0, $assessment->fresh()->lock_version);

            $browser->visit($url)
                ->waitFor('[data-assestme-finding-inspector]')
                ->waitUntil(<<<'JS'
                    return document.documentElement.dataset.assestmeWorkspaceDraftsAsset === 'loaded'
                        && document.querySelector('[data-dusk="local-draft-banner"]')?.hidden === false;
                    JS)
                ->click('[data-dusk="restore-local-draft"]')
                ->waitUntil(<<<'JS'
                    return document.querySelector('[data-dusk="finding-editor-title"]')?.value
                        === 'Titolo conservato in IndexedDB';
                    JS)
                ->waitUntil(<<<'JS'
                    return document.querySelector('[data-dusk="finding-solution-title"]')?.value
                        === 'Soluzione conservata';
                    JS)
                ->waitUntil(<<<'JS'
                    return document.querySelector('[data-dusk="finding-property-report"]')
                        ?.getAttribute('aria-checked') === 'false';
                    JS)
                ->assertInputValue(
                    '[data-dusk="finding-editor-title"]',
                    'Titolo conservato in IndexedDB',
                )
                ->assertInputValue(
                    '[data-dusk="finding-solution-title"]',
                    'Soluzione conservata',
                )
                ->assertInputValue(
                    '[data-dusk="finding-solution-description"]',
                    'Descrizione della soluzione locale',
                )
                ->assertSelected(
                    '[data-dusk="finding-estimate-type"] select',
                    EstimateType::NotApplicable->value,
                );

            Assert::assertSame('Titolo server originale', $finding->fresh()->title);
            Assert::assertTrue($finding->fresh()->include_in_report);
            Assert::assertSame('Soluzione server originale', $solution->fresh()->title);
            Assert::assertSame(0, $assessment->fresh()->lock_version);

            $browser->click('[data-dusk="save-finding"]')
                ->waitUntil(
                    'return document.querySelector(\'[data-assestme-save-status]\')?.dataset.status === "saved";',
                )
                ->waitUntil(<<<'JS'
                    return document.querySelector('[data-dusk="local-draft-banner"]')?.hidden !== false;
                    JS);

            Assert::assertSame('Titolo conservato in IndexedDB', $finding->fresh()->title);
            Assert::assertFalse($finding->fresh()->include_in_report);
            Assert::assertSame('Soluzione conservata', $finding->solutions()->sole()->title);
            Assert::assertSame('Descrizione della soluzione locale', $finding->solutions()->sole()->description);
            Assert::assertSame(EstimateType::NotApplicable, $finding->solutions()->sole()->estimate_type);
            Assert::assertSame(1, $assessment->fresh()->lock_version);

            $browser->visit($url)
                ->waitFor('[data-assestme-finding-inspector]')
                ->waitUntil(
                    'return document.documentElement.dataset.assestmeWorkspaceDraftsAsset === "loaded";',
                )
                ->waitUntil(
                    'return document.documentElement.dataset.assestmeWorkspaceDraftRecovery === "complete";',
                )
                ->assertInputValue(
                    '[data-dusk="finding-editor-title"]',
                    'Titolo conservato in IndexedDB',
                )
                ->assertMissing('[data-dusk="local-draft-banner"]:not([hidden])');

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));

            Assert::assertSame([], $severeLogs, 'The local-draft browser flow contains severe console errors.');
        });

        self::assertSame('Titolo conservato in IndexedDB', $finding->fresh()->title);
        self::assertSame(1, $assessment->fresh()->lock_version);
    }
}
