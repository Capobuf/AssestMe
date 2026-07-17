<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Models\Finding;
use App\Models\PriorityLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OverrideFindingPriority
{
    public function __invoke(Finding $finding, PriorityLevel $priority, string $reason): Finding
    {
        return DB::transaction(function () use ($finding, $priority, $reason): Finding {
            $current = Finding::query()
                ->with(['assessment', 'consequenceLevel', 'likelihoodLevel'])
                ->findOrFail($finding->getKey());
            $currentPriority = PriorityLevel::query()->findOrFail($priority->getKey());

            if ($current->assessment->status !== AssessmentStatus::Draft) {
                throw ValidationException::withMessages(['assessment' => __('assestme.assessments.errors.read_only')]);
            }

            $normalizedReason = trim($reason);
            if ($normalizedReason === '') {
                throw ValidationException::withMessages(['priority_rationale' => __('assestme.findings.errors.override_reason_required')]);
            }

            $profileIds = collect([
                $current->consequenceLevel?->risk_profile_id,
                $current->likelihoodLevel?->risk_profile_id,
                $currentPriority->risk_profile_id,
            ])->filter()->unique();

            if ($profileIds->count() > 1) {
                throw ValidationException::withMessages(['priority_level_id' => __('assestme.templates.errors.risk_profile')]);
            }

            $current->forceFill([
                'priority_level_id' => $currentPriority->getKey(),
                'priority_is_overridden' => true,
                'priority_rationale' => $normalizedReason,
            ])->save();

            return $current->refresh();
        });
    }
}
