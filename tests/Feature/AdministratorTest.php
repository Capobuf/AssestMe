<?php

declare(strict_types=1);

use App\Models\User;

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
