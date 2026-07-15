<?php

declare(strict_types=1);

namespace App\Enums;

enum GeneratedReportFormat: string
{
    case Pdf = 'pdf';
    case Xlsx = 'xlsx';
}
