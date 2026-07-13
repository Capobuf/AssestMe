<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\Login;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('stores the TOTP secret and exactly eight recovery codes securely', function (): void {
    $administrator = User::factory()->create();
    $this->actingAs($administrator);
    $provider = AppAuthentication::make()->recoverable()->recoveryCodeCount(8);
    $secret = $provider->generateSecret();
    $recoveryCodes = $provider->generateRecoveryCodes();

    $provider->saveSecret($administrator, $secret);
    $provider->saveRecoveryCodes($administrator, $recoveryCodes);
    $administrator->refresh();

    $rawSecret = DB::table('users')->where('id', $administrator->id)->value('app_authentication_secret');
    $rawRecoveryCodes = DB::table('users')->where('id', $administrator->id)->value('app_authentication_recovery_codes');

    expect($recoveryCodes)->toHaveCount(8)
        ->and($administrator->mfa_enabled_at)->not->toBeNull()
        ->and($administrator->getAppAuthenticationSecret())->toBe($secret)
        ->and($administrator->getAppAuthenticationRecoveryCodes())->toHaveCount(8)
        ->and($rawSecret)->not->toContain($secret)
        ->and($rawRecoveryCodes)->not->toContain($recoveryCodes[0]);
});

it('verifies TOTP and consumes a recovery code only once', function (): void {
    $administrator = User::factory()->create();
    $this->actingAs($administrator);
    $provider = AppAuthentication::make()->recoverable()->recoveryCodeCount(8);
    $secret = $provider->generateSecret();
    $recoveryCodes = $provider->generateRecoveryCodes();
    $provider->saveSecret($administrator, $secret);
    $provider->saveRecoveryCodes($administrator, $recoveryCodes);

    $currentCode = $provider->getCurrentCode($administrator, $secret);

    expect($provider->verifyCode($currentCode, $secret))->toBeTrue()
        ->and($provider->verifyRecoveryCode($recoveryCodes[0], $administrator->fresh()))->toBeTrue()
        ->and($provider->verifyRecoveryCode($recoveryCodes[0], $administrator->fresh()))->toBeFalse()
        ->and($administrator->fresh()->getAppAuthenticationRecoveryCodes())->toHaveCount(7);
});

it('resets MFA through the confirmed CLI command', function (): void {
    $administrator = User::factory()->create();
    $administrator->forceFill([
        'app_authentication_secret' => 'SECRET',
        'app_authentication_recovery_codes' => ['hash-one', 'hash-two'],
        'mfa_enabled_at' => now(),
    ])->save();

    $this->artisan('assestme:reset-mfa')
        ->expectsConfirmation(__('assestme.mfa.reset_confirmation'), 'no')
        ->assertFailed();

    expect($administrator->fresh()->app_authentication_secret)->toBe('SECRET');

    $this->artisan('assestme:reset-mfa', ['--force' => true])->assertSuccessful();

    $administrator->refresh();
    expect($administrator->app_authentication_secret)->toBeNull()
        ->and($administrator->app_authentication_recovery_codes)->toBeNull()
        ->and($administrator->mfa_enabled_at)->toBeNull();
});

it('requires a valid TOTP challenge after valid primary credentials', function (): void {
    $administrator = User::factory()->create();
    $provider = AppAuthentication::make()->recoverable()->recoveryCodeCount(8);
    $secret = $provider->generateSecret();
    $provider->saveSecret($administrator, $secret);
    $provider->saveRecoveryCodes($administrator, $provider->generateRecoveryCodes());
    $currentCode = $provider->getCurrentCode($administrator, $secret);

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $administrator->email,
            'password' => 'AssestMe-Test!2026',
            'remember' => true,
        ])
        ->call('authenticate')
        ->assertSet('userUndertakingMultiFactorAuthentication', fn (?string $value): bool => filled($value))
        ->set('data.multiFactor.app.code', $currentCode)
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($administrator);
});

it('exposes optional MFA management and enforces the profile password policy', function (): void {
    $administrator = User::factory()->create();
    $this->actingAs($administrator);

    $this->get('/admin/profile')
        ->assertOk()
        ->assertSee('Autenticazione a due fattori (2FA)');

    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => $administrator->name,
            'email' => $administrator->email,
            'password' => 'Too-short1!',
            'passwordConfirmation' => 'Too-short1!',
            'currentPassword' => 'AssestMe-Test!2026',
        ])
        ->call('save')
        ->assertHasFormErrors(['password']);
});
