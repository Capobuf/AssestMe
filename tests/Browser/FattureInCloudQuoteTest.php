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
                ->assertSee('Cliente Trovato')
                ->assertSee('Crea Preventivo - Fatture in Cloud')
                ->assertSeeIn('.fi-breadcrumbs', 'Preventivo - Fatture in Cloud')
                ->assertScript("parseFloat(getComputedStyle(document.querySelector('.assestme-fic-composer__client-icon')).width) >= 44", true)
                ->assertPresent('[data-dusk="fic-findings-panel"]')
                ->assertMissing('.assestme-fic-composer__search > span')
                ->assertAttribute('[data-dusk="fic-finding-search"]', 'placeholder', 'Cerca nei Finding...')
                ->assertPresent('[data-dusk="fic-rows-panel"]')
                ->assertPresent('[data-dusk="fic-summary-panel"]')
                ->assertScript("getComputedStyle(document.querySelector('.assestme-fic-composer__workspace')).gridTemplateColumns.split(' ').length", 3)
                ->press('Aggiungi Riga da Finding')
                ->waitFor('[data-dusk="fic-row-title-0"]')
                ->assertSee('Riga da Finding')
                ->assertScript("document.querySelector('[data-dusk=\"fic-row-title-0\"]').closest('.fi-input-wrp') !== null")
                ->assertScript("getComputedStyle(document.querySelector('[data-dusk=\"fic-commercial-fields-0\"]')).gridTemplateColumns.split(' ').length", 5)
                ->check('[data-dusk="fic-row-finding-0-'.$finding->getKey().'"]')
                ->waitUntil("document.querySelector('[data-dusk=\"fic-summary-linked-findings\"]').textContent.trim() === '1 / 1'")
                ->waitUntil("document.querySelector('[data-dusk=\"fic-row-title-0\"]').value === 'Finding salvato prima del preventivo'")
                ->assertValue('[data-dusk="fic-row-description-0"]', "Problema verificabile nel preventivo\n\nRiferimenti AssestMe: ".sprintf('F-%06d', $finding->getKey()))
                ->type('[data-dusk="fic-row-title-0"]', 'Intervento browser')
                ->type('[data-dusk="fic-row-description-0"]', 'Descrizione commerciale browser')
                ->type('[data-dusk="fic-row-price-0"]', '150')
                ->waitUntil("document.querySelector('[data-dusk=\"fic-summary-net-total\"]').textContent.trim() === '€ 150,00'")
                ->press('Crea Preventivo')
                ->waitForText('Preventivo creato correttamente')
                ->assertSee('ID documento: 901')
                ->assertSeeLink('Apri in Fatture in Cloud');

            $browser->script("document.documentElement.classList.remove('dark')");
            $browser
                ->assertScript("getComputedStyle(document.querySelector('.assestme-fic-composer')).getPropertyValue('--assestme-fic-surface').trim()", '#FFFFFF')
                ->script("document.documentElement.classList.add('dark')");
            $browser
                ->assertScript("getComputedStyle(document.querySelector('.assestme-fic-composer')).getPropertyValue('--assestme-fic-surface').trim()", '#131313')
                ->resize(390, 844)
                ->pause(250)
                ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true);

            $severe = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severe, 'The FIC quote journey has severe browser console errors.');
        });

        Assert::assertSame('Finding salvato prima del preventivo', $finding->refresh()->title);
    }
}
