<?php

declare(strict_types=1);

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Reports\BuildAssessmentSnapshot;
use App\Actions\Reports\GenerateAssessmentPdf;
use App\Data\Reports\AssessmentReportData;
use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\EvidenceType;
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
    'title between seventy-one and one hundred and ten characters' => [
        'Continuità operativa non garantita per i servizi essenziali durante un fermo',
        'finding-title--medium',
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

it('keeps page chrome titles risk evaluation and assets in non-overlapping editorial flow', function (): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();
    $site = Site::factory()->for($assessment->client)->create(['name' => 'Sede Composizione']);
    $asset = Asset::factory()->for($assessment->client)->create([
        'site_id' => $site->getKey(),
        'asset_type_id' => AssetType::query()->where('name', 'NAS')->firstOrFail()->getKey(),
        'name' => 'NAS Composizione',
        'manufacturer' => 'Synology',
        'model' => 'DS923+',
    ]);
    $finding->assets()->attach($asset);

    $result = editorialPdfReportRenderAndGenerate($assessment->fresh());
    $snapshot = $result['snapshot'];
    $html = $result['html'];
    $normalizedHtml = editorialPdfReportNormalizeText($html);

    preg_match('/<article class="finding-detail[^"]*">(.*?)<\/article>/s', $html, $articleMatch);
    preg_match('/<table class="finding-meta">(.*?)<\/table>/s', $articleMatch[1] ?? '', $metaMatch);
    preg_match('/<section class="finding-scope">(.*?)<\/section>/s', $articleMatch[1] ?? '', $scopeMatch);
    preg_match('/<section class="risk-evaluation">(.*?)<\/section>/s', $articleMatch[1] ?? '', $riskMatch);

    expect($normalizedHtml)
        ->toContain(
            '@page { margin: 13mm 17mm 23mm 20mm; }',
            '.section-heading__number, .section-heading__title { vertical-align: top; }',
            '.section-heading__number { color: #111111; font-size: 44pt; font-weight: bold; line-height: 0.9; width: 20mm;',
            '.section-heading__title { font-size: 25pt; font-weight: bold; line-height: 1.08; padding-top: 0;',
            '.finding-title--default { font-size: 26pt; }',
            '.finding-title--medium { font-size: 22pt; }',
            '.finding-title--compact { font-size: 19pt; }',
            '.finding-detail--new-page { page-break-before: always; padding-top: 12mm; }',
            '.finding-evidence { page-break-before: always; padding-top: 12mm; }',
            'class="associated-assets__first"',
        )
        ->and($normalizedHtml)->not->toMatch('/margin:\s*-\d/u')
        ->and($normalizedHtml)->not->toContain('background: #FFFFFF;', '<div class="page-break"></div>')
        ->and($articleMatch)->toHaveKey(1)
        ->and($articleMatch[1])->toMatch('/<div class="finding-heading__number">01<\/div>\s*<h1 class="finding-title/u')
        ->and($articleMatch[1])->not->toContain('class="finding-heading__content"')
        ->and($scopeMatch)->toHaveKey(1)
        ->and($scopeMatch[1])->toContain(__('assestme.reports.document.scope'), $snapshot->findings[0]->scopeLabel)
        ->and($riskMatch)->toHaveKey(1)
        ->and($riskMatch[1])->toContain(
            __('assestme.reports.document.risk_evaluation'),
            __('assestme.reports.document.consequence'),
            __('assestme.reports.document.likelihood'),
            __('assestme.reports.document.resulting_priority'),
        )
        ->and($riskMatch[1])->not->toContain(__('assestme.reports.document.scope'))
        ->and($articleMatch[1])->not->toContain(__('assestme.reports.document.classification'), 'classification-table')
        ->and($metaMatch)->toHaveKey(1)
        ->and($metaMatch[1])->toContain(mb_strtoupper($finding->category->name));
});

it('uses the compact priority legend when descriptions are disabled', function (): void {
    [$assessment] = editorialPdfReportReadyAssessment();
    editorialPdfReportSettings(['show_priority_descriptions' => false]);

    $result = editorialPdfReportRenderAndGenerate($assessment->fresh());
    $snapshot = $result['snapshot'];
    $html = $result['html'];

    expect($html)->toContain('class="priority-legend-inline avoid-break"')
        ->and($html)->not->toContain(
            'priority-legend--detailed',
            'priority-legend-heading',
            'class="editorial-heading">'.__('assestme.reports.document.priority_legend'),
        );
    foreach ($snapshot->priorityLegend as $priority) {
        expect($html)->toContain(
            '<span class="priority-glyph" style="color: '.$priority->color.'">●</span>',
            $priority->label,
        );
        if (filled($priority->description)) {
            expect($html)->not->toContain((string) $priority->description);
        }
    }
});

it('uses the detailed priority legend without row rules when descriptions are enabled', function (): void {
    [$assessment] = editorialPdfReportReadyAssessment();
    editorialPdfReportSettings(['show_priority_descriptions' => true]);

    $snapshot = app(BuildAssessmentSnapshot::class)($assessment->fresh());
    $html = view('reports.assessment', ['report' => $snapshot])->render();
    $normalizedHtml = editorialPdfReportNormalizeText($html);

    expect($html)->toContain('class="priority-legend priority-legend--detailed"')
        ->and($html)->not->toContain('class="priority-legend-inline avoid-break"')
        ->and($normalizedHtml)->toContain('.priority-legend--detailed td { border: 0;');
});

