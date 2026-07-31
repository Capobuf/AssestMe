<?php

declare(strict_types=1);

namespace App\Data\Installation;

use Illuminate\Support\Carbon;

final readonly class SchedulerHeartbeatResult
{
    public function __construct(
        public string $status,
        public ?Carbon $lastRunAt,
    ) {}

    public function isRecent(): bool
    {
        return $this->status === 'verified';
    }
}
