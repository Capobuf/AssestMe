<?php

declare(strict_types=1);

use App\Enums\AssessmentStatus;
use App\Enums\DeletionOperationStatus;
use App\Filament\Pages\GeneralSettingsPage;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\FindingTemplates\FindingTemplateResource;
use App\Filament\Resources\Sites\SiteResource;
use App\Filament\Widgets\OperationalDashboard;
use App\Models\Assessment;
use App\Models\Asset;
use App\Models\Client;
use App\Models\DeletionOperation;
use App\Models\FindingTemplate;
use App\Models\Site;
use App\Models\User;
use App\Services\Backups\LatestBackupStatus;
use Carbon\CarbonImmutable;
use Database\Seeders\MilestoneOneSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

it('requires authentication and renders the operational homepage with real launcher routes', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');

    $administrator = User::factory()->create();
    $this->actingAs($administrator)
        ->get('/admin')
        ->assertOk()
        ->assertSee(__('assestme.dashboard.resume.heading'))
        ->assertSee(__('assestme.dashboard.quick_access'))
        ->assertSee(AssessmentResource::getUrl('create'), false)
        ->assertSee(ClientResource::getUrl('index'), false)
        ->assertSee(AssessmentResource::getUrl('index'), false)
        ->assertSee(GeneralSettingsPage::getUrl(), false);
});

it('shows useful empty states without inventing activity', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(OperationalDashboard::class)
        ->assertSee(__('assestme.dashboard.resume.empty_title'))
        ->assertSee(__('assestme.dashboard.resume.start_first'))
        ->assertSee(__('assestme.dashboard.companies.empty_title'))
        ->assertSee(__('assestme.dashboard.assessments.empty_title'))
        ->assertSee(__('assestme.dashboard.archive.heading'))
        ->assertViewHas('archiveItems', fn (array $items): bool => collect($items)->pluck('count')->all() === [0, 0, 0, 0])
        ->assertSeeHtml('href="'.AssessmentResource::getUrl('create').'"')
        ->assertSeeHtml('href="'.ClientResource::getUrl('create').'"')
        ->assertSeeHtml('href="'.SiteResource::getUrl('index').'"')
        ->assertSeeHtml('href="'.AssetResource::getUrl('index').'"')
        ->assertSeeHtml('href="'.FindingTemplateResource::getUrl('index').'"');
});

it('resumes the most recently updated draft and otherwise falls back to the latest assessment', function (): void {
    $administrator = User::factory()->create();
    $this->actingAs($administrator);
    $olderDraft = Assessment::factory()->create([
        'title' => 'Bozza precedente',
        'assessment_date' => '2026-08-10',
        'status' => AssessmentStatus::Draft,
        'updated_at' => '2026-08-10 12:00:00',
    ]);
    $resumeDraft = Assessment::factory()->create([
        'title' => 'Bozza da riprendere',
        'assessment_date' => '2026-08-08',
        'status' => AssessmentStatus::Draft,
        'updated_at' => '2026-08-12 09:30:00',
    ]);
    Assessment::factory()->create([
        'title' => 'Completato più recente per data',
        'assessment_date' => '2026-08-12',
        'status' => AssessmentStatus::Completed,
        'updated_at' => '2026-08-12 11:00:00',
    ]);

    Livewire::test(OperationalDashboard::class)
        ->assertViewHas('resumeAssessment', fn (?Assessment $assessment): bool => $assessment?->is($resumeDraft) === true)
        ->assertSeeHtml('href="'.WorkspaceAssessment::getUrl(['record' => $resumeDraft]).'"');

    $olderDraft->update(['status' => AssessmentStatus::Completed]);
    $resumeDraft->update(['status' => AssessmentStatus::Completed]);
    $latest = Assessment::query()->where('title', 'Completato più recente per data')->sole();

    Livewire::test(OperationalDashboard::class)
        ->assertViewHas('resumeAssessment', fn (?Assessment $assessment): bool => $assessment?->is($latest) === true);
});

it('orders recent companies by their latest real assessment and handles companies without one', function (): void {
    $this->actingAs(User::factory()->create());
    $withoutAssessment = Client::factory()->create([
        'legal_name' => 'Azienda senza assessment S.r.l.',
        'trade_name' => null,
        'updated_at' => '2026-08-12 12:00:00',
    ]);
    $olderClient = Client::factory()->create(['trade_name' => 'Azienda precedente']);
    Assessment::factory()->for($olderClient)->create([
        'title' => 'Assessment precedente',
        'assessment_date' => '2026-08-09',
    ]);
    $recentClient = Client::factory()->create(['trade_name' => 'Azienda recente']);
    $latestAssessment = Assessment::factory()->for($recentClient)->create([
        'title' => 'Assessment recente',
        'assessment_date' => '2026-08-11',
        'status' => AssessmentStatus::Completed,
    ]);
    Assessment::factory()->for($recentClient)->create([
        'title' => 'Assessment storico stessa azienda',
        'assessment_date' => '2026-08-01',
    ]);

    Livewire::test(OperationalDashboard::class)
        ->assertViewHas('recentCompanies', function ($companies) use ($latestAssessment): bool {
            return $companies->pluck('name')->all() === [
                'Azienda recente',
                'Azienda precedente',
                'Azienda senza assessment S.r.l.',
            ] && $companies->first()['latest_assessment']->is($latestAssessment);
        })
        ->assertSee('Azienda recente')
        ->assertSee('Assessment recente')
        ->assertSee(__('assestme.dashboard.companies.no_assessment'))
        ->assertSeeHtml('href="'.ClientResource::getUrl('edit', ['record' => $withoutAssessment]).'"');
});

