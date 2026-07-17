<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Finding;
use Illuminate\Validation\ValidationException;

final class CreateBlankFinding
{
    public function __invoke(Assessment $assessment): Finding
    {
        if ($assessment->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages([
                'assessment' => __('assestme.assessments.errors.read_only'),
            ]);
        }

        // Blank rows are real records immediately, so no child operation depends on a temporary database identity.
        return $assessment->findings()->create([
            'scope_type' => ScopeType::Organization,
            'status' => FindingStatus::Open,
            'include_in_report' => true,
            'sort_order' => ((int) $assessment->findings()->max('sort_order')) + 1,
        ]);
    }
}
