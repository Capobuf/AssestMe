<?php

declare(strict_types=1);

namespace App\Data\GoogleDrive;

final readonly class GoogleDriveEvidenceSnapshotData
{
    public function __construct(
        public int $id,
        public int $findingId,
        public int $sortOrder,
        public string $type,
        public string $title,
        public ?string $fileName,
        public ?string $url,
        public ?string $originalFilename,
        public ?string $caption,
        public ?string $internalNotes,
        public ?string $mimeType,
        public ?int $sizeBytes,
        public ?string $sha256,
        public bool $included,
    ) {}

    /** @return list<string|int|bool|null> */
    public function row(?string $remoteLink = null): array
    {
        return [
            sprintf('E-%06d', $this->id),
            sprintf('F-%06d', $this->findingId),
            $this->sortOrder,
            $this->type,
            $this->title,
            $this->fileName,
            $this->type === 'url' ? $this->url : $remoteLink,
            $this->originalFilename,
            $this->caption,
            $this->internalNotes,
            $this->mimeType,
            $this->sizeBytes,
            $this->sha256,
            $this->included,
        ];
    }

    /** @return array{id: int, finding_id: int, order: int, type: string, title: string, file_name: string|null, url: string|null, original_name: string|null, caption: string|null, notes: string|null, mime: string|null, size: int|null, sha256: string|null, included: bool} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'finding_id' => $this->findingId,
            'order' => $this->sortOrder,
            'type' => $this->type,
            'title' => $this->title,
            'file_name' => $this->fileName,
            'url' => $this->url,
            'original_name' => $this->originalFilename,
            'caption' => $this->caption,
            'notes' => $this->internalNotes,
            'mime' => $this->mimeType,
            'size' => $this->sizeBytes,
            'sha256' => $this->sha256,
            'included' => $this->included,
        ];
    }
}
