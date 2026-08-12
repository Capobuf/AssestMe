<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Reports\GenerateAssessmentPdf;
use App\Enums\ScopeType;
use App\Filament\Pages\ReportSettingsPage;
use App\Filament\Resources\Assessments\AssessmentResource;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\FindingTemplates\FindingTemplateResource;
use App\Filament\Resources\RiskProfiles\RiskProfileResource;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\Assessment;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Client;
use App\Models\FindingTemplate;
use App\Models\RiskProfile;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Assert;
use Tests\DuskTestCase;

final class ApprovedUxQaTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_approved_pages_render_in_dark_mode_at_both_desktop_viewports(): void
    {
        $this->seed(DatabaseSeeder::class);
        $administrator = User::factory()->create();
        $client = Client::factory()->create(['trade_name' => 'Azienda QA']);
        $site = Site::factory()->for($client)->create(['name' => 'Sede QA']);
        Asset::factory()->for($client)->for($site)->create([
            'asset_type_id' => AssetType::query()->firstOrFail()->getKey(),
            'name' => 'Asset QA',
        ]);
        $template = FindingTemplate::query()->with('solutions')->firstOrFail();
        $profile = RiskProfile::query()->where('is_default', true)->firstOrFail();
        $assessment = Assessment::factory()->for($client)->create(['title' => 'Assessment QA approvato']);
        app(CopyTemplateToAssessment::class)($assessment, $template)->update([
            'scope_type' => ScopeType::Organization,
            'scope_description' => null,
        ]);
        $report = app(GenerateAssessmentPdf::class)($assessment->fresh());
        $artifactRoot = base_path('storage/app/qa-artifacts');
        File::ensureDirectoryExists($artifactRoot);

        $pages = [
            'dashboard' => '/admin',
            'assessment-list' => AssessmentResource::getUrl('index'),
            'assessment-create' => AssessmentResource::getUrl('create'),
            'workspace' => AssessmentResource::getUrl('workspace', ['record' => $assessment]),
            'company-create' => ClientResource::getUrl('create'),
            'site-create' => SiteResource::getUrl('create'),
            'asset-create' => AssetResource::getUrl('create'),
            'template-create' => FindingTemplateResource::getUrl('create'),
            'template-edit' => FindingTemplateResource::getUrl('edit', ['record' => $template]),
            'risk-profile-edit' => RiskProfileResource::getUrl('edit', ['record' => $profile]),
            'report-settings' => ReportSettingsPage::getUrl(),
        ];

        $this->browse(function (Browser $browser) use ($administrator, $assessment, $artifactRoot, $pages, $profile, $report, $template): void {
            $browser->loginAs($administrator);

            foreach ([[1366, 768], [1920, 1080]] as [$width, $height]) {
                foreach ($pages as $name => $url) {
                    $browser->resize($width, $height)
                        ->visit($url)
                        ->waitUntil('return document.readyState === "complete"')
                        ->script("localStorage.setItem('theme', 'dark'); document.documentElement.classList.add('dark');");
                    $browser->pause(350);

                    $layout = $browser->script(<<<'JS'
                        return {
                            dark: document.documentElement.classList.contains('dark'),
                            width: document.documentElement.clientWidth,
                            scrollWidth: document.documentElement.scrollWidth,
                        };
                        JS)[0];
                    Assert::assertTrue($layout['dark'], "Dark mode was not active for {$name}.");
                    Assert::assertLessThanOrEqual(
                        $layout['width'] + 1,
                        $layout['scrollWidth'],
                        "The {$name} page overflowed the desktop viewport.",
                    );
                    $browser->driver->takeScreenshot("{$artifactRoot}/{$name}-{$width}x{$height}-dark.png");
                }

                $browser->resize($width, $height)
                    ->visit(AssessmentResource::getUrl('workspace', ['record' => $assessment]))
                    ->waitForText('File Generati')
                    ->press('File Generati')
                    ->waitForText($report->file_name)
                    ->pause(300);
                $browser->driver->takeScreenshot("{$artifactRoot}/generated-files-{$width}x{$height}-dark.png");

                $browser->visit('/admin')
                    ->script('window.scrollTo(0, document.documentElement.scrollHeight)');
                $browser->waitForText('Archivio AssestMe')
                    ->waitFor('[data-dusk="dashboard-archive"]')
                    ->scrollIntoView('[data-dusk="dashboard-archive"]')
                    ->pause(250);
                $browser->driver->takeScreenshot("{$artifactRoot}/dashboard-archive-{$width}x{$height}-dark.png");

                $browser->visit(RiskProfileResource::getUrl('edit', ['record' => $profile]))
                    ->waitFor('[data-dusk="risk-matrix-grid"]')
                    ->scrollIntoView('[data-dusk="risk-matrix-grid"]')
                    ->pause(250);
                $browser->driver->takeScreenshot("{$artifactRoot}/risk-matrix-{$width}x{$height}-dark.png");
                $browser->script("localStorage.setItem('theme', 'light'); document.documentElement.classList.remove('dark');");
                $browser->pause(200);
                $browser->driver->takeScreenshot("{$artifactRoot}/risk-matrix-{$width}x{$height}-light.png");
                $browser->script("localStorage.setItem('theme', 'dark'); document.documentElement.classList.add('dark');");

                $browser->visit(FindingTemplateResource::getUrl('edit', ['record' => $template]))
                    ->waitUntil(<<<'JS'
                        return Array.from(document.querySelectorAll('.fi-sc-section'))
                            .some((section) => section.textContent.includes('Soluzioni'));
                        JS)
                    ->script(<<<'JS'
                        Array.from(document.querySelectorAll('.fi-sc-section'))
                            .find((section) => section.textContent.includes('Soluzioni'))
                            ?.scrollIntoView({ block: 'start' });
                        JS);
                $browser->pause(250);
                $browser->driver->takeScreenshot("{$artifactRoot}/template-solutions-{$width}x{$height}-dark.png");

                $pdfContents = Storage::disk('local')->get($report->file_path);
                $browser->driver->get('data:application/pdf;base64,'.base64_encode($pdfContents));
                $browser->pause(1200);
                $browser->driver->takeScreenshot("{$artifactRoot}/generated-pdf-{$width}x{$height}-light.png");
            }

            $severeLogs = array_values(array_filter(
                $browser->driver->manage()->getLog('browser'),
                static fn (array $entry): bool => ($entry['level'] ?? '') === 'SEVERE',
            ));
            Assert::assertSame([], $severeLogs, 'The approved UX QA pages contain severe console errors.');
        });

        foreach ([[1366, 768], [1920, 1080]] as [$width, $height]) {
            foreach ([
                ...array_keys($pages),
                'generated-files',
                'dashboard-archive',
                'risk-matrix',
                'template-solutions',
                'generated-pdf',
            ] as $name) {
                $theme = $name === 'generated-pdf' ? 'light' : 'dark';
                $path = "{$artifactRoot}/{$name}-{$width}x{$height}-{$theme}.png";
                self::assertFileExists($path);
                self::assertGreaterThan(0, (int) filesize($path));
            }

            $lightMatrixPath = "{$artifactRoot}/risk-matrix-{$width}x{$height}-light.png";
            self::assertFileExists($lightMatrixPath);
            self::assertGreaterThan(0, (int) filesize($lightMatrixPath));
        }
    }
}
