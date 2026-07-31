<?php

declare(strict_types=1);

namespace App\Services\Database\Restore;

final class MariaDbRestorer extends MySqlCompatibleRestorer
{
    protected function expectedDriver(): string
    {
        return 'mariadb';
    }

    protected function expectedProduct(): string
    {
        return 'MariaDB';
    }
}
