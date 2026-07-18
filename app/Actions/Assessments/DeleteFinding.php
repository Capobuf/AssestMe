<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\Finding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteFinding
{
    public function __construct(private readonly IncrementAssessmentVersion $incrementAssessmentVersion) {}

    public function __invoke(Finding $finding): int
    {
        $assessment = $finding->assessment()->firstOrFail();
        $expectedVersion = (int) $assessment->lock_version;

        return Cache::lock("assessment:{$assessment->getKey()}:save", 10)->block(
            5,
            fn (): int => DB::transaction(function () use ($assessment, $finding, $expectedVersion): int {
                $persistedAssessment = Assessment::query()->findOrFail($assessment->getKey());
                if ($persistedAssessment->status !== AssessmentStatus::Draft) {
                    throw ValidationException::withMessages([
                        'assessment' => __('assestme.assessments.errors.read_only'),
                    ]);
                }

                $persistedFinding = $persistedAssessment->findings()->findOrFail($finding->getKey());
                $persistedFinding->deleteOrFail();

                foreach ($persistedAssessment->findings()->get() as $index => $remaining) {
                    $remaining->forceFill(['sort_order' => $index + 1])->save();
                }

                return ($this->incrementAssessmentVersion)($assessment, $expectedVersion);
            }, attempts: 1),
        );
    }
}
