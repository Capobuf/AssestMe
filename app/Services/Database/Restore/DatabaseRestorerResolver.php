<?php

declare(strict_types=1);

namespace App\Services\Database\Restore;

use Illuminate\Database\Connection;
use RuntimeException;

class DatabaseRestorerResolver
{
    public function __construct(
        private readonly SqliteRestorer $sqliteRestorer,
        private readonly MySqlRestorer $mySqlRestorer,
        private readonly MariaDbRestorer $mariaDbRestorer,
    ) {}

    public function resolve(Connection $connection): DatabaseRestorer
    {
        $driver = $connection->getDriverName();

        return match ($driver) {
            'sqlite' => $this->sqliteRestorer,
            'mysql' => $this->mySqlRestorer,
            'mariadb' => $this->mariaDbRestorer,
            default => throw new RuntimeException("Unsupported database restore driver: {$driver}."),
        };
    }
}
