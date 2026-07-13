<?php

declare(strict_types=1);

namespace App\Actions\EffortLevels;

use App\Models\EffortLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SaveEffortLevel
{
    /** @param array<string, mixed> $data */
    public function handle(?EffortLevel $level, array $data): EffortLevel
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('effort_levels', 'code')->ignore($level?->getKey())],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:20000'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_enabled' => ['required', 'boolean'],
        ])->validate();

        $validated['color'] = mb_strtoupper((string) $validated['color']);

        return DB::transaction(function () use ($level, $validated): EffortLevel {
            $record = $level ?? new EffortLevel;
            $record->fill($validated);
            $record->save();

            return $record;
        });
    }
}
