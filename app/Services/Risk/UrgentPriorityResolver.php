<?php

declare(strict_types=1);

namespace App\Services\Risk;

use App\Models\PriorityLevel;

final class UrgentPriorityResolver
{
    /** @return list<int> */
    public function ids(): array
    {
        return PriorityLevel::query()
            ->orderBy('risk_profile_id')
            ->orderByDesc('sort_order')
            ->orderByDesc('id')
            ->get(['id', 'risk_profile_id'])
            ->groupBy('risk_profile_id')
            ->flatMap(static fn ($levels) => $levels->take(2)->pluck('id'))
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
