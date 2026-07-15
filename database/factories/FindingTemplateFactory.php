<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ScopeType;
use App\Models\Category;
use App\Models\FindingTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FindingTemplate> */
final class FindingTemplateFactory extends Factory
{
    protected $model = FindingTemplate::class;

    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->slug(3),
            'title' => fake()->sentence(4),
            'category_id' => Category::factory(),
            'problem' => fake()->paragraph(),
            'default_scope_type' => ScopeType::Organization,
            'is_enabled' => true,
        ];
    }
}
