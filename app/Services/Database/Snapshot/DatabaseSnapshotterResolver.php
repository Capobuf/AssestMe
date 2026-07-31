<?php

declare(strict_types=1);

namespace App\Services\Database\Snapshot;

use Illuminate\Database\Connection;
use RuntimeException;

final readonly class DatabaseSnapshotterResolver
{
    public function __construct(
        private SqliteSnapshotter $sqliteSnapshotter,
        private MySqlSnapshotter $mySqlSnapshotter,
        private MariaDbSnapshotter $mariaDbSnapshotter,
    ) {}

    public function resolve(Connection $connection): DatabaseSnapshotter
    {
        $driver = $connection->getDriverName();

        return match ($driver) {
            'sqlite' => $this->sqliteSnapshotter,
            'mysql' => $this->mySqlSnapshotter,
            'mariadb' => $this->mariaDbSnapshotter,
            default => throw new RuntimeException("Unsupported database snapshot driver: {$driver}."),
        };
    }
}
