<?php

declare(strict_types=1);

namespace App\Data\Evidence;

final readonly class PreparedEvidenceFileData
{
    public function __construct(
        public PendingEvidenceFileData $pending,
        public string $finalPath,
    ) {}
}
