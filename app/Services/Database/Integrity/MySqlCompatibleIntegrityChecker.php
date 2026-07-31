<?php

declare(strict_types=1);

namespace App\Services\Database\Integrity;

use App\Data\Database\DatabaseIntegrityResult;
use Illuminate\Database\Connection;
use RuntimeException;

abstract class MySqlCompatibleIntegrityChecker implements DatabaseIntegrityChecker
{
    final public function check(Connection $connection): DatabaseIntegrityResult
    {
        $driver = $connection->getDriverName();

        if ($driver !== $this->expectedDriver()) {
            throw new RuntimeException("The {$this->expectedProduct()} integrity checker received a {$driver} connection.");
        }

        $identity = $this->rowValues(
            $connection->selectOne('SELECT VERSION() AS version, @@version_comment AS version_comment'),
            'database server identity',
        );
        $version = $this->requiredValue($identity, 'version', 'database server version');
        $versionComment = $this->requiredValue($identity, 'version_comment', 'database server version comment');
        $product = $this->detectProduct($version, $versionComment);

        if ($product !== $this->expectedProduct()) {
            throw new RuntimeException(
                "Database product mismatch: configured {$this->expectedProduct()}, detected {$product} {$version}.",
            );
        }

        $database = trim($connection->getDatabaseName());

        if ($database === '') {
            throw new RuntimeException('The selected database name is empty.');
        }

        $schema = $this->rowValues(
            $connection->selectOne(
                'SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation '
                .'FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
                [$database],
            ),
            'database schema metadata',
        );
        $charset = strtolower($this->requiredValue($schema, 'charset', 'database character set'));
        $collation = strtolower($this->requiredValue($schema, 'collation', 'database collation'));

        if ($charset !== 'utf8mb4') {
            throw new RuntimeException("Database character set must be utf8mb4; detected {$charset}.");
        }

        if (! str_starts_with($collation, 'utf8mb4_')) {
            throw new RuntimeException("Database collation must use utf8mb4; detected {$collation}.");
        }

        $tableRows = $connection->select(
            'SELECT TABLE_NAME AS table_name, ENGINE AS engine, TABLE_COLLATION AS table_collation '
            .'FROM information_schema.TABLES '
            ."WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
            [$database],
        );

        if ($tableRows === []) {
            throw new RuntimeException('Database integrity cannot be checked because the selected schema has no application tables.');
        }

        $checkedTables = [];

        foreach ($tableRows as $tableRow) {
            $table = $this->rowValues($tableRow, 'application table metadata');
            $tableName = $this->requiredValue($table, 'table_name', 'application table name');
            $engine = $this->requiredValue($table, 'engine', "storage engine for table {$tableName}");
            $tableCollation = strtolower($this->requiredValue(
                $table,
                'table_collation',
                "collation for table {$tableName}",
            ));

            if (preg_match('/\A[A-Za-z0-9_]+\z/D', $tableName) !== 1) {
                throw new RuntimeException("Application table {$tableName} has an unsupported identifier.");
            }

            if (strcasecmp($engine, 'InnoDB') !== 0) {
                throw new RuntimeException("Application table {$tableName} must use InnoDB; detected {$engine}.");
            }

            if (! str_starts_with($tableCollation, 'utf8mb4_')) {
                throw new RuntimeException(
                    "Application table {$tableName} must use an utf8mb4 collation; detected {$tableCollation}.",
                );
            }

            $checkRows = $connection->select(
                'CHECK TABLE '.$connection->getQueryGrammar()->wrap($tableName),
            );

            if ($checkRows === []) {
                throw new RuntimeException("CHECK TABLE returned no result for {$tableName}.");
            }

            foreach ($checkRows as $checkRow) {
                $check = $this->rowValues($checkRow, "CHECK TABLE result for {$tableName}");
                $messageType = strtolower($this->requiredValue($check, 'msg_type', 'CHECK TABLE message type'));
                $message = trim($this->requiredValue($check, 'msg_text', 'CHECK TABLE message'));

                if ($messageType !== 'status' || strcasecmp($message, 'OK') !== 0) {
                    throw new RuntimeException(
                        "CHECK TABLE failed for {$tableName}: {$messageType} {$message}.",
                    );
                }
            }

            $checkedTables[] = $tableName;
        }

        return new DatabaseIntegrityResult(
            healthy: true,
            driver: $driver,
            product: $product,
            serverVersion: $version,
            database: $database,
            checkedTables: $checkedTables,
            details: [
                'database_charset' => $charset,
                'database_collation' => $collation,
                'table_count' => count($checkedTables),
                'table_engine' => 'InnoDB',
                'table_collation' => 'utf8mb4',
                'check_table' => 'OK',
            ],
        );
    }

    abstract protected function expectedDriver(): string;

    abstract protected function expectedProduct(): string;

    /**
     * @return array<string, string>
     */
    private function rowValues(?object $row, string $description): array
    {
        if ($row === null) {
            throw new RuntimeException("The {$description} query returned no result.");
        }

        $values = [];

        foreach ((array) $row as $key => $value) {
            if (! is_string($key) || (! is_string($value) && ! is_int($value) && ! is_float($value))) {
                throw new RuntimeException("The {$description} query returned an invalid value.");
            }

            $values[strtolower($key)] = trim((string) $value);
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function requiredValue(array $values, string $key, string $description): string
    {
        $value = $values[$key] ?? '';

        if ($value === '') {
            throw new RuntimeException("The {$description} could not be determined.");
        }

        return $value;
    }

    private function detectProduct(string $version, string $versionComment): string
    {
        $identity = strtolower("{$version} {$versionComment}");

        if (str_contains($identity, 'mariadb')) {
            return 'MariaDB';
        }

        if (str_contains($identity, 'mysql')) {
            return 'MySQL';
        }

        throw new RuntimeException(
            "Database product could not be recognized as MySQL or MariaDB; detected version {$version}.",
        );
    }
}
