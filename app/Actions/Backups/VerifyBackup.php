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
        $archivePath = realpath($archivePath) ?: $archivePath;

        if (! is_file($archivePath) || ! is_readable($archivePath)) {
            throw new InvalidArgumentException("Backup archive is not readable: {$archivePath}");
        }

        try {
            $archive = new PharData($archivePath);
            $archiveFiles = $this->archiveFiles($archive);

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

            foreach ($manifest->files() as $file) {
                $this->verifyFile($archive, $file);
            }

            return $manifest;
        } catch (Throwable $exception) {
            if ($exception instanceof InvalidArgumentException || $exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('The backup archive could not be verified.', previous: $exception);
        }
    }

    public function extractVerified(string $archivePath, string $destination): BackupManifest
    {
        $manifest = $this->handle($archivePath);
        $archivePath = realpath($archivePath) ?: $archivePath;
        $archive = new PharData($archivePath);

        if (! $archive->extractTo($destination, null, true)) {
            throw new RuntimeException('The verified backup archive could not be extracted.');
        }

        foreach ($manifest->files() as $file) {
            $path = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file->path);

            if (! is_file($path)
                || filesize($path) !== $file->size
                || hash_file('sha256', $path) !== $file->sha256) {
                throw new RuntimeException("Extracted backup file failed verification: {$file->path}");
            }
        }

        return $manifest;
    }

    /** @return list<string> */
    private function archiveFiles(PharData $archive): array
    {
        $files = [];
        $this->collectArchiveFiles($archive, '', $files);

        sort($files);

        return $files;
    }

    /**
     * @param  RecursiveIterator<mixed, \SplFileInfo>  $iterator
     * @param  list<string>  $files
     */
    private function collectArchiveFiles(RecursiveIterator $iterator, string $prefix, array &$files): void
    {
        foreach ($iterator as $item) {
            $path = $prefix.$item->getFilename();

            if ($iterator->hasChildren()) {
                $this->collectArchiveFiles($iterator->getChildren(), $path.'/', $files);

                continue;
            }

            if ($item->isFile()) {
                $files[] = $path;
            }
        }
    }

    private function verifyManifestPaths(BackupManifest $manifest): void
    {
        $paths = $manifest->paths();

        if (count($paths) !== count(array_unique($paths))
            || ! in_array('database/database.sqlite', $paths, true)
            || ! in_array('metadata.json', $paths, true)) {
            throw new RuntimeException('The backup manifest is incomplete or contains duplicate paths.');
        }

        foreach ($paths as $path) {
            $segments = explode('/', $path);
            $allowed = $path === 'database/database.sqlite'
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
            || $hash !== $file->sha256) {
            throw new RuntimeException("Backup file failed verification: {$file->path}");
        }
    }
}
