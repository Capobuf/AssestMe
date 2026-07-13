<?php

declare(strict_types=1);

namespace App\Enums;

enum EvidenceType: string
{
    case File = 'file';
    case Url = 'url';
}
