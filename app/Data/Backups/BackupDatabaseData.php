<?php

declare(strict_types=1);

namespace App\Data\Backups;

final readonly class BackupDatabaseData
{
    public function __construct(
        public string $driver,
        public string $product,
        public string $serverVersion,
        public string $format,
        public string $path,
    ) {}

    /**
     * @return array{
     *     driver: string,
     *     product: string,
     *     server_version: string,
     *     format: string,
     *     path: string
     * }
     */
    public function toArray(): array
    {
        return [
            'driver' => $this->driver,
            'product' => $this->product,
            'server_version' => $this->serverVersion,
            'format' => $this->format,
            'path' => $this->path,
        ];
    }

    public static function legacySqlite(): self
    {
        return new self(
            driver: 'sqlite',
            product: 'SQLite',
            serverVersion: 'unknown',
            format: 'sqlite',
            path: 'database/database.sqlite',
        );
    }
}
