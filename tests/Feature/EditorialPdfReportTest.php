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
use App\Settings\ReportSettings;
use Database\Seeders\MilestoneOneSeeder;
use Database\Seeders\MilestoneTwoSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;

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

    expect($result['html'])->toContain('class="finding-title '.$titleClass.'"', $title)
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
            ->and($result['html'])->not->toContain(__('assestme.reports.document.alternative_solution'))
            ->and($result['text'])->not->toContain('Alternativa editoriale');

        return;
    }

    expect(array_map(static fn ($solution): string => $solution->title, $solutions))->toBe([
        $recommended->title,
        'Alternativa editoriale prima',
        'Alternativa editoriale seconda',
    ])
        ->and($result['html'])->toContain(__('assestme.reports.document.alternative_solution'))
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

it('rejects legacy content over the editorial budget without truncating it', function (): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();
    $recommended = editorialPdfReportKeepOnlyRecommendedSolution($finding);
    $alternative = editorialPdfReportAddAlternative($recommended, 'Alternativa estesa multipagina', 2);
    $alternative->update([
        'description' => 'INIZIO ALTERNATIVA ESTESA. '
            .str_repeat('La procedura descrive responsabilità, verifiche, dipendenze e criteri di accettazione operativa. ', 180)
            .'FINE ALTERNATIVA ESTESA.',
        'comparison_notes' => 'Il confronto resta completo anche quando il contenuto deve proseguire nella pagina successiva.',
    ]);
    expect(fn () => app(GenerateAssessmentPdf::class)($assessment->fresh()))
        ->toThrow(ValidationException::class, 'description supera il limite editoriale');
    expect($alternative->fresh()->description)->toStartWith('INIZIO ALTERNATIVA ESTESA.')
        ->and($alternative->fresh()->description)->toEndWith('FINE ALTERNATIVA ESTESA.')
        ->and(GeneratedReport::query()->count())->toBe(0);
});

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

    preg_match('/<section class="risk-evaluation">(.*?)<\/section>/s', $html, $riskMatch);

    expect(editorialPdfReportNormalizeText($html))
        ->toContain(
            '@page cover { size: A4 portrait;',
            '@page report { size: A4 portrait;',
            '@page summary { size: A4 landscape;',
            '.solution-block { border:',
            'break-inside: avoid;',
        )
        ->and($html)->toContain(
            'data-finding-page="1"',
            'class="finding-body"',
            'class="finding-context-layout"',
            'class="risk-mini-matrix"',
            'class="affected-systems"',
            'class="asset-details"',
        )
        ->and(__('assestme.reports.document.assessment_overview'))->toBe('Quadro Generale')
        ->and($html)->toMatch('/<table class="finding-heading__table"[^>]*>.*?<td class="finding-heading__number">\s*01\s*<\/td>.*?<td class="finding-heading__content">/su')
        ->and(editorialPdfReportNormalizeText($html))->toContain(
            '.finding-heading__number { font-size: 20pt; font-weight: 700; line-height: 1.08;',
            'vertical-align: baseline; width: 17mm;',
            '.finding-heading__content { padding: 0; vertical-align: baseline; }',
        )
        ->and($riskMatch)->toHaveKey(1)
        ->and($riskMatch[1])->toContain(
            __('assestme.reports.document.risk_evaluation'),
            'class="risk-compact-layout"',
            'class="risk-result-compact"',
            (string) $snapshot->findings[0]->consequenceLabel,
            (string) $snapshot->findings[0]->likelihoodLabel,
            $snapshot->findings[0]->priorityLabel,
        )
        ->and($snapshot->findings[0]->riskMatrix)->not->toBeNull()
        ->and($riskMatch[1])->toContain('risk-dot--current');
});

