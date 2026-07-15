<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AssetType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AssetType> */
class AssetTypeFactory extends Factory
{
    protected $model = AssetType::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->unique()->numberBetween(100, 10000),
            'is_enabled' => true,
        ];
    }
}
