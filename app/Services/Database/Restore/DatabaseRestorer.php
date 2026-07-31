<?php

declare(strict_types=1);

namespace App\Services\Database\Restore;

use Illuminate\Database\Connection;

interface DatabaseRestorer
{
    public function restore(Connection $connection, string $source): void;
}
