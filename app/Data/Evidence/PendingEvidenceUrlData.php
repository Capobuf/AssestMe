<?php

declare(strict_types=1);

namespace App\Data\Evidence;

final readonly class PendingEvidenceUrlData
{
    public function __construct(
        public string $title,
        public string $url,
        public bool $includeInReport,
        public ?string $caption = null,
        public ?string $internalNotes = null,
    ) {}

    /** @return array<string, bool|string|null> */
    public function normalizedPayload(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'include_in_report' => $this->includeInReport,
            'caption' => $this->caption,
            'internal_notes' => $this->internalNotes,
        ];
    }
}
