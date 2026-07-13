<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Models\Finding;
use App\Models\FindingSolution;
use Illuminate\Validation\ValidationException;

final class SetFindingSolutionReference
{
    public function handle(Finding $finding, FindingSolution $solution, bool $implemented = false): Finding
    {
        $finding->loadMissing('assessment');
        if ($finding->assessment->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages(['assessment' => __('assestme.assessments.errors.read_only')]);
        }
        if ((int) $solution->finding_id !== (int) $finding->getKey() || $solution->trashed()) {
            throw ValidationException::withMessages(['solution' => __('assestme.findings.errors.solution_ownership')]);
        }

        $finding->setAttribute($implemented ? 'implemented_solution_id' : 'recommended_solution_id', $solution->getKey());
        $finding->save();

        return $finding->refresh();
    }
}
