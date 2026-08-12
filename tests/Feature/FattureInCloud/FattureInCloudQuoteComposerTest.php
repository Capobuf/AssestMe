<?php

declare(strict_types=1);

use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Filament\Resources\Assessments\Pages\CreateFattureInCloudQuote;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\User;
use App\Settings\FattureInCloudSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

it('starts with zero automatic rows and permits repeated manual Finding assignments', function (): void {
    ficComposerReadySettings();
    $this->actingAs(User::factory()->create());
    $assessment = Assessment::factory()->create();
    $assessment->client->update([
        'fatture_in_cloud_company_id' => '4321',
        'fatture_in_cloud_client_id' => '91',
    ]);
    $finding = Finding::factory()->create(['assessment_id' => $assessment->getKey(), 'title' => 'Finding manuale']);
    Http::fake(['*/c/4321/entities/clients/91*' => Http::response(['data' => ficComposerClient(91)], 200)]);
    $tablesBefore = Schema::getTableListing();

    Livewire::test(CreateFattureInCloudQuote::class, ['record' => $assessment->getRouteKey()])
        ->assertSet('rows', [])
        ->call('addGroup')
        ->assertSeeHtml('assestme-fic-composer__row')
        ->assertSeeHtml('fi-input-wrp')
        ->call('addGroup')
        ->set('rows.0.finding_ids', [$finding->getKey()])
        ->set('rows.1.finding_ids', [$finding->getKey()])
        ->set('rows.0.title', 'Gruppo uno')
        ->set('rows.1.title', 'Gruppo due')
        ->assertSet('rows.0.finding_ids', [$finding->getKey()])
        ->assertSet('rows.1.finding_ids', [$finding->getKey()])
        ->call('addFreeRow')
        ->assertCount('rows', 3);

    expect(Schema::getTableListing())->toBe($tablesBefore)
        ->and($assessment->refresh()->getChanges())->toBe([]);
});

it('renders the refined transient workbench terminology search summary and product-first order', function (): void {
    ficComposerReadySettings();
    $this->actingAs(User::factory()->create());
    $assessment = Assessment::factory()->create();
    $assessment->client->update([
        'fatture_in_cloud_company_id' => '4321',
        'fatture_in_cloud_client_id' => '91',
    ]);
    $firstFinding = Finding::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'title' => 'Firewall perimetrale',
        'problem' => 'Regole non revisionate',
    ]);
    $secondFinding = Finding::factory()->create([
        'assessment_id' => $assessment->getKey(),
        'title' => 'Backup immutabile',
        'problem' => 'Ripristino non verificato',
    ]);
    $firstFinding->solutions()->create([
        'title' => 'Revisione configurazione',
        'description' => 'Configurazione proposta',
        'estimate_type' => EstimateType::RequiresQuote,
        'billing_frequency' => BillingFrequency::OneOff,
        'sort_order' => 1,
    ]);
    Http::fake(['*/c/4321/entities/clients/91*' => Http::response(['data' => ficComposerClient(91)], 200)]);
    $tablesBefore = Schema::getTableListing();

    $component = Livewire::test(CreateFattureInCloudQuote::class, ['record' => $assessment->getRouteKey()])
        ->assertSee('Crea Preventivo - Fatture in Cloud')
        ->assertSee('Cliente Trovato')
        ->assertSee('Finding e soluzioni')
        ->assertSee('Cerca nei Finding...')
        ->assertSee('Riepilogo')
        ->assertDontSee('Aggiungi gruppo')
        ->assertDontSee('Salva bozza')
        ->assertDontSee('Stato preventivo: Bozza')
        ->set('findingSearch', 'ripristino')
        ->assertSee('Backup immutabile')
        ->assertDontSee('Firewall perimetrale')
        ->set('findingSearch', $firstFinding->getKey() < 1_000_000 ? sprintf('F-%06d', $firstFinding->getKey()) : 'F-'.$firstFinding->getKey())
        ->assertSee('Firewall perimetrale')
        ->assertDontSee('Backup immutabile')
        ->set('findingSearch', '')
        ->call('addGroup')
        ->assertSee('Riga da Finding')
        ->call('updateRowFinding', 0, $firstFinding->getKey(), true)
        ->assertSet('rows.0.title', 'Firewall perimetrale')
        ->assertSet('rows.0.description', "Regole non revisionate\n\nRiferimenti AssestMe: ".sprintf('F-%06d', $firstFinding->getKey()))
        ->assertSee('Soluzioni / stime di riferimento')
        ->assertSee('Revisione configurazione')
        ->call('updateRowFinding', 0, $secondFinding->getKey(), true)
        ->assertSet('rows.0.title', '')
        ->assertSet('rows.0.description', 'Riferimenti AssestMe: '.implode(', ', [
            sprintf('F-%06d', $firstFinding->getKey()),
            sprintf('F-%06d', $secondFinding->getKey()),
        ]))
        ->call('updateRowFinding', 0, $secondFinding->getKey(), false)
        ->assertSet('rows.0.title', 'Firewall perimetrale')
        ->call('updateRowFinding', 0, $firstFinding->getKey(), false)
        ->assertSet('rows.0.title', '')
        ->assertSet('rows.0.description', '')
        ->call('updateRowFinding', 0, $firstFinding->getKey(), true)
        ->call('addFreeRow')
        ->assertSee('Riga Libera')
        ->set('rows.0.net_price', '100')
        ->set('rows.0.quantity', '2')
        ->set('rows.0.discount', '10')
        ->set('rows.1.net_price', '25.50')
        ->assertSee('€ 205,50');

    expect($component->instance()->rowSummary())->toBe([
        'total' => 2,
        'finding_rows' => 1,
        'free_rows' => 1,
        'linked_findings' => 1,
        'total_findings' => 2,
    ]);

    $html = $component->html();
    expect(strpos($html, 'data-dusk="fic-row-product-0"'))
        ->toBeLessThan(strpos($html, 'data-dusk="fic-row-title-0"'))
        ->and($html)->toContain('U.M.')
        ->and($component->instance()->getBreadcrumb())->toBe('Preventivo - Fatture in Cloud')
        ->and(Schema::getTableListing())->toBe($tablesBefore)
        ->and($assessment->refresh()->getChanges())->toBe([])
        ->and($secondFinding->refresh()->getChanges())->toBe([]);
});