it('shows only the latest five assessments', function (): void {
    $this->seed(MilestoneOneSeeder::class);
    $this->actingAs(User::factory()->create());
    $assessments = Assessment::factory()->count(6)->sequence(
        fn ($sequence): array => [
            'title' => 'Assessment '.($sequence->index + 1),
            'status' => $sequence->index === 0 ? AssessmentStatus::Completed : AssessmentStatus::Draft,
            'assessment_date' => today()->subDays($sequence->index),
        ],
    )->create();
    $assessments->each(function (Assessment $assessment, int $index): void {
        $assessment->client->update(['trade_name' => 'Azienda '.($index + 1)]);
    });
    Livewire::test(OperationalDashboard::class)
        ->assertViewHas('latestAssessments', function ($latestAssessments): bool {
            return $latestAssessments->pluck('client.trade_name')->all() === [
                'Azienda 1',
                'Azienda 2',
                'Azienda 3',
                'Azienda 4',
                'Azienda 5',
            ];
        })
        ->assertSee('Azienda 1')
        ->assertSee('Azienda 5');
});

it('shows real archive counts and links to every existing entity index', function (): void {
    $this->actingAs(User::factory()->create());
    $clients = Client::factory()->count(2)->create();
    Site::factory()->count(3)->for($clients->first())->create();
    Asset::factory()->count(4)->for($clients->first())->create();
    FindingTemplate::factory()->count(5)->create();

    Livewire::test(OperationalDashboard::class)
        ->assertViewHas('archiveItems', function (array $items): bool {
            return collect($items)->pluck('count')->all() === [2, 3, 4, 5];
        })
        ->assertSee(__('assestme.dashboard.archive.heading'))
        ->assertSeeHtml('href="'.ClientResource::getUrl('index').'"')
        ->assertSeeHtml('href="'.SiteResource::getUrl('index').'"')
        ->assertSeeHtml('href="'.AssetResource::getUrl('index').'"')
        ->assertSeeHtml('href="'.FindingTemplateResource::getUrl('index').'"');
});

it('loads bounded dashboard records without a query growing per row', function (): void {
    $this->seed(MilestoneOneSeeder::class);
    $this->actingAs(User::factory()->create());
    Assessment::factory()->count(12)->create();
    $selects = [];
    DB::listen(static function ($query) use (&$selects): void {
        if (str_starts_with(mb_strtolower(ltrim($query->sql)), 'select')) {
            $selects[] = $query->sql;
        }
    });

    Livewire::test(OperationalDashboard::class);

    expect($selects)->not->toBeEmpty()
        ->and(count($selects))->toBeLessThanOrEqual(16);
});

it('registers only the operational dashboard widget and omits the account welcome card', function (): void {
    $widgets = array_values(Filament::getPanel('admin')->getWidgets());

    expect($widgets)
        ->toContain(OperationalDashboard::class)
        ->not->toContain(AccountWidget::class);
});

it('reports the newest managed backup without treating unrelated archives as successful', function (): void {
    $root = storage_path('framework/testing/dashboard-backups-'.bin2hex(random_bytes(6)));
    File::ensureDirectoryExists($root);
    config()->set('assestme.backup.root', $root);

    try {
        $older = $root.DIRECTORY_SEPARATOR.'assestme-20260712-023000.tar.gz';
        $newer = $root.DIRECTORY_SEPARATOR.'assestme-20260713-023000.tar.gz';
        $unrelated = $root.DIRECTORY_SEPARATOR.'manual.tar.gz';
        File::put($older, 'older');
        File::put($newer, 'newer');
        File::put($unrelated, 'unrelated');
        touch($older, 100);
        touch($newer, 200);
        touch($unrelated, 300);

        $status = app(LatestBackupStatus::class)->latestSuccessfulAt();

        expect($status)->toBeInstanceOf(CarbonImmutable::class)
            ->and($status?->getTimestamp())->toBe(200);
    } finally {
        File::deleteDirectory($root);
    }
});

it('casts deletion operation status and manifest deterministically', function (): void {
    $operation = DeletionOperation::query()->create([
        'uuid' => fake()->uuid(),
        'entity_type' => Assessment::class,
        'entity_id' => 123,
        'status' => DeletionOperationStatus::Staged,
        'trash_path' => 'trash/example',
        'manifest' => [
            ['source' => 'evidence/example.png', 'trash' => 'trash/example/example.png'],
        ],
    ])->fresh();

    expect($operation?->status)->toBe(DeletionOperationStatus::Staged)
        ->and($operation?->manifest)->toBe([
            ['source' => 'evidence/example.png', 'trash' => 'trash/example/example.png'],
        ]);
});
