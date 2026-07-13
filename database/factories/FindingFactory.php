<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\FindingStatus;
use App\Models\Assessment;
use App\Models\Finding;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Finding> */
class FindingFactory extends Factory
{
    protected $model = Finding::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'title' => fake()->sentence(5),
            'problem' => fake()->paragraphs(2, true),
            'entrepreneur_notes' => fake()->paragraph(),
            'status' => FindingStatus::Open,
            'include_in_report' => true,
            'sort_order' => 0,
        ];
    }
}
