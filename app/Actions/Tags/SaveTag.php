<?php

declare(strict_types=1);

namespace App\Actions\Tags;

use App\Models\Tag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class SaveTag
{
    /** @param array<string, mixed> $data */
    public function handle(?Tag $tag, array $data): Tag
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ])->validate();

        $baseSlug = Str::slug((string) (($validated['slug'] ?? null) ?: $validated['name']));
        $validated['slug'] = $this->uniqueSlug($baseSlug, $tag);
        $validated['color'] = $this->normalizeColor($validated['color'] ?? null);

        return DB::transaction(function () use ($tag, $validated): Tag {
            $record = $tag ?? new Tag;
            $record->fill($validated);
            $record->save();

            return $record;
        });
    }

    private function uniqueSlug(string $baseSlug, ?Tag $tag): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while (Tag::withTrashed()
            ->when($tag !== null, fn ($query) => $query->whereKeyNot($tag?->getKey()))
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
