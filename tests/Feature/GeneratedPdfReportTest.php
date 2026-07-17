<?php

declare(strict_types=1);

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Reports\FormatEstimate;
use App\Actions\Reports\GenerateAssessmentPdf;
use App\Actions\Storage\AuditPrivateStorage;
use App\Enums\AssessmentStatus;
use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\EvidenceType;
use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Models\GeneratedReport;
use App\Models\Site;
use App\Models\User;
use App\Settings\ReportSettings;
use Database\Seeders\MilestoneOneSeeder;
use Database\Seeders\MilestoneTwoSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Smalot\PdfParser\Parser;

beforeEach(function (): void {
    $this->seed(MilestoneOneSeeder::class);
    $this->seed(MilestoneTwoSeeder::class);
    Storage::fake('local');
});

it('persists immutable versioned PDF snapshots and downloads the authoritative file', function (): void {
    [$assessment, $finding] = createPdfReadyAssessment();
    $finding->category?->delete();

    $first = app(GenerateAssessmentPdf::class)($assessment->fresh());
    $firstPayload = $first->payload_snapshot;
    $firstPath = Storage::disk('local')->path($first->file_path);
    $pdf = (new Parser)->parseFile($firstPath);
    $text = $pdf->getText();
    $fixedNote = 'Tutti gli importi indicati sono stime orientative e si intendono IVA esclusa.';

    expect($first->version)->toBe(1)
        ->and($first->file_name)->toMatch('/^AssestMe_.+_v01\.pdf$/')
        ->and(Storage::disk('local')->exists($first->file_path))->toBeTrue()
        ->and(hash_file('sha256', $firstPath))->toBe($first->file_sha256)
        ->and($first->payload_snapshot['findings'][0]['category'])->not->toBeEmpty()
        ->and($first->payload_snapshot['client']['name'])->not->toBeEmpty()
        ->and($first->settings_snapshot['primary_color'])->toBe('#2563EB')
        ->and($text)->toContain($finding->title, 'Pagina 1 di')
        ->and(substr_count($text, $fixedNote))->toBe(1);

    $finding->update(['problem' => 'Problema modificato dopo il primo snapshot.']);
    $second = app(GenerateAssessmentPdf::class)($assessment->fresh());

    expect($second->version)->toBe(2)
        ->and($second->payload_snapshot['findings'][0]['problem'])->toBe('Problema modificato dopo il primo snapshot.')
        ->and($first->fresh()->payload_snapshot)->toBe($firstPayload)
        ->and(fn () => $first->update(['file_name' => 'mutato.pdf']))->toThrow(LogicException::class);
    $first->refresh();

    $this->get(route('generated-reports.download', $first))->assertRedirect('/admin/login');
    $this->actingAs(User::factory()->create())
        ->get(route('generated-reports.download', $first))
        ->assertOk()
        ->assertDownload($first->file_name)
        ->assertHeader('x-content-type-options', 'nosniff');

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->assertSee($first->file_name)
        ->assertSee($second->file_name);
});

it('formats every approved estimate branch without repeating the VAT note', function (): void {
    [, $finding] = createPdfReadyAssessment();
    $solution = $finding->solutions()->firstOrFail();
    $formatter = app(FormatEstimate::class);

    $solution->forceFill([
        'estimate_type' => EstimateType::Exact,
        'amount_min' => '20.00',
        'amount_max' => null,
        'currency_code' => 'EUR',
        'billing_frequency' => BillingFrequency::Monthly,
    ]);
    expect($formatter->handle($solution))->toBe('Circa 20 €/mese');

    $solution->forceFill([
        'estimate_type' => EstimateType::Range,
        'amount_min' => '500.00',
        'amount_max' => '900.00',
        'billing_frequency' => BillingFrequency::OneOff,
    ]);
    expect($formatter->handle($solution))->toBe('Indicativamente 500–900 € una tantum');

    $expected = [
        EstimateType::Bundled->value => 'Da sommare ad altre attività',
        EstimateType::RequiresQuote->value => 'Richiede preventivo',
        EstimateType::RequiresAnalysis->value => 'Richiede approfondimento',
        EstimateType::Variable->value => 'Variabile in base alla soluzione',
        EstimateType::NotApplicable->value => 'Nessun costo diretto previsto',
    ];
    foreach ($expected as $type => $label) {
        $solution->forceFill([
            'estimate_type' => $type,
            'amount_min' => null,
            'amount_max' => null,
            'currency_code' => null,
        ]);
        expect($formatter->handle($solution))->toBe($label);
    }
});

