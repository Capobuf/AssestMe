<?php

declare(strict_types=1);

namespace App\Data\FattureInCloud;

final readonly class FattureInCloudDocumentData
{
    /** @param list<FattureInCloudDocumentItemData> $items */
    public function __construct(
        public string $id,
        public string $type,
        public string $subject,
        public ?string $visibleSubject,
        public ?string $url,
        public array $items = [],
    ) {}
}
