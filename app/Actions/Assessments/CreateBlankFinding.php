<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\AssessmentStatus;
use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Finding;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateBlankFinding
{
    public function __construct(private readonly IncrementAssessmentVersion $incrementAssessmentVersion) {}

    public function __invoke(Assessment $assessment): Finding
    {
        $expectedVersion = (int) $assessment->lock_version;

        return Cache::lock("assessment:{$assessment->getKey()}:save", 10)->block(
            5,
            fn (): Finding => DB::transaction(function () use ($assessment, $expectedVersion): Finding {
                $persisted = Assessment::query()->findOrFail($assessment->getKey());
                if ($persisted->status !== AssessmentStatus::Draft) {
                    throw ValidationException::withMessages([
                        'assessment' => __('assestme.assessments.errors.read_only'),
                    ]);
                }

                // Blank rows are real records immediately, so no child operation depends on a temporary database identity.
                $finding = $persisted->findings()->create([
                    'scope_type' => ScopeType::Organization,
                    'status' => FindingStatus::Open,
                    'include_in_report' => true,
                    'sort_order' => ((int) $persisted->findings()->max('sort_order')) + 1,
                ]);
                ($this->incrementAssessmentVersion)($assessment, $expectedVersion);

                return $finding;
            }, attempts: 1),
        );
    }
}
