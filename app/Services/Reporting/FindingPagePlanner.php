<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Data\Reports\FindingPagePlanData;
use App\Models\Finding;
use App\Models\FindingSolution;

final class FindingPagePlanner
{
    public function plan(Finding $finding, bool $includeAlternativeSolutions = true): FindingPagePlanData
    {
        $solutions = $finding->solutions
            ->filter(
                static fn (FindingSolution $solution): bool => $includeAlternativeSolutions
                    || $solution->getKey() === $finding->recommended_solution_id
                    || $solution->getKey() === $finding->implemented_solution_id,
            )
            ->values();
        $primaryId = $finding->implemented_solution_id ?? $finding->recommended_solution_id;
        $ordered = $solutions
            ->sortBy(static fn (FindingSolution $solution): array => [
                $solution->getKey() === $primaryId ? 0 : 1,
                $solution->sort_order,
                $solution->getKey(),
            ])
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->values()
            ->all();

        $contentLength = mb_strlen((string) $finding->title)
            + mb_strlen((string) $finding->entrepreneur_notes)
            + mb_strlen((string) $finding->problem)
            + mb_strlen((string) $finding->technical_notes)
            + mb_strlen((string) $finding->resolution_notes)
            + ($finding->consequence_level_id !== null && $finding->likelihood_level_id !== null ? 650 : 0)
            + ($finding->assets->count() * 180)
            + ($finding->sites->count() * 80);

        if (filled($finding->resolution_notes) || $finding->implemented_solution_id !== null) {
            $contentLength += 180;
        }

        foreach ($solutions as $solution) {
            $contentLength += mb_strlen($solution->title)
                + mb_strlen($solution->description)
                + mb_strlen((string) $solution->comparison_notes)
                + mb_strlen((string) $solution->effort_notes)
                + mb_strlen((string) $solution->estimate_notes);
        }

        $hasSecondPage = $solutions->count() >= 2
            || $contentLength > 2_400
            || $finding->assets->count() > 2;

        if (! $hasSecondPage) {
            return new FindingPagePlanData($ordered, [], false);
        }

        if ($solutions->count() === 1) {
            $primary = $solutions->first();
            $openingLength = mb_strlen((string) $finding->title)
                + mb_strlen((string) $finding->entrepreneur_notes)
                + mb_strlen((string) $finding->problem);
            $primaryLength = $primary === null ? 0 : mb_strlen($primary->title)
                + mb_strlen($primary->description)
                + mb_strlen((string) $primary->comparison_notes)
                + mb_strlen((string) $primary->effort_notes)
                + mb_strlen((string) $primary->estimate_notes)
                + 220;

            if ($openingLength + $primaryLength > 2_400) {
                return new FindingPagePlanData([], $ordered, true);
            }

            return new FindingPagePlanData($ordered, [], true);
        }

        return new FindingPagePlanData(
            firstPageSolutionIds: array_slice($ordered, 0, 1),
            secondPageSolutionIds: array_slice($ordered, 1),
            hasSecondPage: true,
        );
    }
}
