<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final readonly class RestoreBackup
{
    public function __construct(
        private Filesystem $files,
        private CreateBackup $createBackup,
        private VerifyBackup $verifyBackup,
    ) {}

    public function handle(string $archivePath): string
    {
        if (! app()->isDownForMaintenance()) {
            throw new RuntimeException('Backup restore requires maintenance mode.');
        }

        $database = DB::connection();
        $databasePath = $this->databasePath($database);
        $privateStoragePath = $this->privateStoragePath();
        $workingDirectory = storage_path('framework/assestme-restore/'.bin2hex(random_bytes(12)));
        $extracted = $workingDirectory.DIRECTORY_SEPARATOR.'extracted';
        $rollback = $workingDirectory.DIRECTORY_SEPARATOR.'rollback';
        $this->files->ensureDirectoryExists($extracted, 0700, true);
        $this->files->ensureDirectoryExists($rollback, 0700, true);

        try {
            $this->verifyBackup->extractVerified($archivePath, $extracted);
            $this->files->ensureDirectoryExists(
                $extracted.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'private',
                0700,
                true,
            );
            $safetyBackup = $this->createSafetyBackup();
            $this->replaceTargets($databasePath, $privateStoragePath, $extracted, $rollback);

            return $safetyBackup;
        } finally {
            $this->files->deleteDirectory($workingDirectory);
        }
    }

    private function createSafetyBackup(): string
    {
        $root = (string) config('assestme.backup.root');
        $this->assertAbsolutePath($root, 'Backup root');
        $path = rtrim($root, '/\\').DIRECTORY_SEPARATOR.'assestme-safety-'.now('UTC')->format('Ymd-His-u').'.tar.gz';

        return $this->createBackup->handle($path, false);
    }

    private function replaceTargets(
        string $databasePath,
        string $privateStoragePath,
        string $extracted,
        string $rollback,
    ): void {
        $stagedDatabase = $extracted.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite';
        $stagedStorage = $extracted.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'private';
        $rollbackDatabase = $rollback.DIRECTORY_SEPARATOR.'database.sqlite';
        $rollbackStorage = $rollback.DIRECTORY_SEPARATOR.'private';
        $storageInstalled = false;
        $databaseMoved = false;
        $storageMoved = false;
        $sidecars = [];

        if (! is_file($stagedDatabase) || ! $this->files->isDirectory($stagedStorage)) {
            throw new RuntimeException('The verified backup does not contain both database and private storage.');
        }

        DB::purge();

        try {
            $this->files->ensureDirectoryExists(dirname($databasePath), 0700, true);
            $this->files->ensureDirectoryExists(dirname($privateStoragePath), 0700, true);

            if ($this->files->isDirectory($privateStoragePath)) {
                if (! $this->files->moveDirectory($privateStoragePath, $rollbackStorage)) {
                    throw new RuntimeException('Current private storage could not be staged for rollback.');
                }

                $storageMoved = true;
            }

            if (is_file($databasePath)) {
                if (! $this->files->move($databasePath, $rollbackDatabase)) {
                    throw new RuntimeException('Current SQLite database could not be staged for rollback.');
                }

                $databaseMoved = true;
            }

            foreach (['-wal', '-shm'] as $suffix) {
                $sidecar = $databasePath.$suffix;

                if (is_file($sidecar)) {
                    $rollbackSidecar = $rollback.DIRECTORY_SEPARATOR.'database.sqlite'.$suffix;

                    if (! $this->files->move($sidecar, $rollbackSidecar)) {
                        throw new RuntimeException("SQLite sidecar could not be staged for rollback: {$suffix}");
                    }

                    $sidecars[$sidecar] = $rollbackSidecar;
                }
            }

            if (! $this->files->moveDirectory($stagedStorage, $privateStoragePath)) {
                throw new RuntimeException('Restored private storage could not be installed.');
            }

            $storageInstalled = true;

            if (! $this->files->move($stagedDatabase, $databasePath)) {
                throw new RuntimeException('Restored SQLite database could not be installed.');
            }

        } catch (Throwable $exception) {
            DB::purge();

            if ($databaseMoved && is_file($databasePath)) {
                $this->files->delete($databasePath);
            }

            if ($storageInstalled && $this->files->isDirectory($privateStoragePath)) {
                $this->files->deleteDirectory($privateStoragePath);
            }

            if ($databaseMoved && is_file($rollbackDatabase)) {
                $this->files->move($rollbackDatabase, $databasePath);
            }

            if ($storageMoved && $this->files->isDirectory($rollbackStorage)) {
                $this->files->moveDirectory($rollbackStorage, $privateStoragePath);
            }

            foreach ($sidecars as $target => $source) {
                if (is_file($source)) {
                    $this->files->move($source, $target);
                }
            }

            DB::reconnect();

            throw new RuntimeException('Backup restore failed and the original targets were reinstated.', previous: $exception);
        }

        DB::reconnect();
    }

    private function databasePath(Connection $database): string
    {
        if ($database->getDriverName() !== 'sqlite') {
            throw new RuntimeException('AssestMe restore requires the configured SQLite connection.');
        }

        $path = $database->getConfig('database');

        if (! is_string($path) || $path === '' || $path === ':memory:') {
            throw new RuntimeException('AssestMe restore requires a file-backed SQLite database.');
        }

        $this->assertAbsolutePath($path, 'SQLite database');

        return $path;
    }

    private function privateStoragePath(): string
    {
        $path = (string) config('assestme.backup.private_storage_path');
        $this->assertAbsolutePath($path, 'Private storage');

        return rtrim($path, '/\\');
    }

    private function assertAbsolutePath(string $path, string $label): void
    {
        if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/|\\\\\\\\)~', $path) !== 1) {
            throw new InvalidArgumentException("{$label} path must be absolute: {$path}");
        }
    }
}
