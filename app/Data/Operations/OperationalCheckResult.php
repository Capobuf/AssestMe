<?php

declare(strict_types=1);

namespace App\Data\Operations;

use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use Carbon\CarbonImmutable;

final readonly class OperationalCheckResult
{
    public function __construct(
        public OperationalCheckType $type,
        public OperationalCheckStatus $status,
        public CarbonImmutable $lastAttemptedAt,
        public ?CarbonImmutable $lastSucceededAt,
        public ?CarbonImmutable $lastFailedAt,
        public ?string $errorText,
    ) {}
}
