<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Finding;

final readonly class FindingSaveResult
{
    public function __construct(
        public Finding $finding,
        public int $appliedVersion,
        public bool $idempotentReplay = false,
    ) {}
}
