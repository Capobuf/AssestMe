<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Reports\GenerateAssessmentPdf;
use App\Enums\ScopeType;
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
            'assessment-list' => '/admin/assessments',
            'assessment-create' => '/admin/assessments/create',
            'workspace' => "/admin/assessments/{$assessment->getKey()}/workspace",
            'company-create' => '/admin/clients/create',
            'site-create' => '/admin/sites/create',
            'asset-create' => '/admin/assets/create',
            'template-create' => '/admin/finding-templates/create',
            'template-edit' => "/admin/finding-templates/{$template->getKey()}/edit",
            'risk-profile-edit' => "/admin/risk-profiles/{$profile->getKey()}/edit",
            'report-settings' => '/admin/report-settings-page',
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
                    ->visit("/admin/assessments/{$assessment->getKey()}/workspace")
                    ->waitForText('File Generati')
                    ->press('File Generati')
                    ->waitForText($report->file_name)
                    ->pause(300);
                $browser->driver->takeScreenshot("{$artifactRoot}/generated-files-{$width}x{$height}-dark.png");

                $browser->visit('/admin')
                    ->script('window.scrollTo(0, document.documentElement.scrollHeight)');
                $browser->waitForText('Stato applicazione')
                    ->waitFor('[data-dusk="application-status"]')
                    ->scrollIntoView('[data-dusk="application-status"]')
                    ->pause(250);
                $browser->driver->takeScreenshot("{$artifactRoot}/dashboard-application-status-{$width}x{$height}-dark.png");

                $browser->visit("/admin/risk-profiles/{$profile->getKey()}/edit")
                    ->waitFor('[data-dusk="risk-matrix-grid"]')
                    ->scrollIntoView('[data-dusk="risk-matrix-grid"]')
                    ->pause(250);
                $browser->driver->takeScreenshot("{$artifactRoot}/risk-matrix-{$width}x{$height}-dark.png");

                $browser->visit("/admin/finding-templates/{$template->getKey()}/edit")
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
                'dashboard-application-status',
                'risk-matrix',
                'template-solutions',
                'generated-pdf',
            ] as $name) {
                $theme = $name === 'generated-pdf' ? 'light' : 'dark';
                $path = "{$artifactRoot}/{$name}-{$width}x{$height}-{$theme}.png";
                self::assertFileExists($path);
                self::assertGreaterThan(0, (int) filesize($path));
            }
        }
    }
}
