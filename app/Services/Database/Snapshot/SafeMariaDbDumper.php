<?php

declare(strict_types=1);

namespace App\Services\Database\Snapshot;

use Spatie\DbDumper\Databases\MariaDb;

final class SafeMariaDbDumper extends MariaDb
{
    use StreamsSecureDatabaseDump;

    protected function credentialsFileArguments(string $credentialsPath): array
    {
        return ["--defaults-file={$credentialsPath}"];
    }

    protected function databaseClientDriver(): string
    {
        return 'mariadb';
    }
}
