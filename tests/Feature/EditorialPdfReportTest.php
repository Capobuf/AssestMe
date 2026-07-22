<?php

declare(strict_types=1);

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Reports\BuildAssessmentSnapshot;
use App\Actions\Reports\GenerateAssessmentPdf;
use App\Data\Reports\AssessmentReportData;
use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Finding;
use App\Models\FindingSolution;
use App\Models\FindingTemplate;
use App\Models\GeneratedReport;
use App\Models\Site;
use App\Settings\GeneralSettings;
use App\Settings\ReportSettings;
use Database\Seeders\MilestoneOneSeeder;
use Database\Seeders\MilestoneTwoSeeder;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

beforeEach(function (): void {
    $this->seed(MilestoneOneSeeder::class);
    $this->seed(MilestoneTwoSeeder::class);
    Storage::fake('local');
});

it('renders each cover branding mode in its editorial position', function (
    string $branding,
    array $expectedOwners,
    array $expectedMarkup,
    array $excludedMarkup,
): void {
    [$assessment] = editorialPdfReportReadyAssessment();
    editorialPdfReportStoreLogos($assessment);
    editorialPdfReportSettings([
        'branding' => $branding,
        'business_name' => 'Studio Editoriale Sicuro',
        'consultant_name' => 'Consulente Editoriale',
        'consultant_role' => 'Responsabile assessment',
        'consultant_logo_path' => 'logos/editorial-consultant.png',
    ]);

    $result = editorialPdfReportRenderAndGenerate($assessment);
    $owners = array_column($result['report']->payload_snapshot['logos'], 'owner');

    expect($owners)->toBe($expectedOwners)
        ->and($result['text'])->toContain('Assessment IT', 'Azienda Editoriale S.r.l.');

    foreach ($expectedMarkup as $markup) {
        expect($result['html'])->toContain($markup);
    }
    foreach ($excludedMarkup as $markup) {
        expect($result['html'])->not->toContain($markup);
    }
})->with([
    'consultant branding' => [
        'consultant',
        ['consultant'],
        ['class="cover__footer-logo"'],
        ['class="cover__footer-client-logo"', 'class="cover__client-logo"'],
    ],
    'client branding' => [
        'client',
        ['client'],
        ['class="cover__footer-client-logo"'],
        ['class="cover__footer-logo"', 'class="cover__client-logo"'],
    ],
    'combined branding' => [
        'both',
        ['consultant', 'client'],
        ['class="cover__footer-logo"', 'class="cover__client-logo"'],
        ['class="cover__footer-client-logo"'],
    ],
]);

it('selects the deterministic finding title class without truncating PDF text', function (string $title, string $titleClass): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();
    $finding->update(['title' => $title]);

    $result = editorialPdfReportRenderAndGenerate($assessment);

    expect($result['html'])->toContain('<h1 class="finding-title '.$titleClass.'">'.$title.'</h1>')
        ->and($result['text'])->toContain($title)
        ->and($result['report']->payload_snapshot['findings'][0]['title'])->toBe($title);
})->with([
    'short title' => [
        'Continuità operativa non garantita',
        'finding-title--default',
    ],
    'title longer than one hundred and ten characters' => [
        'Continuità operativa non garantita per i sistemi informativi principali e per tutti i servizi essenziali dell’azienda durante un fermo prolungato',
        'finding-title--compact',
    ],
]);

it('renders a single recommended solution or ordered alternatives', function (bool $withAlternatives): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();
    $recommended = editorialPdfReportKeepOnlyRecommendedSolution($finding);
    editorialPdfReportSettings(['alternative_solutions' => true]);

    if ($withAlternatives) {
        editorialPdfReportAddAlternative($recommended, 'Alternativa editoriale seconda', 3);
        editorialPdfReportAddAlternative($recommended, 'Alternativa editoriale prima', 2);
    }

    $result = editorialPdfReportRenderAndGenerate($assessment);
    $solutions = $result['snapshot']->findings[0]->solutions;

    if (! $withAlternatives) {
        expect($solutions)->toHaveCount(1)
            ->and($result['html'])->not->toContain(__('assestme.reports.document.alternative_solutions'))
            ->and($result['text'])->not->toContain('Alternativa editoriale');

        return;
    }

    expect(array_map(static fn ($solution): string => $solution->title, $solutions))->toBe([
        $recommended->title,
        'Alternativa editoriale prima',
        'Alternativa editoriale seconda',
    ])
        ->and($result['html'])->toContain(__('assestme.reports.document.alternative_solutions'))
        ->and($result['text'])->toContain('Alternativa editoriale prima', 'Alternativa editoriale seconda');

    $htmlFirst = mb_strpos($result['html'], 'Alternativa editoriale prima');
    $htmlSecond = mb_strpos($result['html'], 'Alternativa editoriale seconda');
    $pdfFirst = mb_strpos($result['text'], 'Alternativa editoriale prima');
    $pdfSecond = mb_strpos($result['text'], 'Alternativa editoriale seconda');

    expect($htmlFirst)->not->toBeFalse()
        ->and($htmlSecond)->not->toBeFalse()
        ->and($pdfFirst)->not->toBeFalse()
        ->and($pdfSecond)->not->toBeFalse()
        ->and($htmlFirst)->toBeLessThan($htmlSecond)
        ->and($pdfFirst)->toBeLessThan($pdfSecond);
})->with([
    'one solution' => [false],
    'multiple solutions' => [true],
]);

