<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Models\FindingSolution;
use App\Settings\GeneralSettings;

final class FormatEstimate
{
    public function __construct(private readonly GeneralSettings $settings) {}

    public function handle(FindingSolution $solution): string
    {
        return match ($solution->estimate_type) {
            EstimateType::Exact => $this->exact($solution),
            EstimateType::Range => $this->range($solution),
            EstimateType::Bundled => __('assestme.reports.estimates.bundled'),
            EstimateType::RequiresQuote => __('assestme.reports.estimates.requires_quote'),
            EstimateType::RequiresAnalysis => __('assestme.reports.estimates.requires_analysis'),
            EstimateType::Variable => __('assestme.reports.estimates.variable'),
            EstimateType::NotApplicable => __('assestme.reports.estimates.not_applicable'),
        };
    }

    private function exact(FindingSolution $solution): string
    {
        if ($solution->amount_min === null) {
            throw new \LogicException('An exact estimate requires a minimum amount.');
        }

        return __('assestme.reports.estimates.exact', [
            'amount' => $this->money((string) $solution->amount_min, $solution->currency_code),
            'frequency' => $this->frequency($solution),
        ]);
    }

    private function range(FindingSolution $solution): string
    {
        if ($solution->amount_min === null || $solution->amount_max === null) {
            throw new \LogicException('A range estimate requires both amounts.');
        }

        return __('assestme.reports.estimates.range', [
            'minimum' => $this->number((string) $solution->amount_min),
            'maximum' => $this->money((string) $solution->amount_max, $solution->currency_code),
            'frequency' => $this->frequency($solution),
        ]);
    }

    private function money(string $amount, ?string $currencyCode): string
    {
        $number = $this->number($amount);
        $symbol = $currencyCode === $this->settings->currency
            ? $this->settings->currency_symbol
            : (string) $currencyCode;

        return $this->settings->currency_symbol_position === 'before'
            ? trim($symbol.' '.$number)
            : trim($number.' '.$symbol);
    }

    private function number(string $amount): string
    {
        $value = (float) $amount;
        $decimals = fmod($value, 1.0) === 0.0 ? 0 : $this->settings->currency_decimals;

        return number_format($value, $decimals, ',', '.');
    }

    private function frequency(FindingSolution $solution): string
    {
        return match ($solution->billing_frequency) {
            BillingFrequency::OneOff => __('assestme.reports.billing.one_off'),
            BillingFrequency::Monthly => __('assestme.reports.billing.monthly'),
            BillingFrequency::Yearly => __('assestme.reports.billing.yearly'),
            BillingFrequency::Custom => __('assestme.reports.billing.custom', [
                'frequency' => (string) $solution->custom_billing_frequency,
            ]),
        };
    }
}
