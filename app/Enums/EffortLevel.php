<?php

declare(strict_types=1);

namespace App\Enums;

enum EffortLevel: string
{
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';
    case VeryHigh = 'very_high';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Low->value => __('assestme.findings.effort.low'),
            self::Moderate->value => __('assestme.findings.effort.moderate'),
            self::High->value => __('assestme.findings.effort.high'),
            self::VeryHigh->value => __('assestme.findings.effort.very_high'),
        ];
    }
}
