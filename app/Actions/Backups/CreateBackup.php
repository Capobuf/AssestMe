<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Data\Backups\BackupFileEntry;
use App\Data\Backups\BackupManifest;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use JsonException;
use Phar;
use PharData;
use RuntimeException;
use SplFileInfo;
use Throwable;

final readonly class CreateBackup
{
    public function __construct(
        private Filesystem $files,
        private PruneBackups $pruneBackups,
        private VerifyBackup $verifyBackup,
    ) {}

    public function __invoke(?string $output = null, bool $prune = true): string
    {
        $output ??= $this->defaultOutputPath();
        $managedOutput = $this->isManagedOutput($output);
        $this->assertAbsolutePath($output, 'Backup output');
        $this->assertArchiveExtension($output);

        if ($this->files->exists($output)) {
            throw new InvalidArgumentException("Backup output already exists: {$output}");
        }

        $database = DB::connection();
        $databasePath = $this->databasePath($database);
        $privateStoragePath = $this->privateStoragePath();
        $this->assertDistinctOutput($output, $databasePath, $privateStoragePath);

        $workingDirectory = storage_path('framework/assestme-backups/'.bin2hex(random_bytes(12)));
        $stage = $workingDirectory.DIRECTORY_SEPARATOR.'stage';
        $archiveCreated = false;

        $this->files->ensureDirectoryExists($stage, 0700, true);
        $this->files->ensureDirectoryExists(dirname($output), 0700, true);

        try {
            $this->snapshotDatabase($database, $databasePath, $stage);
            $this->copyPrivateStorage($privateStoragePath, $stage);
            $createdAt = now('UTC')->toIso8601String();
            $version = (string) config('assestme.version', 'development');
            $this->writeMetadata($stage, $createdAt, $version);
            $this->writeManifest($stage, $createdAt, $version);
            $this->createArchive($stage, $output, $workingDirectory);
            $archiveCreated = true;
            $this->verifyBackup->handle($output);

            if (! chmod($output, 0600)) {
                throw new RuntimeException('The backup archive permissions could not be restricted to mode 0600.');
            }

            if ($managedOutput && $prune) {
                $this->pruneBackups->handle(dirname($output));
            }

            return $output;
        } catch (Throwable $exception) {
            if ($archiveCreated) {
                $this->files->delete($output);
            }

            throw $exception;
        } finally {
            $this->files->deleteDirectory($workingDirectory);
        }
    }

    private function defaultOutputPath(): string
    {
        $root = (string) config('assestme.backup.root');
        $this->assertAbsolutePath($root, 'Backup root');

        return rtrim($root, '/\\').DIRECTORY_SEPARATOR.'assestme-'.now('UTC')->format('Ymd-His-u').'.tar.gz';
    }

    private function isManagedOutput(string $output): bool
    {
        $root = (string) config('assestme.backup.root');
        $normalizedRoot = rtrim(strtolower(str_replace('\\', '/', $root)), '/');
        $normalizedDirectory = rtrim(strtolower(str_replace('\\', '/', dirname($output))), '/');

        return $normalizedDirectory === $normalizedRoot
            && preg_match('/^assestme-\d{8}-\d{6}(?:-\d+)?\.tar\.gz$/', basename($output)) === 1;
    }

    private function databasePath(Connection $database): string
    {
        if ($database->getDriverName() !== 'sqlite') {
            throw new RuntimeException('AssestMe backups require the configured SQLite connection.');
        }

        $path = $database->getConfig('database');

        if (! is_string($path) || $path === '' || $path === ':memory:') {
            throw new RuntimeException('AssestMe backups require a file-backed SQLite database.');
        }

        $this->assertAbsolutePath($path, 'SQLite database');

        if (! is_file($path)) {
            throw new RuntimeException("SQLite database does not exist: {$path}");
        }

        return $path;
    }

    private function privateStoragePath(): string
    {
        $path = (string) config('assestme.backup.private_storage_path');
        $this->assertAbsolutePath($path, 'Private storage');

        return rtrim($path, '/\\');
    }

    private function snapshotDatabase(Connection $database, string $databasePath, string $stage): void
    {
        $destination = $stage.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite';
        $this->files->ensureDirectoryExists(dirname($destination), 0700, true);
        $checkpoint = $database->selectOne('PRAGMA wal_checkpoint(TRUNCATE)');
        $checkpointColumns = is_object($checkpoint) ? array_values((array) $checkpoint) : [];

        if (! isset($checkpointColumns[0]) || (int) $checkpointColumns[0] !== 0) {
            throw new RuntimeException('SQLite WAL checkpoint could not complete without a busy writer.');
        }

        $quotedDestination = $database->getPdo()->quote($destination);

        if ($quotedDestination === false) {
            throw new RuntimeException('The SQLite snapshot destination could not be quoted.');
        }

        $database->unprepared("VACUUM INTO {$quotedDestination}");

        if (! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException("SQLite VACUUM did not create a valid snapshot of {$databasePath}.");
        }
    }

    private function copyPrivateStorage(string $source, string $stage): void
    {
        $destinationRoot = $stage.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'private';
        $this->files->ensureDirectoryExists($destinationRoot, 0700, true);

        if (! $this->files->isDirectory($source)) {
            return;
        }

        foreach ($this->files->allFiles($source, true) as $file) {
            if ($file->isLink()) {
                throw new RuntimeException("Private storage contains an unsupported symbolic link: {$file->getPathname()}");
            }

            $relative = ltrim(substr($file->getPathname(), strlen($source)), '/\\');
            $destination = $destinationRoot.DIRECTORY_SEPARATOR.$relative;
            $this->files->ensureDirectoryExists(dirname($destination), 0700, true);

            if (! $this->files->copy($file->getPathname(), $destination)) {
                throw new RuntimeException("Private storage file could not be staged: {$relative}");
            }
        }
    }

    /** @throws JsonException */
    private function writeMetadata(string $stage, string $createdAt, string $version): void
    {
        $settingsTablePresent = Schema::hasTable('settings');
        $metadata = [
            'format' => 'assestme-backup',
            'schema_version' => 1,
            'created_at' => $createdAt,
            'application' => [
                'version' => $version,
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
            ],
            'settings' => [
                'table_present' => $settingsTablePresent,
                'record_count' => $settingsTablePresent ? DB::table('settings')->count() : 0,
            ],
        ];

        $this->writeFile(
            $stage.DIRECTORY_SEPARATOR.'metadata.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }

    /** @throws JsonException */
    private function writeManifest(string $stage, string $createdAt, string $version): void
    {
        $entries = [];

        foreach ($this->files->allFiles($stage, true) as $file) {
            $entries[] = $this->manifestEntry($stage, $file);
        }

        usort(
            $entries,
            static fn (BackupFileEntry $left, BackupFileEntry $right): int => $left->path <=> $right->path,
        );

        $manifest = new BackupManifest(1, $createdAt, $version, $entries);
        $this->writeFile($stage.DIRECTORY_SEPARATOR.'manifest.json', $manifest->toJson());
    }

    private function manifestEntry(string $stage, SplFileInfo $file): BackupFileEntry
    {
        $path = str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($stage)), '/\\'));
        $size = $file->getSize();
        $hash = hash_file('sha256', $file->getPathname());

        if ($hash === false) {
            throw new RuntimeException("Could not hash staged backup file: {$path}");
        }

        return new BackupFileEntry($path, $size, $hash);
    }

    private function createArchive(string $stage, string $output, string $workingDirectory): void
    {
        $tarPath = $workingDirectory.DIRECTORY_SEPARATOR.'archive.tar';

        try {
            $archive = new PharData($tarPath);
            $archive->addEmptyDir('database');
            $archive->addEmptyDir('storage');
            $archive->addEmptyDir('storage/private');

            foreach ($this->files->allFiles($stage, true) as $file) {
                $relative = str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($stage)), '/\\'));
                $archive->addFile($file->getPathname(), $relative);
            }

            $compressed = $archive->compress(Phar::GZ);
            unset($compressed, $archive);

            if (! $this->files->move($tarPath.'.gz', $output)) {
                throw new RuntimeException('The compressed backup archive could not be moved into place.');
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('The backup archive could not be created.', previous: $exception);
        }
    }

    private function writeFile(string $path, string $contents): void
    {
        if ($this->files->put($path, $contents, true) === false) {
            throw new RuntimeException("Backup staging file could not be written: {$path}");
        }
    }

    private function assertAbsolutePath(string $path, string $label): void
    {
        if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/|\\\\\\\\)~', $path) !== 1) {
            throw new InvalidArgumentException("{$label} path must be absolute: {$path}");
        }
    }

    private function assertArchiveExtension(string $output): void
    {
        if (! str_ends_with(strtolower($output), '.tar.gz')) {
            throw new InvalidArgumentException('Backup output must use the .tar.gz extension.');
        }
    }

    private function assertDistinctOutput(string $output, string $database, string $privateStorage): void
    {
        $normalizedOutput = strtolower(str_replace('\\', '/', $output));
        $normalizedDatabase = strtolower(str_replace('\\', '/', $database));
        $normalizedStorage = rtrim(strtolower(str_replace('\\', '/', $privateStorage)), '/').'/';

        if ($normalizedOutput === $normalizedDatabase || str_starts_with($normalizedOutput, $normalizedStorage)) {
            throw new InvalidArgumentException('Backup output must be outside the database and private storage paths.');
        }
    }
}
