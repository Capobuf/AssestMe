<?php

declare(strict_types=1);

namespace App\Data\Storage;

final readonly class DeletionCleanupResult
{
    public function __construct(
        public int $processed,
        public int $cleaned,
        public int $failed,
    ) {}
}
