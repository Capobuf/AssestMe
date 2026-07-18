<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Exceptions\AssessmentVersionConflict;
use App\Models\Assessment;
use Illuminate\Validation\ValidationException;

final class IncrementAssessmentVersion
{
    /**
     * This action must run while the caller holds the assessment save lock and an open transaction.
     *
     * @throws AssessmentVersionConflict
     * @throws ValidationException
     */
    public function __invoke(Assessment $assessment, int $expectedVersion): int
    {
        $appliedVersion = $expectedVersion + 1;
        $updated = Assessment::query()
            ->whereKey($assessment->getKey())
            ->where('status', AssessmentStatus::Draft)
            ->where('lock_version', $expectedVersion)
            ->update([
                'lock_version' => $appliedVersion,
                'updated_at' => now(),
            ]);

        if ($updated === 1) {
            $assessment->setAttribute('lock_version', $appliedVersion);

            return $appliedVersion;
        }

        $persisted = Assessment::query()->findOrFail($assessment->getKey());

        if ($persisted->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages([
                'assessment_id' => __('assestme.assessments.errors.read_only'),
            ]);
        }

        throw new AssessmentVersionConflict($expectedVersion, $persisted->lock_version);
    }
}
