<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Data\Backups\BackupDatabaseData;
use App\Data\Backups\BackupFileEntry;
use App\Data\Backups\BackupManifest;
use App\Services\Database\Snapshot\DatabaseSnapshotterResolver;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
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
        private DatabaseSnapshotterResolver $databaseSnapshotterResolver,
    ) {}

    public function __invoke(
        ?string $output = null,
        bool $prune = true,
        ?Connection $database = null,
    ): string {
        $output ??= $this->defaultOutputPath();
        $managedOutput = $this->isManagedOutput($output);
        $this->assertAbsolutePath($output, 'Backup output');
        $this->assertArchiveExtension($output);

        if ($this->files->exists($output) || is_link($output)) {
            throw new InvalidArgumentException("Backup output already exists: {$output}");
        }

        $database ??= DB::connection();
        $privateStoragePath = $this->privateStoragePath();
        $this->assertDistinctOutput($output, $database, $privateStoragePath);

        $workingDirectory = storage_path('framework/assestme-backups/'.bin2hex(random_bytes(12)));
        $stage = $workingDirectory.DIRECTORY_SEPARATOR.'stage';
        $archiveCreated = false;

        try {
            $this->files->ensureDirectoryExists($stage, 0700, true);
            $this->files->ensureDirectoryExists(dirname($output), 0700, true);
            $snapshot = $this->databaseSnapshotterResolver
                ->resolve($database)
                ->createSnapshot($database, $stage);
            $this->copyPrivateStorage($privateStoragePath, $stage);
            $createdAt = now('UTC')->toIso8601String();
            $version = (string) config('assestme.version', 'development');
            $this->writeMetadata($database, $snapshot, $stage, $createdAt, $version);
            $this->writeManifest($snapshot, $stage, $createdAt, $version);
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
            if ($archiveCreated && ! $this->deleteFileIfPresent($output)) {
                throw new RuntimeException('The failed backup archive could not be removed securely.');
            }

            throw $exception;
        } finally {
            if (! $this->deleteDirectoryIfPresent($workingDirectory)) {
                if ($archiveCreated) {
                    $this->deleteFileIfPresent($output);
                }

                throw new RuntimeException('The backup working directory could not be removed securely.');
            }
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

    private function privateStoragePath(): string
    {
        $path = (string) config('assestme.backup.private_storage_path');
        $this->assertAbsolutePath($path, 'Private storage');

        return rtrim($path, '/\\');
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
    private function writeMetadata(
        Connection $database,
        BackupDatabaseData $snapshot,
        string $stage,
        string $createdAt,
        string $version,
    ): void {
        $settingsTablePresent = $database->getSchemaBuilder()->hasTable('settings');
        $metadata = [
            'format' => 'assestme-backup',
            'schema_version' => 2,
            'created_at' => $createdAt,
            'application' => [
                'version' => $version,
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
            ],
            'database' => $snapshot->toArray(),
            'settings' => [
                'table_present' => $settingsTablePresent,
                'record_count' => $settingsTablePresent ? $database->table('settings')->count() : 0,
            ],
        ];

        $this->writeFile(
            $stage.DIRECTORY_SEPARATOR.'metadata.json',
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }

    /** @throws JsonException */
    private function writeManifest(
        BackupDatabaseData $snapshot,
        string $stage,
        string $createdAt,
        string $version,
    ): void {
        $entries = [];

        foreach ($this->files->allFiles($stage, true) as $file) {
            $entries[] = $this->manifestEntry($stage, $file);
        }

        usort(
            $entries,
            static fn (BackupFileEntry $left, BackupFileEntry $right): int => $left->path <=> $right->path,
        );

        $manifest = new BackupManifest(2, $createdAt, $version, $snapshot, $entries);
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

            if (! chmod($tarPath.'.gz', 0600)) {
                throw new RuntimeException('The staged backup archive permissions could not be restricted.');
            }

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

    private function assertDistinctOutput(
        string $output,
        Connection $database,
        string $privateStorage,
    ): void {
        $canonicalOutput = $this->canonicalProspectivePath($output, 'Backup output', true);
        $canonicalStorage = $this->canonicalProspectivePath($privateStorage, 'Private storage', false);
        $canonicalPublic = $this->canonicalProspectivePath(public_path(), 'Public directory', false);
        $databasePath = $database->getDriverName() === 'sqlite'
            ? $database->getConfig('database')
            : null;
        $canonicalDatabase = is_string($databasePath) && $databasePath !== ':memory:'
            ? $this->canonicalProspectivePath($databasePath, 'SQLite database', false)
            : null;

        if (($canonicalDatabase !== null && $canonicalOutput === $canonicalDatabase)
            || $this->pathEqualsOrIsWithin($canonicalOutput, $canonicalStorage)
            || $this->pathEqualsOrIsWithin($canonicalOutput, $canonicalPublic)) {
            throw new InvalidArgumentException(
                'Backup output must be outside the public directory, database, and private storage paths.',
            );
        }
    }

    private function canonicalProspectivePath(
        string $path,
        string $label,
        bool $rejectSymbolicAncestors,
    ): string {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');

        if (! str_starts_with($normalized, '/')
            || preg_match('/[\x00-\x1F\x7F]/', $normalized) === 1) {
            throw new InvalidArgumentException("{$label} path is not a valid canonical absolute path: {$path}");
        }

        $segments = explode('/', substr($normalized, 1));

        if (array_intersect($segments, ['', '.', '..']) !== []) {
            throw new InvalidArgumentException("{$label} path is not canonical: {$path}");
        }

        $candidate = '/'.implode('/', $segments);
        $suffix = [];

        while (! file_exists($candidate) && ! is_link($candidate)) {
            array_unshift($suffix, basename($candidate));
            $parent = dirname($candidate);

            if ($parent === $candidate) {
                throw new InvalidArgumentException("{$label} path could not be resolved safely: {$path}");
            }

            $candidate = $parent;
        }

        if (is_link($candidate) && $rejectSymbolicAncestors) {
            throw new InvalidArgumentException("{$label} path must not use symbolic links: {$path}");
        }

        $resolvedAncestor = realpath($candidate);

        if ($resolvedAncestor === false
            || ($suffix !== [] && ! is_dir($resolvedAncestor))
            || ($suffix === [] && ! is_dir($resolvedAncestor) && ! is_file($resolvedAncestor))) {
            throw new InvalidArgumentException("{$label} path has no valid directory ancestor: {$path}");
        }

        if ($rejectSymbolicAncestors
            && rtrim(str_replace('\\', '/', $resolvedAncestor), '/') !== rtrim($candidate, '/')) {
            throw new InvalidArgumentException("{$label} path must not use symbolic links: {$path}");
        }

        return rtrim(str_replace('\\', '/', $resolvedAncestor), '/')
            .($suffix === [] ? '' : '/'.implode('/', $suffix));
    }

    private function pathEqualsOrIsWithin(string $path, string $root): bool
    {
        $root = rtrim($root, '/');

        return $path === $root || str_starts_with($path, $root.'/');
    }

    private function deleteFileIfPresent(string $path): bool
    {
        if (! file_exists($path) && ! is_link($path)) {
            return true;
        }

        return $this->files->delete($path)
            && ! file_exists($path)
            && ! is_link($path);
    }

    private function deleteDirectoryIfPresent(string $path): bool
    {
        if (! is_dir($path) && ! is_link($path)) {
            return true;
        }

        if (is_link($path) || ! $this->files->deleteDirectory($path)) {
            return false;
        }

        return ! file_exists($path);
    }
}
