<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Models\FindingSolution;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteFindingSolution
{
    public function handle(FindingSolution $solution): void
    {
        DB::transaction(function () use ($solution): void {
            $finding = $solution->finding()->firstOrFail();
            if ($finding->recommended_solution_id === $solution->getKey()
                || $finding->implemented_solution_id === $solution->getKey()) {
                throw ValidationException::withMessages(['solution' => __('assestme.findings.errors.referenced_solution')]);
            }

            $solution->delete();
        });
    }
}
