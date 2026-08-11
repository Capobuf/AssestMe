<?php

declare(strict_types=1);

use App\Filament\Pages\FattureInCloudSettingsPage;
use App\Models\User;
use App\Services\FattureInCloud\FattureInCloudTokenService;
use App\Settings\FattureInCloudSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

it('keeps Fatture in Cloud optional and encrypts every secret setting', function (): void {
    $settings = app(FattureInCloudSettings::class);

    expect($settings->client_id)->toBeNull()
        ->and($settings->encrypted_client_secret)->toBeNull()
        ->and($settings->encrypted_access_token)->toBeNull()
        ->and($settings->encrypted_refresh_token)->toBeNull()
        ->and($settings->company_id)->toBeNull()
        ->and($settings->default_vat_type_id)->toBeNull();

    $settings->client_id = 'fic-client-id';
    $settings->encrypted_client_secret = 'fic-client-secret';
    $settings->encrypted_access_token = 'fic-access-token';
    $settings->encrypted_refresh_token = 'fic-refresh-token';
    $settings->access_token_expires_at = '2026-08-12T10:00:00+00:00';
    $settings->save();

    $payloads = DB::table('settings')
        ->where('group', 'fatture_in_cloud')
        ->whereIn('name', [
            'encrypted_client_secret',
            'encrypted_access_token',
            'encrypted_refresh_token',
        ])
        ->pluck('payload');

    expect($payloads)->toHaveCount(3);
    foreach ($payloads as $payload) {
        expect((string) $payload)
            ->not->toContain('fic-client-secret')
            ->not->toContain('fic-access-token')
            ->not->toContain('fic-refresh-token');
    }
});

it('requires authentication for settings and both OAuth routes', function (): void {
    $this->get(FattureInCloudSettingsPage::getUrl())->assertRedirect('/admin/login');
    $this->get(route('fatture-in-cloud.oauth.redirect'))->assertRedirect('/admin/login');
    $this->get(route('fatture-in-cloud.oauth.callback'))->assertRedirect('/admin/login');
});

it('renders a callback copy fallback for insecure HTTP origins', function (): void {
    configuredFattureInCloudApplication();
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(FattureInCloudSettingsPage::class);

    expect($component->html())
        ->toContain("document.execCommand('copy')")
        ->toContain(__('assestme.fatture_in_cloud.notifications.callback_copy_failed'));
});

it('starts stateful authorization with only the required scopes', function (): void {
    configuredFattureInCloudApplication();
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('fatture-in-cloud.oauth.redirect'));

    $response->assertRedirect();
    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect(strtok($location, '?'))->toBe('https://api-v2.fattureincloud.it/oauth/authorize')
        ->and($query['response_type'] ?? null)->toBe('code')
        ->and($query['client_id'] ?? null)->toBe('fic-client-id')
        ->and($query['redirect_uri'] ?? null)->toBe(route('fatture-in-cloud.oauth.callback'))
        ->and($query['scope'] ?? null)->toBe('entity.clients:r entity.clients:a products:r settings:r issued_documents.quotes:r issued_documents.quotes:a')
        ->and($query['state'] ?? null)->toBeString()->not->toBeEmpty()
        ->and(session('fatture_in_cloud.oauth_state'))->toBe($query['state']);
});

it('accepts exactly one company and stores its live provider default VAT', function (): void {
    configuredFattureInCloudApplication();
    $this->actingAs(User::factory()->create());
    Http::fake([
        'api-v2.fattureincloud.it/oauth/token' => Http::response(tokenResponse(), 200),
        'api-v2.fattureincloud.it/user/companies' => Http::response([
            'data' => ['companies' => [['id' => 4321, 'name' => 'Studio Demo']]],
        ], 200),
        'api-v2.fattureincloud.it/c/4321/info/vat_types*' => Http::response([
            'data' => [
                ['id' => 22, 'value' => 22, 'description' => 'Ordinaria', 'is_disabled' => false, 'default' => true],
                ['id' => 4, 'value' => 4, 'description' => 'Ridotta', 'is_disabled' => false, 'default' => false],
                ['id' => 99, 'value' => null, 'description' => 'Fuori campo', 'is_disabled' => false, 'default' => false],
                ['id' => null, 'value' => 0, 'description' => 'Non indirizzabile', 'is_disabled' => false, 'default' => false],
                null,
            ],
        ], 200),
    ]);

    $this->withSession(['fatture_in_cloud.oauth_state' => 'expected-state'])
        ->get(route('fatture-in-cloud.oauth.callback', ['state' => 'expected-state', 'code' => 'c/code']))
        ->assertRedirect(FattureInCloudSettingsPage::getUrl(isAbsolute: false))
        ->assertSessionHas('filament.notifications');

    $settings = app(FattureInCloudSettings::class);
    expect($settings->company_id)->toBe('4321')
        ->and($settings->company_name)->toBe('Studio Demo')
        ->and($settings->default_vat_type_id)->toBe('22')
        ->and($settings->default_vat_type_label)->toContain('22%')
        ->and($settings->scope_version)->toBe(1)
        ->and($settings->encrypted_refresh_token)->toBe('r/refresh')
        ->and($settings->encrypted_access_token)->toBe('a/access');
});

