<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\AssetTypes\SaveAssetType;
use App\Actions\Categories\SaveCategory;
use App\Actions\EffortLevels\SaveEffortLevel;
use App\Actions\Risk\SaveRiskProfileConfiguration;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\EffortLevel;
use App\Models\RiskProfile;
use App\Settings\GeneralSettings;
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

        $this->seedRiskConfiguration();
        $this->seedEffortLevels();
    }

    private function seedRiskConfiguration(): void
    {
        $consequences = [
            ['code' => 'limited', 'label' => 'Limitata', 'score' => 1, 'color' => '#2563EB'],
            ['code' => 'significant', 'label' => 'Significativa', 'score' => 2, 'color' => '#D97706'],
            ['code' => 'serious', 'label' => 'Seria', 'score' => 3, 'color' => '#EA580C'],
            ['code' => 'critical', 'label' => 'Critica', 'score' => 4, 'color' => '#DC2626'],
        ];
        $likelihoods = [
            ['code' => 'unlikely', 'label' => 'Improbabile', 'score' => 1, 'color' => '#2563EB'],
            ['code' => 'possible', 'label' => 'Possibile', 'score' => 2, 'color' => '#D97706'],
            ['code' => 'likely', 'label' => 'Probabile', 'score' => 3, 'color' => '#EA580C'],
            ['code' => 'current', 'label' => 'Attuale o imminente', 'score' => 4, 'color' => '#DC2626'],
        ];
        $priorities = [
            ['code' => 'low', 'label' => 'Bassa', 'color' => '#15803D'],
            ['code' => 'moderate', 'label' => 'Moderata', 'color' => '#D97706'],
            ['code' => 'high', 'label' => 'Alta', 'color' => '#EA580C'],
            ['code' => 'critical', 'label' => 'Critica', 'color' => '#DC2626'],
        ];
        $matrixCodes = [
            ['low', 'low', 'moderate', 'moderate'],
            ['low', 'moderate', 'moderate', 'high'],
            ['moderate', 'high', 'high', 'critical'],
            ['high', 'high', 'critical', 'critical'],
        ];
        $decorate = static fn (array $row, int $index): array => $row + [
            '_form_key' => (string) Str::uuid(),
            'description' => null,
            'sort_order' => $index + 1,
            'is_enabled' => true,
        ];

        $profile = RiskProfile::query()->where('code', 'default')->first();
        $consequences = array_map($decorate, $consequences, array_keys($consequences));
        $likelihoods = array_map($decorate, $likelihoods, array_keys($likelihoods));
        $priorities = array_map($decorate, $priorities, array_keys($priorities));

        if ($profile !== null) {
            foreach ([
                'consequenceLevels' => &$consequences,
                'likelihoodLevels' => &$likelihoods,
                'priorityLevels' => &$priorities,
            ] as $relation => &$rows) {
                $idsByCode = $profile->{$relation}()->pluck('id', 'code');
                foreach ($rows as &$row) {
                    $row['id'] = $idsByCode->get($row['code']);
                }
                unset($row);
            }
            unset($rows);
        }

        $priorityIdentitiesByCode = [];
        foreach ($priorities as $priority) {
            $priorityIdentitiesByCode[$priority['code']] = (string) ($priority['id'] ?? $priority['_form_key']);
        }

        $matrix = [];
        foreach ($consequences as $consequenceIndex => $consequence) {
            $consequenceIdentity = (string) ($consequence['id'] ?? $consequence['_form_key']);
            foreach ($likelihoods as $likelihoodIndex => $likelihood) {
                $likelihoodIdentity = (string) ($likelihood['id'] ?? $likelihood['_form_key']);
                $matrix[$consequenceIdentity][$likelihoodIdentity] = $priorityIdentitiesByCode[$matrixCodes[$consequenceIndex][$likelihoodIndex]];
            }
        }

        $profile = app(SaveRiskProfileConfiguration::class)->handle(
            $profile,
            [
                'code' => 'default',
                'label' => 'Profilo predefinito',
                'description' => 'Matrice di priorità predefinita AssestMe.',
                'is_default' => true,
                'is_enabled' => true,
                'consequences' => $consequences,
                'likelihoods' => $likelihoods,
                'priorities' => $priorities,
                'matrix' => $matrix,
            ],
        );

        $settings = app(GeneralSettings::class);
        $settings->active_risk_profile_id = $profile->getKey();
        $settings->save();
    }

    private function seedEffortLevels(): void
    {
        $levels = [
            ['code' => 'low', 'label' => 'Basso', 'color' => '#15803D'],
            ['code' => 'moderate', 'label' => 'Moderato', 'color' => '#D97706'],
            ['code' => 'high', 'label' => 'Alto', 'color' => '#EA580C'],
            ['code' => 'very_high', 'label' => 'Molto alto', 'color' => '#DC2626'],
        ];

        foreach ($levels as $index => $data) {
            app(SaveEffortLevel::class)->handle(
                EffortLevel::query()->where('code', $data['code'])->first(),
                $data + ['description' => null, 'sort_order' => $index + 1, 'is_enabled' => true],
            );
        }
    }
}
