<?php

declare(strict_types=1);

namespace App\Services\Database\Integrity;

final class MySqlIntegrityChecker extends MySqlCompatibleIntegrityChecker
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
