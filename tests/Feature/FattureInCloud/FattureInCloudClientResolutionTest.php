<?php

declare(strict_types=1);

use App\Actions\FattureInCloud\ResolveFattureInCloudClient;
use App\Enums\AssessmentStatus;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assessments\Pages\CreateFattureInCloudQuote;
use App\Models\Assessment;
use App\Models\Client;
use App\Models\User;
use App\Settings\FattureInCloudSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('reuses a live mapping for the current provider company', function (): void {
    ficReadySettingsV2();
    $client = Client::factory()->create([
        'fatture_in_cloud_company_id' => '4321',
        'fatture_in_cloud_client_id' => '91',
    ]);
    Http::fake([
        '*/c/4321/entities/clients/91*' => Http::response(['data' => ficClientV2(91)], 200),
    ]);

    $resolution = app(ResolveFattureInCloudClient::class)($client);

    expect($resolution->client?->id)->toBe('91')
        ->and($resolution->candidates)->toBe([]);
});

it('follows pages and maps only one exact normalized fiscal match', function (): void {
    ficReadySettingsV2();
    $client = Client::factory()->create(['vat_number' => 'IT 01234567890', 'tax_code' => null]);
    Http::fake(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        expect($query['q'] ?? null)->toBeIn([
            "vat_number = '01234567890'",
            "vat_number = 'IT01234567890'",
        ]);
        if (($query['q'] ?? null) === "vat_number = 'IT01234567890'") {
            return Http::response([
                'data' => [],
                'meta' => ['pagination' => ['last_page' => 1]],
            ], 200);
        }
        $page = (int) ($query['page'] ?? 1);

        return Http::response([
            'data' => $page === 1
                ? [ficClientV2(10, 'Nome simile', '99999999999')]
                : [ficClientV2(11, 'Nome diverso', '01234567890')],
            'meta' => ['pagination' => ['last_page' => 2]],
        ], 200);
    });

    $resolution = app(ResolveFattureInCloudClient::class)($client);

    expect($resolution->client?->id)->toBe('11')
        ->and($client->refresh()->fatture_in_cloud_client_id)->toBe('11');
    Http::assertSentCount(3);
});

it('requires explicit selection when multiple exact clients exist', function (): void {
    ficReadySettingsV2();
    $client = Client::factory()->create(['vat_number' => 'IT01234567890', 'tax_code' => null]);
    Http::fake([
        '*/c/4321/entities/clients*' => Http::response([
            'data' => [
                ficClientV2(10, 'Primo', '01234567890'),
                ficClientV2(11, 'Secondo', 'IT01234567890'),
            ],
            'meta' => ['pagination' => ['last_page' => 1]],
        ], 200),
    ]);

    $resolution = app(ResolveFattureInCloudClient::class)($client);

    expect($resolution->client)->toBeNull()
        ->and($resolution->candidates)->toHaveCount(2)
        ->and($client->refresh()->fatture_in_cloud_client_id)->toBeNull();
});

it('creates a provider client with supported fields and stores only its mapping', function (): void {
    ficReadySettingsV2();
    $client = Client::factory()->create([
        'legal_name' => 'Azienda Demo S.r.l.',
        'vat_number' => 'IT01234567890',
        'country' => 'IT',
    ]);
    Http::fake([
        '*/c/4321/entities/clients' => Http::response(['data' => ficClientV2(77, 'Azienda Demo S.r.l.')], 200),
    ]);

    $remote = app(ResolveFattureInCloudClient::class)->create($client);

    expect($remote->id)->toBe('77')
        ->and($client->refresh()->fatture_in_cloud_company_id)->toBe('4321')
        ->and($client->fatture_in_cloud_client_id)->toBe('77');
    Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
        && $request['data']['name'] === 'Azienda Demo S.r.l.'
        && $request['data']['country'] === 'Italia');
});

it('opens the real composer for a draft with a resolved client and rejects completed assessments', function (): void {
    ficReadySettingsV2();
    $this->actingAs(User::factory()->create());
    $draft = Assessment::factory()->create();
    $draft->client->update([
        'fatture_in_cloud_company_id' => '4321',
        'fatture_in_cloud_client_id' => '91',
    ]);
    Http::fake([
        '*/c/4321/entities/clients/91*' => Http::response(['data' => ficClientV2(91)], 200),
    ]);

    Livewire::test(CreateFattureInCloudQuote::class, ['record' => $draft->getRouteKey()])
        ->assertSet('resolvedClientId', '91')
        ->assertSee(__('assestme.fatture_in_cloud.composer.client_resolved', ['name' => 'Cliente FIC']));

    $completed = Assessment::factory()->create(['status' => AssessmentStatus::Completed]);
    $this->get(AssessmentResource::getUrl(
        'create-fatture-in-cloud-quote',
        ['record' => $completed],
    ))->assertForbidden();
});

function ficReadySettingsV2(): FattureInCloudSettings
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

    return $settings;
}

/** @return array{id: int, name: string, vat_number: string, tax_code: null} */
function ficClientV2(int $id, string $name = 'Cliente FIC', string $vatNumber = '01234567890'): array
{
    return ['id' => $id, 'name' => $name, 'vat_number' => $vatNumber, 'tax_code' => null];
}
