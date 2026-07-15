<?php

declare(strict_types=1);

namespace App\Actions\Risk;

use App\Models\ConsequenceLevel;
use App\Models\LikelihoodLevel;
use App\Models\PriorityLevel;
use App\Models\RiskMatrixEntry;
use App\Models\RiskProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveRiskProfileConfiguration
{
    /**
     * The matrix is saved as one aggregate so a profile can never expose a partially
     * updated classification model to assessment calculations.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(?RiskProfile $profile, array $data): RiskProfile
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('risk_profiles', 'code')->ignore($profile?->getKey())],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:20000'],
            'is_default' => ['required', 'boolean'],
            'is_enabled' => ['required', 'boolean'],
            'consequences' => ['required', 'array', 'size:4'],
            'consequences.*.code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'consequences.*.label' => ['required', 'string', 'max:120'],
            'consequences.*.description' => ['nullable', 'string', 'max:20000'],
            'consequences.*.score' => ['required', 'integer', 'between:1,4', 'distinct'],
            'consequences.*.color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'consequences.*.sort_order' => ['required', 'integer', 'min:0'],
            'consequences.*.is_enabled' => ['required', 'boolean'],
            'likelihoods' => ['required', 'array', 'size:4'],
            'likelihoods.*.code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'likelihoods.*.label' => ['required', 'string', 'max:120'],
            'likelihoods.*.description' => ['nullable', 'string', 'max:20000'],
            'likelihoods.*.score' => ['required', 'integer', 'between:1,4', 'distinct'],
            'likelihoods.*.color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'likelihoods.*.sort_order' => ['required', 'integer', 'min:0'],
            'likelihoods.*.is_enabled' => ['required', 'boolean'],
            'priorities' => ['required', 'array', 'min:1'],
            'priorities.*.code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'priorities.*.label' => ['required', 'string', 'max:120'],
            'priorities.*.description' => ['nullable', 'string', 'max:20000'],
            'priorities.*.color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'priorities.*.sort_order' => ['required', 'integer', 'min:0'],
            'priorities.*.is_enabled' => ['required', 'boolean'],
            'matrix' => ['required', 'array', 'size:16'],
            'matrix.*.consequence_code' => ['required', 'string'],
            'matrix.*.likelihood_code' => ['required', 'string'],
            'matrix.*.priority_code' => ['required', 'string'],
        ])->validate();

        if ((bool) $validated['is_default'] && ! (bool) $validated['is_enabled']) {
            throw ValidationException::withMessages(['is_enabled' => __('assestme.risk.errors.default_must_be_enabled')]);
        }

        if ($profile?->is_default === true && ! (bool) $validated['is_default']) {
            throw ValidationException::withMessages(['is_default' => __('assestme.risk.errors.choose_replacement_default')]);
        }

        return DB::transaction(function () use ($profile, $validated): RiskProfile {
            $record = $profile ?? new RiskProfile;
            $record->fill([
                'code' => $validated['code'],
                'label' => $validated['label'],
                'description' => $validated['description'] ?? null,
                'is_default' => $validated['is_default'],
                'is_enabled' => $validated['is_enabled'],
            ]);

            if ((bool) $validated['is_default']) {
                // Clearing the prior default and saving the replacement share the same transaction.
                RiskProfile::query()
                    ->when($record->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
                    ->update(['is_default' => false]);
            }

            $record->save();

            $consequences = $this->syncScoredLevels($record, ConsequenceLevel::class, $validated['consequences']);
            $likelihoods = $this->syncScoredLevels($record, LikelihoodLevel::class, $validated['likelihoods']);
            $priorities = $this->syncPriorityLevels($record, $validated['priorities']);
            $this->syncMatrix($record, $validated['matrix'], $consequences, $likelihoods, $priorities);

            return $record->refresh();
        });
    }

    /**
     * @template TLevel of ConsequenceLevel|LikelihoodLevel
     *
     * @param  class-string<TLevel>  $modelClass
     * @param  array<int, mixed>  $rows
     * @return array<string, TLevel>
     */
    private function syncScoredLevels(RiskProfile $profile, string $modelClass, array $rows): array
    {
        $levels = [];
        $activeCodes = [];

        foreach ($rows as $row) {
            /** @var array<string, mixed> $row */
            $code = (string) $row['code'];
            $activeCodes[] = $code;
            /** @var TLevel $level */
            $level = $modelClass::query()->updateOrCreate(
                ['risk_profile_id' => $profile->getKey(), 'code' => $code],
                [
                    'label' => $row['label'],
                    'description' => $row['description'] ?? null,
                    'score' => $row['score'],
                    'color' => mb_strtoupper((string) $row['color']),
                    'sort_order' => $row['sort_order'],
                    'is_enabled' => $row['is_enabled'],
                ],
            );
            $levels[$code] = $level;
        }

        // Historical levels remain referentially valid and are disabled instead of deleted.
        $modelClass::query()
            ->where('risk_profile_id', $profile->getKey())
            ->whereNotIn('code', $activeCodes)
            ->update(['is_enabled' => false]);

        return $levels;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<string, PriorityLevel>
     */
    private function syncPriorityLevels(RiskProfile $profile, array $rows): array
    {
        $levels = [];
        $activeCodes = [];

        foreach ($rows as $row) {
            /** @var array<string, mixed> $row */
            $code = (string) $row['code'];
            $activeCodes[] = $code;
            $levels[$code] = PriorityLevel::query()->updateOrCreate(
                ['risk_profile_id' => $profile->getKey(), 'code' => $code],
                [
                    'label' => $row['label'],
                    'description' => $row['description'] ?? null,
                    'color' => mb_strtoupper((string) $row['color']),
                    'sort_order' => $row['sort_order'],
                    'is_enabled' => $row['is_enabled'],
                ],
            );
        }

        PriorityLevel::query()
            ->where('risk_profile_id', $profile->getKey())
            ->whereNotIn('code', $activeCodes)
            ->update(['is_enabled' => false]);

        return $levels;
    }

    /**
     * @param  array<int, mixed>  $rows
     * @param  array<string, ConsequenceLevel>  $consequences
     * @param  array<string, LikelihoodLevel>  $likelihoods
     * @param  array<string, PriorityLevel>  $priorities
     */
    private function syncMatrix(RiskProfile $profile, array $rows, array $consequences, array $likelihoods, array $priorities): void
    {
        $seenPairs = [];

        foreach ($rows as $index => $row) {
            /** @var array<string, mixed> $row */
            $consequenceCode = (string) $row['consequence_code'];
            $likelihoodCode = (string) $row['likelihood_code'];
            $priorityCode = (string) $row['priority_code'];
            $pair = $consequenceCode.'|'.$likelihoodCode;

            if (isset($seenPairs[$pair]) || ! isset($consequences[$consequenceCode], $likelihoods[$likelihoodCode], $priorities[$priorityCode])) {
                throw ValidationException::withMessages(["matrix.{$index}" => __('assestme.risk.errors.invalid_matrix')]);
            }

            $seenPairs[$pair] = true;
            RiskMatrixEntry::query()->updateOrCreate(
                [
                    'risk_profile_id' => $profile->getKey(),
                    'consequence_level_id' => $consequences[$consequenceCode]->getKey(),
                    'likelihood_level_id' => $likelihoods[$likelihoodCode]->getKey(),
                ],
                ['priority_level_id' => $priorities[$priorityCode]->getKey()],
            );
        }

        if (count($seenPairs) !== 16) {
            throw ValidationException::withMessages(['matrix' => __('assestme.risk.errors.invalid_matrix')]);
        }
    }
}
