<?php

declare(strict_types=1);

namespace App\Actions\Storage;

use App\Data\Storage\StorageAuditResult;
use App\Enums\DeletionOperationStatus;
use App\Enums\EvidenceType;
use App\Models\DeletionOperation;
use App\Models\Evidence;
use Illuminate\Support\Facades\Storage;

final class AuditPrivateStorage
{
    public function handle(): StorageAuditResult
    {
        $disk = Storage::disk('local');
        $evidences = Evidence::withTrashed()->where('type', EvidenceType::File)->get();
        $referenced = $evidences->pluck('file_path')->filter()->mapWithKeys(
            static fn (string $path): array => [$path => true],
        );

        $orphanFiles = collect($disk->allFiles('clients'))
            ->filter(static fn (string $path): bool => str_contains($path, '/evidence/'))
            ->reject(static fn (string $path): bool => $referenced->has($path))
            ->sort()
            ->values()
            ->all();
        $missingFiles = [];
        $hashMismatches = [];

        foreach ($evidences as $evidence) {
            if ($evidence->file_path === null || ! $disk->exists($evidence->file_path)) {
                $missingFiles[] = "evidence:{$evidence->id}";

                continue;
            }

            $hash = hash_file('sha256', $disk->path($evidence->file_path));
            if ($hash === false || ! hash_equals((string) $evidence->sha256, $hash)) {
                $hashMismatches[] = "evidence:{$evidence->id}";
            }
        }

        $unfinishedOperations = DeletionOperation::query()
            ->whereIn('status', [DeletionOperationStatus::Staged, DeletionOperationStatus::CleanupFailed])
            ->orderBy('id')
            ->pluck('uuid')
            ->all();

        return new StorageAuditResult(
            orphanFiles: $orphanFiles,
            missingFiles: $missingFiles,
            hashMismatches: $hashMismatches,
            unfinishedOperations: $unfinishedOperations,
        );
    }
}
