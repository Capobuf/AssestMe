<?php

declare(strict_types=1);

use App\Actions\Clients\FindDuplicateClientIdentifiers;
use App\Actions\Clients\SaveClient;
use App\Actions\Sites\SaveSite;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Sites\Pages\CreateSite;
use App\Models\Client;
use App\Models\Site;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('normalizes client identifiers and accepts only HTTP or HTTPS websites', function (): void {
    $client = app(SaveClient::class)->handle(null, [
        'legal_name' => 'Impresa Esempio S.r.l.',
        'vat_number' => ' it 123 456 78901 ',
        'tax_code' => ' rss mra 80a01 h501u ',
        'website' => 'https://example.test',
        'country' => 'it',
    ]);

    expect($client->vat_number)->toBe('IT12345678901')
        ->and($client->tax_code)->toBe('RSSMRA80A01H501U')
        ->and($client->country)->toBe('IT');

    expect(fn () => app(SaveClient::class)->handle(null, [
        'legal_name' => 'Sito non valido',
        'website' => 'ftp://example.test/file',
        'country' => 'IT',
    ]))->toThrow(ValidationException::class);
});

it('reports duplicate fiscal identifiers without rejecting the client', function (): void {
    Client::factory()->create([
        'vat_number' => 'IT12345678901',
        'tax_code' => 'RSSMRA80A01H501U',
    ]);

    $duplicate = app(SaveClient::class)->handle(null, [
        'legal_name' => 'Seconda Impresa S.r.l.',
        'vat_number' => 'it 12345678901',
        'tax_code' => 'rssmra80a01h501u',
        'country' => 'IT',
    ]);

    expect(app(FindDuplicateClientIdentifiers::class)->handle($duplicate))
        ->toBe(['vat_number', 'tax_code'])
        ->and(Client::query()->count())->toBe(2);
});

it('creates a client and its site through the application actions', function (): void {
    $client = app(SaveClient::class)->handle(null, [
        'legal_name' => 'Cliente con sede S.p.A.',
        'country' => 'IT',
    ]);

    $site = app(SaveSite::class)->handle(null, [
        'client_id' => $client->id,
        'name' => 'Sede operativa',
        'city' => 'Milano',
        'country' => 'it',
    ]);

    expect($site->country)->toBe('IT')
        ->and($client->sites()->firstOrFail()->is($site))->toBeTrue()
        ->and($site->client->is($client))->toBeTrue();
});

it('rejects a site for an archived client', function (): void {
    $client = Client::factory()->create();
    $client->delete();

    expect(fn () => app(SaveSite::class)->handle(null, [
        'client_id' => $client->id,
        'name' => 'Sede non consentita',
        'country' => 'IT',
    ]))->toThrow(ValidationException::class);
});

it('creates clients and sites through their Filament resources', function (): void {
    $administrator = User::factory()->create();
    $this->actingAs($administrator);

    Livewire::test(CreateClient::class)
        ->fillForm([
            'legal_name' => 'Cliente Filament S.r.l.',
            'vat_number' => 'it 111 222 33344',
            'country' => 'it',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $client = Client::query()->where('legal_name', 'Cliente Filament S.r.l.')->firstOrFail();

    Livewire::test(CreateSite::class)
        ->fillForm([
            'client_id' => $client->id,
            'name' => 'Sede Filament',
            'city' => 'Roma',
            'country' => 'it',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($client->vat_number)->toBe('IT11122233344')
        ->and(Site::query()->whereBelongsTo($client)->where('name', 'Sede Filament')->exists())->toBeTrue();
});

it('requires authentication for client and site resource pages', function (): void {
    $this->get('/admin/clients')->assertRedirect('/admin/login');
    $this->get('/admin/sites')->assertRedirect('/admin/login');
});