it('uses print-safe table structures and a symbol-only compact risk matrix', function (): void {
    [$assessment] = editorialPdfReportReadyAssessment();
    $snapshot = app(BuildAssessmentSnapshot::class)($assessment->fresh());
    $html = view('reports.assessment', ['report' => $snapshot])->render();
    $riskMatrix = $snapshot->findings[0]->riskMatrix;

    preg_match('/\.solution-metrics\s*\{([^}]*)\}/u', $html, $metricsRule);
    preg_match('/\.finding-meta\s*\{([^}]*)\}/u', $html, $metaRule);
    preg_match('/\.summary-table th\s*\{([^}]*)\}/u', $html, $summaryHeaderRule);
    preg_match('/<table\s+class="risk-mini-matrix".*?<\/table>/su', $html, $matrixMatch);
    preg_match('/<table class="risk-mini-layout".*?<\/table>\s*<\/td>/su', $html, $matrixLayoutMatch);

    if ($riskMatrix === null) {
        throw new RuntimeException('The report fixture must contain a risk matrix.');
    }

    expect($metricsRule)->toHaveKey(1)
        ->and($metricsRule[1])->not->toContain('display: grid', 'grid-template-columns')
        ->and($metaRule)->toHaveKey(1)
        ->and($metaRule[1])->not->toContain('margin-left: 20mm', 'display: flex', 'gap:')
        ->and($summaryHeaderRule)->toHaveKey(1)
        ->and($summaryHeaderRule[1])->toContain('background: #F0F0EC', 'color: var(--ink)')
        ->and($html)->toContain(
            'class="finding-body"',
            'class="finding-heading__table"',
            'class="finding-heading__content"',
            'class="solution-metrics__effort"',
            'class="solution-metrics__estimate"',
        )
        ->and($matrixMatch)->toHaveKey(0)
        ->and($matrixLayoutMatch)->toHaveKey(0)
        ->and($riskMatrix)->not->toBeNull();

    $matrix = $matrixMatch[0];
    $matrixLayout = $matrixLayoutMatch[0];
    preg_match_all('/<tr data-consequence-id="(\d+)">(.*?)<\/tr>/su', $matrix, $matrixRows);
    $expectedConsequenceIds = array_map(
        static fn (array $consequence): string => (string) $consequence['id'],
        array_reverse($riskMatrix->consequences),
    );
    $expectedLikelihoodIds = array_map(
        static fn (array $likelihood): string => (string) $likelihood['id'],
        $riskMatrix->likelihoods,
    );

    expect(preg_match_all('/class="risk-dot(?:\s|")/u', $matrix))->toBe(16)
        ->and(substr_count($matrix, 'risk-dot--current'))->toBe(1)
        ->and(substr_count($matrix, 'risk-dot__current-mark'))->toBe(0)
        ->and(trim((string) preg_replace('/\s+/u', '', strip_tags($matrix))))->toBe('')
        ->and($matrixRows[1])->toBe($expectedConsequenceIds)
        ->and($matrixLayout)->toContain(
            __('assestme.reports.document.consequence'),
            __('assestme.reports.document.likelihood'),
        )
        ->and($matrixLayout)->not->toContain('↓', '→')
        ->and(editorialPdfReportNormalizeText($html))->not->toContain('.risk-dot__current-mark');

    foreach ($matrixRows[2] as $row) {
        preg_match_all('/data-likelihood-id="(\d+)"/u', $row, $likelihoodMatches);
        expect($likelihoodMatches[1])->toBe($expectedLikelihoodIds);
    }

    $topRight = $riskMatrix->cell(
        (int) $expectedConsequenceIds[0],
        (int) $expectedLikelihoodIds[3],
    );
    $bottomLeft = $riskMatrix->cell(
        (int) $expectedConsequenceIds[3],
        (int) $expectedLikelihoodIds[0],
    );

    if ($topRight === null || $bottomLeft === null) {
        throw new RuntimeException('The report fixture must contain both diagonal matrix corners.');
    }

    if ($snapshot->priorityLegend === []) {
        throw new RuntimeException('The report fixture must contain the priority legend.');
    }

    $lowestPriority = $snapshot->priorityLegend[0];
    $highestPriority = $snapshot->priorityLegend[count($snapshot->priorityLegend) - 1];

    expect($matrixRows[2][0])->toContain('title="'.$topRight['priority_label'].'"')
        ->and($matrixRows[2][3])->toContain('title="'.$bottomLeft['priority_label'].'"')
        ->and($topRight['priority_label'])->toBe($highestPriority->label)
        ->and($bottomLeft['priority_label'])->toBe($lowestPriority->label);
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

    $result = editorialPdfReportRenderAndGenerate($assessment->fresh());
    $snapshot = $result['snapshot'];
    $html = $result['html'];
    $normalizedHtml = editorialPdfReportNormalizeText($html);

    expect($html)->toContain('class="priority-legend priority-legend--detailed"')
        ->and($html)->not->toContain('class="priority-legend-inline avoid-break"')
        ->and($normalizedHtml)->toContain('.priority-legend--detailed td { padding:');
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
    preg_match('/<section class="risk-evaluation">(.*?)<\/section>/s', $html, $riskMatch);
    $snapshotFinding = $snapshot->findings[0];

    expect($riskMatch)->toHaveKey(1)
        ->and($riskMatch[1])->toContain(
            (string) $snapshotFinding->consequenceLabel,
            (string) $snapshotFinding->likelihoodLabel,
            $snapshotFinding->priorityLabel,
            'class="risk-mini-matrix"',
            'risk-dot--current',
        )
        ->and($snapshotFinding->priorityOverridden)->toBe($overridden)
        ->and($snapshotFinding->priorityRationale)->toBe($rationale)
        ->and($snapshotFinding->riskMatrix)->not->toBeNull()
        ->and(array_filter(
            $snapshotFinding->riskMatrix?->cells ?? [],
            static fn (array $cell): bool => $cell['current'],
        ))->toHaveCount(1);

    if ($overridden) {
        expect($riskMatch[1])->toContain((string) $rationale)
            ->and(substr_count($html, (string) $rationale))->toBe(1)
            ->and($result['text'])->toContain((string) $rationale);

        return;
    }

    expect($riskMatch[1])->toContain($snapshotFinding->riskMatrix?->resultingPriorityLabel ?? '');
})->with([
    'matrix priority' => [false],
    'manual priority' => [true],
]);

