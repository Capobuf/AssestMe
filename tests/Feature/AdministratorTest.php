<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

it('enforces exactly one administrator in the application model', function (): void {
    User::factory()->create();

    expect(fn () => User::factory()->create())->toThrow(LogicException::class)
        ->and(User::query()->count())->toBe(1);
});

it('creates the administrator without printing the password', function (): void {
    $password = 'AssestMe-Admin!2026';

    $this->artisan('assestme:create-admin', [
        '--name' => 'Amministratore locale',
        '--email' => 'admin@assestme.local',
        '--password' => $password,
        '--no-interaction' => true,
    ])->assertSuccessful()->doesntExpectOutput($password);

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->firstOrFail()->email)->toBe('admin@assestme.local');
});

it('refuses implicit administrator replacement', function (): void {
    User::factory()->create();

    $this->artisan('assestme:create-admin', [
        '--name' => 'Altro',
        '--email' => 'other@assestme.local',
        '--password' => 'AssestMe-Other!2026',
        '--no-interaction' => true,
    ])->assertFailed();
});

it('creates the missing administrator from environment-backed configuration', function (): void {
    $password = 'Configured-Admin!2026';
    Config::set('assestme.development_administrator', [
        'name' => 'Amministratore configurato',
        'email' => 'configured@assestme.local',
        'password' => $password,
    ]);

    $this->artisan('assestme:create-admin', [
        '--from-env' => true,
        '--no-interaction' => true,
    ])->assertSuccessful()->doesntExpectOutput($password);

    $administrator = User::query()->sole();

    expect($administrator->name)->toBe('Amministratore configurato')
        ->and($administrator->email)->toBe('configured@assestme.local')
        ->and(Hash::check($password, $administrator->password))->toBeTrue();
});

it('generates a one-time local password when the environment password is empty', function (): void {
    Config::set('assestme.development_administrator', [
        'name' => 'Amministratore locale',
        'email' => 'generated@assestme.local',
        'password' => '',
    ]);

    $this->artisan('assestme:create-admin', [
        '--from-env' => true,
        '--no-interaction' => true,
    ])->expectsOutputToContain('Credenziali locali generate una sola volta')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->sole()->email)->toBe('generated@assestme.local');
});

it('rejects invalid environment-backed credentials without creating an administrator', function (): void {
    Config::set('assestme.development_administrator', [
        'name' => 'Amministratore non valido',
        'email' => 'not-an-email',
        'password' => 'weak',
    ]);

    $this->artisan('assestme:create-admin', [
        '--from-env' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('does not replace an existing administrator from environment-backed configuration', function (): void {
    $administrator = User::factory()->create([
        'email' => 'existing@assestme.local',
    ]);
    Config::set('assestme.development_administrator', [
        'name' => 'Sostituzione implicita',
        'email' => 'replacement@assestme.local',
        'password' => 'Replacement-Admin!2026',
    ]);

    $this->artisan('assestme:create-admin', [
        '--from-env' => true,
        '--no-interaction' => true,
    ])->assertFailed();

    expect($administrator->fresh()?->email)->toBe('existing@assestme.local')
        ->and(User::query()->count())->toBe(1);
});
