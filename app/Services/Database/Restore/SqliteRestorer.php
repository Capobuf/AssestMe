<?php

declare(strict_types=1);

namespace App\Services\Database\Restore;

use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;

class SqliteRestorer implements DatabaseRestorer
{
    public function __construct(private readonly Filesystem $files) {}

    public function restore(Connection $connection, string $source): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            throw new RuntimeException('The SQLite restorer received a non-SQLite connection.');
        }

        $target = $connection->getConfig('database');

        if (! is_string($target)
            || ! str_starts_with($target, DIRECTORY_SEPARATOR)
            || $target === ':memory:'
            || is_link($target)
            || ! is_file($target)
            || realpath(dirname($target)) !== dirname($target)) {
            throw new RuntimeException('SQLite restore requires a canonical regular file-backed database.');
        }

        $header = @file_get_contents($source, false, null, 0, 16);

        if (! str_starts_with((string) $header, "SQLite format 3\0")
            || is_link($source)
            || ! is_file($source)
            || ! is_readable($source)) {
            throw new RuntimeException('The verified SQLite restore payload is invalid.');
        }

        $identifier = bin2hex(random_bytes(12));
        $pending = dirname($target).DIRECTORY_SEPARATOR.'.assestme-restore-pending-'.$identifier;
        $previous = dirname($target).DIRECTORY_SEPARATOR.'.assestme-restore-previous-'.$identifier;
        $previousSidecars = [];
        $targetMoved = false;
        $pendingInstalled = false;

        if (! $this->files->copy($source, $pending)
            || ! chmod($pending, 0600)
            || hash_file('sha256', $pending) !== hash_file('sha256', $source)) {
            $this->files->delete($pending);

            throw new RuntimeException('The SQLite restore payload could not be staged securely.');
        }

        try {
            if (! $this->files->move($target, $previous)) {
                throw new RuntimeException('The current SQLite database could not be staged for compensation.');
            }

            $targetMoved = true;

            foreach (['-wal', '-shm'] as $suffix) {
                $sidecar = $target.$suffix;

                if (! is_file($sidecar)) {
                    continue;
                }

                $previousSidecar = $previous.$suffix;

                if (! $this->files->move($sidecar, $previousSidecar)) {
                    throw new RuntimeException("SQLite sidecar could not be staged: {$suffix}");
                }

                $previousSidecars[$sidecar] = $previousSidecar;
            }

            if (! $this->files->move($pending, $target)) {
                throw new RuntimeException('The staged SQLite database could not be installed.');
            }

            $pendingInstalled = true;

            if (! chmod($target, 0600)) {
                throw new RuntimeException('The restored SQLite database permissions could not be restricted.');
            }

            foreach ($previousSidecars as $previousSidecar) {
                if (! $this->files->delete($previousSidecar)) {
                    throw new RuntimeException('A previous SQLite sidecar could not be removed securely.');
                }
            }

            if (! $this->files->delete($previous)) {
                throw new RuntimeException('The previous SQLite database could not be removed securely.');
            }
        } catch (Throwable) {
            $compensated = true;

            if ($pendingInstalled) {
                $compensated = $this->files->delete($target);
            }

            if ($targetMoved) {
                $compensated = is_file($previous)
                    && $this->files->move($previous, $target)
                    && $compensated;
            }

            foreach ($previousSidecars as $targetSidecar => $previousSidecar) {
                if (is_file($previousSidecar)) {
                    $compensated = $this->files->move($previousSidecar, $targetSidecar) && $compensated;
                }
            }

            if (is_file($pending)) {
                $compensated = $this->files->delete($pending) && $compensated;
            }

            throw new RuntimeException($compensated
                ? 'SQLite restore failed; the prior database file was reinstated.'
                : 'SQLite restore and local file compensation both failed.');
        }
    }
}
