<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Filament\Panel;

final class ResolveCanaryAdministrator
{
    public static function resolve(Panel $panel): User
    {
        return User::query()->first() ?? User::factory()->create();
    }
}
