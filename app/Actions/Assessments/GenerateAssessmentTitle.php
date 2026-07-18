<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\ScopeType;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class GenerateAssessmentTitle
{
    /** @param list<int|string> $siteIds */
    public function __invoke(
        Client $client,
        ScopeType $scope,
        CarbonInterface|string $assessmentDate,
        array $siteIds = [],
        ?string $scopeDescription = null,
    ): string {
        $date = $assessmentDate instanceof CarbonInterface
            ? $assessmentDate
            : CarbonImmutable::parse($assessmentDate);

        $scopeLabel = match ($scope) {
            ScopeType::Organization => __('assestme.scopes.organization'),
            ScopeType::SelectedSites => $this->siteLabel($client, $siteIds),
            ScopeType::Custom => filled($scopeDescription)
                ? trim((string) $scopeDescription)
                : __('assestme.scopes.custom'),
            default => __('assestme.scopes.'.$scope->value),
        };

        return implode(' — ', [
            $client->displayName(),
            $scopeLabel,
            $date->format('d/m/Y'),
        ]);
    }

    /** @param list<int|string> $siteIds */
    private function siteLabel(Client $client, array $siteIds): string
    {
        $ids = array_values(array_unique(array_map('intval', $siteIds)));
        if ($ids === []) {
            return __('assestme.scopes.site');
        }

        $names = $client->sites()
            ->whereIn('id', $ids)
            ->orderByRaw('CASE id '.collect($ids)->map(
                static fn (int $id, int $index): string => "WHEN {$id} THEN {$index}",
            )->implode(' ').' END')
            ->pluck('name')
            ->all();

        return $names === [] ? __('assestme.scopes.site') : implode(', ', $names);
    }
}
