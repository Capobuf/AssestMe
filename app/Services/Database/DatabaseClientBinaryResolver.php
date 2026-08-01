<?php

declare(strict_types=1);

namespace App\Services\Database;

use RuntimeException;

final readonly class DatabaseClientBinaryResolver
{
    /** @param list<string>|null $searchDirectories */
    public function __construct(
        private DatabaseDumpBinaryValidator $dumpBinaryValidator,
        private DatabaseRestoreBinaryValidator $restoreBinaryValidator,
        private ?array $searchDirectories = null,
    ) {}

    public function resolveDump(string $driver): ?string
    {
        $names = match ($driver) {
            'mysql' => ['mysqldump'],
            'mariadb' => ['mariadb-dump', 'mysqldump'],
            default => throw new RuntimeException("Database dump binaries are unsupported for driver {$driver}."),
        };

        return $this->resolve(
            $driver,
            $names,
            'assestme.backup.dump_binary',
            $this->dumpBinaryValidator->validate(...),
        );
    }

    public function resolveRestore(string $driver): ?string
    {
        $names = match ($driver) {
            'mysql' => ['mysql'],
            'mariadb' => ['mariadb', 'mysql'],
            default => throw new RuntimeException("Database restore binaries are unsupported for driver {$driver}."),
        };

        return $this->resolve(
            $driver,
            $names,
            'assestme.backup.restore_binary',
            $this->restoreBinaryValidator->validate(...),
        );
    }

    /**
     * @param  list<string>  $names
     * @param  callable(string, string): string  $validator
     */
    private function resolve(
        string $driver,
        array $names,
        string $overrideKey,
        callable $validator,
    ): ?string {
        $override = config($overrideKey);
        $candidates = is_string($override) && trim($override) !== ''
            ? [trim($override)]
            : [];
        foreach ($this->candidateDirectories() as $directory) {
            foreach ($names as $name) {
                $candidates[] = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$name;
            }
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            try {
                return $validator($driver, $candidate);
            } catch (RuntimeException) {
                // Invalid candidates are skipped so a later standard location can still be used.
            }
        }

        return null;
    }

    /** @return list<string> */
    private function candidateDirectories(): array
    {
        if ($this->searchDirectories !== null) {
            return $this->searchDirectories;
        }

        $directories = ['/usr/bin', '/usr/local/bin'];
        $path = getenv('PATH');

        if (is_string($path)) {
            foreach (explode(PATH_SEPARATOR, $path) as $directory) {
                if ($this->isAbsolutePath($directory)) {
                    $directories[] = rtrim($directory, DIRECTORY_SEPARATOR);
                }
            }
        }

        return array_values(array_unique($directories));
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('~^(?:[A-Za-z]:[\\\\/]|/|\\\\\\\\)~', $path) === 1;
    }
}
