<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransitionAssessment
{
    public function __construct(private readonly ValidateAssessmentCompletion $validateCompletion) {}

    public function handle(Assessment $assessment, AssessmentStatus $target): Assessment
    {
        $source = $assessment->status;
        if (! $this->isAllowed($source, $target)) {
            throw ValidationException::withMessages(['status' => __('assestme.assessments.errors.invalid_transition')]);
        }

        if ($target === AssessmentStatus::Completed) {
            $this->validateCompletion->handle($assessment);
        }

        return Cache::lock("assessment:{$assessment->getKey()}:save", 10)->block(5, function () use ($assessment, $target): Assessment {
            return DB::transaction(function () use ($assessment, $target): Assessment {
                $locked = Assessment::query()->lockForUpdate()->findOrFail($assessment->getKey());

                // The lifecycle transition participates in optimistic locking just like a workspace save.
                $locked->forceFill([
                    'status' => $target,
                    // Archiving a completed assessment must retain when it was finalized for report traceability.
                    'completed_at' => match ($target) {
                        AssessmentStatus::Completed => now(),
                        AssessmentStatus::Archived => $locked->completed_at,
                        AssessmentStatus::Draft => null,
                    },
                    'lock_version' => $locked->lock_version + 1,
                ])->save();

                return $locked->refresh();
            });
        });
    }

    private function isAllowed(AssessmentStatus $source, AssessmentStatus $target): bool
    {
        return match ($source) {
            AssessmentStatus::Draft => in_array($target, [AssessmentStatus::Completed, AssessmentStatus::Archived], true),
            AssessmentStatus::Completed => in_array($target, [AssessmentStatus::Draft, AssessmentStatus::Archived], true),
            AssessmentStatus::Archived => $target === AssessmentStatus::Draft,
        };
    }
}
