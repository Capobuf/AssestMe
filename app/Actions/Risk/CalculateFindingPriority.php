<?php

declare(strict_types=1);

namespace App\Actions\Risk;

use App\Models\ConsequenceLevel;
use App\Models\LikelihoodLevel;
use App\Models\PriorityLevel;
use App\Models\RiskMatrixEntry;
use DomainException;

final class CalculateFindingPriority
{
    public function __invoke(ConsequenceLevel $consequence, LikelihoodLevel $likelihood): PriorityLevel
    {
        if ($consequence->risk_profile_id !== $likelihood->risk_profile_id) {
            throw new DomainException('Consequence and likelihood levels must belong to the same risk profile.');
        }

        $entry = RiskMatrixEntry::query()
            ->where('risk_profile_id', $consequence->risk_profile_id)
            ->where('consequence_level_id', $consequence->getKey())
            ->where('likelihood_level_id', $likelihood->getKey())
            ->first();

        if ($entry === null) {
            throw new DomainException('The selected risk profile has no matrix entry for these levels.');
        }

        return $entry->priorityLevel()->firstOrFail();
    }
}
