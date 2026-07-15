<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Models\WorkspaceSaveRequest;
use Illuminate\Support\Carbon;

final class PurgeExpiredWorkspaceSaveRequests
{
    public function __invoke(?Carbon $cutoff = null): int
    {
        return WorkspaceSaveRequest::query()
            ->where('created_at', '<', $cutoff ?? now('UTC')->subDay())
            ->delete();
    }
}