it('connects consequence likelihood and priority while keeping scope and manual rationale separate', function (
    bool $overridden,
): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();
    $rationale = $overridden ? 'Priorità aumentata per esposizione operativa verificata' : null;
    $finding->update([
        'priority_is_overridden' => $overridden,
        'priority_rationale' => $rationale,
    ]);

    $result = editorialPdfReportRenderAndGenerate($assessment->fresh());
    $snapshot = $result['snapshot'];
    $html = $result['html'];
    preg_match('/<article class="finding-detail[^"]*">(.*?)<\/article>/s', $html, $articleMatch);
    preg_match('/<section class="finding-scope">(.*?)<\/section>/s', $articleMatch[1] ?? '', $scopeMatch);
    preg_match('/<section class="risk-evaluation">(.*?)<\/section>/s', $articleMatch[1] ?? '', $riskMatch);
    $snapshotFinding = $snapshot->findings[0];

    expect($scopeMatch)->toHaveKey(1)
        ->and($scopeMatch[1])->toContain($snapshotFinding->scopeLabel)
        ->and($riskMatch)->toHaveKey(1)
        ->and($riskMatch[1])->toContain(
            (string) $snapshotFinding->consequenceLabel,
            (string) $snapshotFinding->likelihoodLabel,
            $snapshotFinding->priorityLabel,
        )
        ->and($riskMatch[1])->not->toContain($snapshotFinding->scopeLabel)
        ->and($snapshotFinding->priorityOverridden)->toBe($overridden)
        ->and($snapshotFinding->priorityRationale)->toBe($rationale);

    if ($overridden) {
        expect($riskMatch[1])->toContain(__('assestme.reports.document.manual_priority'), (string) $rationale)
            ->and($riskMatch[1])->not->toContain(__('assestme.reports.document.resulting_priority'))
            ->and(substr_count($articleMatch[1], (string) $rationale))->toBe(1)
            ->and(mb_strtoupper($result['text']))->toContain(mb_strtoupper(__('assestme.reports.document.manual_priority')))
            ->and($result['text'])->toContain((string) $rationale);

        return;
    }

    expect($riskMatch[1])->toContain(__('assestme.reports.document.resulting_priority'))
        ->and($riskMatch[1])->not->toContain(
            __('assestme.reports.document.manual_priority'),
            __('assestme.reports.document.priority_rationale'),
        )
        ->and(mb_strtoupper($result['text']))->toContain(mb_strtoupper(__('assestme.reports.document.resulting_priority')))
        ->and(mb_strtoupper($result['text']))->not->toContain(mb_strtoupper(__('assestme.reports.document.manual_priority')));
})->with([
    'matrix priority' => [false],
    'manual priority' => [true],
]);

it('uses font glyphs instead of CSS squares for priority and status markers', function (): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();
    editorialPdfReportSettings(['show_priority_descriptions' => false]);

    $snapshot = app(BuildAssessmentSnapshot::class)($assessment->fresh());
    $html = view('reports.assessment', ['report' => $snapshot])->render();
    $normalizedHtml = editorialPdfReportNormalizeText($html);

    expect($html)->toContain(
        '<span class="priority-glyph" style="color: '.$snapshot->findings[0]->priorityColor.'">●</span>',
        '<span class="status-glyph">○</span>',
    )
        ->and($html)->not->toContain('kpi__marker', 'priority-marker', 'priority-legend__bar')
        ->and($normalizedHtml)->not->toMatch('/\\.(?:kpi__marker|priority-marker)\\s*\\{[^}]*background/u')
        ->and($finding->status->value)->toBe('open');
});

it('maps every persisted finding status to the approved editorial glyph', function (
    string $status,
    string $glyph,
): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();
    $finding->update([
        'status' => $status,
        'resolution_notes' => $status === 'resolved' ? 'Risoluzione verificata' : null,
        'resolved_at' => $status === 'resolved' ? now() : null,
    ]);

    $snapshot = app(BuildAssessmentSnapshot::class)($assessment->fresh());
    $html = view('reports.assessment', ['report' => $snapshot])->render();

    expect(substr_count($html, '<span class="status-glyph">'.$glyph.'</span>'))->toBeGreaterThanOrEqual(2)
        ->and($snapshot->findings[0]->status)->toBe($status);
})->with([
    'open' => ['open', '○'],
    'planned' => ['planned', '○'],
    'in progress' => ['in_progress', '–'],
    'resolved' => ['resolved', '✓'],
    'accepted' => ['accepted', '–'],
    'not applicable' => ['not_applicable', '–'],
]);

