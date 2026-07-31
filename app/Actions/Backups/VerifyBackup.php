<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Data\Backups\BackupFileEntry;
use App\Data\Backups\BackupManifest;
use InvalidArgumentException;
use PharData;
use RecursiveIterator;
use RuntimeException;
use Throwable;

final class VerifyBackup
{
    public function handle(string $archivePath): BackupManifest
    {
        return $this->verifiedArchive($archivePath)['manifest'];
    }

    public function extractVerified(string $archivePath, string $destination): BackupManifest
    {
        $verified = $this->verifiedArchive($archivePath);
        $manifest = $verified['manifest'];
        $archive = $verified['archive'];
        $destination = $this->emptyCanonicalDestination($destination);

        if (! $archive->extractTo($destination, null, false)) {
            throw new RuntimeException('The verified backup archive could not be extracted.');
        }

        if (is_link($destination) || realpath($destination) !== $destination) {
            throw new RuntimeException('The backup extraction destination changed unexpectedly.');
        }

        foreach ($manifest->files() as $file) {
            $path = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file->path);

            if (! is_file($path)
                || is_link($path)
                || filesize($path) !== $file->size
                || hash_file('sha256', $path) !== $file->sha256) {
                throw new RuntimeException("Extracted backup file failed verification: {$file->path}");
            }
        }

        return $manifest;
    }

    /** @return array{manifest: BackupManifest, archive: PharData} */
    private function verifiedArchive(string $archivePath): array
    {
        $archivePath = realpath($archivePath) ?: $archivePath;

        if (! is_file($archivePath) || ! is_readable($archivePath)) {
            throw new InvalidArgumentException("Backup archive is not readable: {$archivePath}");
        }

        try {
            $archive = new PharData($archivePath);
            $archiveEntries = $this->archiveEntries($archive);
            $archiveFiles = $archiveEntries['files'];

            if (! in_array('manifest.json', $archiveFiles, true)) {
                throw new RuntimeException('The backup archive does not contain manifest.json.');
            }

            $manifest = BackupManifest::fromJson($archive['manifest.json']->getContent());
            $this->verifyManifestPaths($manifest);

            $expectedFiles = [...$manifest->paths(), 'manifest.json'];
            sort($expectedFiles);
            sort($archiveFiles);

            if ($expectedFiles !== $archiveFiles) {
                throw new RuntimeException('The backup archive content does not match its manifest.');
            }

            $this->verifyArchiveDirectories($archiveEntries['directories'], $expectedFiles);

            foreach ($manifest->files() as $file) {
                $this->verifyFile($archive, $file);
            }

            return [
                'manifest' => $manifest,
                'archive' => $archive,
            ];
        } catch (Throwable $exception) {
            if ($exception instanceof InvalidArgumentException || $exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('The backup archive could not be verified.', previous: $exception);
        }
    }

    private function emptyCanonicalDestination(string $destination): string
    {
        if (is_link($destination)
            || ! is_dir($destination)
            || ! is_readable($destination)
            || ! is_writable($destination)) {
            throw new InvalidArgumentException('Backup extraction requires an existing writable directory.');
        }

        $resolved = realpath($destination);
        $normalized = rtrim(str_replace('\\', '/', $destination), '/');

        if ($resolved === false
            || $resolved !== $normalized
            || preg_match('/[\x00-\x1F\x7F]/', $normalized) === 1) {
            throw new InvalidArgumentException('Backup extraction directory must be canonical and must not use symbolic links.');
        }

        $entries = scandir($resolved);

        if ($entries === false || array_values(array_diff($entries, ['.', '..'])) !== []) {
            throw new InvalidArgumentException('Backup extraction directory must be empty.');
        }

        return $resolved;
    }

    /** @return array{files: list<string>, directories: list<string>} */
    private function archiveEntries(PharData $archive): array
    {
        $files = [];
        $directories = [];
        $this->collectArchiveEntries($archive, '', $files, $directories);

        sort($files);
        sort($directories);

        return [
            'files' => $files,
            'directories' => $directories,
        ];
    }

    /**
     * @param  RecursiveIterator<mixed, \SplFileInfo>  $iterator
     * @param  list<string>  $files
     * @param  list<string>  $directories
     */
    private function collectArchiveEntries(
        RecursiveIterator $iterator,
        string $prefix,
        array &$files,
        array &$directories,
    ): void {
        foreach ($iterator as $item) {
            $path = $prefix.$item->getFilename();

            if ($item->isLink()) {
                throw new RuntimeException("The backup archive contains an unsupported symbolic link: {$path}");
            }

            if ($iterator->hasChildren()) {
                $directories[] = $path;
                $this->collectArchiveEntries($iterator->getChildren(), $path.'/', $files, $directories);

                continue;
            }

            if ($item->isFile()) {
                $files[] = $path;

                continue;
            }

            throw new RuntimeException("The backup archive contains an unsupported entry: {$path}");
        }
    }

    /**
     * @param  list<string>  $directories
     * @param  list<string>  $files
     */
    private function verifyArchiveDirectories(array $directories, array $files): void
    {
        $allowed = ['database', 'storage', 'storage/private'];

        foreach ($files as $file) {
            $segments = explode('/', $file);
            array_pop($segments);
            $path = '';

            foreach ($segments as $segment) {
                $path = $path === '' ? $segment : $path.'/'.$segment;
                $allowed[] = $path;
            }
        }

        $allowed = array_unique($allowed);

        foreach ($directories as $directory) {
            if (! in_array($directory, $allowed, true)) {
                throw new RuntimeException("Unsafe backup archive directory: {$directory}");
            }
        }
    }

    private function verifyManifestPaths(BackupManifest $manifest): void
    {
        $paths = $manifest->paths();
        $databasePath = $manifest->database->path;
        $databasePaths = array_values(array_filter(
            $paths,
            static fn (string $path): bool => str_starts_with($path, 'database/'),
        ));

        if (count($paths) !== count(array_unique($paths))
            || $databasePaths !== [$databasePath]
            || ! in_array('metadata.json', $paths, true)) {
            throw new RuntimeException('The backup manifest is incomplete or contains duplicate paths.');
        }

        foreach ($paths as $path) {
            $segments = explode('/', $path);
            $allowed = $path === $databasePath
                || $path === 'metadata.json'
                || str_starts_with($path, 'storage/private/');

            if (! $allowed
                || str_contains($path, "\0")
                || str_contains($path, '\\')
                || str_starts_with($path, '/')
                || in_array('', $segments, true)
                || in_array('.', $segments, true)
                || in_array('..', $segments, true)) {
                throw new RuntimeException("Unsafe backup manifest path: {$path}");
            }
        }
    }

    private function verifyFile(PharData $archive, BackupFileEntry $file): void
    {
        $archiveFile = $archive[$file->path];
        $hash = hash_file('sha256', $archiveFile->getPathname());

        if (! $archiveFile->isFile()
            || $archiveFile->getSize() !== $file->size
            || $hash === false
            || ! hash_equals($file->sha256, $hash)) {
            throw new RuntimeException("Backup file failed verification: {$file->path}");
        }
    }
}
