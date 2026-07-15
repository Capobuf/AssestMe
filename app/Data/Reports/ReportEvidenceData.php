<?php

declare(strict_types=1);

namespace App\Data\Reports;

final readonly class ReportEvidenceData
{
    public function __construct(
        public int $id,
        public string $type,
        public string $title,
        public ?string $filePath,
        public ?string $url,
        public ?string $originalFilename,
        public ?string $caption,
        public ?string $mimeType,
        public ?int $sizeBytes,
        public ?string $sha256,
        public bool $included,
        public int $sortOrder,
        public ?string $imageDataUri,
    ) {}

    public function isImage(): bool
    {
        return $this->imageDataUri !== null;
    }

    /** @return array{id: int, type: string, title: string, file_path: string|null, url: string|null, original_filename: string|null, caption: string|null, mime_type: string|null, size_bytes: int|null, sha256: string|null, included: bool, sort_order: int} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'file_path' => $this->filePath,
            'url' => $this->url,
            'original_filename' => $this->originalFilename,
            'caption' => $this->caption,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'sha256' => $this->sha256,
            'included' => $this->included,
            'sort_order' => $this->sortOrder,
        ];
    }
}
