<?php

declare(strict_types=1);

namespace App\Data\Reports;

final readonly class ReportRiskMatrixData
{
    /**
     * @param  list<array{id: int, label: string, color: string}>  $consequences
     * @param  list<array{id: int, label: string, color: string}>  $likelihoods
     * @param  list<array{consequence_id: int, likelihood_id: int, priority_label: string, priority_color: string, current: bool}>  $cells
     */
    public function __construct(
        public array $consequences,
        public array $likelihoods,
        public array $cells,
        public ?int $currentConsequenceId,
        public ?int $currentLikelihoodId,
        public string $resultingPriorityLabel,
        public string $resultingPriorityColor,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'consequences' => $this->consequences,
            'likelihoods' => $this->likelihoods,
            'cells' => $this->cells,
            'current_consequence_id' => $this->currentConsequenceId,
            'current_likelihood_id' => $this->currentLikelihoodId,
            'resulting_priority_label' => $this->resultingPriorityLabel,
            'resulting_priority_color' => $this->resultingPriorityColor,
        ];
    }

    /** @return array{consequence_id: int, likelihood_id: int, priority_label: string, priority_color: string, current: bool}|null */
    public function cell(int $consequenceId, int $likelihoodId): ?array
    {
        foreach ($this->cells as $cell) {
            if ($cell['consequence_id'] === $consequenceId && $cell['likelihood_id'] === $likelihoodId) {
                return $cell;
            }
        }

        return null;
    }
}
