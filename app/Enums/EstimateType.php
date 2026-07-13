<?php

declare(strict_types=1);

namespace App\Enums;

enum EstimateType: string
{
    case Exact = 'exact';
    case Range = 'range';
    case Bundled = 'bundled';
    case RequiresQuote = 'requires_quote';
    case RequiresAnalysis = 'requires_analysis';
    case Variable = 'variable';
    case NotApplicable = 'not_applicable';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Exact->value => __('assestme.findings.estimate_type.exact'),
            self::Range->value => __('assestme.findings.estimate_type.range'),
            self::Bundled->value => __('assestme.findings.estimate_type.bundled'),
            self::RequiresQuote->value => __('assestme.findings.estimate_type.requires_quote'),
            self::RequiresAnalysis->value => __('assestme.findings.estimate_type.requires_analysis'),
            self::Variable->value => __('assestme.findings.estimate_type.variable'),
            self::NotApplicable->value => __('assestme.findings.estimate_type.not_applicable'),
        ];
    }
}
