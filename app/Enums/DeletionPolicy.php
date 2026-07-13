<?php

declare(strict_types=1);

namespace App\Enums;

enum DeletionPolicy: string
{
    case Archive = 'archive';
    case Permanent = 'permanent';
}
