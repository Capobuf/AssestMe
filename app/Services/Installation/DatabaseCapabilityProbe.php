<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Data\Installation\DatabaseCapabilityProbeResultData;
use App\Data\Installation\DatabaseConfigurationData;
use App\Data\Installation\DatabaseServerIdentityData;
use App\Enums\SupportedDatabaseDriver;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class DatabaseCapabilityProbe
{
    private const CONNECTION = 'install_probe';

    public function __construct(private DatabaseServerProductRecognizer $productRecognizer) {}

    public function probe(DatabaseConfigurationData $configuration): DatabaseCapabilityProbeResultData
    {
        $extension = $configuration->driver === SupportedDatabaseDriver::Sqlite
            ? 'pdo_sqlite'
            : 'pdo_mysql';

        if (! extension_loaded($extension)) {
            throw new DatabaseCapabilityProbeException(
                "L'estensione PHP {$extension} è obbligatoria per usare {$configuration->driver->label()}.",
            );
        }

        $this->assertSafeSqlitePath($configuration);
        $this->createSqliteFileWhenMissing($configuration);

        $suffix = bin2hex(random_bytes(6));
        $parentTable = "assestme_probe_parent_{$suffix}";
        $childTable = "assestme_probe_child_{$suffix}";
        $connection = null;
        $result = null;
        $failure = null;
        $phase = 'connection and authentication';
        $schemaCreationStarted = false;

        try {
            $connection = $this->connect($configuration);
            $connection->getPdo();

            $phase = 'server identity';
            $identity = $this->serverIdentity($connection, $configuration->driver);
            [$charset, $collation] = $this->connectionEncoding($connection, $configuration);

            $phase = 'schema creation';
            $schemaCreationStarted = true;
            $this->createProbeTables($connection, $parentTable, $childTable);

            if ($configuration->driver !== SupportedDatabaseDriver::Sqlite) {
                $phase = 'utf8mb4 and InnoDB verification';
                $this->assertServerTableCapabilities(
                    $connection,
                    $configuration,
                    $parentTable,
                    $childTable,
                );
            }

            $phase = 'relational constraints and data operations';
            $this->exerciseDataCapabilities($connection, $parentTable, $childTable);

            $checks = [
                'connection',
                'product_identity',
                'create_table',
                'primary_key',
                'auto_increment',
                'unique_index',
                'foreign_key',
                'restrict_delete',
                'insert',
                'select',
                'update',
                'delete',
                'transaction_rollback',
                'decimal',
                'text',
                'timestamps',
            ];
            $checks = array_merge(
                $checks,
                $configuration->driver === SupportedDatabaseDriver::Sqlite
                    ? ['foreign_keys', 'wal', 'busy_timeout', 'transaction_mode']
                    : ['utf8mb4', 'innodb'],
                ['cleanup'],
            );

            $result = new DatabaseCapabilityProbeResultData(
                driver: $identity->driver,
                product: $identity->product,
                serverVersion: $identity->version,
                charset: $charset,
                collation: $collation,
                checks: $checks,
            );
        } catch (DatabaseCapabilityProbeException $exception) {
            $failure = $exception;
        } catch (Throwable) {
            $failure = new DatabaseCapabilityProbeException(
                "Database capability probe failed during {$phase}.",
            );
        } finally {
            $residualTables = $connection instanceof Connection && $schemaCreationStarted
                ? $this->cleanupProbeTables($connection, [$childTable, $parentTable])
                : [];

            $this->disconnect();
        }

        if ($residualTables !== []) {
            throw new DatabaseCapabilityProbeException(
                'Database probe cleanup could not remove or verify these probe tables: '.implode(', ', $residualTables).'.',
            );
        }

        if ($failure instanceof DatabaseCapabilityProbeException) {
            throw $failure;
        }

        if (! $result instanceof DatabaseCapabilityProbeResultData) {
            throw new DatabaseCapabilityProbeException('Database capability probe did not produce a result.');
        }

        return $result;
    }

    private function connect(DatabaseConfigurationData $configuration): Connection
    {
        Config::set('database.connections.'.self::CONNECTION, $configuration->toLaravelConfig());
        DB::purge(self::CONNECTION);

        return DB::connection(self::CONNECTION);
    }

    private function disconnect(): void
    {
        DB::purge(self::CONNECTION);
        Config::set('database.connections.'.self::CONNECTION, null);
    }

    private function assertSafeSqlitePath(DatabaseConfigurationData $configuration): void
    {
        if ($configuration->driver !== SupportedDatabaseDriver::Sqlite) {
            return;
        }

        $path = $configuration->database;

        if ($path === '' || ! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            throw new DatabaseCapabilityProbeException('The SQLite database path must be absolute.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new DatabaseCapabilityProbeException('The SQLite database path contains invalid control characters.');
        }

        if (in_array('..', explode(DIRECTORY_SEPARATOR, $path), true)) {
            throw new DatabaseCapabilityProbeException('The SQLite database path must not contain parent traversal segments.');
        }

        $this->assertPathHasNoSymbolicLinks($path);

        $normalizedPath = $this->normalizeAbsolutePath($path);
        $normalizedPublicPath = $this->normalizeAbsolutePath(
            realpath(public_path()) ?: public_path(),
        );

        if ($normalizedPath === $normalizedPublicPath
            || str_starts_with($normalizedPath, $normalizedPublicPath.DIRECTORY_SEPARATOR)) {
            throw new DatabaseCapabilityProbeException('The SQLite database must not be stored under the public directory.');
        }

        $directory = dirname($normalizedPath);
        $realDirectory = realpath($directory);

        if (! is_string($realDirectory) || ! is_dir($realDirectory) || ! is_writable($realDirectory)) {
            throw new DatabaseCapabilityProbeException('The SQLite database directory must exist and be writable.');
        }

        $resolvedPath = $this->normalizeAbsolutePath($realDirectory.DIRECTORY_SEPARATOR.basename($normalizedPath));
        if ($resolvedPath === $normalizedPublicPath
            || str_starts_with($resolvedPath, $normalizedPublicPath.DIRECTORY_SEPARATOR)) {
            throw new DatabaseCapabilityProbeException('The SQLite database must not resolve under the public directory.');
        }

        if (file_exists($normalizedPath) && (! is_file($normalizedPath) || ! is_writable($normalizedPath))) {
            throw new DatabaseCapabilityProbeException('The SQLite database must be a writable regular file.');
        }
    }

    private function createSqliteFileWhenMissing(DatabaseConfigurationData $configuration): void
    {
        if ($configuration->driver !== SupportedDatabaseDriver::Sqlite || file_exists($configuration->database)) {
            return;
        }

        $handle = @fopen($configuration->database, 'x+b');

        if ($handle === false) {
            throw new DatabaseCapabilityProbeException('The SQLite database file could not be created securely.');
        }

        try {
            if (! @chmod($configuration->database, 0600)) {
                throw new DatabaseCapabilityProbeException('The SQLite database file permissions could not be secured.');
            }
        } finally {
            fclose($handle);
        }
    }

    private function normalizeAbsolutePath(string $path): string
    {
        $parts = [];

        foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);

                continue;
            }

            $parts[] = $part;
        }

        return DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $parts);
    }

    private function assertPathHasNoSymbolicLinks(string $path): void
    {
        $current = DIRECTORY_SEPARATOR;

        foreach (explode(DIRECTORY_SEPARATOR, ltrim($path, DIRECTORY_SEPARATOR)) as $part) {
            $current = rtrim($current, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$part;

            if (is_link($current)) {
                throw new DatabaseCapabilityProbeException('The SQLite database path must not contain symbolic links.');
            }
        }
    }

    private function serverIdentity(
        Connection $connection,
        SupportedDatabaseDriver $driver,
    ): DatabaseServerIdentityData {
        if ($driver === SupportedDatabaseDriver::Sqlite) {
            $version = $this->firstScalar($connection, 'SELECT sqlite_version() AS value');
            $this->assertSqliteRuntime($connection);

            return new DatabaseServerIdentityData(
                driver: SupportedDatabaseDriver::Sqlite,
                product: SupportedDatabaseDriver::Sqlite->label(),
                version: $version,
                versionComment: '',
            );
        }

        $version = $this->firstScalar($connection, 'SELECT VERSION() AS value');
        $versionComment = $this->firstScalar($connection, 'SELECT @@version_comment AS value');

        return $this->productRecognizer->recognize($driver, $version, $versionComment);
    }

    private function assertSqliteRuntime(Connection $connection): void
    {
        if ((int) $this->firstScalar($connection, 'PRAGMA foreign_keys') !== 1) {
            throw new DatabaseCapabilityProbeException('SQLite foreign key enforcement is not active.');
        }

        if (mb_strtolower($this->firstScalar($connection, 'PRAGMA journal_mode')) !== 'wal') {
            throw new DatabaseCapabilityProbeException('SQLite WAL journal mode is not active.');
        }

        if ((int) $this->firstScalar($connection, 'PRAGMA busy_timeout') < 5000) {
            throw new DatabaseCapabilityProbeException('SQLite busy timeout is below the required value.');
        }

        if (Config::get('database.connections.'.self::CONNECTION.'.transaction_mode') !== 'IMMEDIATE') {
            throw new DatabaseCapabilityProbeException('SQLite transaction mode is not configured as IMMEDIATE.');
        }
    }

    /** @return array{string, string} */
    private function connectionEncoding(
        Connection $connection,
        DatabaseConfigurationData $configuration,
    ): array {
        if ($configuration->driver === SupportedDatabaseDriver::Sqlite) {
            return ['UTF-8', 'SQLite native'];
        }

        $charset = mb_strtolower($this->firstScalar(
            $connection,
            'SELECT @@character_set_connection AS value',
        ));
        $collation = mb_strtolower($this->firstScalar(
            $connection,
            'SELECT @@collation_connection AS value',
        ));

        if ($charset !== 'utf8mb4') {
            throw new DatabaseCapabilityProbeException('The database connection is not using utf8mb4.');
        }

        if ($collation !== mb_strtolower($configuration->collation)
            || ! str_starts_with($collation, 'utf8mb4_')) {
            throw new DatabaseCapabilityProbeException('The database connection collation does not match the verified utf8mb4 collation.');
        }

        return [$charset, $collation];
    }

    private function createProbeTables(Connection $connection, string $parentTable, string $childTable): void
    {
        $schema = $connection->getSchemaBuilder();

        $schema->create($parentTable, static function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->decimal('amount', 12, 2);
            $table->text('payload');
            $table->timestamps();
        });

        $schema->create($childTable, static function (Blueprint $table) use ($parentTable): void {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->text('payload');
            $table->foreign('parent_id')
                ->references('id')
                ->on($parentTable)
                ->restrictOnDelete();
        });
    }

    private function assertServerTableCapabilities(
        Connection $connection,
        DatabaseConfigurationData $configuration,
        string $parentTable,
        string $childTable,
    ): void {
        $rows = $connection->select(
            <<<'SQL'
                SELECT TABLE_NAME AS table_name, ENGINE AS engine, TABLE_COLLATION AS table_collation
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?)
                SQL,
            [$configuration->database, $parentTable, $childTable],
        );

        if (count($rows) !== 2) {
            throw new DatabaseCapabilityProbeException('The database did not expose both probe tables for verification.');
        }

        foreach ($rows as $row) {
            $values = (array) $row;
            $engine = mb_strtolower((string) ($values['engine'] ?? ''));
            $collation = mb_strtolower((string) ($values['table_collation'] ?? ''));

            if ($engine !== 'innodb') {
                throw new DatabaseCapabilityProbeException('The database did not create every probe table with InnoDB.');
            }

            if (! str_starts_with($collation, 'utf8mb4_')) {
                throw new DatabaseCapabilityProbeException('The database did not create every probe table with utf8mb4.');
            }
        }
    }

    private function exerciseDataCapabilities(Connection $connection, string $parentTable, string $childTable): void
    {
        $timestamp = now('UTC');
        $payload = 'AssestMe database capability probe — àèìòù 🔐';
        $parentId = $connection->table($parentTable)->insertGetId([
            'code' => 'probe',
            'amount' => 12.34,
            'payload' => $payload,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        if ($parentId < 1) {
            throw new DatabaseCapabilityProbeException('The database returned an invalid auto-incrementing primary key.');
        }

        try {
            $connection->table($parentTable)->insert([
                'id' => $parentId,
                'code' => 'duplicate-primary-key',
                'amount' => 1,
                'payload' => 'duplicate',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            throw new DatabaseCapabilityProbeException('The database did not enforce the probe primary key.');
        } catch (QueryException) {
            // Expected: the duplicate primary key must be rejected by the database.
        }

        try {
            $connection->table($parentTable)->insert([
                'code' => 'probe',
                'amount' => 1,
                'payload' => 'duplicate',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            throw new DatabaseCapabilityProbeException('The database did not enforce the probe unique index.');
        } catch (QueryException) {
            // Expected: the duplicate unique value must be rejected by the database.
        }

        $row = $connection->table($parentTable)->where('id', $parentId)->first();
        $values = is_object($row) ? get_object_vars($row) : [];

        if (($values['code'] ?? null) !== 'probe'
            || abs((float) ($values['amount'] ?? 0) - 12.34) > 0.00001
            || ($values['payload'] ?? null) !== $payload
            || ($values['created_at'] ?? null) === null
            || ($values['updated_at'] ?? null) === null) {
            throw new DatabaseCapabilityProbeException('The database did not preserve selected probe values.');
        }

        if ($connection->table($parentTable)->where('id', $parentId)->update(['payload' => 'updated']) !== 1
            || $connection->table($parentTable)->where('id', $parentId)->value('payload') !== 'updated') {
            throw new DatabaseCapabilityProbeException('The database did not update the probe row correctly.');
        }

        try {
            $connection->table($childTable)->insert([
                'parent_id' => $parentId + 999999,
                'payload' => 'invalid foreign key',
            ]);

            throw new DatabaseCapabilityProbeException('The database did not enforce the probe foreign key.');
        } catch (QueryException) {
            // Expected: an unknown parent must be rejected by the database.
        }

        $connection->table($childTable)->insert([
            'parent_id' => $parentId,
            'payload' => 'AssestMe child probe',
        ]);

        try {
            $connection->table($parentTable)->where('id', $parentId)->delete();

            throw new DatabaseCapabilityProbeException('The database did not enforce RESTRICT on delete.');
        } catch (QueryException) {
            // Expected: the referenced parent must not be deleted.
        }

        $connection->beginTransaction();
        try {
            $connection->table($parentTable)->insert([
                'code' => 'rollback',
                'amount' => 99.99,
                'payload' => 'transaction rollback',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }

        if ($connection->table($parentTable)->where('code', 'rollback')->exists()) {
            throw new DatabaseCapabilityProbeException('The database did not roll back the probe transaction.');
        }

        if ($connection->table($childTable)->where('parent_id', $parentId)->delete() !== 1
            || $connection->table($parentTable)->where('id', $parentId)->delete() !== 1
            || $connection->table($childTable)->where('parent_id', $parentId)->exists()
            || $connection->table($parentTable)->where('id', $parentId)->exists()) {
            throw new DatabaseCapabilityProbeException('The database did not delete the probe rows cleanly.');
        }
    }

    /**
     * @param  list<string>  $tables
     * @return list<string>
     */
    private function cleanupProbeTables(Connection $connection, array $tables): array
    {
        $schema = $connection->getSchemaBuilder();
        $dropFailures = [];

        foreach ($tables as $table) {
            try {
                $schema->dropIfExists($table);
            } catch (Throwable) {
                $dropFailures[] = $table;
            }
        }

        $residual = [];
        foreach ($tables as $table) {
            try {
                if ($schema->hasTable($table)) {
                    $residual[] = $table;
                }
            } catch (Throwable) {
                if (in_array($table, $dropFailures, true)) {
                    $residual[] = $table;
                }
            }
        }

        $residual = array_values(array_unique($residual));
        sort($residual, SORT_STRING);

        return $residual;
    }

    private function firstScalar(Connection $connection, string $sql): string
    {
        $row = $connection->selectOne($sql);
        $values = is_object($row) ? array_values(get_object_vars($row)) : [];
        $value = $values[0] ?? null;

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new DatabaseCapabilityProbeException('The database returned an invalid capability value.');
        }

        return (string) $value;
    }
}
