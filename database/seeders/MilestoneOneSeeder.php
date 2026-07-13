<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\AssetTypes\SaveAssetType;
use App\Actions\Categories\SaveCategory;
use App\Models\AssetType;
use App\Models\Category;
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

    /** @var list<string> */
    private const CATEGORIES = [
        'Governance IT',
        'Sicurezza',
        'Rete',
        'Cablaggio e Infrastruttura Fisica',
        'Server',
        'NAS e Storage',
        'Backup',
        'Endpoint',
        'Identità e Accessi',
        'Cloud e Microsoft 365',
        'Posta Elettronica',
        'VoIP',
        'Videosorveglianza',
        'Continuità Operativa',
        'Monitoraggio',
        'Documentazione',
        'Licenze e Conformità',
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

        $saveCategory = app(SaveCategory::class);

        foreach (self::CATEGORIES as $index => $name) {
            $slug = Str::slug($name);
            $existing = Category::withTrashed()->where('slug', $slug)->first();

            $saveCategory->handle($existing, [
                'name' => $name,
                'slug' => $slug,
                'description' => $existing?->description,
                'color' => $existing?->color,
                'sort_order' => $index + 1,
                'is_enabled' => true,
            ]);
        }
    }
}
