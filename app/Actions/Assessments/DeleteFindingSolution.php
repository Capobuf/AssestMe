<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Models\FindingSolution;
use Illuminate\Validation\ValidationException;

final class DeleteFindingSolution
{
    public function handle(FindingSolution $solution): void
    {
        $solution->loadMissing('finding');
        if ($solution->finding->recommended_solution_id === $solution->getKey()
            || $solution->finding->implemented_solution_id === $solution->getKey()) {
            throw ValidationException::withMessages(['solution' => __('assestme.findings.errors.referenced_solution')]);
        }

        $solution->delete();
    }
}
