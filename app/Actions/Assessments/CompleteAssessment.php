<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CompleteAssessment
{
    public function __construct(private ValidateAssessmentCompletion $validateCompletion) {}

    public function __invoke(Assessment $assessment): Assessment
    {
        return Cache::lock("assessment:{$assessment->getKey()}:save", 10)->block(5, function () use ($assessment): Assessment {
            return DB::transaction(function () use ($assessment): Assessment {
                $locked = Assessment::query()->lockForUpdate()->findOrFail($assessment->getKey());

                if ($locked->status !== AssessmentStatus::Draft) {
                    throw ValidationException::withMessages(['status' => __('assestme.assessments.errors.invalid_transition')]);
                }

                ($this->validateCompletion)($locked);

                $locked->forceFill([
                    'status' => AssessmentStatus::Completed,
                    'completed_at' => now(),
                    'lock_version' => $locked->lock_version + 1,
                ])->save();

                return $locked->refresh();
            });
        });
    }
}
