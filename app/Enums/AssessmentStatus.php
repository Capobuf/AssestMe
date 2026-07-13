<?php

declare(strict_types=1);

namespace App\Enums;

enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Archived = 'archived';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Draft->value => __('assestme.assessments.status.draft'),
            self::Completed->value => __('assestme.assessments.status.completed'),
            self::Archived->value => __('assestme.assessments.status.archived'),
        ];
    }
}
