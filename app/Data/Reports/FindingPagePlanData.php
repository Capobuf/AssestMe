<?php

declare(strict_types=1);

namespace App\Data\Reports;

final readonly class FindingPagePlanData
{
    /**
     * @param  list<int>  $firstPageSolutionIds
     * @param  list<int>  $secondPageSolutionIds
     */
    public function __construct(
        public array $firstPageSolutionIds,
        public array $secondPageSolutionIds,
        public bool $hasSecondPage,
    ) {}

    /** @return array{first_page_solution_ids: list<int>, second_page_solution_ids: list<int>, has_second_page: bool} */
    public function toArray(): array
    {
        return [
            'first_page_solution_ids' => $this->firstPageSolutionIds,
            'second_page_solution_ids' => $this->secondPageSolutionIds,
            'has_second_page' => $this->hasSecondPage,
        ];
    }
}
