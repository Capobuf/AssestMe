<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\FindingStatus;
use App\Models\Assessment;
use App\Models\Finding;
use App\Services\Reporting\EditorialLimits;
use Illuminate\Validation\ValidationException;

final class ValidateAssessmentCompletion
{
    public function __construct(private readonly ValidateFindingScopeSelection $validateScope) {}

    /** @throws ValidationException */
    public function __invoke(Assessment $assessment): void
    {
        $assessment->loadMissing([
            'findings.category',
            'findings.solutions',
            'findings.recommendedSolution',
            'findings.implementedSolution',
            'findings.sites',
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
    public function findingErrors(Finding $finding): array
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
        if ($finding->solutions->count() > 3) {
            $messages[] = __('assestme.findings.errors.solution_limit');
        }
        if ($finding->recommended_solution_id === null
            || $finding->solutions->where('id', $finding->recommended_solution_id)->isEmpty()) {
            $messages[] = __('assestme.findings.errors.recommended_solution_required');
        }
        array_push($messages, ...$this->validateScope->messages(
            $finding->scope_type,
            $finding->sites->count(),
            $finding->scope_description,
        ));
        if ($finding->status === FindingStatus::Resolved
            && $finding->implemented_solution_id === null
            && blank($finding->resolution_notes)) {
            $messages[] = __('assestme.findings.errors.resolution_required');
        }
        foreach (EditorialLimits::violations($finding) as $violation) {
            $messages[] = __('assestme.findings.errors.editorial_limit', $violation);
        }

        return $messages;
    }
}
