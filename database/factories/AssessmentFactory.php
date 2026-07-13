<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Assessment> */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => 'Assessment IT — '.fake()->company(),
            'assessment_date' => today(),
            'status' => AssessmentStatus::Draft,
            'lock_version' => 0,
        ];
    }
}
