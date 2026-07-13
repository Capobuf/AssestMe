<?php

declare(strict_types=1);
use App\Models\User;
use Filament\Panel;

// config for Baspa/FilamentCanary
// acting_as / tenant below were proposed by `php artisan canary:install` — review them.
return [

    'panels' => [
        'only' => [],
        'except' => [],
    ],

    'exclude' => [],

    'test_guests' => true,

    'strict_authorization' => false,

    'acting_as' => [
        // admin — Could not read the access gate confidently; using a plain factory user. Adjust if this panel's pages come back as needs-auth. (confidence: low)
        'admin' => fn (Panel $panel) => User::query()->first()
            ?? User::factory()->create(),
    ],

    'tenant' => null,

];
