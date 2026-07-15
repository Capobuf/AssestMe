<?php

declare(strict_types=1);

namespace App\Data\Reports;

final readonly class ReportPriorityData
{
    public function __construct(
        public string $code,
        public string $label,
        public ?string $description,
        public string $color,
        public int $sortOrder,
    ) {}

    /** @return array{code: string, label: string, description: string|null, color: string, sort_order: int} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'description' => $this->description,
            'color' => $this->color,
            'sort_order' => $this->sortOrder,
        ];
    }
}
