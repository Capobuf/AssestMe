<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Finding;
use Illuminate\Validation\ValidationException;

final class ValidateAssessmentCompletion
{
    /** @throws ValidationException */
    public function handle(Assessment $assessment): void
    {
        $assessment->loadMissing([
            'findings.category',
            'findings.solutions',
            'findings.recommendedSolution',
            'findings.implementedSolution',
            'findings.sites',
            'findings.assets',
        ]);

        /** @var array<string, list<string>> $errors */
        $errors = [];
        foreach ($assessment->findings->where('include_in_report', true)->values() as $index => $finding) {
            $row = $index + 1;
            $messages = $this->findingErrors($finding);
            if ($messages !== []) {
                $errors["findings.{$index}"] = array_map(
                    static fn (string $message): string => __('assestme.findings.errors.row', ['row' => $row, 'message' => $message]),
                    $messages,
                );
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return list<string> */
    private function findingErrors(Finding $finding): array
    {
        $messages = [];
        if (blank($finding->title)) {
            $messages[] = __('assestme.findings.errors.title_required');
        }
        if ($finding->category_id === null) {
            $messages[] = __('assestme.findings.errors.category_required');
        }
        if (blank($finding->problem)) {
            $messages[] = __('assestme.findings.errors.problem_required');
        }
        if ($finding->priority_level_id === null) {
            $messages[] = __('assestme.findings.errors.priority_required');
        }
        if ($finding->priority_is_overridden && blank($finding->priority_rationale)) {
            $messages[] = __('assestme.findings.errors.override_reason_required');
        }
        if ($finding->solutions->isEmpty()) {
            $messages[] = __('assestme.findings.errors.solution_required');
        }
        if ($finding->recommended_solution_id === null
            || $finding->solutions->where('id', $finding->recommended_solution_id)->isEmpty()) {
            $messages[] = __('assestme.findings.errors.recommended_solution_required');
        }
        if ($finding->scope_type === ScopeType::SelectedSites && $finding->sites->isEmpty()) {
            $messages[] = __('assestme.findings.errors.site_scope_required');
        }
        if ($finding->scope_type === ScopeType::SelectedAssets && $finding->assets->isEmpty()) {
            $messages[] = __('assestme.findings.errors.asset_scope_required');
        }
        if (in_array($finding->scope_type, [ScopeType::Network, ScopeType::Custom], true)
            && blank($finding->scope_description)) {
            $messages[] = __('assestme.findings.errors.scope_description_required');
        }
        if ($finding->status === FindingStatus::Resolved
            && $finding->implemented_solution_id === null
            && blank($finding->resolution_notes)) {
            $messages[] = __('assestme.findings.errors.resolution_required');
        }

        return $messages;
    }
}
