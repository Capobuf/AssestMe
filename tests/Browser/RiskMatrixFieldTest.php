<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Filament\Resources\RiskProfiles\RiskProfileResource;
use App\Models\RiskProfile;
use App\Models\User;
use Database\Seeders\MilestoneOneSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class RiskMatrixFieldTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_risk_matrix_validates_and_persists_one_of_sixteen_cells(): void
    {
        $this->seed(MilestoneOneSeeder::class);
        $administrator = User::factory()->create();
        $profile = RiskProfile::query()
            ->where('code', 'default')
            ->with(['priorityLevels', 'matrixEntries'])
            ->firstOrFail();
        $entry = $profile->matrixEntries->firstOrFail();
        $replacement = $profile->priorityLevels->firstWhere('id', '!=', $entry->priority_level_id)
            ?? $profile->priorityLevels->last();
        Assert::assertNotNull($replacement);
        $selector = sprintf(
            '[data-dusk="risk-matrix-cell-%d-%d"]',
            $entry->consequence_level_id,
            $entry->likelihood_level_id,
        );

        $this->browse(function (Browser $browser) use ($administrator, $profile, $replacement, $selector): void {
            $browser->loginAs($administrator)
                ->visit(RiskProfileResource::getUrl('edit', ['record' => $profile]))
                ->waitFor('[data-dusk="risk-matrix-grid"]')
                ->waitUntil('return document.querySelectorAll(\'[data-dusk^="risk-matrix-cell-"]\').length === 16')
                ->select($selector, (string) $replacement->getKey())
                ->press('Salva')
                ->waitForText('Salvato')
                ->refresh()
                ->waitFor($selector)
                ->assertSelected($selector, (string) $replacement->getKey())
                ->select($selector, '')
                ->press('Salva')
                ->waitForText(__('assestme.risk.errors.invalid_matrix'))
                ->assertSelected($selector, '');

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The risk-matrix browser flow contains severe console errors.');
        });

        $entry->refresh();
        self::assertSame($replacement->getKey(), $entry->priority_level_id);
        self::assertSame(16, $profile->matrixEntries()->count());
    }
}
