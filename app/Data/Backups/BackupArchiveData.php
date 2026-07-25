<?php

declare(strict_types=1);

namespace App\Data\Backups;

use Carbon\CarbonImmutable;

final readonly class BackupArchiveData
{
    public function __construct(
        public string $name,
        public string $absolutePath,
        public string $kind,
        public int $size,
        public CarbonImmutable $modifiedAt,
    ) {}

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     kind: string,
     *     size: int,
     *     modified_at: CarbonImmutable
     * }
     */
    public function toTableRecord(): array
    {
        return [
            'key' => $this->name,
            'name' => $this->name,
            'kind' => $this->kind,
            'size' => $this->size,
            'modified_at' => $this->modifiedAt,
        ];
    }
}
