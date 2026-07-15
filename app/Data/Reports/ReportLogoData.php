<?php

declare(strict_types=1);

namespace App\Data\Reports;

final readonly class ReportLogoData
{
    public function __construct(
        public string $owner,
        public string $path,
        public string $mimeType,
        public string $sha256,
        public string $dataUri,
    ) {}

    /** @return array{owner: string, path: string, mime_type: string, sha256: string} */
    public function toArray(): array
    {
        return [
            'owner' => $this->owner,
            'path' => $this->path,
            'mime_type' => $this->mimeType,
            'sha256' => $this->sha256,
        ];
    }
}
