<?php

declare(strict_types=1);

namespace App\Data\GoogleDrive;

final readonly class GoogleDriveFileSnapshotData
{
    public function __construct(
        public int $localId,
        public string $remoteName,
        public string $localPath,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256,
    ) {}

    /** @return array{id: int, name: string, path: string, mime: string, size: int, sha256: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->localId,
            'name' => $this->remoteName,
            'path' => $this->localPath,
            'mime' => $this->mimeType,
            'size' => $this->sizeBytes,
            'sha256' => $this->sha256,
        ];
    }
}
