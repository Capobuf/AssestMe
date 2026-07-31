<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class InstallationAnomalyDetector
{
    public function __construct(private InstallationState $installationState) {}

    public function hasCompletedSchemaWithoutLock(): bool
    {
        if ($this->installationState->hasProgress()) {
            return false;
        }

        $environmentPath = config('assestme.installation.environment_path');

        if (! is_string($environmentPath) || ! is_file($environmentPath) || is_link($environmentPath)) {
            return false;
        }

        try {
            $connection = DB::connection();
            $schema = $connection->getSchemaBuilder();

            return $schema->hasTable('migrations')
                && $schema->hasTable('settings')
                && $schema->hasTable('users')
                && User::query()->count() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
