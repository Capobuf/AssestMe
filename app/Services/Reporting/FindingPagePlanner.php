<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Data\Reports\FindingPagePlanData;
use App\Models\Finding;
use App\Models\FindingSolution;

final class FindingPagePlanner
{
    private const PAGE_BUDGET = 2_400;

    private const CONTINUATION_HEADER_WEIGHT = 180;

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
        $solutionModels = $solutions
            ->sortBy(static fn (FindingSolution $solution): array => [
                $solution->getKey() === $primaryId ? 0 : 1,
                $solution->sort_order,
                $solution->getKey(),
            ])
            ->values();
        $orderedIds = $solutionModels
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
        $openingWeight = $this->openingWeight($finding);
        $contextWeight = $this->contextWeight($finding);
        $solutionsWeight = $solutionModels->sum(
            fn (FindingSolution $solution): int => $this->solutionWeight($solution),
        );
        $hasSecondPage = $solutionModels->count() >= 2
            || $openingWeight + $solutionsWeight + $contextWeight > self::PAGE_BUDGET
            || $finding->assets->count() > 2;

        if (! $hasSecondPage) {
            return new FindingPagePlanData($orderedIds, [], false);
        }

        if ($solutionModels->count() === 1) {
            if ($openingWeight + $solutionsWeight > self::PAGE_BUDGET) {
                return new FindingPagePlanData([], $orderedIds, true);
            }

            return new FindingPagePlanData($orderedIds, [], true);
        }

        $best = null;

        for ($split = 1; $split < $solutionModels->count(); $split++) {
            $firstSolutions = $solutionModels->slice(0, $split);
            $secondSolutions = $solutionModels->slice($split);

            $firstWeight = $openingWeight
                + $firstSolutions->sum(
                    fn (FindingSolution $solution): int => $this->solutionWeight($solution),
                );

            $secondWeight = self::CONTINUATION_HEADER_WEIGHT
                + $secondSolutions->sum(
                    fn (FindingSolution $solution): int => $this->solutionWeight($solution),
                )
                + $contextWeight;

            if (
                $firstWeight > self::PAGE_BUDGET
                || $secondWeight > self::PAGE_BUDGET
            ) {
                continue;
            }

            $candidate = [
                'split' => $split,
                'difference' => abs($firstWeight - $secondWeight),
            ];

            if (
                $best === null
                || $candidate['difference'] < $best['difference']
                || (
                    $candidate['difference'] === $best['difference']
                    && $candidate['split'] > $best['split']
                )
            ) {
                $best = $candidate;
            }
        }

        if ($best !== null) {
            return new FindingPagePlanData(
                firstPageSolutionIds: array_slice(
                    $orderedIds,
                    0,
                    $best['split'],
                ),
                secondPageSolutionIds: array_slice(
                    $orderedIds,
                    $best['split'],
                ),
                hasSecondPage: true,
            );
        }

        return new FindingPagePlanData(
            firstPageSolutionIds: array_slice($orderedIds, 0, 1),
            secondPageSolutionIds: array_slice($orderedIds, 1),
            hasSecondPage: true,
        );
    }

    private function openingWeight(Finding $finding): int
    {
        return mb_strlen((string) $finding->title)
            + mb_strlen((string) $finding->entrepreneur_notes)
            + mb_strlen((string) $finding->problem);
    }

    private function solutionWeight(FindingSolution $solution): int
    {
        return mb_strlen($solution->title)
            + mb_strlen($solution->description)
            + mb_strlen((string) $solution->comparison_notes)
            + mb_strlen((string) $solution->effort_notes)
            + mb_strlen((string) $solution->estimate_notes)
            + 220;
    }

    private function contextWeight(Finding $finding): int
    {
        $weight = mb_strlen((string) $finding->technical_notes)
            + mb_strlen((string) $finding->resolution_notes)
            + ($finding->consequence_level_id !== null
                && $finding->likelihood_level_id !== null
                    ? 650
                    : 0)
            + ($finding->assets->count() * 180)
            + ($finding->sites->count() * 80);

        if (
            filled($finding->resolution_notes)
            || $finding->implemented_solution_id !== null
        ) {
            $weight += 180;
        }

        return $weight;
    }
}
