<?php

declare(strict_types=1);

namespace App\Actions\Storage;

use App\Enums\DeletionOperationStatus;
use App\Enums\DeletionPolicy;
use App\Models\DeletionOperation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final readonly class DeleteEntityAccordingToPolicy
{
    public function __construct(private Filesystem $files) {}

    /**
     * @param  list<string>  $privateRelativePaths
     */
    public function handle(
        Model $entity,
        DeletionPolicy $policy,
        array $privateRelativePaths = [],
    ): ?DeletionOperation {
        if (! $entity->exists || $entity->getKey() === null) {
            throw new InvalidArgumentException('Only a persisted entity can be deleted.');
        }

        if ($policy === DeletionPolicy::Archive) {
            $entity->deleteOrFail();

            return null;
        }

        return $this->permanentlyDelete($entity, $privateRelativePaths);
    }

    /**
     * @param  list<string>  $privateRelativePaths
     */
    private function permanentlyDelete(Model $entity, array $privateRelativePaths): DeletionOperation
    {
        $uuid = (string) Str::uuid();
        $privateRoot = $this->privateRoot();
        $trashRoot = $this->trashRoot($privateRoot);
        $operationTrashPath = $trashRoot.DIRECTORY_SEPARATOR.$uuid;
        $manifest = $this->buildManifest($privateRoot, $operationTrashPath, $privateRelativePaths);

        $operation = DeletionOperation::query()->create([
            'uuid' => $uuid,
            'entity_type' => $entity::class,
            'entity_id' => (int) $entity->getKey(),
            'status' => DeletionOperationStatus::Staged,
            'trash_path' => $this->relativePath($privateRoot, $operationTrashPath),
            'manifest' => $manifest,
        ]);

        try {
            $this->stageFiles($privateRoot, $manifest);
        } catch (Throwable $exception) {
            $this->compensateStagingFailure($operation, $privateRoot, $manifest, $exception);

            throw $exception;
        }

        try {
            DB::transaction(function () use ($entity): void {
                $affected = $entity->newModelQuery()
                    ->whereKey($entity->getKey())
                    ->forceDelete();

                if ($affected !== 1) {
                    throw new RuntimeException('The permanent database deletion did not affect exactly one entity.');
                }
            });
        } catch (Throwable $exception) {
            $this->compensateDatabaseFailure($operation, $privateRoot, $manifest, $exception);

            throw $exception;
        }

        $operation->update([
            'status' => DeletionOperationStatus::Committed,
            'error_text' => null,
        ]);

        if ($this->files->isDirectory($operationTrashPath) && ! $this->files->deleteDirectory($operationTrashPath)) {
            $operation->update([
                'status' => DeletionOperationStatus::CleanupFailed,
                'error_text' => 'The committed trash directory could not be removed.',
            ]);

            return $operation->refresh();
        }

        $operation->update([
            'status' => DeletionOperationStatus::Cleaned,
            'error_text' => null,
        ]);

        return $operation->refresh();
    }

    private function privateRoot(): string
    {
        $root = rtrim((string) config('assestme.backup.private_storage_path'), '/\\');

        if (! $this->isAbsolutePath($root)) {
            throw new InvalidArgumentException('The private storage path must be absolute.');
        }

        $this->files->ensureDirectoryExists($root, 0700, true);

        return $root;
    }

    private function trashRoot(string $privateRoot): string
    {
        $root = rtrim((string) config('assestme.deletion.trash_root'), '/\\');

        if (! $this->isAbsolutePath($root)) {
            throw new InvalidArgumentException('The deletion trash path must be absolute.');
        }

        $normalizedPrivate = $this->normalizeAbsolutePath($privateRoot).'/';
        $normalizedTrash = $this->normalizeAbsolutePath($root).'/';

        if (! str_starts_with($normalizedTrash, $normalizedPrivate)) {
            throw new InvalidArgumentException('The deletion trash path must stay inside private storage.');
        }

        $this->files->ensureDirectoryExists($root, 0700, true);

        return $root;
    }

    /**
     * @param  list<string>  $relativePaths
     * @return list<array{source: string, trash: string, size: int, sha256: string}>
     */
    private function buildManifest(string $privateRoot, string $operationTrashPath, array $relativePaths): array
    {
        $manifest = [];
        $seen = [];

        foreach ($relativePaths as $relativePath) {
            $normalized = $this->normalizeRelativePath($relativePath);

            if (isset($seen[$normalized])) {
                throw new InvalidArgumentException("Duplicate deletion file path: {$normalized}");
            }

            $seen[$normalized] = true;
            $source = $privateRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalized);

            if (! $this->files->isFile($source) || is_link($source)) {
                throw new RuntimeException("Referenced private file is missing or unsupported: {$normalized}");
            }

            $hash = hash_file('sha256', $source);

            if ($hash === false) {
                throw new RuntimeException("Referenced private file could not be hashed: {$normalized}");
            }

            $trash = $operationTrashPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalized);
            $manifest[] = [
                'source' => $normalized,
                'trash' => $this->relativePath($privateRoot, $trash),
                'size' => $this->files->size($source),
                'sha256' => $hash,
            ];
        }

        return $manifest;
    }

    /** @param list<array{source: string, trash: string, size: int, sha256: string}> $manifest */
    private function stageFiles(string $privateRoot, array $manifest): void
    {
        foreach ($manifest as $entry) {
            $source = $this->absoluteFromRelative($privateRoot, $entry['source']);
            $trash = $this->absoluteFromRelative($privateRoot, $entry['trash']);
            $this->files->ensureDirectoryExists(dirname($trash), 0700, true);

            if (! $this->files->move($source, $trash)) {
                throw new RuntimeException("Private file could not be staged for deletion: {$entry['source']}");
            }
        }
    }

    /** @param list<array{source: string, trash: string, size: int, sha256: string}> $manifest */
    private function restoreFiles(string $privateRoot, array $manifest): void
    {
        foreach (array_reverse($manifest) as $entry) {
            $source = $this->absoluteFromRelative($privateRoot, $entry['source']);
            $trash = $this->absoluteFromRelative($privateRoot, $entry['trash']);

            if (! $this->files->exists($trash)) {
                continue;
            }

            $this->files->ensureDirectoryExists(dirname($source), 0700, true);

            if ($this->files->exists($source) || ! $this->files->move($trash, $source)) {
                throw new RuntimeException("Staged private file could not be restored: {$entry['source']}");
            }
        }
    }

    /** @param list<array{source: string, trash: string, size: int, sha256: string}> $manifest */
    private function compensateStagingFailure(
        DeletionOperation $operation,
        string $privateRoot,
        array $manifest,
        Throwable $original,
    ): void {
        try {
            $this->restoreFiles($privateRoot, $manifest);
            $operation->update([
                'status' => DeletionOperationStatus::Restored,
                'error_text' => $original->getMessage(),
            ]);
        } catch (Throwable $restoreException) {
            $operation->update([
                'error_text' => $original->getMessage().' Restore failed: '.$restoreException->getMessage(),
            ]);

            throw new RuntimeException('Deletion staging failed and its filesystem compensation also failed.', previous: $restoreException);
        }
    }

    /** @param list<array{source: string, trash: string, size: int, sha256: string}> $manifest */
    private function compensateDatabaseFailure(
        DeletionOperation $operation,
        string $privateRoot,
        array $manifest,
        Throwable $original,
    ): void {
        try {
            $this->restoreFiles($privateRoot, $manifest);
            $operation->update([
                'status' => DeletionOperationStatus::Restored,
                'error_text' => $original->getMessage(),
            ]);
        } catch (Throwable $restoreException) {
            $operation->update([
                'error_text' => $original->getMessage().' Restore failed: '.$restoreException->getMessage(),
            ]);

            throw new RuntimeException('Database deletion failed and its filesystem compensation also failed.', previous: $restoreException);
        }
    }

    private function normalizeRelativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', trim($path));
        $segments = explode('/', $normalized);

        if ($normalized === '' || $this->isAbsolutePath($normalized) || in_array('..', $segments, true) || in_array('.', $segments, true)) {
            throw new InvalidArgumentException("Invalid private relative path: {$path}");
        }

        return implode('/', array_values(array_filter($segments, static fn (string $segment): bool => $segment !== '')));
    }

    private function absoluteFromRelative(string $root, string $relative): string
    {
        return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function relativePath(string $root, string $absolute): string
    {
        return str_replace('\\', '/', ltrim(substr($absolute, strlen($root)), '/\\'));
    }

    private function normalizeAbsolutePath(string $path): string
    {
        return strtolower(rtrim(str_replace('\\', '/', $path), '/'));
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('~^(?:[A-Za-z]:[\\\\/]|/|\\\\\\\\)~', $path) === 1;
    }
}
