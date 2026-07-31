<?php

declare(strict_types=1);

namespace App\Services\Database\Integrity;

final class MariaDbIntegrityChecker extends MySqlCompatibleIntegrityChecker
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
