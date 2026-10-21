<?php

declare(strict_types=1);

namespace App\Support;

final class PhpRuntime
{
    public const REQUIREMENT = '~8.3.36 || ~8.4.27 || ~8.5.12 || >=8.6.0';

    public static function supports(string $version): bool
    {
        // PHP2Shell was fixed separately in each maintained PHP release series.
        return (version_compare($version, '8.3.36', '>=') && version_compare($version, '8.4.0-dev', '<'))
            || (version_compare($version, '8.4.27', '>=') && version_compare($version, '8.5.0-dev', '<'))
            || (version_compare($version, '8.5.12', '>=') && version_compare($version, '8.6.0-dev', '<'))
            || version_compare($version, '8.6.0', '>=');
    }
}
