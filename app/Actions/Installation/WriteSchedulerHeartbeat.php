<?php

declare(strict_types=1);

namespace App\Actions\Installation;

use App\Services\Installation\InstallationState;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use RuntimeException;

final readonly class WriteSchedulerHeartbeat
{
    public function __construct(
        private Filesystem $files,
        private InstallationState $installationState,
    ) {}

    public function __invoke(): void
    {
        if (! $this->installationState->isInstalled()) {
            return;
        }

        $path = $this->path();
        $directory = dirname($path);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0700, true);
        }

        try {
            $payload = json_encode([
                'schema_version' => 1,
                'last_run_at' => now('UTC')->toIso8601String(),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
        } catch (JsonException $exception) {
            throw new RuntimeException('Scheduler heartbeat could not be encoded.', previous: $exception);
        }

        $temporary = $path.'.tmp.'.bin2hex(random_bytes(8));

        try {
            if ($this->files->put($temporary, $payload, true) === false || ! @chmod($temporary, 0600) || ! @rename($temporary, $path)) {
                throw new RuntimeException('Scheduler heartbeat could not be written atomically.');
            }
        } finally {
            if ($this->files->exists($temporary)) {
                $this->files->delete($temporary);
            }
        }
    }

    private function path(): string
    {
        $path = config('assestme.scheduler.heartbeat_path');

        if (! is_string($path) || ! str_starts_with($path, DIRECTORY_SEPARATOR) || is_link($path)) {
            throw new RuntimeException('Scheduler heartbeat path is invalid.');
        }

        return $path;
    }
}
