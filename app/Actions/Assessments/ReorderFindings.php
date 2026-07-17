<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Finding;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReorderFindings
{
    /**
     * This action runs inside SaveAssessmentWorkspace's signed lock and transaction.
     *
     * @param  list<int>  $orderedFindingIds
     *
     * @throws ValidationException
     */
    public function __invoke(Assessment $assessment, array $orderedFindingIds): void
    {
        DB::transaction(function () use ($assessment, $orderedFindingIds): void {
            $persisted = Assessment::query()->findOrFail($assessment->getKey());

            if ($persisted->status !== AssessmentStatus::Draft) {
                throw ValidationException::withMessages([
                    'assessment_id' => __('assestme.assessments.errors.read_only'),
                ]);
            }

            $currentIds = $persisted->findings()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
            $normalizedIds = array_values(array_unique($orderedFindingIds));
            $currentSet = $currentIds;
            $requestedSet = $normalizedIds;
            sort($currentSet);
            sort($requestedSet);

            if (count($normalizedIds) !== count($orderedFindingIds) || $currentSet !== $requestedSet) {
                throw ValidationException::withMessages([
                    'findings' => __('assestme.workspace.errors.finding_ownership'),
                ]);
            }

            foreach ($orderedFindingIds as $index => $findingId) {
                $updated = Finding::query()
                    ->where('assessment_id', $persisted->getKey())
                    ->whereKey($findingId)
                    ->update(['sort_order' => $index + 1]);

                if ($updated !== 1) {
                    throw ValidationException::withMessages([
                        "findings.{$index}.id" => __('assestme.workspace.errors.finding_ownership'),
                    ]);
                }
            }
        });
    }
}
