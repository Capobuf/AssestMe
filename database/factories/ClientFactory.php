<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'legal_name' => fake()->company().' S.r.l.',
            'trade_name' => fake()->optional()->company(),
            'vat_number' => fake()->numerify('IT###########'),
            'tax_code' => null,
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'website' => fake()->url(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'province' => mb_strtoupper(fake()->lexify('??')),
            'country' => 'IT',
            'logo_path' => null,
            'internal_notes' => null,
        ];
    }
}
