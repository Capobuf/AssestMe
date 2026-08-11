<?php

declare(strict_types=1);

namespace App\Actions\Risk;

use App\Models\ConsequenceLevel;
use App\Models\LikelihoodLevel;
use App\Models\PriorityLevel;
use App\Models\RiskMatrixEntry;
use App\Models\RiskProfile;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveRiskProfileConfiguration
{
    public function __construct(private readonly GeneralSettings $settings) {}

    /**
     * The matrix is saved as one aggregate so a profile can never expose a partially
     * updated classification model to assessment calculations.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(?RiskProfile $profile, array $data): RiskProfile
    {
        $data = $this->withStableCodes($profile, $data);
        $profileId = $profile?->getKey();

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('risk_profiles', 'code')->ignore($profile?->getKey())],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:20000'],
            'is_default' => ['required', 'boolean'],
            'is_enabled' => ['required', 'boolean'],
            'consequences' => ['required', 'array', 'size:4'],
            'consequences.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::prohibitedIf($profile === null),
                Rule::exists('consequence_levels', 'id')->where('risk_profile_id', $profileId),
            ],
            'consequences.*._form_key' => ['nullable', 'uuid', 'distinct'],
            'consequences.*.code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'consequences.*.label' => ['required', 'string', 'max:120'],
            'consequences.*.description' => ['nullable', 'string', 'max:20000'],
            'consequences.*.score' => ['required', 'integer', 'between:1,4', 'distinct'],
            'consequences.*.color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'consequences.*.sort_order' => ['required', 'integer', 'min:0'],
            'consequences.*.is_enabled' => ['required', 'boolean'],
            'likelihoods' => ['required', 'array', 'size:4'],
            'likelihoods.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::prohibitedIf($profile === null),
                Rule::exists('likelihood_levels', 'id')->where('risk_profile_id', $profileId),
            ],
            'likelihoods.*._form_key' => ['nullable', 'uuid', 'distinct'],
            'likelihoods.*.code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'likelihoods.*.label' => ['required', 'string', 'max:120'],
            'likelihoods.*.description' => ['nullable', 'string', 'max:20000'],
            'likelihoods.*.score' => ['required', 'integer', 'between:1,4', 'distinct'],
            'likelihoods.*.color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'likelihoods.*.sort_order' => ['required', 'integer', 'min:0'],
            'likelihoods.*.is_enabled' => ['required', 'boolean'],
            'priorities' => ['required', 'array', 'min:1'],
            'priorities.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::prohibitedIf($profile === null),
                Rule::exists('priority_levels', 'id')->where('risk_profile_id', $profileId),
            ],
            'priorities.*._form_key' => ['nullable', 'uuid', 'distinct'],
            'priorities.*.code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', 'distinct'],
            'priorities.*.label' => ['required', 'string', 'max:120'],
            'priorities.*.description' => ['nullable', 'string', 'max:20000'],
            'priorities.*.color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'priorities.*.sort_order' => ['required', 'integer', 'min:0'],
            'priorities.*.is_enabled' => ['required', 'boolean'],
            'matrix' => ['required', 'array', 'size:4'],
            'matrix.*' => ['required', 'array', 'size:4'],
            'matrix.*.*' => ['required', 'string'],
        ], [
            'consequences.*.id.exists' => __('assestme.risk.errors.foreign_consequence'),
            'likelihoods.*.id.exists' => __('assestme.risk.errors.foreign_likelihood'),
            'priorities.*.id.exists' => __('assestme.risk.errors.foreign_priority'),
            'matrix.size' => __('assestme.risk.errors.invalid_matrix'),
            'matrix.*.size' => __('assestme.risk.errors.invalid_matrix'),
            'matrix.*.*.required' => __('assestme.risk.errors.invalid_matrix'),
            'matrix.*.*.string' => __('assestme.risk.errors.invalid_matrix'),
        ])->validate();

        $this->validateMatrixState($validated);

        if ((bool) $validated['is_default'] && ! (bool) $validated['is_enabled']) {
            throw ValidationException::withMessages(['is_enabled' => __('assestme.risk.errors.default_must_be_enabled')]);
        }

        if ($profile?->is_default === true && ! (bool) $validated['is_default']) {
            throw ValidationException::withMessages(['is_default' => __('assestme.risk.errors.choose_replacement_default')]);
        }

        if ($profile !== null
            && (int) $this->settings->active_risk_profile_id === (int) $profile->getKey()
            && ! (bool) $validated['is_enabled']) {
            throw ValidationException::withMessages(['is_enabled' => __('assestme.risk.errors.active_must_be_enabled')]);
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withStableCodes(?RiskProfile $profile, array $data): array
    {
        if ($profile !== null) {
            if (isset($data['code']) && $data['code'] !== $profile->code) {
                throw ValidationException::withMessages(['code' => __('assestme.risk.errors.code_immutable')]);
            }
            $data['code'] = $profile->code;
        } else {
            $suppliedCode = is_string($data['code'] ?? null) ? trim($data['code']) : '';
            $data['code'] = $suppliedCode !== ''
                ? $suppliedCode
                : $this->nextCode((string) ($data['label'] ?? ''), static fn (string $code): bool => RiskProfile::query()
                    ->where('code', $code)
                    ->exists());
        }

        foreach ([
            'consequences' => ConsequenceLevel::class,
            'likelihoods' => LikelihoodLevel::class,
            'priorities' => PriorityLevel::class,
        ] as $key => $modelClass) {
            $rows = is_array($data[$key] ?? null) ? array_values($data[$key]) : [];
            $data[$key] = $rows;
            $reserved = [];
            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $existing = null;
                if ($profile !== null) {
                    $existing = isset($row['id'])
                        ? $modelClass::query()
                            ->where('risk_profile_id', $profile->getKey())
                            ->find($row['id'])
                        : $modelClass::query()
                            ->where('risk_profile_id', $profile->getKey())
                            ->where('code', $row['code'] ?? null)
                            ->first();
                }
                if ($existing !== null) {
                    if (isset($row['code']) && $row['code'] !== $existing->code) {
                        throw ValidationException::withMessages([
                            "{$key}.{$index}.code" => __('assestme.risk.errors.code_immutable'),
                        ]);
                    }
                    $code = $existing->code;
                    $data[$key][$index]['id'] = $existing->getKey();
                } else {
                    $suppliedCode = is_string($row['code'] ?? null) ? trim($row['code']) : '';
                    $code = $suppliedCode !== ''
                        ? $suppliedCode
                        : $this->nextCode(
                            (string) ($row['label'] ?? ''),
                            static fn (string $candidate): bool => in_array($candidate, $reserved, true)
                                || ($profile !== null && $modelClass::query()
                                    ->where('risk_profile_id', $profile->getKey())
                                    ->where('code', $candidate)
                                    ->exists()),
                        );
                }

                $data[$key][$index]['code'] = $code;
                $reserved[] = $code;
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function validateMatrixState(array $data): void
    {
        $consequenceIdentities = $this->levelIdentities($data['consequences'], 'consequences');
        $likelihoodIdentities = $this->levelIdentities($data['likelihoods'], 'likelihoods');
        $priorityIdentities = $this->levelIdentities($data['priorities'], 'priorities');

        if (count(array_filter($data['consequences'], static fn (array $row): bool => (bool) $row['is_enabled'])) !== 4) {
            throw ValidationException::withMessages(['consequences' => __('assestme.risk.errors.four_enabled_consequences')]);
        }

        if (count(array_filter($data['likelihoods'], static fn (array $row): bool => (bool) $row['is_enabled'])) !== 4) {
            throw ValidationException::withMessages(['likelihoods' => __('assestme.risk.errors.four_enabled_likelihoods')]);
        }

        /** @var array<int|string, mixed> $matrix */
        $matrix = $data['matrix'];
        if ($this->normalizedKeys($matrix) !== $this->normalizedValues($consequenceIdentities)) {
            throw ValidationException::withMessages(['matrix' => __('assestme.risk.errors.invalid_matrix')]);
        }

        $expectedLikelihoods = $this->normalizedValues($likelihoodIdentities);
        $knownPriorities = array_fill_keys($priorityIdentities, true);
        foreach ($consequenceIdentities as $consequenceIdentity) {
            $row = $matrix[$consequenceIdentity] ?? null;
            if (! is_array($row) || $this->normalizedKeys($row) !== $expectedLikelihoods) {
                throw ValidationException::withMessages(['matrix' => __('assestme.risk.errors.invalid_matrix')]);
            }

            foreach ($likelihoodIdentities as $likelihoodIdentity) {
                $priorityIdentity = (string) ($row[$likelihoodIdentity] ?? '');
                if (! isset($knownPriorities[$priorityIdentity])) {
                    throw ValidationException::withMessages([
                        "matrix.{$consequenceIdentity}.{$likelihoodIdentity}" => __('assestme.risk.errors.foreign_priority'),
                    ]);
                }
            }
        }
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<string>
     */
    private function levelIdentities(array $rows, string $statePath): array
    {
        $identities = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages([$statePath => __('assestme.risk.errors.invalid_matrix')]);
            }

            $identity = filled($row['id'] ?? null)
                ? (string) $row['id']
                : (string) ($row['_form_key'] ?? '');
            if ($identity === '' || in_array($identity, $identities, true)) {
                throw ValidationException::withMessages([
                    "{$statePath}.{$index}" => __('assestme.risk.errors.unknown_level'),
                ]);
            }

            $identities[] = $identity;
        }

        return $identities;
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return list<string>
     */
    private function normalizedKeys(array $values): array
    {
        return $this->normalizedValues(array_map('strval', array_keys($values)));
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function normalizedValues(array $values): array
    {
        sort($values, SORT_STRING);

        return $values;
    }

    /** @param callable(string): bool $exists */
    private function nextCode(string $label, callable $exists): string
    {
        $base = Str::limit(Str::slug(Str::lower(trim($label)), '_'), 32, '');
        $base = $base === '' ? 'livello' : $base;
        $candidate = $base;
        $suffix = 2;
        while ($exists($candidate)) {
            $candidate = Str::limit($base, 40 - strlen((string) $suffix) - 1, '').'_'.$suffix;
            $suffix++;
        }

        return $candidate;
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
        $keptIds = [];

        foreach ($rows as $row) {
            /** @var array<string, mixed> $row */
            $code = (string) $row['code'];
            $identity = filled($row['id'] ?? null) ? (string) $row['id'] : (string) $row['_form_key'];
            /** @var TLevel $level */
            $level = isset($row['id'])
                ? $modelClass::query()->where('risk_profile_id', $profile->getKey())->findOrFail($row['id'])
                : new $modelClass(['risk_profile_id' => $profile->getKey(), 'code' => $code]);
            $level->fill([
                'label' => $row['label'],
                'description' => $row['description'] ?? null,
                'score' => $row['score'],
                'color' => mb_strtoupper((string) $row['color']),
                'sort_order' => $row['sort_order'],
                'is_enabled' => $row['is_enabled'],
            ]);
            $level->save();
            $keptIds[] = (int) $level->getKey();
            $levels[$identity] = $level;
        }

        // Historical levels remain referentially valid and are disabled instead of deleted.
        $modelClass::query()
            ->where('risk_profile_id', $profile->getKey())
            ->whereNotIn('id', $keptIds)
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
        $keptIds = [];

        foreach ($rows as $row) {
            /** @var array<string, mixed> $row */
            $code = (string) $row['code'];
            $identity = filled($row['id'] ?? null) ? (string) $row['id'] : (string) $row['_form_key'];
            $level = isset($row['id'])
                ? PriorityLevel::query()->where('risk_profile_id', $profile->getKey())->findOrFail($row['id'])
                : new PriorityLevel(['risk_profile_id' => $profile->getKey(), 'code' => $code]);
            $level->fill([
                'label' => $row['label'],
                'description' => $row['description'] ?? null,
                'color' => mb_strtoupper((string) $row['color']),
                'sort_order' => $row['sort_order'],
                'is_enabled' => $row['is_enabled'],
            ]);
            $level->save();
            $keptIds[] = (int) $level->getKey();
            $levels[$identity] = $level;
        }

        PriorityLevel::query()
            ->where('risk_profile_id', $profile->getKey())
            ->whereNotIn('id', $keptIds)
            ->update(['is_enabled' => false]);

        return $levels;
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @param  array<string, ConsequenceLevel>  $consequences
     * @param  array<string, LikelihoodLevel>  $likelihoods
     * @param  array<string, PriorityLevel>  $priorities
     */
    private function syncMatrix(RiskProfile $profile, array $rows, array $consequences, array $likelihoods, array $priorities): void
    {
        $keptIds = [];

        foreach ($consequences as $consequenceIdentity => $consequence) {
            foreach ($likelihoods as $likelihoodIdentity => $likelihood) {
                $priorityIdentity = (string) $rows[$consequenceIdentity][$likelihoodIdentity];
                $priority = $priorities[$priorityIdentity];
                $entry = RiskMatrixEntry::query()->updateOrCreate(
                    [
                        'risk_profile_id' => $profile->getKey(),
                        'consequence_level_id' => $consequence->getKey(),
                        'likelihood_level_id' => $likelihood->getKey(),
                    ],
                    ['priority_level_id' => $priority->getKey()],
                );
                $keptIds[] = (int) $entry->getKey();
            }
        }

        RiskMatrixEntry::query()
            ->where('risk_profile_id', $profile->getKey())
            ->whereNotIn('id', $keptIds)
            ->delete();
    }
}