it('uses text labels with priority and status markers', function (): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();
    editorialPdfReportSettings(['show_priority_descriptions' => false]);

    $result = editorialPdfReportRenderAndGenerate($assessment->fresh());
    $snapshot = $result['snapshot'];
    $html = $result['html'];
    $normalizedHtml = editorialPdfReportNormalizeText($html);

    expect($html)->toContain(
        '<span class="priority-glyph" style="color: '.$snapshot->findings[0]->priorityColor.'">●</span>',
        'class="priority-marker"',
    )
        ->and(editorialPdfReportNormalizeText($html))->toContain(
            'class="status-open"> ○ '.$snapshot->findings[0]->statusLabel.' </span>',
        )
        ->and($normalizedHtml)->not->toMatch('/\\.priority-marker\\s*\\{[^}]*background/u')
        ->and($result['text'])->toContain('○', mb_strtoupper($snapshot->findings[0]->statusLabel))
        ->and($finding->status->value)->toBe('open');
});

it('uses a visible semantic heading hierarchy without indenting the problem explanation', function (): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();
    $finding->update(['entrepreneur_notes' => 'Decisione imprenditoriale da assumere con priorità operativa.']);
    editorialPdfReportSettings(['primary_color' => '#135E75']);

    $result = editorialPdfReportRenderAndGenerate($assessment->fresh());
    preg_match('/<section class="problem-explanation">(.*?)<\/section>/s', $result['html'], $noteMatch);
    preg_match('/<section class="finding-section problem-section">(.*?)<\/section>/s', $result['html'], $problemMatch);
    preg_match('/<section class="solution-block.*?<\/section>/s', $result['html'], $solutionMatch);
    $normalizedHtml = editorialPdfReportNormalizeText($result['html']);
    $problemPosition = strpos($result['html'], '<section class="finding-section problem-section">');
    $explanationPosition = strpos($result['html'], '<section class="problem-explanation">');

    if (! is_int($problemPosition) || ! is_int($explanationPosition)) {
        throw new RuntimeException('The Finding problem and explanation sections must both be rendered.');
    }

    expect($noteMatch)->toHaveKey(1)
        ->and($noteMatch[1])->toContain(
            '<h2 class="finding-section__label finding-section__label--prominent">',
            __('assestme.reports.document.entrepreneur_notes'),
            'Decisione imprenditoriale da assumere con priorità operativa.',
        )
        ->and($problemMatch)->toHaveKey(1)
        ->and($problemMatch[1])->toContain(
            '<h2 class="finding-section__label finding-section__label--prominent">',
            __('assestme.reports.document.problem'),
        )
        ->and($problemPosition)->toBeLessThan($explanationPosition)
        ->and($solutionMatch)->toHaveKey(0)
        ->and($solutionMatch[0])->toContain(
            '<h2 class="finding-section__label">',
            '<h3>',
        )
        ->and($result['html'])->toContain('<h1 class="finding-title finding-title--')
        ->and($result['html'])->not->toContain(
            '<section class="problem-explanation" style=',
            'border-left-color: '.$result['snapshot']->findings[0]->priorityColor,
        )
        ->and($normalizedHtml)->toContain(
            '--accent: #135E75;',
            '.problem-explanation { margin: 0 0 4mm; padding: 0; }',
            '.finding-section__label--prominent { color: var(--ink); font-size: 11pt;',
            '.problem-explanation .pre-line { font-size: 9.4pt; line-height: 1.45; }',
        )
        ->and($normalizedHtml)->not->toContain('.problem-explanation { border-left:');
});

