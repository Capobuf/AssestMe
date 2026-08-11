<?php

declare(strict_types=1);

use Baspa\FilamentCanary\Testing\InteractsWithCanary;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Config;

uses(InteractsWithCanary::class);

it('sweeps every Filament page for administrators and guests', function (): void {
    $this->seed(DatabaseSeeder::class);
    Config::set('filament-canary.strict_authorization', true);

    $this->canarySweep();
});