it('omits disabled costs or an absent effort metric from HTML and PDF', function (bool $costs, bool $withEffort): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();
    $recommended = editorialPdfReportKeepOnlyRecommendedSolution($finding);
    $effortLevelId = $withEffort ? $recommended->effort_level_id : null;
    $recommended->update([
        'effort_level_id' => $effortLevelId,
        'effort_notes' => $withEffort ? 'Nota impegno editoriale univoca' : null,
        'estimate_type' => EstimateType::Exact,
        'amount_min' => '9876.00',
        'amount_max' => null,
        'currency_code' => 'EUR',
        'billing_frequency' => BillingFrequency::OneOff,
        'custom_billing_frequency' => null,
        'estimate_notes' => 'Nota stima editoriale univoca',
    ]);
    editorialPdfReportSettings([
        'costs' => $costs,
        'summary_table' => false,
    ]);

    $result = editorialPdfReportRenderAndGenerate($assessment);
    $metrics = editorialPdfReportMetricsMarkup($result['html']);
    $uppercasePdf = mb_strtoupper($result['text']);
    $effortLabel = mb_strtoupper(__('assestme.reports.document.effort'));
    $estimateLabel = mb_strtoupper(__('assestme.reports.document.indicative_estimate'));

    if (! $costs) {
        expect($metrics)->toContain(__('assestme.reports.document.effort'), 'Nota impegno editoriale univoca')
            ->and($metrics)->not->toContain(__('assestme.reports.document.indicative_estimate'), 'Nota stima editoriale univoca')
            ->and($uppercasePdf)->toContain($effortLabel)
            ->and($uppercasePdf)->not->toContain($estimateLabel)
            ->and($result['text'])->toContain('Nota impegno editoriale univoca')
            ->and($result['text'])->not->toContain('Nota stima editoriale univoca');

        return;
    }

    expect($result['snapshot']->findings[0]->recommendedSolution()->effortLabel)->toBeNull()
        ->and($metrics)->toContain(__('assestme.reports.document.indicative_estimate'), 'Nota stima editoriale univoca')
        ->and($metrics)->not->toContain(__('assestme.reports.document.effort'))
        ->and($uppercasePdf)->toContain($estimateLabel)
        ->and($uppercasePdf)->not->toContain($effortLabel)
        ->and($result['text'])->toContain('Nota stima editoriale univoca');
})->with([
    'costs disabled' => [false, true],
    'effort absent' => [true, false],
]);

it('omits the evidence page when the finding has no evidence', function (): void {
    [$assessment] = editorialPdfReportReadyAssessment();

    $result = editorialPdfReportRenderAndGenerate($assessment);

    expect($result['report']->payload_snapshot['findings'][0]['evidences'])->toBe([])
        ->and($result['html'])->not->toContain('class="finding-evidence"')
        ->and($result['text'])->not->toContain(__('assestme.reports.document.evidence'));
});