it('freezes a draft only after the PDF and generated report are stored successfully', function (): void {
    [$assessment] = createPdfReadyAssessment();
    $settings = app(ReportSettings::class);
    $settings->freeze_after_generation = true;
    $settings->save();

    $report = app(GenerateAssessmentPdf::class)($assessment);
    $assessment->refresh();

    expect($report->exists)->toBeTrue()
        ->and(Storage::disk('local')->exists($report->file_path))->toBeTrue()
        ->and($assessment->status)->toBe(AssessmentStatus::Completed)
        ->and($assessment->completed_at)->not->toBeNull()
        ->and($assessment->lock_version)->toBe(1);
});

it('rejects missing or corrupt included evidence without recording a report or success file', function (string $sha256): void {
    [$assessment, $finding] = createPdfReadyAssessment();
    $finding->evidences()->create([
        'type' => EvidenceType::File,
        'title' => 'Verbale tecnico',
        'file_path' => 'clients/missing/verbale.pdf',
        'original_filename' => 'verbale.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 100,
        'sha256' => $sha256,
        'include_in_report' => true,
        'sort_order' => 1,
    ]);

    if ($sha256 !== str_repeat('0', 64)) {
        Storage::disk('local')->put('clients/missing/verbale.pdf', 'contenuto alterato');
    }

    expect(fn () => app(GenerateAssessmentPdf::class)($assessment))
        ->toThrow(ValidationException::class)
        ->and(GeneratedReport::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('reports'))->toBe([]);
})->with([
    'missing file' => [str_repeat('0', 64)],
    'hash mismatch' => [str_repeat('1', 64)],
]);

it('refuses a generated download when the authoritative file hash no longer matches', function (): void {
    [$assessment] = createPdfReadyAssessment();
    $report = app(GenerateAssessmentPdf::class)($assessment);
    Storage::disk('local')->put($report->file_path, 'tampered');

    $this->actingAs(User::factory()->create())
        ->get(route('generated-reports.download', $report))
        ->assertConflict();

    expect(app(AuditPrivateStorage::class)->handle()->hashMismatches)
        ->toContain("generated_report:{$report->id}");
});

it('renders verified evidence, links, alternatives, branding, scope, and resolution details', function (): void {
    [$assessment, $finding] = createPdfReadyAssessment();
    $client = $assessment->client;
    $site = Site::factory()->for($client)->create(['name' => 'Sede operativa Milano']);
    $assessment->sites()->attach($site);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
    expect($png)->toBeString();
    Storage::disk('local')->put('logos/client.png', $png);
    Storage::disk('local')->put('logos/consultant.png', $png);
    $client->update(['logo_path' => 'logos/client.png']);

    $settings = app(ReportSettings::class);
    $settings->branding = 'both';
    $settings->consultant_logo_path = 'logos/consultant.png';
    $settings->consultant_name = 'Mario Rossi';
    $settings->business_name = 'Consulenza Sicura S.r.l.';
    $settings->consultant_email = 'mario@example.test';
    $settings->consultant_website = 'https://example.test/consulenza';
    $settings->content_index = true;
    $settings->technical_notes = true;
    $settings->save();

    $recommended = $finding->solutions()->firstOrFail();
    $alternative = $recommended->replicate();
    $alternative->external_key = 'manual-alternative-report';
    $alternative->title = 'Soluzione alternativa verificata';
    $alternative->comparison_notes = 'Minore impatto operativo, tempi più lunghi.';
    $alternative->sort_order = 2;
    $alternative->save();

    $finding->update([
        'problem' => str_repeat('Testo lungo verificabile per il report definitivo. ', 120),
        'technical_notes' => 'Nota tecnica riservata inclusa.',
        'status' => FindingStatus::Resolved,
        'implemented_solution_id' => $recommended->getKey(),
        'resolution_notes' => 'Risoluzione verificata durante il collaudo.',
        'resolved_at' => now(),
    ]);

    Storage::disk('local')->put('evidence/screenshot.png', $png);
    Storage::disk('local')->put('evidence/verbale.pdf', '%PDF-1.4 evidence reference');
    $finding->evidences()->create([
        'type' => EvidenceType::File,
        'title' => 'Screenshot configurazione',
        'file_path' => 'evidence/screenshot.png',
        'original_filename' => 'configurazione.png',
        'caption' => 'Didascalia immagine verificata',
        'mime_type' => 'image/png',
        'size_bytes' => strlen($png),
        'sha256' => hash('sha256', $png),
        'include_in_report' => true,
        'sort_order' => 1,
    ]);
    $finding->evidences()->create([
        'type' => EvidenceType::File,
        'title' => 'Verbale tecnico allegato',
        'file_path' => 'evidence/verbale.pdf',
        'original_filename' => 'verbale.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen('%PDF-1.4 evidence reference'),
        'sha256' => hash('sha256', '%PDF-1.4 evidence reference'),
        'include_in_report' => true,
        'sort_order' => 2,
    ]);
    $finding->evidences()->create([
        'type' => EvidenceType::Url,
        'title' => 'Riferimento sicurezza online',
        'url' => 'https://example.test/security',
        'include_in_report' => true,
        'sort_order' => 3,
    ]);

    $report = app(GenerateAssessmentPdf::class)($assessment->fresh());
    $path = Storage::disk('local')->path($report->file_path);
    $text = (new Parser)->parseFile($path)->getText();
    $rawPdf = file_get_contents($path);

    expect($report->payload_snapshot['logos'])->toHaveCount(2)
        ->and($report->payload_snapshot['assessment']['sites'])->toBe(['Sede operativa Milano'])
        ->and($report->settings_snapshot['dark_mode'])->toBeTrue()
        ->and($text)->toContain(
            'Sede operativa Milano',
            'Mario Rossi',
            'Soluzione alternativa verificata',
            'Didascalia immagine verificata',
            'Verbale tecnico allegato',
            'application/pdf',
            'Riferimento sicurezza online',
            'Nota tecnica riservata inclusa',
            'Risoluzione verificata durante il collaudo.',
        )
        ->and($rawPdf)->toContain('https://example.test/security');
});

