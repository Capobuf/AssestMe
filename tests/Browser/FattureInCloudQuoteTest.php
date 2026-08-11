<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Models\Assessment;
use App\Models\FindingTemplate;
use App\Models\User;
use App\Settings\FattureInCloudSettings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Carbon;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class FattureInCloudQuoteTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_dirty_workspace_reaches_one_successful_manual_quote(): void
    {
        Assert::assertTrue((bool) config('fatture-in-cloud.dusk_fake'), 'The FIC browser fake must be explicitly enabled.');
        $this->seed(DatabaseSeeder::class);
        $administrator = User::factory()->create();
        $assessment = Assessment::factory()->create(['title' => 'Assessment preventivo browser']);
        $assessment->client->update([
            'vat_number' => 'IT01234567890',
            'fatture_in_cloud_company_id' => '4321',
            'fatture_in_cloud_client_id' => '91',
        ]);
        $finding = app(CopyTemplateToAssessment::class)(
            $assessment,
            FindingTemplate::query()->where('default_scope_type', 'organization')->firstOrFail(),
        );
        $finding->update([
            'title' => 'Finding prima del salvataggio',
            'problem' => 'Problema verificabile nel preventivo',
        ]);
        $settings = app(FattureInCloudSettings::class);
        $settings->client_id = 'dusk-client';
        $settings->encrypted_client_secret = 'dusk-secret';
        $settings->encrypted_access_token = 'dusk-access';
        $settings->access_token_expires_at = Carbon::now('UTC')->addHour()->toIso8601String();
        $settings->encrypted_refresh_token = 'dusk-refresh';
        $settings->company_id = '4321';
        $settings->company_name = 'Studio browser';
        $settings->default_vat_type_id = '22';
        $settings->default_vat_type_label = '22% — Ordinaria';
        $settings->scope_version = 1;
        $settings->save();

        $this->browse(function (Browser $browser) use ($administrator, $assessment, $finding): void {
            $browser->loginAs($administrator)
                ->resize(1440, 1000)
                ->visit(AssessmentResource::getUrl('workspace', ['record' => $assessment]))
                ->waitFor('.assestme-finding-row')
                ->click('.assestme-finding-row:first-of-type')
                ->waitFor('[data-dusk="finding-editor-title"]')
                ->type('[data-dusk="finding-editor-title"]', 'Finding salvato prima del preventivo')
                ->press('Crea preventivo')
                ->waitForLocation(parse_url(AssessmentResource::getUrl('create-fatture-in-cloud-quote', [
                    'record' => $assessment,
                ]), PHP_URL_PATH))
                ->waitFor('[data-dusk="fic-client-resolved"]')
                ->assertSee('Cliente browser FIC')
                ->press('Aggiungi gruppo')
                ->waitFor('[data-dusk="fic-row-title-0"]')
                ->assertScript("getComputedStyle(document.querySelector('.assestme-fic-composer__workspace')).display", 'grid')
                ->assertScript("document.querySelector('[data-dusk=\"fic-row-title-0\"]').closest('.fi-input-wrp') !== null")
                ->check('[data-dusk="fic-row-finding-0-'.$finding->getKey().'"]')
                ->type('[data-dusk="fic-row-title-0"]', 'Intervento browser')
                ->type('[data-dusk="fic-row-description-0"]', 'Descrizione commerciale browser')
                ->type('[data-dusk="fic-row-price-0"]', '150')
                ->press('Crea preventivo in Fatture in Cloud')
                ->waitForText('Preventivo creato correttamente')
                ->assertSee('ID documento: 901')
                ->assertSeeLink('Apri preventivo');

            $severe = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severe, 'The FIC quote journey has severe browser console errors.');
        });

        Assert::assertSame('Finding salvato prima del preventivo', $finding->refresh()->title);
    }
}
