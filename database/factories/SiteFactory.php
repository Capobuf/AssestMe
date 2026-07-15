<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Site> */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => 'Sede '.fake()->city(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'province' => mb_strtoupper(fake()->lexify('??')),
            'country' => 'IT',
            'description' => fake()->optional()->sentence(),
            'notes' => null,
        ];
    }
}
