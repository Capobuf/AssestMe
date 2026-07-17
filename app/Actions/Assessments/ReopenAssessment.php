<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReopenAssessment
{
    public function __invoke(Assessment $assessment): Assessment
    {
        return Cache::lock("assessment:{$assessment->getKey()}:save", 10)->block(5, function () use ($assessment): Assessment {
            return DB::transaction(function () use ($assessment): Assessment {
                $locked = Assessment::query()->lockForUpdate()->findOrFail($assessment->getKey());

                if (! in_array($locked->status, [AssessmentStatus::Completed, AssessmentStatus::Archived], true)) {
                    throw ValidationException::withMessages(['status' => __('assestme.assessments.errors.invalid_transition')]);
                }

                $locked->forceFill([
                    'status' => AssessmentStatus::Draft,
                    'completed_at' => null,
                    'lock_version' => $locked->lock_version + 1,
                ])->save();

                return $locked->refresh();
            });
        });
    }
}
