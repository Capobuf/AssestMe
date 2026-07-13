<?php

declare(strict_types=1);

use App\Actions\Assets\SaveAsset;
use App\Actions\AssetTypes\SaveAssetType;
use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Client;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\MilestoneOneSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('seeds exactly the approved asset types in order on a fresh domain', function (): void {
    $this->seed(MilestoneOneSeeder::class);

    expect(AssetType::query()->orderBy('sort_order')->pluck('name')->all())->toBe([
        'NAS',
        'Server',
        'Firewall',
        'Router',
        'Switch',
        'Access Point',
        'PBX',
        'Telefono',
        'Postazione di Lavoro',
        'Stampante',
        'UPS',
        'Armadio Rack',
        'Altro',
    ]);
});

it('reserves asset type slugs by assigning deterministic suffixes', function (): void {
    $first = app(SaveAssetType::class)->handle(null, [
        'name' => 'Server',
        'sort_order' => 1,
        'is_enabled' => true,
    ]);
    $second = app(SaveAssetType::class)->handle(null, [
        'name' => 'Server',
        'sort_order' => 2,
        'is_enabled' => true,
    ]);

    expect($first->slug)->toBe('server')
        ->and($second->slug)->toBe('server-2');
});

it('normalizes MAC addresses and preserves client site and type relations', function (): void {
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $assetType = AssetType::factory()->create();

    $asset = app(SaveAsset::class)->handle(null, [
        'client_id' => $client->id,
        'site_id' => $site->id,
        'asset_type_id' => $assetType->id,
        'name' => null,
        'model' => 'RS1221+',
        'mac_address' => 'aa-bb-cc-dd-ee-ff',
    ]);

    expect($asset->mac_address)->toBe('AA:BB:CC:DD:EE:FF')
        ->and($asset->client->is($client))->toBeTrue()
        ->and($asset->site?->is($site))->toBeTrue()
        ->and($asset->assetType->is($assetType))->toBeTrue();
});

it('rejects missing identifiers invalid MAC and a site from another client', function (): void {
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $otherSite = Site::factory()->for($otherClient)->create();
    $assetType = AssetType::factory()->create();

    expect(fn () => app(SaveAsset::class)->handle(null, [
        'client_id' => $client->id,
        'site_id' => $otherSite->id,
        'asset_type_id' => $assetType->id,
    ]))->toThrow(ValidationException::class);

    expect(fn () => app(SaveAsset::class)->handle(null, [
        'client_id' => $client->id,
        'asset_type_id' => $assetType->id,
        'name' => 'Firewall',
        'mac_address' => 'not-a-mac',
    ]))->toThrow(ValidationException::class);
});

it('blocks new disabled types while preserving an existing asset type', function (): void {
    $client = Client::factory()->create();
    $assetType = AssetType::factory()->create(['is_enabled' => false]);

    expect(fn () => app(SaveAsset::class)->handle(null, [
        'client_id' => $client->id,
        'asset_type_id' => $assetType->id,
        'name' => 'Asset nuovo',
    ]))->toThrow(ValidationException::class);

    $asset = Asset::factory()->for($client)->for($assetType, 'assetType')->create();
    $updated = app(SaveAsset::class)->handle($asset, [
        'client_id' => $client->id,
        'asset_type_id' => $assetType->id,
        'name' => 'Asset esistente aggiornato',
    ]);

    expect($updated->name)->toBe('Asset esistente aggiornato');
});

it('creates an asset through its Filament resource', function (): void {
    $administrator = User::factory()->create();
    $client = Client::factory()->create();
    $site = Site::factory()->for($client)->create();
    $assetType = AssetType::factory()->create();
    $this->actingAs($administrator);

    Livewire::test(CreateAsset::class)
        ->fillForm([
            'client_id' => $client->id,
            'site_id' => $site->id,
            'asset_type_id' => $assetType->id,
            'hostname' => 'server-01.example.test',
            'mac_address' => '001122334455',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Asset::query()->where('hostname', 'server-01.example.test')->firstOrFail()->mac_address)
        ->toBe('00:11:22:33:44:55');
});

it('requires authentication for asset and asset type resources', function (): void {
    $this->get('/admin/assets')->assertRedirect('/admin/login');
    $this->get('/admin/asset-types')->assertRedirect('/admin/login');
});