it('uses paginated read-only products and enabled live VAT types as editable suggestions', function (): void {
    ficComposerReadySettings();
    $this->actingAs(User::factory()->create());
    $assessment = Assessment::factory()->create();
    $assessment->client->update([
        'fatture_in_cloud_company_id' => '4321',
        'fatture_in_cloud_client_id' => '91',
    ]);
    Http::fake(function (Request $request) {
        $url = $request->url();
        if (str_contains($url, '/info/vat_types')) {
            return Http::response(['data' => [
                ['id' => 22, 'value' => 22, 'description' => 'Ordinaria', 'is_disabled' => false, 'default' => true],
                ['id' => 4, 'value' => 4, 'description' => 'Disabilitata', 'is_disabled' => true, 'default' => false],
            ]], 200);
        }
        if (str_contains($url, '/entities/clients/91')) {
            return Http::response(['data' => ficComposerClient(91)], 200);
        }
        if (str_contains($url, '/products/200')) {
            return Http::response(['data' => [
                'id' => 200, 'code' => 'SERV-200', 'name' => 'Servizio gestito',
                'description' => 'Descrizione prodotto', 'measure' => 'ore', 'net_price' => 125.5,
                'default_vat' => ['id' => 22],
            ]], 200);
        }
        if (str_contains($url, '/products')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            expect($query['q'] ?? null)->toBe("(name contains 'serv' or code contains 'serv' or description contains 'serv')");
            $page = (int) ($query['page'] ?? 1);

            return Http::response([
                'data' => [[
                    'id' => $page === 1 ? 100 : 200,
                    'code' => 'SERV-'.$page,
                    'name' => 'Prodotto '.$page,
                    'measure' => $page === 1 ? 'ore' : 'giornate',
                    'net_price' => 10 * $page,
                    'default_vat' => ['id' => 22],
                ]],
                'meta' => ['pagination' => ['last_page' => 2]],
            ], 200);
        }

        return Http::response([], 500);
    });

    Livewire::test(CreateFattureInCloudQuote::class, ['record' => $assessment->getRouteKey()])
        ->assertCount('vatTypes', 1)
        ->call('addGroup')
        ->assertSet('rows.0.vat_type_id', '22')
        ->call('searchProducts', 'serv')
        ->assertCount('products', 2)
        ->assertSee('ore')
        ->assertSee('giornate')
        ->call('selectProduct', 0, '200')
        ->assertSet('rows.0.product_id', '200')
        ->assertSet('rows.0.product_code', 'SERV-200')
        ->assertSet('rows.0.net_price', '125.5')
        ->assertSet('rows.0.measure', 'ore')
        ->set('rows.0.title', 'Titolo modificato')
        ->assertSet('rows.0.title', 'Titolo modificato');

    Http::assertNotSent(static fn (Request $request): bool => $request->method() !== 'GET');
});

it('rejects a product that is no longer available without mutating the row', function (): void {
    ficComposerReadySettings();
    $this->actingAs(User::factory()->create());
    $assessment = Assessment::factory()->create();
    $assessment->client->update([
        'fatture_in_cloud_company_id' => '4321',
        'fatture_in_cloud_client_id' => '91',
    ]);
    Http::fake([
        '*/info/vat_types*' => Http::response(['data' => [[
            'id' => 22, 'value' => 22, 'description' => 'Ordinaria', 'is_disabled' => false, 'default' => true,
        ]]], 200),
        '*/entities/clients/91*' => Http::response(['data' => ficComposerClient(91)], 200),
        '*/products/999*' => Http::response([], 404),
    ]);

    Livewire::test(CreateFattureInCloudQuote::class, ['record' => $assessment->getRouteKey()])
        ->call('addGroup')
        ->call('selectProduct', 0, '999')
        ->assertSet('rows.0.product_id', null);
});

function ficComposerReadySettings(): void
{
    $settings = app(FattureInCloudSettings::class);
    $settings->client_id = 'client';
    $settings->encrypted_client_secret = 'secret';
    $settings->encrypted_access_token = 'access';
    $settings->access_token_expires_at = Carbon::now('UTC')->addHour()->toIso8601String();
    $settings->encrypted_refresh_token = 'refresh';
    $settings->company_id = '4321';
    $settings->company_name = 'Studio Demo';
    $settings->default_vat_type_id = '22';
    $settings->default_vat_type_label = '22%';
    $settings->scope_version = 1;
    $settings->save();
}

/** @return array{id: int, name: string, vat_number: string, tax_code: null} */
function ficComposerClient(int $id): array
{
    return ['id' => $id, 'name' => 'Cliente FIC', 'vat_number' => '01234567890', 'tax_code' => null];
}
