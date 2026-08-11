<?php

declare(strict_types=1);

use App\Models\AssetType;
use App\Models\Category;
use App\Models\EffortLevel;
use App\Models\FindingTemplate;
use App\Models\RiskProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException as SettingsMigrationException;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

it('keeps the assessment workspace migration independent from physical column ordering', function (): void {
    $migration = File::get(database_path('migrations/2026_07_13_000016_complete_assessment_workspace_schema.php'));

    expect($migration)->not->toContain('->after(');
});

it('keeps the Finding template lineage migration portable across supported databases', function (): void {
    $migration = File::get(database_path('migrations/2026_08_11_000021_add_source_template_fingerprint_to_findings_table.php'));

    expect($migration)
        ->toContain("->char('source_template_fingerprint', 64)->nullable()")
        ->not->toContain('->after(')
        ->not->toContain('DB::statement');
});

it('can resume and rerun every settings migration without overwriting existing values', function (): void {
    $encode = static fn (bool|int|string|null $value): string => json_encode($value, JSON_THROW_ON_ERROR);
    $runSettingsMigrations = static function (): void {
        $paths = glob(database_path('settings/*.php'));

        if (! is_array($paths)) {
            throw new SettingsMigrationException('Unable to enumerate settings migrations.');
        }

        sort($paths, SORT_STRING);

        foreach ($paths as $path) {
            $migration = require $path;

            if (! $migration instanceof SettingsMigration) {
                throw new SettingsMigrationException("Invalid settings migration at {$path}.");
            }

            $migration->up();
        }
    };

    DB::table('settings')
        ->where('group', 'general')
        ->where('name', 'application_name')
        ->update(['payload' => $encode('AssestMe personalizzato')]);
    DB::table('settings')
        ->where('group', 'general')
        ->where('name', 'timezone')
        ->delete();
    DB::table('settings')
        ->where('group', 'report')
        ->where('name', 'cover_title_mode')
        ->delete();
    DB::table('settings')->updateOrInsert(
        ['group' => 'report', 'name' => 'confidentiality_label'],
        [
            'locked' => false,
            'payload' => $encode('Valore precedente'),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );

    $runSettingsMigrations();
    $countAfterResume = DB::table('settings')->count();
    $runSettingsMigrations();

    expect(DB::table('settings')
        ->where('group', 'general')
        ->where('name', 'application_name')
        ->value('payload'))->toBe($encode('AssestMe personalizzato'))
        ->and(DB::table('settings')
            ->where('group', 'general')
            ->where('name', 'timezone')
            ->value('payload'))->toBe($encode('Europe/Rome'))
        ->and(DB::table('settings')
            ->where('group', 'report')
            ->where('name', 'cover_title_mode')
            ->value('payload'))->toBe($encode('separate'))
        ->and(DB::table('settings')
            ->where('group', 'report')
            ->where('name', 'show_resolution')
            ->value('payload'))->toBe($encode(true))
        ->and(DB::table('settings')->count())->toBe($countAfterResume)
        ->and(DB::table('settings')
            ->where('group', 'report')
            ->whereIn('name', [
                'confidentiality_label',
                'repeated_header_footer',
                'header_text',
                'footer_text',
                'new_page_per_finding',
            ])
            ->exists())->toBeFalse();
});

it('can rerun initial domain seeders without changing seeded identities or creating duplicates', function (): void {
    $seededIdentities = static fn (): array => [
        'asset_types' => AssetType::query()->orderBy('slug')->pluck('id', 'slug')->all(),
        'categories' => Category::withTrashed()->orderBy('slug')->pluck('id', 'slug')->all(),
        'risk_profiles' => RiskProfile::query()->orderBy('code')->pluck('id', 'code')->all(),
        'effort_levels' => EffortLevel::query()->orderBy('code')->pluck('id', 'code')->all(),
        'finding_templates' => FindingTemplate::withTrashed()->orderBy('external_id')->pluck('id', 'external_id')->all(),
    ];

    $this->seed(DatabaseSeeder::class);
    $before = $seededIdentities();
    $templateCount = FindingTemplate::withTrashed()->count();

    $this->seed(DatabaseSeeder::class);

    expect($seededIdentities())->toBe($before)
        ->and(AssetType::query()->count())->toBe(13)
        ->and(Category::withTrashed()->count())->toBe(18)
        ->and(RiskProfile::query()->count())->toBe(1)
        ->and(EffortLevel::query()->count())->toBe(4)
        ->and(FindingTemplate::withTrashed()->count())->toBe($templateCount);
});
