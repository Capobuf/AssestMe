<?php

declare(strict_types=1);

namespace App\Data\Backups;

use InvalidArgumentException;
use JsonException;

final class BackupManifest
{
    /** @param list<BackupFileEntry> $files */
    public function __construct(
        public readonly int $schemaVersion,
        public readonly string $createdAt,
        public readonly string $applicationVersion,
        public readonly BackupDatabaseData $database,
        private readonly array $files,
    ) {}

    /** @return list<BackupFileEntry> */
    public function files(): array
    {
        return $this->files;
    }

    /** @return list<string> */
    public function paths(): array
    {
        return array_map(
            static fn (BackupFileEntry $file): string => $file->path,
            $this->files,
        );
    }

    /** @throws JsonException */
    public function toJson(): string
    {
        $manifest = [
            'schema_version' => $this->schemaVersion,
            'created_at' => $this->createdAt,
            'application_version' => $this->applicationVersion,
        ];

        if ($this->schemaVersion === 2) {
            $manifest['database'] = $this->database->toArray();
        } elseif ($this->schemaVersion !== 1) {
            throw new InvalidArgumentException('The backup manifest schema version is unsupported.');
        }

        $manifest['files'] = array_map(
            static fn (BackupFileEntry $file): array => $file->toArray(),
            $this->files,
        );

        return json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ).PHP_EOL;
    }

    /** @throws JsonException */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data) || ! is_int($data['schema_version'] ?? null)) {
            throw new InvalidArgumentException('The backup manifest structure is invalid.');
        }

        $schemaVersion = $data['schema_version'];
        $expectedKeys = $schemaVersion === 1
            ? ['application_version', 'created_at', 'files', 'schema_version']
            : ['application_version', 'created_at', 'database', 'files', 'schema_version'];

        if (! in_array($schemaVersion, [1, 2], true)
            || ! self::hasExactKeys($data, $expectedKeys)
            || ! is_string($data['created_at'] ?? null)
            || $data['created_at'] === ''
            || ! is_string($data['application_version'] ?? null)
            || $data['application_version'] === ''
            || ! is_array($data['files'] ?? null)) {
            throw new InvalidArgumentException('The backup manifest structure is invalid.');
        }

        $database = BackupDatabaseData::legacySqlite();

        if ($schemaVersion === 2) {
            $databaseData = $data['database'] ?? null;

            if (! is_array($databaseData)) {
                throw new InvalidArgumentException('The backup manifest database entry is invalid.');
            }

            $database = self::parseDatabase($databaseData);
        }

        return new self(
            schemaVersion: $schemaVersion,
            createdAt: $data['created_at'],
            applicationVersion: $data['application_version'],
            database: $database,
            files: self::parseFiles($data['files']),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $expected
     */
    private static function hasExactKeys(array $data, array $expected): bool
    {
        $keys = array_keys($data);
        sort($keys);
        sort($expected);

        return $keys === $expected;
    }

    /** @param array<array-key, mixed> $data */
    private static function parseDatabase(array $data): BackupDatabaseData
    {
        if (! self::hasExactKeys($data, ['driver', 'format', 'path', 'product', 'server_version'])
            || ! is_string($data['driver'] ?? null)
            || ! is_string($data['product'] ?? null)
            || ! is_string($data['server_version'] ?? null)
            || $data['server_version'] === ''
            || preg_match('/[\x00-\x1F\x7F]/', $data['server_version']) === 1
            || ! is_string($data['format'] ?? null)
            || ! is_string($data['path'] ?? null)) {
            throw new InvalidArgumentException('The backup manifest database entry is invalid.');
        }

        $expected = match ($data['driver']) {
            'sqlite' => ['SQLite', 'sqlite', 'database/database.sqlite'],
            'mysql' => ['MySQL', 'sql', 'database/database.sql'],
            'mariadb' => ['MariaDB', 'sql', 'database/database.sql'],
            default => null,
        };

        if ($expected === null
            || [$data['product'], $data['format'], $data['path']] !== $expected) {
            throw new InvalidArgumentException('The backup manifest database entry is inconsistent.');
        }

        return new BackupDatabaseData(
            driver: $data['driver'],
            product: $data['product'],
            serverVersion: $data['server_version'],
            format: $data['format'],
            path: $data['path'],
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<BackupFileEntry>
     */
    private static function parseFiles(array $data): array
    {
        $files = [];

        foreach ($data as $file) {
            if (! is_array($file)
                || ! self::hasExactKeys($file, ['path', 'sha256', 'size'])
                || ! is_string($file['path'] ?? null)
                || ! is_int($file['size'] ?? null)
                || $file['size'] < 0
                || ! is_string($file['sha256'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/D', $file['sha256']) !== 1) {
                throw new InvalidArgumentException('A backup manifest file entry is invalid.');
            }

            $files[] = new BackupFileEntry(
                path: $file['path'],
                size: $file['size'],
                sha256: $file['sha256'],
            );
        }

        return $files;
    }
}
