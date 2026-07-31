<?php

declare(strict_types=1);

namespace App\Data\Installation;

enum InstallationDatabaseStatus: string
{
    case Empty = 'empty';
    case RecognizedPartial = 'recognized_partial';
    case Foreign = 'foreign';
    case Complete = 'complete';
}
