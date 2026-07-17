<?php

declare(strict_types=1);

namespace App\Enums;

enum OperationalCheckType: string
{
    case Backup = 'backup';
    case DatabaseIntegrity = 'database_integrity';
}
