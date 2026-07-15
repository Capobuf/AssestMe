<?php

declare(strict_types=1);

namespace App\Actions\Categories;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class SaveCategory
{
    /** @param array<string, mixed> $data */
    public function handle(?Category $category, array $data): Category
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:20000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'is_enabled' => ['required', 'boolean'],
        ])->validate();

        $baseSlug = Str::slug((string) (($validated['slug'] ?? null) ?: $validated['name']));
        $validated['slug'] = $this->uniqueSlug($baseSlug, $category);
        $validated['color'] = $this->normalizeColor($validated['color'] ?? null);

        return DB::transaction(function () use ($category, $validated): Category {
            $record = $category ?? new Category;
            $record->fill($validated);
            $record->save();

            return $record;
        });
    }

    private function uniqueSlug(string $baseSlug, ?Category $category): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while (Category::withTrashed()
            ->when($category !== null, fn ($query) => $query->whereKeyNot($category?->getKey()))
            ->where('slug', $slug)
            ->exists()) {
            $slug = Str::limit($baseSlug, 160 - strlen((string) $suffix) - 1, '').'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function normalizeColor(mixed $color): ?string
    {
        return is_string($color) && $color !== '' ? mb_strtoupper($color) : null;
    }
}
