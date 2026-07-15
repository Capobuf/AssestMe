<?php

declare(strict_types=1);

namespace App\Actions\Storage;

use App\Enums\DeletionPolicy;
use App\Models\Assessment;
use App\Models\Client;
use App\Models\DeletionOperation;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\GeneratedReport;
use App\Settings\GeneralSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final readonly class DeleteArchivableEntity
{
    public function __construct(
        private DeleteEntityAccordingToPolicy $deleteEntity,
        private GeneralSettings $settings,
    ) {}

    public function handle(Model $entity): ?DeletionOperation
    {
        if (! in_array(SoftDeletes::class, class_uses_recursive($entity), true)) {
            throw new InvalidArgumentException('The entity does not support archival deletion.');
        }

        $policy = DeletionPolicy::tryFrom($this->settings->deletion_policy);

        if (! $policy instanceof DeletionPolicy) {
            throw new InvalidArgumentException('The configured deletion policy is invalid.');
        }

        if ($entity instanceof Assessment) {
            return Cache::lock("assessment:{$entity->getKey()}:save", 10)->block(
                5,
                fn (): ?DeletionOperation => $this->delete($entity, $policy),
            );
        }

        return $this->delete($entity, $policy);
    }

    private function delete(Model $entity, DeletionPolicy $policy): ?DeletionOperation
    {
        return $this->deleteEntity->handle($entity, $policy, $this->privatePaths($entity));
    }

    /** @return list<string> */
    private function privatePaths(Model $entity): array
    {
        $paths = match (true) {
            $entity instanceof Client => [$entity->logo_path],
            $entity instanceof Assessment => $this->assessmentPrivatePaths($entity),
            default => [],
        };

        return collect($paths)
            ->filter(static fn (mixed $path): bool => is_string($path) && $path !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function assessmentPrivatePaths(Assessment $assessment): array
    {
        $findingIds = Finding::withTrashed()
            ->where('assessment_id', $assessment->getKey())
            ->pluck('id');

        $evidencePaths = Evidence::withTrashed()
            ->whereIn('finding_id', $findingIds)
            ->whereNotNull('file_path')
            ->pluck('file_path')
            ->all();

        $reportPaths = GeneratedReport::query()
            ->where('assessment_id', $assessment->getKey())
            ->pluck('file_path')
            ->all();

        return [...$evidencePaths, ...$reportPaths];
    }
}
