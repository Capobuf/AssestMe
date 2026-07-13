<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RiskProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RiskProfile> */
final class RiskProfileFactory extends Factory
{
    protected $model = RiskProfile::class;

    public function definition(): array
    {
        $code = fake()->unique()->lexify('profile_????');

        return [
            'code' => $code,
            'label' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'is_default' => false,
            'is_enabled' => true,
        ];
    }
}