it('omits an absent problem explanation without suppressing the visible problem heading', function (): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();
    $finding->update(['entrepreneur_notes' => null]);

    $result = editorialPdfReportRenderAndGenerate($assessment->fresh());
    preg_match('/<section class="finding-section problem-section">(.*?)<\/section>/s', $result['html'], $problemMatch);

    expect($result['html'])->not->toContain(
        'class="problem-explanation"',
        __('assestme.reports.document.entrepreneur_notes'),
    )
        ->and($problemMatch)->toHaveKey(1)
        ->and($problemMatch[1])->toContain(
            '<h2 class="finding-section__label finding-section__label--prominent">',
            __('assestme.reports.document.problem'),
            $result['snapshot']->findings[0]->problem,
        )
        ->and($result['text'])->toContain(
            __('assestme.reports.document.problem'),
            $result['snapshot']->findings[0]->problem,
        );
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

    expect(editorialPdfReportNormalizeText($html))->toContain(
        'class="status-'.$status.'"> '.$glyph.' '.$snapshot->findings[0]->statusLabel.' </span>',
    )
        ->and($snapshot->findings[0]->status)->toBe($status);
})->with([
    'open' => ['open', '○'],
    'planned' => ['planned', '▦'],
    'in progress' => ['in_progress', '⌛'],
    'resolved' => ['resolved', '✓'],
    'accepted' => ['accepted', '●'],
    'not applicable' => ['not_applicable', '●'],
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
        ->and($result['pages'][3])->toContain($titles[0], 'NAS')
        ->and($result['pages'][4])->toContain($titles[1], 'NAS')
        ->and($result['pages'][5])->toContain($titles[2])
        ->and($result['pages'][6])->toContain(__('assestme.reports.document.evidence'), __('assestme.reports.document.image_number', ['number' => '01']))
        ->and($result['pages'][6])->not->toContain('VIP-CABLAGGIO.PNG', 'vip-cablaggio.png')
        ->and($result['pages'][7])->toContain($titles[3], 'NAS')
        ->and($result['html'])->toContain('class="affected-systems"');

    expect(substr_count($result['html'], 'class="risk-mini-matrix"'))->toBe(4)
        ->and($result['html'])->not->toContain(__('assestme.reports.document.classification'));

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
    if (! $costs) {
        expect($metrics)->toContain(__('assestme.reports.document.effort'), 'Nota impegno editoriale univoca')
            ->and($metrics)->not->toContain(__('assestme.reports.document.estimate'), 'Nota stima editoriale univoca')
            ->and($result['text'])->toContain('Nota impegno editoriale univoca')
            ->and($result['text'])->not->toContain('Nota stima editoriale univoca');

        return;
    }

    expect($result['snapshot']->findings[0]->recommendedSolution()->effortLabel)->toBeNull()
        ->and($metrics)->toContain(
            __('assestme.reports.document.effort'),
            __('assestme.reports.document.not_available'),
            __('assestme.reports.document.estimate'),
            'Nota stima editoriale univoca',
        )
        ->and($result['text'])->toContain('Nota stima editoriale univoca');
})->with([
    'costs disabled' => [false, true],
    'effort absent' => [true, false],
]);

