<?php

declare(strict_types=1);

namespace App\Services\Database;

use App\Services\Database\Integrity\DatabaseIntegrityChecker;
use App\Services\Database\Integrity\MariaDbIntegrityChecker;
use App\Services\Database\Integrity\MySqlIntegrityChecker;
use App\Services\Database\Integrity\SqliteIntegrityChecker;
use Illuminate\Database\Connection;
use RuntimeException;

final readonly class DatabaseDriverResolver
{
    public function __construct(
        private SqliteIntegrityChecker $sqliteIntegrityChecker,
        private MySqlIntegrityChecker $mySqlIntegrityChecker,
        private MariaDbIntegrityChecker $mariaDbIntegrityChecker,
    ) {}

    public function integrityChecker(Connection $connection): DatabaseIntegrityChecker
    {
        $driver = $connection->getDriverName();

        return match ($driver) {
            'sqlite' => $this->sqliteIntegrityChecker,
            'mysql' => $this->mySqlIntegrityChecker,
            'mariadb' => $this->mariaDbIntegrityChecker,
            default => throw new RuntimeException("Unsupported database driver: {$driver}."),
        };
    }
}
