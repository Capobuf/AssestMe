<?php

declare(strict_types=1);

namespace App\Data\Backups;

final readonly class BackupFileEntry
{
    public function __construct(
        public string $path,
        public int $size,
        public string $sha256,
    ) {}

    /** @return array{path: string, size: int, sha256: string} */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'size' => $this->size,
            'sha256' => $this->sha256,
        ];
    }
}
