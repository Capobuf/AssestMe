<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\AssetTypes\SaveAssetType;
use App\Models\AssetType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class MilestoneOneSeeder extends Seeder
{
    /** @var list<string> */
    private const ASSET_TYPES = [
        'NAS',
        'Server',
        'Firewall',
        'Router',
        'Switch',
        'Access Point',
        'PBX',
        'Telefono',
        'Postazione di Lavoro',
        'Stampante',
        'UPS',
        'Armadio Rack',
        'Altro',
    ];

    public function run(): void
    {
        $saveAssetType = app(SaveAssetType::class);

        foreach (self::ASSET_TYPES as $index => $name) {
            $slug = Str::slug($name);
            $existing = AssetType::query()->where('slug', $slug)->first();

            $saveAssetType->handle($existing, [
                'name' => $name,
                'slug' => $slug,
                'description' => $existing?->description,
                'sort_order' => $index + 1,
                'is_enabled' => true,
            ]);
        }
    }
}
