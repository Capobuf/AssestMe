<?php

declare(strict_types=1);

use App\Filament\Resources\Assessments\Pages\ListAssessments;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\AssetTypes\Pages\ListAssetTypes;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Sites\Pages\ListSites;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\Client;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('provides right click actions only as duplicates of visible client actions', function (): void {
    $administrator = User::factory()->create();
    $client = Client::factory()->create();
    $this->actingAs($administrator);

    Livewire::test(ListClients::class)
        ->assertSeeHtml('data-filament-right-click-record-config')
        ->assertActionVisible(TestAction::make('edit')->table($client))
        ->assertActionVisible(TestAction::make('delete')->table($client))
        ->assertActionExists(TestAction::make('contextEdit')->table($client))
        ->assertActionExists(TestAction::make('contextDelete')->table($client));
});

it('limits generic table exports to CSV and XLSX with Italian controls', function (): void {
    $administrator = User::factory()->create();
    Client::factory()->create([
        'legal_name' => 'Cliente esportazione S.r.l.',
        'trade_name' => 'Cliente Export',
    ]);
    $this->actingAs($administrator);

    Livewire::test(ListClients::class)
        ->mountAction(TestAction::make('table-export')->table())
        ->assertMountedActionModalSee([
            __('assestme.exports.heading'),
            __('assestme.exports.fields.format'),
            __('assestme.exports.fields.filename'),
            __('assestme.exports.fields.columns'),
            'CSV',
            'XLSX',
        ])
        ->assertMountedActionModalDontSee(['PDF', 'JSON', 'XML']);
});

it('downloads a real generic XLSX export from the current client table', function (): void {
    $administrator = User::factory()->create();
    Client::factory()->create([
        'legal_name' => 'Cliente esportazione S.r.l.',
        'trade_name' => 'Cliente Export',
    ]);
    $this->actingAs($administrator);

    Livewire::test(ListClients::class)
        ->callAction(TestAction::make('table-export')->table(), [
            'format' => 'xlsx',
            'file_name' => 'clienti-test',
            'enabled_columns' => ['legal_name', 'trade_name'],
        ])
        ->assertHasNoActionErrors()
        ->assertFileDownloaded();
});

it('enhances every approved standard resource table', function (string $page): void {
    $administrator = User::factory()->create();
    $this->actingAs($administrator);

    Livewire::test($page)
        ->assertSee(__('assestme.exports.action'))
        ->assertSeeHtml('data-filament-right-click-record-config');
})->with([
    ListAssessments::class,
    ListClients::class,
    ListSites::class,
    ListAssets::class,
    ListAssetTypes::class,
    ListCategories::class,
    ListTags::class,
]);