it('rejects invalid and oversized report logos before creating a report', function (string $contents, string $message): void {
    [$assessment] = createPdfReadyAssessment();
    Storage::disk('local')->put('logos/invalid.png', $contents);
    $settings = app(ReportSettings::class);
    $settings->consultant_logo_path = 'logos/invalid.png';
    $settings->save();

    expect(fn () => app(GenerateAssessmentPdf::class)($assessment))
        ->toThrow(ValidationException::class, $message)
        ->and(GeneratedReport::query()->count())->toBe(0);
})->with([
    'invalid image bytes' => ['not a decodable PNG', 'Il logo configurato non è un file PNG o JPEG valido.'],
    'larger than five megabytes' => [str_repeat('x', 5 * 1024 * 1024 + 1), 'Il logo configurato supera il limite di 5 MB.'],
]);

it('generates a parsable definitive PDF with fifty findings inside the operational limits', function (): void {
    $assessment = Assessment::factory()->create([
        'title' => 'Assessment definitivo con cinquanta finding',
    ]);
    $template = FindingTemplate::query()->with('solutions')->firstOrFail();

    foreach (range(1, 50) as $number) {
        $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
        $finding->update([
            'title' => sprintf('Finding definitivo %02d', $number),
            'scope_type' => ScopeType::Organization,
            'scope_description' => null,
        ]);
    }

    $startedAt = hrtime(true);
    $report = app(GenerateAssessmentPdf::class)($assessment->fresh());
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;
    $path = Storage::disk('local')->path($report->file_path);
    $parsed = (new Parser)->parseFile($path);
    $text = $parsed->getText();
    $contentPageCount = count($parsed->getPages()) - 1;

    expect($report->payload_snapshot['findings'])->toHaveCount(50)
        ->and($text)->toContain('Finding definitivo 01', 'Finding definitivo 50')
        ->and($text)->toContain("Pagina 1 di {$contentPageCount}", "Pagina {$contentPageCount} di {$contentPageCount}")
        ->and(count($parsed->getPages()))->toBeGreaterThanOrEqual(50)
        ->and(filesize($path))->toBeLessThanOrEqual(50 * 1024 * 1024)
        ->and($elapsedSeconds)->toBeLessThanOrEqual(30);
});

/** @return array{Assessment, Finding} */
function createPdfReadyAssessment(): array
{
    $assessment = Assessment::factory()->create([
        'title' => 'Assessment continuità operativa',
        'introduction' => "Introduzione completa\nper la direzione.",
        'executive_summary' => 'Riepilogo esecutivo con priorità operative.',
        'methodology_notes' => 'Analisi documentale e verifica tecnica.',
    ]);
    $template = FindingTemplate::query()->with('solutions')->firstOrFail();
    $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
    $finding->update([
        'scope_type' => ScopeType::Organization,
        'scope_description' => null,
    ]);

    return [$assessment->fresh(), $finding->fresh()];
}
