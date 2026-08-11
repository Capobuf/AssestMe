<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Models\Finding;

final readonly class FindingSaveResult
{
    /** @param list<int> $evidenceIds */
    public function __construct(
        public Finding $finding,
        public int $appliedVersion,
        public bool $idempotentReplay = false,
        public array $evidenceIds = [],
    ) {}
}
