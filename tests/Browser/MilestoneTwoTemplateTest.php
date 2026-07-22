<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Filament\Resources\FindingTemplates\FindingTemplateResource;
use App\Models\FindingTemplate;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class MilestoneTwoTemplateTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_template_library_and_nested_solutions_render_without_console_errors(): void
    {
        $this->seed(DatabaseSeeder::class);
        $administrator = User::factory()->create();
        $template = FindingTemplate::query()->where('external_id', 'nas.notifications.missing')->firstOrFail();

        $this->browse(function (Browser $browser) use ($administrator, $template): void {
            $browser->loginAs($administrator)
                ->visit(FindingTemplateResource::getUrl('index'))
                ->waitForText('Notifiche del NAS non configurate')
                ->assertSee('Importa JSON')
                ->assertSee('Esporta JSON')
                ->visit(FindingTemplateResource::getUrl('edit', ['record' => $template]))
                ->waitForText('Soluzioni')
                ->assertSee('Problema');

            $inputValues = $browser->script('return Array.from(document.querySelectorAll("input")).map((element) => element.value);');
            Assert::assertContains('Configurare e verificare le notifiche', $inputValues[0] ?? []);

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The browser console contains severe errors.');
        });
    }
}
