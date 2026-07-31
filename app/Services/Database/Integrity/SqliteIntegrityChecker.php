<?php

declare(strict_types=1);

namespace App\Services\Database\Integrity;

use App\Data\Database\DatabaseIntegrityResult;
use Illuminate\Database\Connection;
use RuntimeException;

final class SqliteIntegrityChecker implements DatabaseIntegrityChecker
{
    public function check(Connection $connection): DatabaseIntegrityResult
    {
        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('The SQLite integrity checker received a non-SQLite connection.');
        }

        $results = [];

        foreach ($connection->select('PRAGMA integrity_check') as $row) {
            if (! is_object($row)) {
                throw new RuntimeException('SQLite integrity_check returned an invalid result row.');
            }

            $values = array_values((array) $row);
            $value = $values[0] ?? null;

            if (! is_scalar($value)) {
                throw new RuntimeException('SQLite integrity_check returned an invalid result value.');
            }

            $results[] = strtolower(trim((string) $value));
        }

        if ($results !== ['ok']) {
            $detail = implode('; ', array_filter($results));

            throw new RuntimeException('SQLite integrity check failed'.($detail === '' ? '.' : ": {$detail}"));
        }

        $foreignKeys = $this->integerPragma($connection, 'foreign_keys');
        $journalMode = strtolower($this->stringPragma($connection, 'journal_mode'));
        $busyTimeout = $this->integerPragma($connection, 'busy_timeout');
        $synchronous = $this->integerPragma($connection, 'synchronous');
        $transactionMode = strtoupper(trim((string) $connection->getConfig('transaction_mode')));

        if ($foreignKeys !== 1) {
            throw new RuntimeException('SQLite foreign key enforcement is disabled.');
        }

        if ($journalMode !== 'wal') {
            throw new RuntimeException("SQLite journal_mode must be WAL; detected {$journalMode}.");
        }

        if ($busyTimeout !== 5000) {
            throw new RuntimeException("SQLite busy_timeout must be 5000 ms; detected {$busyTimeout} ms.");
        }

        if ($synchronous !== 1) {
            throw new RuntimeException("SQLite synchronous mode must be NORMAL (1); detected {$synchronous}.");
        }

        if ($transactionMode !== 'IMMEDIATE') {
            throw new RuntimeException("SQLite transaction mode must be IMMEDIATE; configured {$transactionMode}.");
        }

        $versionRow = $connection->selectOne('SELECT sqlite_version() AS version');

        if (! is_object($versionRow)) {
            throw new RuntimeException('SQLite server version could not be determined.');
        }

        $version = trim((string) (((array) $versionRow)['version'] ?? ''));

        if ($version === '') {
            throw new RuntimeException('SQLite server version could not be determined.');
        }

        return new DatabaseIntegrityResult(
            healthy: true,
            driver: 'sqlite',
            product: 'SQLite',
            serverVersion: $version,
            database: $connection->getDatabaseName(),
            checkedTables: [],
            details: [
                'integrity_check' => 'OK',
                'foreign_keys' => true,
                'journal_mode' => 'WAL',
                'busy_timeout_ms' => $busyTimeout,
                'synchronous' => 'NORMAL',
                'transaction_mode' => $transactionMode,
            ],
        );
    }

    private function integerPragma(Connection $connection, string $name): int
    {
        $value = $connection->scalar("PRAGMA {$name}");

        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new RuntimeException("SQLite PRAGMA {$name} returned an invalid value.");
        }

        $normalized = filter_var($value, FILTER_VALIDATE_INT);

        if ($normalized === false) {
            throw new RuntimeException("SQLite PRAGMA {$name} returned a non-integer value.");
        }

        return $normalized;
    }

    private function stringPragma(Connection $connection, string $name): string
    {
        $value = $connection->scalar("PRAGMA {$name}");

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new RuntimeException("SQLite PRAGMA {$name} returned an invalid value.");
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            throw new RuntimeException("SQLite PRAGMA {$name} returned an empty value.");
        }

        return $normalized;
    }
}
