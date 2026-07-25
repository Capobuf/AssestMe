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
        return $this->format(
            estimateType: $solution->estimate_type,
            amountMin: $solution->amount_min === null ? null : (string) $solution->amount_min,
            amountMax: $solution->amount_max === null ? null : (string) $solution->amount_max,
            currencyCode: $solution->currency_code,
            billingFrequency: $solution->billing_frequency,
            customBillingFrequency: $solution->custom_billing_frequency,
            displayCurrency: $this->settings->currency,
            currencySymbol: $this->settings->currency_symbol,
            currencySymbolPosition: $this->settings->currency_symbol_position,
            currencyDecimals: $this->settings->currency_decimals,
        );
    }

    public function format(
        EstimateType $estimateType,
        ?string $amountMin,
        ?string $amountMax,
        ?string $currencyCode,
        BillingFrequency $billingFrequency,
        ?string $customBillingFrequency,
        string $displayCurrency,
        string $currencySymbol,
        string $currencySymbolPosition,
        int $currencyDecimals,
    ): string {
        return match ($estimateType) {
            EstimateType::Exact => $this->exact(
                $amountMin,
                $currencyCode,
                $billingFrequency,
                $customBillingFrequency,
                $displayCurrency,
                $currencySymbol,
                $currencySymbolPosition,
                $currencyDecimals,
            ),
            EstimateType::Range => $this->range(
                $amountMin,
                $amountMax,
                $currencyCode,
                $billingFrequency,
                $customBillingFrequency,
                $displayCurrency,
                $currencySymbol,
                $currencySymbolPosition,
                $currencyDecimals,
            ),
            EstimateType::Bundled => __('assestme.reports.estimates.bundled'),
            EstimateType::RequiresQuote => __('assestme.reports.estimates.requires_quote'),
            EstimateType::RequiresAnalysis => __('assestme.reports.estimates.requires_analysis'),
            EstimateType::Variable => __('assestme.reports.estimates.variable'),
            EstimateType::NotApplicable => __('assestme.reports.estimates.not_applicable'),
        };
    }

    private function exact(
        ?string $amountMin,
        ?string $currencyCode,
        BillingFrequency $billingFrequency,
        ?string $customBillingFrequency,
        string $displayCurrency,
        string $currencySymbol,
        string $currencySymbolPosition,
        int $currencyDecimals,
    ): string {
        if ($amountMin === null) {
            throw new \LogicException('An exact estimate requires a minimum amount.');
        }

        return __('assestme.reports.estimates.exact', [
            'amount' => $this->money(
                $amountMin,
                $currencyCode,
                $displayCurrency,
                $currencySymbol,
                $currencySymbolPosition,
                $currencyDecimals,
            ),
            'frequency' => $this->frequency($billingFrequency, $customBillingFrequency),
        ]);
    }

    private function range(
        ?string $amountMin,
        ?string $amountMax,
        ?string $currencyCode,
        BillingFrequency $billingFrequency,
        ?string $customBillingFrequency,
        string $displayCurrency,
        string $currencySymbol,
        string $currencySymbolPosition,
        int $currencyDecimals,
    ): string {
        if ($amountMin === null || $amountMax === null) {
            throw new \LogicException('A range estimate requires both amounts.');
        }

        return __('assestme.reports.estimates.range', [
            'minimum' => $this->number($amountMin, $currencyDecimals),
            'maximum' => $this->money(
                $amountMax,
                $currencyCode,
                $displayCurrency,
                $currencySymbol,
                $currencySymbolPosition,
                $currencyDecimals,
            ),
            'frequency' => $this->frequency($billingFrequency, $customBillingFrequency),
        ]);
    }

    private function money(
        string $amount,
        ?string $currencyCode,
        string $displayCurrency,
        string $currencySymbol,
        string $currencySymbolPosition,
        int $currencyDecimals,
    ): string {
        $number = $this->number($amount, $currencyDecimals);
        $symbol = $currencyCode === $displayCurrency
            ? $currencySymbol
            : (string) $currencyCode;

        return $currencySymbolPosition === 'before'
            ? trim($symbol.' '.$number)
            : trim($number.' '.$symbol);
    }

    private function number(string $amount, int $currencyDecimals): string
    {
        $value = (float) $amount;
        $decimals = fmod($value, 1.0) === 0.0 ? 0 : $currencyDecimals;

        return number_format($value, $decimals, ',', '.');
    }

    private function frequency(
        BillingFrequency $billingFrequency,
        ?string $customBillingFrequency,
    ): string {
        return match ($billingFrequency) {
            BillingFrequency::OneOff => __('assestme.reports.billing.one_off'),
            BillingFrequency::Monthly => __('assestme.reports.billing.monthly'),
            BillingFrequency::Yearly => __('assestme.reports.billing.yearly'),
            BillingFrequency::Custom => __('assestme.reports.billing.custom', [
                'frequency' => (string) $customBillingFrequency,
            ]),
        };
    }
}
