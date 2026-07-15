<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Enums\FindingStatus;
use App\Models\Finding;
use Illuminate\Validation\ValidationException;

final class TransitionFinding
{
    public function handle(Finding $finding, FindingStatus $target): Finding
    {
        $finding->loadMissing('assessment');
        if ($finding->assessment->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages(['assessment' => __('assestme.assessments.errors.read_only')]);
        }
        if (! in_array($target, $this->destinations($finding->status), true)) {
            throw ValidationException::withMessages(['status' => __('assestme.findings.errors.invalid_transition')]);
        }
        if ($target === FindingStatus::Resolved
            && $finding->implemented_solution_id === null
            && blank($finding->resolution_notes)) {
            throw ValidationException::withMessages(['status' => __('assestme.findings.errors.resolution_required')]);
        }

        $finding->forceFill([
            'status' => $target,
            'resolved_at' => $target === FindingStatus::Resolved ? now() : null,
        ])->save();

        return $finding->refresh();
    }

    /** @return list<FindingStatus> */
    private function destinations(FindingStatus $source): array
    {
        return match ($source) {
            FindingStatus::Open => [FindingStatus::Planned, FindingStatus::InProgress, FindingStatus::Accepted, FindingStatus::NotApplicable],
            FindingStatus::Planned => [FindingStatus::Open, FindingStatus::InProgress, FindingStatus::Resolved, FindingStatus::Accepted],
            FindingStatus::InProgress => [FindingStatus::Open, FindingStatus::Planned, FindingStatus::Resolved],
            FindingStatus::Resolved => [FindingStatus::Open, FindingStatus::InProgress],
            FindingStatus::Accepted => [FindingStatus::Open, FindingStatus::Planned],
            FindingStatus::NotApplicable => [FindingStatus::Open],
        };
    }
}
