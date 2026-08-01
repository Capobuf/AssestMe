<?php

declare(strict_types=1);

namespace App\Services\Database;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class DatabaseDumpBinaryValidator
{
    public function validate(string $driver, string $path): string
    {
        $expectedBasenames = match ($driver) {
            'mysql' => ['mysqldump'],
            'mariadb' => ['mariadb-dump', 'mysqldump'],
            default => throw new RuntimeException("Database dump binaries are unsupported for driver {$driver}."),
        };

        if (! $this->isAbsolutePath($path)
            || ! in_array(basename($path), $expectedBasenames, true)) {
            throw new RuntimeException("The configured {$driver} dump binary is invalid.");
        }

        $realPath = realpath($path);

        if ($realPath === false || ! is_file($realPath) || ! is_executable($realPath)) {
            throw new RuntimeException("The configured {$driver} dump binary is invalid.");
        }

        try {
            $process = new Process(
                [$realPath, '--version'],
                env: [
                    'DATABASE_URL' => false,
                    'DB_DATABASE' => false,
                    'DB_HOST' => false,
                    'DB_PASSWORD' => false,
                    'DB_PORT' => false,
                    'DB_SOCKET' => false,
                    'DB_URL' => false,
                    'DB_USERNAME' => false,
                    'MARIADB_PASSWORD' => false,
                    'MARIADB_PWD' => false,
                    'MARIADB_ROOT_PASSWORD' => false,
                    'MARIADB_USER' => false,
                    'MYSQL_PASSWORD' => false,
                    'MYSQL_PWD' => false,
                    'MYSQL_ROOT_PASSWORD' => false,
                    'MYSQL_USER' => false,
                ],
                timeout: 10,
            );
            $process->run();
        } catch (Throwable) {
            throw new RuntimeException("The configured {$driver} dump binary could not be executed.");
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException("The configured {$driver} dump binary failed its version check.");
        }

        $versionOutput = strtolower(trim($process->getOutput().' '.$process->getErrorOutput()));
        $productMatches = match ($driver) {
            'mysql' => str_contains($versionOutput, 'mysqldump')
                && ! str_contains($versionOutput, 'mariadb'),
            'mariadb' => str_contains($versionOutput, 'mariadb'),
        };

        if (! $productMatches) {
            throw new RuntimeException("The configured dump binary does not match driver {$driver}.");
        }

        return $realPath;
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('~^(?:[A-Za-z]:[\\/]|/|\\\\)~', $path) === 1;
    }
}
