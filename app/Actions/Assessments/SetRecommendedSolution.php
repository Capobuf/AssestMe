<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Models\Finding;
use App\Models\FindingSolution;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetRecommendedSolution
{
    public function __invoke(Finding $finding, ?FindingSolution $solution): Finding
    {
        return DB::transaction(function () use ($finding, $solution): Finding {
            if ($finding->assessment()->firstOrFail()->status !== AssessmentStatus::Draft) {
                throw ValidationException::withMessages(['assessment' => __('assestme.assessments.errors.read_only')]);
            }
            if ($solution !== null
                && ($solution->getKey() === null || $finding->solutions()->whereKey($solution->getKey())->doesntExist())) {
                throw ValidationException::withMessages(['solution' => __('assestme.findings.errors.solution_ownership')]);
            }

            $finding->recommended_solution_id = $solution === null ? null : (int) $solution->getKey();
            $finding->save();

            return $finding->refresh();
        });
    }
}
