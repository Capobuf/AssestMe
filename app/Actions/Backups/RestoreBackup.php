<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Data\Backups\BackupManifest;
use App\Services\Database\DatabaseServerIdentityResolver;
use App\Services\Database\Restore\DatabaseRestorer;
use App\Services\Database\Restore\DatabaseRestorerResolver;
use App\Services\Database\Restore\DatabaseRestoreVerifier;
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
        private DatabaseRestorerResolver $databaseRestorerResolver,
        private DatabaseRestoreVerifier $databaseRestoreVerifier,
        private DatabaseServerIdentityResolver $identityResolver,
    ) {}

    public function __invoke(string $archivePath): string
    {
        if (! app()->isDownForMaintenance()) {
            throw new RuntimeException('Backup restore requires maintenance mode.');
        }

        $lock = $this->acquireLock();

        try {
            return $this->restoreLocked($archivePath);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function restoreLocked(string $archivePath): string
    {
        $database = DB::connection();
        $connectionName = $database->getName();
        $privateStoragePath = $this->privateStoragePath();
        $workingDirectory = storage_path('framework/assestme-restore/work-'.bin2hex(random_bytes(12)));
        $extracted = $workingDirectory.DIRECTORY_SEPARATOR.'incoming';
        $compensation = $workingDirectory.DIRECTORY_SEPARATOR.'compensation';
        $rollbackStorage = $workingDirectory.DIRECTORY_SEPARATOR.'rollback-private';
        $cleanupWorkingDirectory = true;
        $mutationStarted = false;
        $storageMoved = false;
        $storageInstalled = false;
        $safetyBackup = null;
        $restorer = null;

        $this->files->ensureDirectoryExists($extracted, 0700, true);
        $this->files->ensureDirectoryExists($compensation, 0700, true);

        try {
            $manifest = $this->verifyBackup->extractVerified($archivePath, $extracted);
            $this->assertArchiveMatchesConnection($manifest, $database);
            $incomingDatabase = $this->databasePayloadPath($extracted, $manifest);
            $incomingStorage = $extracted.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'private';
            $this->files->ensureDirectoryExists($incomingStorage, 0700, true);
            $safetyBackup = $this->createSafetyBackup($database);
            $restorer = $this->databaseRestorerResolver->resolve($database);
            $mutationStarted = true;

            if ($this->files->isDirectory($privateStoragePath)) {
                if (! $this->files->moveDirectory($privateStoragePath, $rollbackStorage)) {
                    throw new RuntimeException('Current private storage could not be staged for compensation.');
                }

                $storageMoved = true;
            }

            $this->files->ensureDirectoryExists(dirname($privateStoragePath), 0700, true);

            if (! $this->files->moveDirectory($incomingStorage, $privateStoragePath)) {
                throw new RuntimeException('Restored private storage could not be installed.');
            }

            $storageInstalled = true;
            $database = $this->importAndReconnect($restorer, $database, $incomingDatabase, $connectionName);
            $this->databaseRestoreVerifier->verify($database);

            return $safetyBackup;
        } catch (Throwable $restoreFailure) {
            if (! $mutationStarted || $safetyBackup === null) {
                throw $restoreFailure;
            }

            try {
                $safetyManifest = $this->verifyBackup->extractVerified($safetyBackup, $compensation);
                $this->assertArchiveMatchesConnection($safetyManifest, $database);
                $safetyDatabase = $this->databasePayloadPath($compensation, $safetyManifest);
                $restorer ??= $this->databaseRestorerResolver->resolve($database);
                $database = $this->importAndReconnect($restorer, $database, $safetyDatabase, $connectionName);
                $this->reinstatePrivateStorage(
                    $privateStoragePath,
                    $rollbackStorage,
                    $storageInstalled,
                    $storageMoved,
                );
                $this->databaseRestoreVerifier->verify($database);
            } catch (Throwable) {
                $cleanupWorkingDirectory = false;

                throw new RuntimeException(sprintf(
                    'Backup restore and safety compensation both failed. Source: %s; safety: %s; recovery files: %s',
                    $archivePath,
                    $safetyBackup,
                    $workingDirectory,
                ));
            }

            throw new RuntimeException(
                "Backup restore failed; the safety backup was reinstated: {$safetyBackup}",
                previous: $restoreFailure,
            );
        } finally {
            if ($cleanupWorkingDirectory && ! $this->deleteDirectoryIfPresent($workingDirectory)) {
                throw new RuntimeException(
                    "Backup restore working files could not be removed securely: {$workingDirectory}",
                );
            }
        }
    }

    private function createSafetyBackup(Connection $database): string
    {
        $root = (string) config('assestme.backup.root');
        $this->assertAbsolutePath($root, 'Backup root');
        $path = rtrim($root, '/\\').DIRECTORY_SEPARATOR.'assestme-safety-'.now('UTC')->format('Ymd-His-u').'.tar.gz';

        return ($this->createBackup)($path, false, $database);
    }

    private function importAndReconnect(
        DatabaseRestorer $restorer,
        Connection $database,
        string $source,
        string $connectionName,
    ): Connection {
        DB::purge($connectionName);
        $restorer->restore($database, $source);
        DB::purge($connectionName);

        return DB::connection($connectionName);
    }

    private function reinstatePrivateStorage(
        string $privateStoragePath,
        string $rollbackStorage,
        bool $storageInstalled,
        bool $storageMoved,
    ): void {
        if ($storageInstalled && $this->files->isDirectory($privateStoragePath)) {
            if (! $this->files->deleteDirectory($privateStoragePath)
                || is_dir($privateStoragePath)) {
                throw new RuntimeException('Failed restored private storage could not be removed.');
            }
        }

        if ($storageMoved) {
            if (! $this->files->isDirectory($rollbackStorage)
                || ! $this->files->moveDirectory($rollbackStorage, $privateStoragePath)) {
                throw new RuntimeException('Original private storage could not be reinstated.');
            }
        }
    }

    private function assertArchiveMatchesConnection(BackupManifest $manifest, Connection $database): void
    {
        $driver = $database->getDriverName();

        if ($manifest->database->driver !== $driver) {
            throw new RuntimeException(
                "Backup driver {$manifest->database->driver} does not match configured driver {$driver}.",
            );
        }

        $product = $driver === 'sqlite'
            ? 'SQLite'
            : $this->identityResolver->resolve($database)['product'];

        if ($manifest->database->product !== $product) {
            throw new RuntimeException(
                "Backup product {$manifest->database->product} does not match configured product {$product}.",
            );
        }
    }

    private function databasePayloadPath(string $root, BackupManifest $manifest): string
    {
        $path = $root.DIRECTORY_SEPARATOR.str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $manifest->database->path,
        );

        if (! is_file($path) || is_link($path) || ! is_readable($path)) {
            throw new RuntimeException('The verified backup database payload is unavailable.');
        }

        return $path;
    }

    private function privateStoragePath(): string
    {
        $path = (string) config('assestme.backup.private_storage_path');
        $this->assertAbsolutePath($path, 'Private storage');

        return rtrim($path, '/\\');
    }

    /** @return resource */
    private function acquireLock()
    {
        $root = storage_path('framework/assestme-restore');
        $this->files->ensureDirectoryExists($root, 0700, true);

        if (is_link($root) || realpath($root) !== $root) {
            throw new RuntimeException('Backup restore runtime directory is not canonical.');
        }

        $path = $root.DIRECTORY_SEPARATOR.'restore.lock';
        $handle = @fopen($path, 'c+');

        if ($handle === false || ! chmod($path, 0600)) {
            if (is_resource($handle)) {
                fclose($handle);
            }

            throw new RuntimeException('Backup restore lock could not be created securely.');
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            throw new RuntimeException('Another backup restore is already running.');
        }

        return $handle;
    }

    private function deleteDirectoryIfPresent(string $path): bool
    {
        if (! is_dir($path) && ! is_link($path)) {
            return true;
        }

        return ! is_link($path)
            && $this->files->deleteDirectory($path)
            && ! file_exists($path);
    }

    private function assertAbsolutePath(string $path, string $label): void
    {
        if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/|\\\\\\\\)~', $path) !== 1) {
            throw new InvalidArgumentException("{$label} path must be absolute: {$path}");
        }
    }
}
