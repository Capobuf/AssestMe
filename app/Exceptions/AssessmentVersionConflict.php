<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class AssessmentVersionConflict extends RuntimeException
{
    public function __construct(
        public readonly int $expectedVersion,
        public readonly int $actualVersion,
    ) {
        parent::__construct("Assessment version conflict: expected {$expectedVersion}, actual {$actualVersion}.");
    }
}