it('renders associated assets only from the report DTO fields', function (bool $withAsset): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();

    if ($withAsset) {
        $site = Site::factory()->for($assessment->client)->create(['name' => 'Sede Asset Editoriale']);
        $asset = Asset::factory()->for($assessment->client)->create([
            'site_id' => $site->getKey(),
            'asset_type_id' => AssetType::query()->where('name', 'Server')->firstOrFail()->getKey(),
            'name' => 'Server Editoriale Principale',
            'manufacturer' => 'Produttore Editoriale',
            'model' => 'Modello Editoriale X1',
            'hostname' => 'editorial-server.local',
            'ip_address' => '192.0.2.44',
            'serial_number' => 'SERIALE-INTERNO-NON-DTO',
            'description' => 'Descrizione interna non inclusa nel DTO',
            'notes' => 'Note interne non incluse nel DTO',
        ]);
        $finding->assets()->attach($asset);
    }

    $result = editorialPdfReportRenderAndGenerate($assessment);
    $assets = $result['report']->payload_snapshot['findings'][0]['assets'];

    if (! $withAsset) {
        expect($assets)->toBe([])
            ->and($result['html'])->not->toContain('class="finding-section associated-assets"')
            ->and($result['text'])->not->toContain(__('assestme.reports.document.associated_assets'));

        return;
    }

    expect($assets)->toHaveCount(1)
        ->and(array_keys($assets[0]))->toBe([
            'id',
            'name',
            'type',
            'site',
            'manufacturer',
            'model',
            'hostname',
            'ip_address',
            'display_label',
        ])
        ->and($result['html'])->toContain('class="finding-section associated-assets"')
        ->and($result['text'])->toContain(
            'Server Editoriale Principale',
            'Server',
            'Sede Asset Editoriale',
            'Produttore Editoriale Modello Editoriale X1',
            'editorial-server.local',
            '192.0.2.44',
        )
        ->and(json_encode($assets, JSON_THROW_ON_ERROR))->not->toContain(
            'SERIALE-INTERNO-NON-DTO',
            'Descrizione interna non inclusa nel DTO',
            'Note interne non incluse nel DTO',
        )
        ->and($result['text'])->not->toContain('SERIALE-INTERNO-NON-DTO');
})->with([
    'without assets' => [false],
    'with one complete asset' => [true],
]);

it('honors the technical notes presentation setting', function (bool $enabled): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();
    $finding->update(['technical_notes' => 'Nota tecnica editoriale condizionale']);
    editorialPdfReportSettings(['technical_notes' => $enabled]);

    $result = editorialPdfReportRenderAndGenerate($assessment);

    expect($result['report']->payload_snapshot['findings'][0]['technical_notes'])
        ->toBe('Nota tecnica editoriale condizionale');

    if ($enabled) {
        expect($result['html'])->toContain('class="finding-section technical-notes"', 'Nota tecnica editoriale condizionale')
            ->and($result['text'])->toContain('Nota tecnica editoriale condizionale');

        return;
    }

    expect($result['html'])->not->toContain('class="finding-section technical-notes"', 'Nota tecnica editoriale condizionale')
        ->and($result['text'])->not->toContain('Nota tecnica editoriale condizionale');
})->with([
    'technical notes disabled' => [false],
    'technical notes enabled' => [true],
]);

it('extracts custom and ordered fallback page chrome from every internal PDF page', function (
    array $settingValues,
    string $applicationName,
    string $expectedHeader,
    string $expectedFooter,
): void {
    [$assessment] = editorialPdfReportReadyAssessment();
    editorialPdfReportSettings(array_merge([
        'cover' => false,
        'repeated_header_footer' => true,
        'page_numbers' => true,
    ], $settingValues));
    $generalSettings = app(GeneralSettings::class);
    $generalSettings->application_name = $applicationName;
    $generalSettings->save();

    $result = editorialPdfReportRenderAndGenerate($assessment);

    expect($result['pages'])->not->toBeEmpty();
    foreach ($result['pages'] as $pageText) {
        expect($pageText)->toContain($expectedHeader, $expectedFooter, 'Pagina ');
    }
})->with([
    'custom header and footer' => [
        [
            'header_text' => 'Header editoriale personalizzato',
            'footer_text' => 'Footer editoriale personalizzato',
            'business_name' => 'Business non selezionato',
            'consultant_name' => 'Consulente non selezionato',
        ],
        'Applicazione non selezionata',
        'Header editoriale personalizzato',
        'Footer editoriale personalizzato',
    ],
    'client header and business footer fallback' => [
        [
            'header_text' => null,
            'footer_text' => null,
            'business_name' => 'Studio Fallback Business',
            'consultant_name' => 'Consulente subordinato',
        ],
        'Applicazione subordinata',
        'ASSESTME / Azienda Editoriale S.r.l.',
        'Studio Fallback Business',
    ],
    'consultant footer fallback' => [
        [
            'header_text' => null,
            'footer_text' => null,
            'business_name' => null,
            'consultant_name' => 'Consulente Fallback',
        ],
        'Applicazione subordinata',
        'ASSESTME / Azienda Editoriale S.r.l.',
        'Consulente Fallback',
    ],
    'application footer fallback' => [
        [
            'header_text' => null,
            'footer_text' => null,
            'business_name' => null,
            'consultant_name' => null,
        ],
        'Applicazione Fallback Finale',
        'ASSESTME / Azienda Editoriale S.r.l.',
        'Applicazione Fallback Finale',
    ],
]);

