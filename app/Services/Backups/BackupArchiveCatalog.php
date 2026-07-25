<?php

declare(strict_types=1);

namespace App\Services\Backups;

use App\Data\Backups\BackupArchiveData;
use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use RuntimeException;
use SplFileInfo;
use Throwable;

final readonly class BackupArchiveCatalog
{
    private const BACKUP_PATTERN = '/^assestme-\d{8}-\d{6}(?:-\d+)?\.tar\.gz$/';

    private const SAFETY_PATTERN = '/^assestme-safety-\d{8}-\d{6}(?:-\d+)?\.tar\.gz$/';

    public function __construct(private Filesystem $files) {}

    /** @return list<BackupArchiveData> */
    public function all(): array
    {
        $root = $this->configuredRoot();

        if (! $this->files->isDirectory($root)) {
            return [];
        }

        $archives = [];

        foreach ($this->files->files($root) as $file) {
            if ($file->isLink() || $this->kind($file->getFilename()) === null) {
                continue;
            }

            try {
                $path = $this->resolveManagedArchive($file->getFilename());
                $archives[] = $this->archiveData($file, $path);
            } catch (InvalidArgumentException|RuntimeException) {
                // A file that changes during the directory scan is simply absent from this rendering.
                continue;
            }
        }

        usort($archives, static function (BackupArchiveData $left, BackupArchiveData $right): int {
            $timestampOrder = $right->modifiedAt->getTimestamp() <=> $left->modifiedAt->getTimestamp();

            return $timestampOrder !== 0 ? $timestampOrder : $right->name <=> $left->name;
        });

        return $archives;
    }

    public function configuredRoot(): string
    {
        $root = rtrim((string) config('assestme.backup.root'), '/\\');

        if ($root === '' || ! $this->isAbsolutePath($root)) {
            throw new InvalidArgumentException('The configured backup root must be an absolute path.');
        }

        return $root;
    }

    public function resolveManagedArchive(string $name): string
    {
        if ($name === ''
            || $name === '.'
            || $name === '..'
            || str_contains($name, "\0")
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || $this->kind($name) === null) {
            throw new InvalidArgumentException('The backup archive name is not managed by AssestMe.');
        }

        $root = $this->configuredRoot();

        if (! $this->files->isDirectory($root) || is_link($root)) {
            throw new RuntimeException('The configured backup root is not an available regular directory.');
        }

        $resolvedRoot = realpath($root);

        if ($resolvedRoot === false) {
            throw new RuntimeException('The configured backup root could not be resolved.');
        }

        $candidate = $resolvedRoot.DIRECTORY_SEPARATOR.$name;

        if (is_link($candidate) || ! is_file($candidate)) {
            throw new RuntimeException('The managed backup archive was not found as a regular file.');
        }

        $resolvedArchive = realpath($candidate);

        if ($resolvedArchive === false
            || dirname($resolvedArchive) !== $resolvedRoot
            || basename($resolvedArchive) !== $name) {
            throw new RuntimeException('The managed backup archive resolves outside the configured root.');
        }

        return $resolvedArchive;
    }

    private function archiveData(SplFileInfo $file, string $path): BackupArchiveData
    {
        try {
            $modifiedAt = CarbonImmutable::createFromTimestampUTC($file->getMTime());
            $size = $file->getSize();
        } catch (Throwable $exception) {
            throw new RuntimeException('The managed backup archive metadata could not be read.', previous: $exception);
        }

        return new BackupArchiveData(
            name: $file->getFilename(),
            absolutePath: $path,
            kind: $this->kind($file->getFilename()) ?? throw new RuntimeException('Unknown backup kind.'),
            size: $size,
            modifiedAt: $modifiedAt,
        );
    }

    private function kind(string $name): ?string
    {
        if (preg_match(self::SAFETY_PATTERN, $name) === 1) {
            return 'safety';
        }

        return preg_match(self::BACKUP_PATTERN, $name) === 1 ? 'backup' : null;
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('~^(?:[A-Za-z]:[\\\\/]|/|\\\\\\\\)~', $path) === 1;
    }
}
