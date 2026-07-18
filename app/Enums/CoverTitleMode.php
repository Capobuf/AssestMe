<?php

declare(strict_types=1);

namespace App\Enums;

enum CoverTitleMode: string
{
    case Separate = 'separate';
    case Combined = 'combined';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Separate->value => __('assestme.settings.values.cover_title_separate'),
            self::Combined->value => __('assestme.settings.values.cover_title_combined'),
        ];
    }
}
