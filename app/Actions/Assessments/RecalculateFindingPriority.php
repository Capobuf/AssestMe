<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Models\Finding;
use App\Models\RiskMatrixEntry;
use Illuminate\Validation\ValidationException;

final class RecalculateFindingPriority
{
    public function handle(Finding $finding): Finding
    {
        if ($finding->consequence_level_id === null || $finding->likelihood_level_id === null) {
            throw ValidationException::withMessages(['priority' => __('assestme.findings.errors.risk_pair_required')]);
        }

        $entry = RiskMatrixEntry::query()
            ->where('consequence_level_id', $finding->consequence_level_id)
            ->where('likelihood_level_id', $finding->likelihood_level_id)
            ->first();
        if ($entry === null) {
            throw ValidationException::withMessages(['priority' => __('assestme.findings.errors.matrix_entry_missing')]);
        }

        $finding->forceFill([
            'priority_level_id' => $entry->priority_level_id,
            'priority_is_overridden' => false,
            'priority_rationale' => null,
        ])->save();

        return $finding->refresh();
    }
}
