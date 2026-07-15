<?php

declare(strict_types=1);

namespace App\Actions\AssetTypes;

use App\Models\AssetType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class SaveAssetType
{
    /** @param array<string, mixed> $data */
    public function handle(?AssetType $assetType, array $data): AssetType
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:20000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_enabled' => ['required', 'boolean'],
        ])->validate();

        $requestedSlug = Str::slug((string) (($validated['slug'] ?? null) ?: $validated['name']));
        $validated['slug'] = $this->uniqueSlug($requestedSlug, $assetType);

        return DB::transaction(function () use ($assetType, $validated): AssetType {
            $record = $assetType ?? new AssetType;
            $record->fill($validated);
            $record->save();

            return $record;
        });
    }

    private function uniqueSlug(string $requestedSlug, ?AssetType $assetType): string
    {
        $slug = $requestedSlug;
        $suffix = 2;

        while (AssetType::query()
            ->when($assetType !== null, fn ($query) => $query->whereKeyNot($assetType?->getKey()))
            ->where('slug', $slug)
            ->exists()) {
            $slug = Str::limit($requestedSlug, 160 - strlen((string) $suffix) - 1, '').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
