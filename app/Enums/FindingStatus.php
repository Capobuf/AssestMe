<?php

declare(strict_types=1);

namespace App\Enums;

enum FindingStatus: string
{
    case Open = 'open';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Accepted = 'accepted';
    case NotApplicable = 'not_applicable';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Open->value => __('assestme.findings.status.open'),
            self::Planned->value => __('assestme.findings.status.planned'),
            self::InProgress->value => __('assestme.findings.status.in_progress'),
            self::Resolved->value => __('assestme.findings.status.resolved'),
            self::Accepted->value => __('assestme.findings.status.accepted'),
            self::NotApplicable->value => __('assestme.findings.status.not_applicable'),
        ];
    }
}
