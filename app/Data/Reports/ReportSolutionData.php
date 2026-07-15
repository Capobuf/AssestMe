<?php

declare(strict_types=1);

namespace App\Data\Reports;

final readonly class ReportSolutionData
{
    public function __construct(
        public int $id,
        public string $title,
        public string $description,
        public ?string $comparisonNotes,
        public ?string $effortLabel,
        public ?string $effortNotes,
        public string $estimateType,
        public ?string $amountMin,
        public ?string $amountMax,
        public ?string $currencyCode,
        public string $billingFrequency,
        public ?string $customBillingFrequency,
        public ?string $estimateNotes,
        public string $estimateLabel,
        public bool $recommended,
        public bool $implemented,
        public int $sortOrder,
    ) {}

    /** @return array{id: int, title: string, description: string, comparison_notes: string|null, effort_label: string|null, effort_notes: string|null, estimate_type: string, amount_min: string|null, amount_max: string|null, currency_code: string|null, billing_frequency: string, custom_billing_frequency: string|null, estimate_notes: string|null, estimate_label: string, recommended: bool, implemented: bool, sort_order: int} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'comparison_notes' => $this->comparisonNotes,
            'effort_label' => $this->effortLabel,
            'effort_notes' => $this->effortNotes,
            'estimate_type' => $this->estimateType,
            'amount_min' => $this->amountMin,
            'amount_max' => $this->amountMax,
            'currency_code' => $this->currencyCode,
            'billing_frequency' => $this->billingFrequency,
            'custom_billing_frequency' => $this->customBillingFrequency,
            'estimate_notes' => $this->estimateNotes,
            'estimate_label' => $this->estimateLabel,
            'recommended' => $this->recommended,
            'implemented' => $this->implemented,
            'sort_order' => $this->sortOrder,
        ];
    }
}
