<?php

declare(strict_types=1);

namespace App\Data\Evidence;

use Illuminate\Http\UploadedFile;

final readonly class PendingEvidenceFileData
{
    public function __construct(
        public UploadedFile $file,
        public string $title,
        public bool $includeInReport,
        public string $extension,
        public string $mimeType,
        public int $sizeBytes,
        public string $sha256,
        public ?string $caption = null,
        public ?string $internalNotes = null,
    ) {}

    /** @return array<string, bool|int|string|null> */
    public function normalizedPayload(): array
    {
        return [
            'title' => $this->title,
            'include_in_report' => $this->includeInReport,
            'original_filename' => $this->file->getClientOriginalName(),
            'extension' => $this->extension,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'sha256' => $this->sha256,
            'caption' => $this->caption,
            'internal_notes' => $this->internalNotes,
        ];
    }
}