it('keeps the four-finding NAS and image composition on eight meaningful pages', function (): void {
    $assessment = Assessment::factory()->create([
        'title' => 'Assessment',
        'assessment_date' => '2026-07-18',
        'introduction' => null,
        'executive_summary' => null,
        'methodology_notes' => null,
    ]);
    $assessment->client->update([
        'legal_name' => 'VIP Estintori',
        'trade_name' => 'VIP Estintori',
    ]);

    $titles = [
        'Il DHCP distribuisce server DNS non corretti',
        'Notifiche del NAS non configurate',
        'Cablaggio disordinato o non identificato',
        "NAS appoggiato sopra l'UPS senza supporto adeguato",
    ];
    $findings = [];
    foreach ($titles as $title) {
        $template = FindingTemplate::query()->where('title', $title)->firstOrFail();
        $findings[] = app(CopyTemplateToAssessment::class)($assessment, $template);
    }

    $site = Site::factory()->for($assessment->client)->create(['name' => 'Sede Principale']);
    $asset = Asset::factory()->for($assessment->client)->create([
        'site_id' => $site->getKey(),
        'asset_type_id' => AssetType::query()->where('name', 'NAS')->firstOrFail()->getKey(),
        'name' => 'NAS',
        'manufacturer' => 'Synology',
        'model' => 'DS923+',
        'hostname' => null,
        'ip_address' => null,
    ]);
    foreach ([0, 1, 3] as $findingIndex) {
        $findings[$findingIndex]->assets()->attach($asset);
    }

    $image = editorialPdfReportPng(720, 960);
    $imagePath = 'evidence/editorial/vip-cablaggio.png';
    Storage::disk('local')->put($imagePath, $image);
    $findings[2]->evidences()->create([
        'type' => EvidenceType::File,
        'title' => 'VIP-CABLAGGIO.PNG',
        'file_path' => $imagePath,
        'original_filename' => 'vip-cablaggio.png',
        'caption' => null,
        'mime_type' => 'image/png',
        'size_bytes' => strlen($image),
        'sha256' => hash('sha256', $image),
        'include_in_report' => true,
        'sort_order' => 1,
    ]);

    editorialPdfReportSettings([
        'cover' => true,
        'content_index' => false,
        'executive_summary' => true,
        'risk_legend' => true,
        'summary_table' => true,
        'methodology' => true,
        'disclaimer' => true,
        'technical_notes' => false,
        'alternative_solutions' => true,
        'costs' => true,
        'evidence' => true,
        'evidence_captions' => true,
        'new_page_per_finding' => true,
        'show_priority_descriptions' => false,
    ]);

    $result = editorialPdfReportRenderAndGenerate($assessment->fresh());

    $pageDiagnostics = array_map(
        static fn (string $page): array => [mb_strlen($page), mb_substr($page, 0, 160)],
        $result['pages'],
    );
    expect(count($result['pages']))->toBe(8, json_encode($pageDiagnostics, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

    expect($result['pages'][0])->toContain('VIP Estintori')
        ->and($result['pages'][1])->toContain(__('assestme.reports.document.assessment_overview'))
        ->and($result['pages'][2])->toContain(__('assestme.reports.document.findings_summary'))
        ->and($result['pages'][3])->toContain($titles[0], mb_strtoupper(__('assestme.reports.document.associated_assets')), 'NAS')
        ->and($result['pages'][4])->toContain($titles[1], mb_strtoupper(__('assestme.reports.document.associated_assets')), 'NAS')
        ->and($result['pages'][5])->toContain($titles[2])
        ->and($result['pages'][6])->toContain(__('assestme.reports.document.evidence'), __('assestme.reports.document.image_number', ['number' => '01']))
        ->and($result['pages'][6])->not->toContain('VIP-CABLAGGIO.PNG', 'vip-cablaggio.png')
        ->and($result['pages'][7])->toContain($titles[3], mb_strtoupper(__('assestme.reports.document.associated_assets')), 'NAS');

    foreach ([3, 4, 5, 7] as $findingPageIndex) {
        $findingPage = mb_strtoupper($result['pages'][$findingPageIndex]);
        expect($findingPage)->toContain(
            mb_strtoupper(__('assestme.reports.document.risk_evaluation')),
            mb_strtoupper(__('assestme.reports.document.consequence')),
            mb_strtoupper(__('assestme.reports.document.likelihood')),
        )->not->toContain(mb_strtoupper(__('assestme.reports.document.classification')));
    }

    foreach ($result['pages'] as $index => $page) {
        if ($index === 0 || $index === 6) {
            continue;
        }

        expect(mb_strlen($page))->toBeGreaterThan(180);
    }
});

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

function editorialPdfReportPng(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        throw new RuntimeException('Unable to create the composition image fixture.');
    }

    $background = imagecolorallocate($image, 45, 55, 72);
    $line = imagecolorallocate($image, 225, 235, 220);
    imagefill($image, 0, 0, $background);
    imageline($image, 0, 0, $width - 1, $height - 1, $line);
    imageline($image, $width - 1, 0, 0, $height - 1, $line);

    ob_start();
    $encoded = imagepng($image);
    $contents = ob_get_clean();
    imagedestroy($image);

    if (! $encoded || ! is_string($contents)) {
        throw new RuntimeException('Unable to encode the composition image fixture.');
    }

    return $contents;
}
