<?php

declare(strict_types=1);

namespace App\Services\Database\Snapshot;

use App\Data\Backups\BackupDatabaseData;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final readonly class SqliteSnapshotter implements DatabaseSnapshotter
{
    public function __construct(private Filesystem $files) {}

    public function createSnapshot(Connection $connection, string $stage): BackupDatabaseData
    {
        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('The SQLite snapshotter received a non-SQLite connection.');
        }

        $databasePath = $connection->getConfig('database');

        if (! is_string($databasePath)
            || $databasePath === ''
            || $databasePath === ':memory:'
            || ! $this->isAbsolutePath($databasePath)
            || is_link($databasePath)
            || ! is_file($databasePath)) {
            throw new RuntimeException('SQLite snapshots require a regular absolute file-backed database.');
        }

        $destination = $stage.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite';
        $this->files->ensureDirectoryExists(dirname($destination), 0700, true);

        if (file_exists($destination) || is_link($destination)) {
            throw new RuntimeException('The SQLite snapshot destination already exists.');
        }

        $checkpoint = $connection->selectOne('PRAGMA wal_checkpoint(TRUNCATE)');
        $checkpointColumns = is_object($checkpoint) ? array_values((array) $checkpoint) : [];

        if (! isset($checkpointColumns[0]) || (int) $checkpointColumns[0] !== 0) {
            throw new RuntimeException('SQLite WAL checkpoint could not complete without a busy writer.');
        }

        $quotedDestination = $connection->getPdo()->quote($destination);

        if ($quotedDestination === false) {
            throw new RuntimeException('The SQLite snapshot destination could not be quoted.');
        }

        $connection->unprepared("VACUUM INTO {$quotedDestination}");

        if (! is_file($destination) || filesize($destination) === 0 || ! chmod($destination, 0600)) {
            throw new RuntimeException('SQLite VACUUM did not create a protected non-empty snapshot.');
        }

        $versionRow = $connection->selectOne('SELECT sqlite_version() AS version');
        $version = is_object($versionRow)
            ? trim((string) (((array) $versionRow)['version'] ?? ''))
            : '';

        if ($version === '' || preg_match('/[\x00-\x1F\x7F]/', $version) === 1) {
            throw new RuntimeException('SQLite server version could not be determined.');
        }

        return new BackupDatabaseData(
            driver: 'sqlite',
            product: 'SQLite',
            serverVersion: $version,
            format: 'sqlite',
            path: 'database/database.sqlite',
        );
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('~^(?:[A-Za-z]:[\\/]|/|\\\\)~', $path) === 1;
    }
}
