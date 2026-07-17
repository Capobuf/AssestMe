<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\WorkspaceSaveData;
use App\Enums\AssessmentStatus;
use App\Exceptions\AssessmentVersionConflict;
use App\Models\Assessment;
use Illuminate\Validation\ValidationException;

final class SaveAssessmentPatch
{
    /**
     * This action runs inside SaveAssessmentWorkspace's signed lock and transaction.
     *
     * @throws AssessmentVersionConflict
     * @throws ValidationException
     */
    public function __invoke(Assessment $assessment, WorkspaceSaveData $request): int
    {
        $appliedVersion = $request->expectedVersion + 1;
        $updated = Assessment::query()
            ->whereKey($assessment->getKey())
            ->where('status', AssessmentStatus::Draft)
            ->where('lock_version', $request->expectedVersion)
            ->update([
                'title' => $request->payload['assessment']['title'],
                'assessment_date' => $request->payload['assessment']['assessment_date'],
                'report_title_override' => $request->payload['assessment']['report_title_override'] ?? null,
                'scope_type' => $request->payload['assessment']['scope_type'],
                'scope_description' => $request->payload['assessment']['scope_description'] ?? null,
                'introduction' => $request->payload['assessment']['introduction'] ?? null,
                'executive_summary' => $request->payload['assessment']['executive_summary'] ?? null,
                'methodology_notes' => $request->payload['assessment']['methodology_notes'] ?? null,
                'lock_version' => $appliedVersion,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            $persisted = Assessment::query()->findOrFail($assessment->getKey());

            if ($persisted->status !== AssessmentStatus::Draft) {
                throw ValidationException::withMessages([
                    'assessment_id' => __('assestme.assessments.errors.read_only'),
                ]);
            }

            throw new AssessmentVersionConflict(
                $request->expectedVersion,
                $persisted->lock_version,
            );
        }

        $assessment->sites()->sync($request->payload['assessment']['site_ids'] ?? []);

        return $appliedVersion;
    }
}
