<?php

declare(strict_types=1);

namespace App\Services\Database;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class DatabaseRestoreBinaryValidator
{
    public function validate(string $driver, string $path): string
    {
        $expectedBasename = match ($driver) {
            'mysql' => 'mysql',
            'mariadb' => 'mariadb',
            default => throw new RuntimeException("Database restore binaries are unsupported for driver {$driver}."),
        };

        if (! str_starts_with($path, DIRECTORY_SEPARATOR)
            || basename($path) !== $expectedBasename) {
            throw new RuntimeException("The configured {$driver} restore binary is invalid.");
        }

        $realPath = realpath($path);

        if ($realPath === false || ! is_file($realPath) || ! is_executable($realPath)) {
            throw new RuntimeException("The configured {$driver} restore binary is invalid.");
        }

        try {
            $process = new Process(
                [$realPath, '--version'],
                env: (new DatabaseClientProcessEnvironment)->forDriver(
                    $driver,
                    storage_path('framework/.assestme-version-check'),
                ),
                timeout: 10,
            );
            $process->run();
        } catch (Throwable) {
            throw new RuntimeException("The configured {$driver} restore binary could not be executed.");
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException("The configured {$driver} restore binary failed its version check.");
        }

        $versionOutput = strtolower(trim($process->getOutput().' '.$process->getErrorOutput()));
        $productMatches = match ($driver) {
            'mysql' => str_contains($versionOutput, 'mysql')
                && ! str_contains($versionOutput, 'mariadb'),
            'mariadb' => str_contains($versionOutput, 'mariadb'),
        };

        if (! $productMatches) {
            throw new RuntimeException("The configured restore binary does not match driver {$driver}.");
        }

        return $realPath;
    }
}
