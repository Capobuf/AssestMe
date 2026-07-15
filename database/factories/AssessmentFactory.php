<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssessmentStatus;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Assessment> */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'title' => 'Assessment IT — '.fake()->company(),
            'assessment_date' => today(),
            'status' => AssessmentStatus::Draft,
            'scope_type' => ScopeType::Organization,
            'lock_version' => 0,
        ];
    }
}