it('aligns effort and estimate labels on the same PDF baseline', function (): void {
    [$assessment] = editorialPdfReportReadyAssessment();
    editorialPdfReportSettings([
        'cover' => false,
        'executive_summary' => false,
        'summary_table' => false,
        'content_index' => false,
        'methodology' => false,
        'disclaimer' => false,
        'signature_block' => false,
        'costs' => true,
    ]);

    $result = editorialPdfReportRenderAndGenerate($assessment->fresh());
    $finding = $result['snapshot']->findings[0];
    $pdfText = mb_strtolower($result['text']);
    $priorityLabelOccurrences = 0;
    foreach ($result['snapshot']->priorityLegend as $priority) {
        $count = substr_count($pdfText, mb_strtolower($priority->label));
        $priorityLabelOccurrences += $count;
        expect($count)->toBeLessThanOrEqual(2);
    }
    expect($priorityLabelOccurrences)->toBeLessThanOrEqual(3)
        ->and(substr_count($pdfText, mb_strtolower($finding->priorityLabel)))->toBe(2);

    $path = Storage::disk('local')->path($result['report']->file_path);
    $process = new Process(['pdftotext', '-bbox-layout', $path, '-']);
    $process->mustRun();
    $document = new DOMDocument;
    $loaded = $document->loadXML($process->getOutput());
    expect($loaded)->toBeTrue();

    $coordinates = [];
    foreach ((new DOMXPath($document))->query('//*[local-name()="word"]') ?: [] as $word) {
        $label = mb_strtolower(trim($word->textContent));
        if (in_array($label, ['impegno', 'stima'], true)) {
            $coordinates[$label][] = (float) $word->attributes?->getNamedItem('yMin')?->nodeValue;
        }
    }

    expect($coordinates)->toHaveKeys(['impegno', 'stima'])
        ->and($coordinates['impegno'])->toHaveCount(1)
        ->and($coordinates['stima'])->not->toBeEmpty()
        ->and(min(array_map(
            static fn (float $estimateY): float => abs($coordinates['impegno'][0] - $estimateY),
            $coordinates['stima'],
        )))->toBeLessThan(0.2);
});

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
            ->and($result['html'])->not->toContain('class="asset-details"');

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
        ->and($result['html'])->toContain('class="affected-systems"', 'class="asset-details"')
        ->and($result['text'])->toContain(
            'Server Editoriale Principale',
            'Sede Asset Editoriale',
            'Produttore Editoriale',
            '192.0.2.44',
        )
        ->and($result['text'])->not->toContain('Modello Editoriale X1', 'editorial-server.local')
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

it('prints an asset name and matching type only once case-insensitively', function (): void {
    [$assessment, $finding] = editorialPdfReportReadyAssessment();
    $asset = Asset::factory()->for($assessment->client)->create([
        'asset_type_id' => AssetType::query()->where('name', 'NAS')->firstOrFail()->getKey(),
        'name' => 'nas',
        'manufacturer' => null,
        'model' => null,
        'hostname' => null,
        'ip_address' => null,
    ]);
    $finding->assets()->attach($asset);

    $result = editorialPdfReportRenderAndGenerate($assessment->fresh());
    preg_match('/<section class="asset-details">(.*?)<\/section>/s', $result['html'], $assetMatch);

    expect($assetMatch)->toHaveKey(1)
        ->and(substr_count(mb_strtolower($assetMatch[1]), '>nas<'))->toBe(1)
        ->and($result['text'])->toContain('nas');
});

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

it('keeps the cover unnumbered and uses only the client name in internal headers', function (): void {
    [$assessment] = editorialPdfReportReadyAssessment();
    editorialPdfReportSettings([
        'cover' => true,
        'page_numbers' => true,
    ]);

    $result = editorialPdfReportRenderAndGenerate($assessment);
    $header = $assessment->client->displayName();
    $duplicatedHeader = $assessment->title.' · '.$header;

    expect($result['pages'])->toHaveCount(4)
        ->and($result['html'])->toContain('@top-left { content: "'.$header.'";')
        ->and($result['html'])->not->toContain($duplicatedHeader)
        ->and(trim($result['pages'][0]))->not->toMatch('/(?:^|\\s)\\d+$/')
        ->and(trim($result['pages'][1]))->toEndWith('1')
        ->and($result['pages'][1])->toContain($header)
        ->and($result['pages'][1])->not->toContain($duplicatedHeader)
        ->and(trim($result['pages'][2]))->toEndWith('2')
        ->and(trim($result['pages'][3]))->toEndWith('3');

    foreach (array_slice($result['pages'], 1) as $pageText) {
        expect($pageText)->toContain($header)
            ->and($pageText)->not->toContain($duplicatedHeader, 'Pagina ', 'AssestMe');
    }
});

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

function editorialPdfReportAddAlternative(FindingSolution $source, string $title, int $sortOrder): FindingSolution
{
    $alternative = $source->replicate();
    $alternative->external_key = 'editorial-alternative-'.$sortOrder;
    $alternative->title = $title;
    $alternative->description = 'Descrizione '.$title;
    $alternative->comparison_notes = 'Confronto '.$title;
    $alternative->sort_order = $sortOrder;
    $alternative->save();

    return $alternative->refresh();
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
    preg_match('/<section class="solution-block[^"]*"[^>]*>(.*?)<\/section>/s', $html, $matches);

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
