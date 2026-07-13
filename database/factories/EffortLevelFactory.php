<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\EffortLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EffortLevel> */
final class EffortLevelFactory extends Factory
{
    protected $model = EffortLevel::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('effort_????'),
            'label' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'color' => '#2563EB',
            'sort_order' => fake()->numberBetween(1, 100),
            'is_enabled' => true,
        ];
    }
}