it('rejects invalid company cardinality without adopting any company', function (array $companies): void {
    configuredFattureInCloudApplication();
    $this->actingAs(User::factory()->create());
    Http::fake([
        'api-v2.fattureincloud.it/oauth/token' => Http::response(tokenResponse(), 200),
        'api-v2.fattureincloud.it/user/companies' => Http::response(['data' => ['companies' => $companies]], 200),
    ]);

    $this->withSession(['fatture_in_cloud.oauth_state' => 'expected-state'])
        ->get(route('fatture-in-cloud.oauth.callback', ['state' => 'expected-state', 'code' => 'c/code']))
        ->assertRedirect(FattureInCloudSettingsPage::getUrl(isAbsolute: false))
        ->assertSessionHas('filament.notifications');

    $settings = app(FattureInCloudSettings::class);
    expect($settings->company_id)->toBeNull()
        ->and($settings->encrypted_access_token)->toBeNull()
        ->and($settings->encrypted_refresh_token)->toBeNull();
})->with([
    'zero companies' => [[]],
    'multiple companies' => [[
        ['id' => 1, 'name' => 'Uno'],
        ['id' => 2, 'name' => 'Due'],
    ]],
]);

it('rejects a missing or mismatched OAuth state without exchanging the code', function (): void {
    configuredFattureInCloudApplication();
    $this->actingAs(User::factory()->create());
    Http::fake();
    Log::spy();

    $this->withSession(['fatture_in_cloud.oauth_state' => 'expected-state'])
        ->get(route('fatture-in-cloud.oauth.callback', [
            'state' => 'wrong-state-never-log',
            'code' => 'code-never-log',
        ]))
        ->assertRedirect(FattureInCloudSettingsPage::getUrl(isAbsolute: false));

    Http::assertNothingSent();
    Log::shouldHaveReceived('warning')
        ->withArgs(static function (string $message, array $context): bool {
            $serialized = json_encode([$message, $context], JSON_THROW_ON_ERROR);

            return $message === 'Fatture in Cloud OAuth callback rejected.'
                && $context === ['reason' => 'state_mismatch']
                && ! str_contains($serialized, 'wrong-state-never-log')
                && ! str_contains($serialized, 'code-never-log');
        })
        ->once();
});

it('ignores a repeated provider callback after the connection was persisted', function (): void {
    connectedFattureInCloudSettings();
    $this->actingAs(User::factory()->create());
    Http::fake();
    Log::spy();

    $this->get(route('fatture-in-cloud.oauth.callback', [
        'state' => 'already-consumed-state',
        'code' => 'already-consumed-code',
    ]))->assertRedirect(FattureInCloudSettingsPage::getUrl(isAbsolute: false));

    Http::assertNothingSent();
    Log::shouldHaveReceived('notice')
        ->with('Fatture in Cloud OAuth callback replay was ignored after connection.')
        ->once();
});

it('logs a sanitized OAuth stage and provider status when token exchange fails', function (): void {
    configuredFattureInCloudApplication();
    $this->actingAs(User::factory()->create());
    Http::fake([
        'api-v2.fattureincloud.it/oauth/token' => Http::response([
            'error' => 'provider-body-never-log',
        ], 422),
    ]);
    Log::spy();

    $this->withSession(['fatture_in_cloud.oauth_state' => 'expected-state'])
        ->get(route('fatture-in-cloud.oauth.callback', [
            'state' => 'expected-state',
            'code' => 'authorization-code-never-log',
        ]))
        ->assertRedirect(FattureInCloudSettingsPage::getUrl(isAbsolute: false))
        ->assertSessionHas('filament.notifications');

    Log::shouldHaveReceived('warning')
        ->with('Fatture in Cloud token request was rejected.', [
            'grant_type' => 'authorization_code',
            'status' => 422,
        ])
        ->once();
    Log::shouldHaveReceived('warning')
        ->withArgs(static function (string $message, array $context): bool {
            $serialized = json_encode([$message, $context], JSON_THROW_ON_ERROR);

            return $message === 'Fatture in Cloud OAuth callback failed.'
                && $context['stage'] === 'token_exchange'
                && $context['reason'] === 'provider_unavailable'
                && is_string($context['exception'])
                && ! str_contains($serialized, 'authorization-code-never-log')
                && ! str_contains($serialized, 'provider-body-never-log');
        })
        ->once();
});

