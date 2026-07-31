<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Data\Installation\SchedulerHeartbeatResult;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Throwable;

final readonly class SchedulerHeartbeat
{
    public function __construct(private Filesystem $files) {}

    public function status(): SchedulerHeartbeatResult
    {
        $path = config('assestme.scheduler.heartbeat_path');

        if (! is_string($path) || ! is_file($path) || is_link($path)) {
            return new SchedulerHeartbeatResult('pending', null);
        }

        try {
            /** @var array{schema_version?: int, last_run_at?: string} $data */
            $data = json_decode($this->files->get($path), true, 8, JSON_THROW_ON_ERROR);
            $lastRunAt = isset($data['last_run_at']) ? Carbon::parse($data['last_run_at'], 'UTC') : null;
        } catch (Throwable) {
            return new SchedulerHeartbeatResult('invalid', null);
        }

        if (($data['schema_version'] ?? null) !== 1 || $lastRunAt === null) {
            return new SchedulerHeartbeatResult('invalid', null);
        }

        $freshFor = (int) config('assestme.scheduler.fresh_for_seconds', 150);

        return new SchedulerHeartbeatResult(
            $lastRunAt->greaterThanOrEqualTo(now('UTC')->subSeconds($freshFor)) ? 'verified' : 'stale',
            $lastRunAt,
        );
    }
}
