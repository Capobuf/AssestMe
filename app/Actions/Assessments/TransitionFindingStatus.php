<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Enums\FindingStatus;
use App\Models\Finding;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransitionFindingStatus
{
    public function __invoke(Finding $finding, FindingStatus $target): Finding
    {
        return DB::transaction(function () use ($finding, $target): Finding {
            $current = Finding::query()->with('assessment')->findOrFail($finding->getKey());

            if ($current->assessment->status !== AssessmentStatus::Draft) {
                throw ValidationException::withMessages(['assessment' => __('assestme.assessments.errors.read_only')]);
            }
            if (! in_array($target, $this->destinations($current->status), true)) {
                throw ValidationException::withMessages(['status' => __('assestme.findings.errors.invalid_transition')]);
            }
            if ($target === FindingStatus::Resolved
                && $current->implemented_solution_id === null
                && blank($current->resolution_notes)) {
                throw ValidationException::withMessages(['status' => __('assestme.findings.errors.resolution_required')]);
            }

            $current->forceFill([
                'status' => $target,
                'resolved_at' => $target === FindingStatus::Resolved ? now() : null,
            ])->save();

            return $current->refresh();
        });
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
