<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Asset> */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'site_id' => null,
            'asset_type_id' => AssetType::factory(),
            'name' => 'Asset '.fake()->unique()->numerify('####'),
            'manufacturer' => fake()->company(),
            'model' => fake()->bothify('Model-###??'),
            'hostname' => null,
            'ip_address' => fake()->optional()->ipv4(),
            'mac_address' => null,
            'serial_number' => fake()->optional()->bothify('SN-########'),
            'description' => null,
            'notes' => null,
        ];
    }
}
