<?php

declare(strict_types=1);

namespace App\Services\Risk;

use App\Models\ConsequenceLevel;
use App\Models\LikelihoodLevel;
use App\Models\PriorityLevel;
use App\Models\RiskProfile;
use App\Settings\GeneralSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class ActiveRiskProfileResolver
{
    public function __construct(private readonly GeneralSettings $settings) {}

    public function resolve(): RiskProfile
    {
        $profileId = $this->settings->active_risk_profile_id;
        if ($profileId === null) {
            throw new RuntimeException('No active risk profile is configured.');
        }

        $profile = RiskProfile::query()->whereKey($profileId)->where('is_enabled', true)->first();
        if (! $profile instanceof RiskProfile) {
            throw new RuntimeException('The configured active risk profile does not exist or is disabled.');
        }

        return $profile;
    }

    /** @return array<int, string> */
    public function consequenceOptions(?int $currentId = null): array
    {
        return $this->options(ConsequenceLevel::class, $currentId);
    }

    /** @return array<int, string> */
    public function likelihoodOptions(?int $currentId = null): array
    {
        return $this->options(LikelihoodLevel::class, $currentId);
    }

    /** @return array<int, string> */
    public function priorityOptions(?int $currentId = null): array
    {
        return $this->options(PriorityLevel::class, $currentId);
    }

    /**
     * @param  array{consequence:?int,likelihood:?int,priority:?int}  $classification
     */
    public function assertSelectableClassification(array $classification, string $errorKey): void
    {
        $activeId = (int) $this->resolve()->getKey();
        $models = [
            'consequence' => ConsequenceLevel::class,
            'likelihood' => LikelihoodLevel::class,
            'priority' => PriorityLevel::class,
        ];

        foreach ($models as $field => $modelClass) {
            $id = $classification[$field];
            if ($id === null) {
                continue;
            }
            if (! $modelClass::query()
                ->whereKey($id)
                ->where('risk_profile_id', $activeId)
                ->where('is_enabled', true)
                ->exists()) {
                throw ValidationException::withMessages([$errorKey => __('assestme.templates.errors.risk_profile')]);
            }
        }
    }

    /**
     * @param  class-string<ConsequenceLevel|LikelihoodLevel|PriorityLevel>  $modelClass
     * @return array<int, string>
     */
    private function options(string $modelClass, ?int $currentId): array
    {
        $options = $modelClass::query()
            ->where('risk_profile_id', $this->resolve()->getKey())
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->pluck('label', 'id')
            ->all();

        if ($currentId !== null && ! array_key_exists($currentId, $options)) {
            $historical = $modelClass::query()->find($currentId);
            if ($historical instanceof Model) {
                $options[$currentId] = (string) $historical->getAttribute('label');
            }
        }

        return $options;
    }
}
