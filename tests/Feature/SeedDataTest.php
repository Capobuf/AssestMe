<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Client;
use App\Models\EffortLevel;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Models\RiskProfile;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleDataSeeder;

it('keeps the default seed limited to domain configuration', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Category::query()->count())->toBe(18)
        ->and(AssetType::query()->count())->toBe(13)
        ->and(RiskProfile::query()->count())->toBe(1)
        ->and(EffortLevel::query()->count())->toBe(4)
        ->and(FindingTemplate::query()->count())->toBeGreaterThan(0)
        ->and(Client::query()->count())->toBe(0)
        ->and(Assessment::query()->count())->toBe(0)
        ->and(Finding::query()->count())->toBe(0);
});

it('creates demonstration records only through the explicit sample seeder', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(SampleDataSeeder::class);

    expect(Client::query()->count())->toBe(1)
        ->and(Assessment::query()->count())->toBe(1)
        ->and(Finding::query()->count())->toBe(50);
});
