<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AssessmentStatus;
use App\Filament\Pages\GeneralSettingsPage;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\FindingTemplates\FindingTemplateResource;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\Assessment;
use App\Models\Asset;
use App\Models\Client;
use App\Models\FindingTemplate;
use App\Models\Site;
use Filament\Widgets\Widget;

final class OperationalDashboard extends Widget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.operational-dashboard';

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $resumeAssessment = $this->resumeAssessment();
        $recentCompanies = Client::query()
            ->with('latestAssessment')
            ->withMax('assessments as latest_assessment_date', 'assessment_date')
            ->orderByRaw('CASE WHEN latest_assessment_date IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('latest_assessment_date')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Client $client): array => [
                'name' => $client->displayName(),
                'initials' => $this->initials($client->displayName()),
                'url' => ClientResource::getUrl('edit', ['record' => $client]),
                'latest_assessment' => $client->latestAssessment,
            ]);
        $latestAssessments = Assessment::query()
            ->with('client')
            ->latest('assessment_date')
            ->latest('id')
            ->limit(5)
            ->get();

        return [
            'resumeAssessment' => $resumeAssessment,
            'resumeUrl' => $resumeAssessment === null
                ? AssessmentResource::getUrl('create')
                : WorkspaceAssessment::getUrl(['record' => $resumeAssessment]),
            'launchers' => $this->launchers(),
            'recentCompanies' => $recentCompanies,
            'latestAssessments' => $latestAssessments,
            'archiveItems' => $this->archiveItems(),
        ];
    }

    private function resumeAssessment(): ?Assessment
    {
        return Assessment::query()
            ->with('client')
            ->where('status', AssessmentStatus::Draft)
            ->latest('updated_at')
            ->latest('id')
            ->first()
            ?? Assessment::query()
                ->with('client')
                ->latest('assessment_date')
                ->latest('id')
                ->first();
    }

    /** @return list<array{title: string, description: string, icon: string, url: string, primary: bool}> */
    private function launchers(): array
    {
        return [
            [
                'title' => __('assestme.dashboard.launchers.new_assessment'),
                'description' => __('assestme.dashboard.launchers.new_assessment_help'),
                'icon' => 'heroicon-o-plus',
                'url' => AssessmentResource::getUrl('create'),
                'primary' => true,
            ],
            [
                'title' => __('assestme.dashboard.launchers.companies'),
                'description' => __('assestme.dashboard.launchers.companies_help'),
                'icon' => 'heroicon-o-building-office-2',
                'url' => ClientResource::getUrl('index'),
                'primary' => false,
            ],
            [
                'title' => __('assestme.dashboard.launchers.assessments'),
                'description' => __('assestme.dashboard.launchers.assessments_help'),
                'icon' => 'heroicon-o-clipboard-document-check',
                'url' => AssessmentResource::getUrl('index'),
                'primary' => false,
            ],
            [
                'title' => __('assestme.dashboard.launchers.settings'),
                'description' => __('assestme.dashboard.launchers.settings_help'),
                'icon' => 'heroicon-o-cog-6-tooth',
                'url' => GeneralSettingsPage::getUrl(),
                'primary' => false,
            ],
        ];
    }

    /** @return list<array{label: string, description: string, count: int, icon: string, url: string}> */
    private function archiveItems(): array
    {
        return [
            [
                'label' => __('assestme.dashboard.archive.companies'),
                'description' => __('assestme.dashboard.archive.companies_help'),
                'count' => Client::query()->count(),
                'icon' => 'heroicon-o-building-office-2',
                'url' => ClientResource::getUrl('index'),
            ],
            [
                'label' => __('assestme.dashboard.archive.sites'),
                'description' => __('assestme.dashboard.archive.sites_help'),
                'count' => Site::query()->count(),
                'icon' => 'heroicon-o-map-pin',
                'url' => SiteResource::getUrl('index'),
            ],
            [
                'label' => __('assestme.dashboard.archive.assets'),
                'description' => __('assestme.dashboard.archive.assets_help'),
                'count' => Asset::query()->count(),
                'icon' => 'heroicon-o-server-stack',
                'url' => AssetResource::getUrl('index'),
            ],
            [
                'label' => __('assestme.dashboard.archive.templates'),
                'description' => __('assestme.dashboard.archive.templates_help'),
                'count' => FindingTemplate::query()->count(),
                'icon' => 'heroicon-o-document-duplicate',
                'url' => FindingTemplateResource::getUrl('index'),
            ],
        ];
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(implode('', array_map(
            static fn (string $word): string => mb_substr($word, 0, 1),
            array_slice($words, 0, 2),
        )));
    }
}
