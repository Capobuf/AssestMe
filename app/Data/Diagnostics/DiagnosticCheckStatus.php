<?php

declare(strict_types=1);

namespace App\Data\Diagnostics;

enum DiagnosticCheckStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Warning = 'warning';
}