it('refreshes an expired access token and persists the rotated encrypted refresh token', function (): void {
    $settings = configuredFattureInCloudApplication();
    $settings->encrypted_access_token = 'a/expired';
    $settings->encrypted_refresh_token = 'r/old';
    $settings->access_token_expires_at = Carbon::now('UTC')->subMinute()->toIso8601String();
    $settings->save();
    Http::fake([
        'api-v2.fattureincloud.it/oauth/token' => Http::response([
            'token_type' => 'bearer',
            'access_token' => 'a/new',
            'refresh_token' => 'r/rotated',
            'expires_in' => 86400,
        ], 200),
    ]);

    expect(app(FattureInCloudTokenService::class)->accessToken())->toBe('a/new')
        ->and($settings->refresh()->encrypted_refresh_token)->toBe('r/rotated')
        ->and($settings->encrypted_access_token)->toBe('a/new');

    $payload = (string) DB::table('settings')
        ->where('group', 'fatture_in_cloud')
        ->where('name', 'encrypted_refresh_token')
        ->value('payload');
    expect($payload)->not->toContain('r/rotated');
});

it('retains a write-only secret and clears connection when effective credentials change', function (): void {
    $settings = connectedFattureInCloudSettings();
    $this->actingAs(User::factory()->create());
    Http::fake([
        'api-v2.fattureincloud.it/c/4321/info/vat_types*' => Http::response([
            'data' => [
                ['id' => 22, 'value' => 22, 'description' => 'Ordinaria', 'is_disabled' => false, 'default' => true],
            ],
        ], 200),
    ]);

    Livewire::test(FattureInCloudSettingsPage::class)
        ->assertSet('data.client_secret', null)
        ->set('data.client_secret', '')
        ->call('save')
        ->assertHasNoErrors();
    expect($settings->refresh()->encrypted_client_secret)->toBe('fic-client-secret')
        ->and($settings->company_id)->toBe('4321');

    Livewire::test(FattureInCloudSettingsPage::class)
        ->set('data.client_id', 'changed-client-id')
        ->call('save')
        ->assertHasNoErrors();

    $settings->refresh();
    expect($settings->encrypted_client_secret)->toBe('fic-client-secret')
        ->and($settings->encrypted_access_token)->toBeNull()
        ->and($settings->encrypted_refresh_token)->toBeNull()
        ->and($settings->company_id)->toBeNull()
        ->and($settings->default_vat_type_id)->toBeNull();
});

it('stores a selected live VAT default and disconnects locally without an invented revoke request', function (): void {
    $settings = connectedFattureInCloudSettings();
    $this->actingAs(User::factory()->create());
    Http::fake([
        'api-v2.fattureincloud.it/c/4321/info/vat_types*' => Http::response([
            'data' => [
                ['id' => 22, 'value' => 22, 'description' => 'Ordinaria', 'is_disabled' => false, 'default' => true],
                ['id' => 10, 'value' => 10, 'description' => 'Ridotta', 'is_disabled' => false, 'default' => false],
            ],
        ], 200),
    ]);

    Livewire::test(FattureInCloudSettingsPage::class)
        ->set('data.default_vat_type_id', '10')
        ->call('save')
        ->assertHasNoErrors()
        ->call('disconnect')
        ->assertHasNoErrors();

    $settings->refresh();
    expect($settings->encrypted_access_token)->toBeNull()
        ->and($settings->encrypted_refresh_token)->toBeNull()
        ->and($settings->company_id)->toBeNull()
        ->and($settings->company_name)->toBeNull()
        ->and($settings->default_vat_type_id)->toBeNull();

    Http::assertNotSent(static fn (Request $request): bool => ! str_contains($request->url(), '/info/vat_types'));
});

/** @return array{token_type: string, access_token: string, refresh_token: string, expires_in: int} */
function tokenResponse(): array
{
    return [
        'token_type' => 'bearer',
        'access_token' => 'a/access',
        'refresh_token' => 'r/refresh',
        'expires_in' => 86400,
    ];
}

function configuredFattureInCloudApplication(): FattureInCloudSettings
{
    $settings = app(FattureInCloudSettings::class);
    $settings->client_id = 'fic-client-id';
    $settings->encrypted_client_secret = 'fic-client-secret';
    $settings->save();

    return $settings;
}

function connectedFattureInCloudSettings(): FattureInCloudSettings
{
    $settings = configuredFattureInCloudApplication();
    $settings->encrypted_access_token = 'a/access';
    $settings->access_token_expires_at = Carbon::now('UTC')->addHour()->toIso8601String();
    $settings->encrypted_refresh_token = 'r/refresh';
    $settings->company_id = '4321';
    $settings->company_name = 'Studio Demo';
    $settings->default_vat_type_id = '22';
    $settings->default_vat_type_label = '22% — Ordinaria';
    $settings->scope_version = 1;
    $settings->save();

    return $settings;
}
