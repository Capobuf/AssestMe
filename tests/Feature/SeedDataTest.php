<?php

declare(strict_types=1);

use App\Actions\Templates\ExportFindingTemplates;
use App\Models\Assessment;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Client;
use App\Models\ConsequenceLevel;
use App\Models\EffortLevel;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Models\LikelihoodLevel;
use App\Models\PriorityLevel;
use App\Models\RiskProfile;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SampleDataSeeder;

it('keeps the default seed limited to domain configuration', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Category::query()->count())->toBe(18)
        ->and(AssetType::query()->count())->toBe(13)
        ->and(RiskProfile::query()->count())->toBe(1)
        ->and(EffortLevel::query()->count())->toBe(4)
        ->and(FindingTemplate::query()->count())->toBe(221)
        ->and(Client::query()->count())->toBe(0)
        ->and(Assessment::query()->count())->toBe(0)
        ->and(Finding::query()->count())->toBe(0);
});

it('seeds the comprehensive MSP library idempotently with valid stable classifications', function (): void {
    $this->seed(DatabaseSeeder::class);
    $firstExport = app(ExportFindingTemplates::class)(true);
    $templates = FindingTemplate::query()
        ->with(['solutions', 'category', 'defaultConsequenceLevel', 'defaultLikelihoodLevel', 'defaultPriorityLevel'])
        ->get();
    $legacyIds = [
        'nas.notifications.missing', 'infrastructure.nas.on-ups', 'security.rdp.public',
        'network.cabling.unlabelled', 'network.dhcp.invalid-dns', 'backup.restore-test.missing',
        'infrastructure.rack.unsuitable-location', 'ups.monitoring.missing',
    ];

    expect($templates)->toHaveCount(221)
        ->and($templates->pluck('external_id')->unique())->toHaveCount(221)
        ->and($templates->pluck('external_id')->all())->toContain(...$legacyIds)
        ->and($templates->every(fn (FindingTemplate $template): bool => $template->solutions->where('is_recommended', true)->count() === 1))->toBeTrue()
        ->and($templates->pluck('category.name')->unique()->sort()->values()->all())->toBe([
            'Backup', 'Cablaggio e Infrastruttura Fisica', 'Cloud e Microsoft 365', 'Continuità Operativa',
            'Documentazione', 'Endpoint', 'Governance IT', 'Identità e Accessi', 'Licenze e Conformità',
            'Monitoraggio', 'NAS e Storage', 'Posta Elettronica', 'Rete', 'Server', 'Sicurezza',
            'Videosorveglianza', 'VoIP',
        ])
        ->and($templates->every(static fn (FindingTemplate $template): bool => $template->defaultConsequenceLevel instanceof ConsequenceLevel
            && $template->defaultLikelihoodLevel instanceof LikelihoodLevel
            && $template->defaultPriorityLevel instanceof PriorityLevel))->toBeTrue();

    $this->seed(DatabaseSeeder::class);

    expect(FindingTemplate::query()->count())->toBe(221)
        ->and(app(ExportFindingTemplates::class)(true))->toBe($firstExport);
});

it('creates demonstration records only through the explicit sample seeder', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(SampleDataSeeder::class);

    expect(Client::query()->count())->toBe(1)
        ->and(Assessment::query()->count())->toBe(1)
        ->and(Finding::query()->count())->toBe(50);
});
