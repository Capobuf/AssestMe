<?php

declare(strict_types=1);

namespace App\Services\Database\Restore;

final class MySqlRestorer extends MySqlCompatibleRestorer
{
    protected function expectedDriver(): string
    {
        return 'mysql';
    }

    protected function expectedProduct(): string
    {
        return 'MySQL';
    }
}
