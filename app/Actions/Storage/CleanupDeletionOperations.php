<?php

declare(strict_types=1);

namespace App\Actions\Storage;

use App\Data\Storage\DeletionCleanupResult;
use App\Enums\DeletionOperationStatus;
use App\Models\DeletionOperation;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use Throwable;

final readonly class CleanupDeletionOperations
{
    public function __construct(private Filesystem $files) {}

    public function handle(int $limit = 100): DeletionCleanupResult
    {
        if ($limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('Deletion cleanup limit must be between 1 and 1000.');
        }

        $operations = DeletionOperation::query()
            ->whereIn('status', [
                DeletionOperationStatus::Committed,
                DeletionOperationStatus::CleanupFailed,
            ])
            ->oldest('id')
            ->limit($limit)
            ->get();

        $cleaned = 0;
        $failed = 0;

        foreach ($operations as $operation) {
            if ($this->cleanup($operation)) {
                $cleaned++;
            } else {
                $failed++;
            }
        }

        return new DeletionCleanupResult($operations->count(), $cleaned, $failed);
    }

    private function cleanup(DeletionOperation $operation): bool
    {
        try {
            $trashPath = $this->validatedTrashPath($operation);

            if ($this->files->isDirectory($trashPath) && ! $this->files->deleteDirectory($trashPath)) {
                throw new InvalidArgumentException('The committed trash directory could not be removed.');
            }

            $operation->update([
                'status' => DeletionOperationStatus::Cleaned,
                'error_text' => null,
            ]);

            return true;
        } catch (Throwable $exception) {
            $operation->update([
                'status' => DeletionOperationStatus::CleanupFailed,
                'error_text' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function validatedTrashPath(DeletionOperation $operation): string
    {
        $privateRoot = rtrim((string) config('assestme.backup.private_storage_path'), '/\\');
        $trashRoot = rtrim((string) config('assestme.deletion.trash_root'), '/\\');

        if (! $this->isAbsolutePath($privateRoot) || ! $this->isAbsolutePath($trashRoot)) {
            throw new InvalidArgumentException('Private storage and deletion trash paths must be absolute.');
        }

        $normalizedPrivate = $this->normalizeAbsolutePath($privateRoot).'/';
        $normalizedTrash = $this->normalizeAbsolutePath($trashRoot).'/';

        if (! str_starts_with($normalizedTrash, $normalizedPrivate)) {
            throw new InvalidArgumentException('The deletion trash path must stay inside private storage.');
        }

        $expectedRelative = str_replace(
            '\\',
            '/',
            ltrim(substr($trashRoot, strlen($privateRoot)), '/\\').'/'.$operation->uuid,
        );

        if ($operation->trash_path !== $expectedRelative) {
            throw new InvalidArgumentException('The deletion operation contains an invalid trash path.');
        }

        return $trashRoot.DIRECTORY_SEPARATOR.$operation->uuid;
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
