<?php

declare(strict_types=1);

namespace App\Enums;

enum DeletionOperationStatus: string
{
    case Staged = 'staged';
    case Committed = 'committed';
    case CleanupFailed = 'cleanup_failed';
    case Cleaned = 'cleaned';
    case Restored = 'restored';
}
