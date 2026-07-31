<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Installation\InstallationState;
use App\Services\Installation\SchedulerHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final readonly class HealthController
{
    public function __construct(
        private InstallationState $installationState,
        private SchedulerHeartbeat $schedulerHeartbeat,
    ) {}

    public function __invoke(): JsonResponse
    {
        if (! $this->installationState->isInstalled()) {
            return response()->json([
                'status' => 'not_installed',
                'bootable' => true,
                'scheduler' => $this->schedulerHeartbeat->status()->status,
            ]);
        }

        try {
            $connection = DB::connection();
            $connection->select('SELECT 1');
            $schema = Schema::connection($connection->getName());

            if (! $schema->hasTable('migrations') || ! $schema->hasTable('settings') || ! $schema->hasTable('users') || User::query()->count() !== 1) {
                throw new \RuntimeException('Installed schema or administrator invariant is invalid.');
            }
        } catch (Throwable) {
            return response()->json([
                'status' => 'runtime_error',
                'bootable' => true,
                'scheduler' => $this->schedulerHeartbeat->status()->status,
            ], 503);
        }

        return response()->json([
            'status' => 'healthy',
            'bootable' => true,
            'scheduler' => $this->schedulerHeartbeat->status()->status,
        ]);
    }
}
