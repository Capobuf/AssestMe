<?php

declare(strict_types=1);

namespace App\Enums;

enum FindingPriority: string
{
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';
    case Critical = 'critical';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Low->value => __('assestme.findings.priority.low'),
            self::Moderate->value => __('assestme.findings.priority.moderate'),
            self::High->value => __('assestme.findings.priority.high'),
            self::Critical->value => __('assestme.findings.priority.critical'),
        ];
    }
}
