<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Finding;

final class EditorialLimits
{
    public const FINDING_TITLE = 180;

    public const SOLUTION_TITLE = 140;

    public const EFFORT_NOTES = 240;

    public const ESTIMATE_NOTES = 240;

    public const COMPARISON_NOTES = 320;

    /** @return array{entrepreneur_notes: int, problem: int, solution_description: int, comparison_notes: int, effort_notes: int, estimate_notes: int, technical_notes: int, resolution_notes: int} */
    public static function forSolutionCount(int $solutionCount): array
    {
        return match (max(1, min(3, $solutionCount))) {
            1 => [
                'entrepreneur_notes' => 900,
                'problem' => 1_400,
                'solution_description' => 1_000,
                'comparison_notes' => self::COMPARISON_NOTES,
                'effort_notes' => self::EFFORT_NOTES,
                'estimate_notes' => self::ESTIMATE_NOTES,
                'technical_notes' => 500,
                'resolution_notes' => 500,
            ],
            2 => [
                'entrepreneur_notes' => 750,
                'problem' => 1_100,
                'solution_description' => 800,
                'comparison_notes' => 220,
                'effort_notes' => 180,
                'estimate_notes' => 180,
                'technical_notes' => 350,
                'resolution_notes' => 350,
            ],
            3 => [
                'entrepreneur_notes' => 600,
                'problem' => 850,
                'solution_description' => 350,
                'comparison_notes' => 140,
                'effort_notes' => 120,
                'estimate_notes' => 120,
                'technical_notes' => 200,
                'resolution_notes' => 140,
            ],
        };
    }

    /** @return list<array{field: string, limit: int}> */
    public static function violations(Finding $finding): array
    {
        $limits = self::forSolutionCount($finding->solutions->count());
        $values = [
            'title' => [(string) $finding->title, self::FINDING_TITLE],
            'entrepreneur_notes' => [(string) $finding->entrepreneur_notes, $limits['entrepreneur_notes']],
            'problem' => [(string) $finding->problem, $limits['problem']],
            'technical_notes' => [(string) $finding->technical_notes, $limits['technical_notes']],
            'resolution_notes' => [(string) $finding->resolution_notes, $limits['resolution_notes']],
        ];

        foreach ($finding->solutions as $index => $solution) {
            $prefix = 'solutions.'.($index + 1).'.';
            $values[$prefix.'title'] = [$solution->title, self::SOLUTION_TITLE];
            $values[$prefix.'description'] = [$solution->description, $limits['solution_description']];
            $values[$prefix.'comparison_notes'] = [(string) $solution->comparison_notes, $limits['comparison_notes']];
            $values[$prefix.'effort_notes'] = [(string) $solution->effort_notes, $limits['effort_notes']];
            $values[$prefix.'estimate_notes'] = [(string) $solution->estimate_notes, $limits['estimate_notes']];
        }

        $violations = [];
        foreach ($values as $field => [$value, $limit]) {
            if (mb_strlen((string) $value) > $limit) {
                $violations[] = ['field' => $field, 'limit' => $limit];
            }
        }

        return $violations;
    }

    /**
     * @param  array<string, string|null>  $fields
     * @param  list<array<string, string|null>>  $solutions
     * @return list<array{field: string, limit: int}>
     */
    public static function violationsForValues(array $fields, array $solutions): array
    {
        $limits = self::forSolutionCount(count($solutions));
        $values = [
            'title' => [$fields['title'] ?? '', self::FINDING_TITLE],
            'entrepreneur_notes' => [$fields['entrepreneur_notes'] ?? '', $limits['entrepreneur_notes']],
            'problem' => [$fields['problem'] ?? '', $limits['problem']],
            'technical_notes' => [$fields['technical_notes'] ?? '', $limits['technical_notes']],
            'resolution_notes' => [$fields['resolution_notes'] ?? '', $limits['resolution_notes']],
        ];
        foreach ($solutions as $index => $solution) {
            $prefix = 'solutions.'.($index + 1).'.';
            $values[$prefix.'title'] = [$solution['title'] ?? '', self::SOLUTION_TITLE];
            $values[$prefix.'description'] = [$solution['description'] ?? '', $limits['solution_description']];
            $values[$prefix.'comparison_notes'] = [$solution['comparison_notes'] ?? '', $limits['comparison_notes']];
            $values[$prefix.'effort_notes'] = [$solution['effort_notes'] ?? '', $limits['effort_notes']];
            $values[$prefix.'estimate_notes'] = [$solution['estimate_notes'] ?? '', $limits['estimate_notes']];
        }

        $violations = [];
        foreach ($values as $field => [$value, $limit]) {
            if (mb_strlen((string) $value) > $limit) {
                $violations[] = ['field' => $field, 'limit' => $limit];
            }
        }

        return $violations;
    }
}