/** @return array{Assessment, Finding} */
function editorialPdfReportReadyAssessment(): array
{
    $assessment = Assessment::factory()->create([
        'title' => 'Assessment editoriale verificabile',
        'assessment_date' => '2026-07-22',
        'introduction' => 'Introduzione editoriale verificabile.',
        'executive_summary' => 'Sintesi editoriale verificabile.',
        'methodology_notes' => null,
    ]);
    $assessment->client->update([
        'legal_name' => 'Azienda Editoriale S.r.l.',
        'trade_name' => null,
        'logo_path' => null,
    ]);

    $template = FindingTemplate::query()
        ->with('solutions')
        ->where('default_scope_type', ScopeType::Organization->value)
        ->firstOrFail();
    $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
    $finding->update([
        'title' => 'Finding editoriale verificabile',
        'scope_type' => ScopeType::Organization,
        'scope_description' => null,
        'technical_notes' => null,
    ]);

    return [$assessment->fresh(), $finding->fresh()];
}

/** @param array<string, bool|string|null> $values */
function editorialPdfReportSettings(array $values): void
{
    $settings = app(ReportSettings::class);
    foreach ($values as $property => $value) {
        $settings->{$property} = $value;
    }
    $settings->save();
}

function editorialPdfReportStoreLogos(Assessment $assessment): void
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    expect($png)->toBeString();

    Storage::disk('local')->put('logos/editorial-client.png', $png);
    Storage::disk('local')->put('logos/editorial-consultant.png', $png);
    $assessment->client->update(['logo_path' => 'logos/editorial-client.png']);
}

function editorialPdfReportKeepOnlyRecommendedSolution(Finding $finding): FindingSolution
{
    $recommended = $finding->recommendedSolution()->firstOrFail();
    $finding->solutions()->whereKeyNot($recommended->getKey())->delete();
    $recommended->update(['sort_order' => 1]);

    return $recommended->fresh();
}

function editorialPdfReportAddAlternative(FindingSolution $source, string $title, int $sortOrder): void
{
    $alternative = $source->replicate();
    $alternative->external_key = 'editorial-alternative-'.$sortOrder;
    $alternative->title = $title;
    $alternative->description = 'Descrizione '.$title;
    $alternative->comparison_notes = 'Confronto '.$title;
    $alternative->sort_order = $sortOrder;
    $alternative->save();
}

/**
 * @return array{
 *     snapshot: AssessmentReportData,
 *     report: GeneratedReport,
 *     html: string,
 *     text: string,
 *     pages: list<string>
 * }
 */
function editorialPdfReportRenderAndGenerate(Assessment $assessment): array
{
    $snapshot = app(BuildAssessmentSnapshot::class)($assessment->fresh());
    $html = view('reports.assessment', ['report' => $snapshot])->render();
    $report = app(GenerateAssessmentPdf::class)($assessment->fresh());
    $path = Storage::disk('local')->path($report->file_path);
    $signature = file_get_contents($path, false, null, 0, 5);
    $document = (new Parser)->parseFile($path);
    $text = editorialPdfReportNormalizeText($document->getText());
    $pages = array_values(array_map(
        static fn ($page): string => editorialPdfReportNormalizeText($page->getText()),
        $document->getPages(),
    ));
    $payload = json_encode($report->payload_snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

    expect($signature)->toBe('%PDF-')
        ->and($document->getPages())->not->toBeEmpty()
        ->and($snapshot->settingsSnapshot)->not->toHaveKey('confidentiality_label')
        ->and($report->settings_snapshot)->not->toHaveKey('confidentiality_label')
        ->and($report->payload_snapshot['settings'])->not->toHaveKey('confidentiality_label')
        ->and($payload)->not->toContain('confidentiality');

    return [
        'snapshot' => $snapshot,
        'report' => $report,
        'html' => $html,
        'text' => $text,
        'pages' => $pages,
    ];
}

function editorialPdfReportNormalizeText(string $text): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

function editorialPdfReportMetricsMarkup(string $html): string
{
    preg_match('/<table class="solution-metrics">(.*?)<\/table>/s', $html, $matches);

    expect($matches)->toHaveKey(1);

    return $matches[1];
}
