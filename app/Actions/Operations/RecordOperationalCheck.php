<?php

declare(strict_types=1);

namespace App\Actions\Operations;

use App\Data\Operations\OperationalCheckResult;
use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use App\Services\Operations\OperationalCheckStore;
use Carbon\CarbonImmutable;

final readonly class RecordOperationalCheck
{
    public function __construct(private OperationalCheckStore $store) {}

    public function __invoke(
        OperationalCheckType $type,
        OperationalCheckStatus $status,
        ?string $error = null,
        ?CarbonImmutable $attemptedAt = null,
    ): OperationalCheckResult {
        $attemptedAt ??= CarbonImmutable::now('UTC');

        return $this->store->put($type, $status, $attemptedAt, $error);
    }
}
