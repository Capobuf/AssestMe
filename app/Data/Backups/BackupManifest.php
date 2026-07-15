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
        return json_encode([
            'schema_version' => $this->schemaVersion,
            'created_at' => $this->createdAt,
            'application_version' => $this->applicationVersion,
            'files' => array_map(
                static fn (BackupFileEntry $file): array => $file->toArray(),
                $this->files,
            ),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    }

    /** @throws JsonException */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)
            || ($data['schema_version'] ?? null) !== 1
            || ! is_string($data['created_at'] ?? null)
            || ! is_string($data['application_version'] ?? null)
            || ! is_array($data['files'] ?? null)) {
            throw new InvalidArgumentException('The backup manifest structure is invalid.');
        }

        $files = [];

        foreach ($data['files'] as $file) {
            if (! is_array($file)
                || ! is_string($file['path'] ?? null)
                || ! is_int($file['size'] ?? null)
                || $file['size'] < 0
                || ! is_string($file['sha256'] ?? null)
                || preg_match('/^[a-f0-9]{64}$/', $file['sha256']) !== 1) {
                throw new InvalidArgumentException('A backup manifest file entry is invalid.');
            }

            $files[] = new BackupFileEntry(
                path: $file['path'],
                size: $file['size'],
                sha256: $file['sha256'],
            );
        }

        return new self(
            schemaVersion: 1,
            createdAt: $data['created_at'],
            applicationVersion: $data['application_version'],
            files: $files,
        );
    }
}
