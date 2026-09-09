<?php

declare(strict_types=1);

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Reports\GenerateAssessmentPdf;
use App\Actions\Reports\GenerateAssessmentWorkbook;
use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\EvidenceType;
use App\Enums\GeneratedReportFormat;
use App\Enums\ScopeType;
use App\Filament\Resources\Assessments\Pages\WorkspaceAssessment;
use App\Models\Assessment;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Models\GeneratedReport;
use App\Models\Site;
use App\Models\User;
use App\Settings\GeneralSettings;
use App\Settings\ReportSettings;
use Database\Seeders\MilestoneOneSeeder;
use Database\Seeders\MilestoneTwoSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

beforeEach(function (): void {
    $this->seed(MilestoneOneSeeder::class);
    $this->seed(MilestoneTwoSeeder::class);
    Storage::fake('local');
});

it('persists the selected finding before generating the workbook snapshot', function (): void {
    [$assessment, $finding] = createWorkbookReadyAssessment();
    $this->actingAs(User::factory()->create());

    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->call('selectFinding', $finding->getKey())
        ->set('findingData.title', 'Titolo persistito nel foglio XLSX')
        ->callAction(TestAction::make('download_xlsx'), ['include_excluded_findings' => false])
        ->assertHasNoErrors();

    $report = GeneratedReport::query()->where('format', GeneratedReportFormat::Xlsx)->sole();
    expect($report->payload_snapshot['findings'][0]['title'])->toBe('Titolo persistito nel foglio XLSX')
        ->and(Storage::disk('local')->exists($report->file_path))->toBeTrue();
});

it('persists and downloads complete immutable three-sheet workbook versions', function (): void {
    [$assessment, $finding] = createWorkbookReadyAssessment();
    $client = $assessment->client;
    $site = Site::factory()->for($client)->create(['name' => 'Sede Nord']);
    $asset = Asset::factory()->for($client)->create([
        'site_id' => $site->getKey(),
        'asset_type_id' => AssetType::query()->where('name', 'Server')->value('id'),
        'name' => 'Server gestionale',
        'manufacturer' => 'Vendor',
        'model' => 'Model X',
        'hostname' => 'srv-gestionale',
        'ip_address' => '10.20.30.40',
    ]);
    $finding->sites()->attach($site);
    $finding->assets()->attach($asset);
    $finding->update([
        'scope_type' => ScopeType::SelectedAssets,
        'priority_rationale' => 'Priorità confermata dalla matrice.',
        'technical_notes' => "Nota tecnica riga uno\nNota tecnica riga due",
    ]);

    $recommended = $finding->solutions()->firstOrFail();
    $recommended->update([
        'estimate_type' => EstimateType::Approximate,
        'amount_min' => '250.00',
        'amount_max' => null,
        'currency_code' => 'EUR',
        'billing_frequency' => BillingFrequency::OneOff,
        'estimate_notes' => 'Importo indicativo verificato.',
    ]);
    $alternative = $recommended->replicate();
    $alternative->external_key = 'manual-workbook-alternative';
    $alternative->title = 'Soluzione alternativa';
    $alternative->description = 'Descrizione completa alternativa.';
    $alternative->comparison_notes = 'Minore impatto, tempi maggiori.';
    $alternative->estimate_type = EstimateType::Range;
    $alternative->amount_min = '500.00';
    $alternative->amount_max = '900.00';
    $alternative->billing_frequency = BillingFrequency::Yearly;
    $alternative->sort_order = 2;
    $alternative->save();

    $fileContents = 'evidenza testuale completa';
    Storage::disk('local')->put('evidence/verbale.txt', $fileContents);
    $finding->evidences()->create([
        'type' => EvidenceType::File,
        'title' => 'Verbale tecnico',
        'file_path' => 'evidence/verbale.txt',
        'original_filename' => 'verbale.txt',
        'caption' => 'Verbale raccolto durante il sopralluogo.',
        'mime_type' => 'text/plain',
        'size_bytes' => strlen($fileContents),
        'sha256' => hash('sha256', $fileContents),
        'include_in_report' => true,
        'sort_order' => 1,
    ]);
    $finding->evidences()->create([
        'type' => EvidenceType::Url,
        'title' => 'Riferimento online',
        'url' => 'https://example.test/verifica',
        'caption' => 'Collegamento di verifica.',
        'include_in_report' => false,
        'sort_order' => 2,
    ]);

    $settings = app(ReportSettings::class);
    $settings->technical_notes = true;
    $settings->save();

    $first = app(GenerateAssessmentWorkbook::class)($assessment->fresh(), false);
    $pdf = app(GenerateAssessmentPdf::class)($assessment->fresh());
    $second = app(GenerateAssessmentWorkbook::class)($assessment->fresh(), false);
    $path = Storage::disk('local')->path($first->file_path);
    $workbook = IOFactory::load($path);
    $findingSheet = $workbook->getSheetByName('Finding');
    $solutionSheet = $workbook->getSheetByName('Soluzioni');
    $evidenceSheet = $workbook->getSheetByName('Evidenze');
    $fixedNote = 'Tutti gli importi indicati sono stime orientative e si intendono IVA esclusa.';

    expect($first->format)->toBe(GeneratedReportFormat::Xlsx)
        ->and($first->version)->toBe(1)
        ->and($pdf->version)->toBe(1)
        ->and($second->version)->toBe(2)
        ->and($first->file_name)->toMatch('/^AssestMe_.+_v01\.xlsx$/')
        ->and(Storage::disk('local')->exists($first->file_path))->toBeTrue()
        ->and(hash_file('sha256', $path))->toBe($first->file_sha256)
        ->and($first->payload_snapshot['export_options']['include_excluded_findings'])->toBeFalse()
        ->and($first->settings_snapshot['xlsx_include_excluded_findings'])->toBeFalse()
        ->and($first->payload_snapshot['findings'][0]['include_in_report'])->toBeTrue()
        ->and($first->payload_snapshot['findings'][0])->not->toHaveKey('tags')
        ->and($workbook->getSheetNames())->toBe(['Finding', 'Soluzioni', 'Evidenze'])
        ->and($findingSheet)->not->toBeNull()
        ->and($solutionSheet)->not->toBeNull()
        ->and($evidenceSheet)->not->toBeNull()
        ->and($findingSheet?->getMergeCells())->toHaveKey('A1:P1')
        ->and($findingSheet?->getCell('A1')->getValue())->toBe($fixedNote)
        ->and(countWorkbookValue($workbook, $fixedNote))->toBe(1)
        ->and($findingSheet?->getFreezePane())->toBe('A3')
        ->and($findingSheet?->getAutoFilter()->getRange())->toBe('A2:P3')
        ->and($findingSheet?->getCell('B3')->getValue())->toBe($finding->title)
        ->and($findingSheet?->getCell('D2')->getValue())->toBe('Ambito')
        ->and($findingSheet?->getCell('D3')->getValue())->toContain('Asset selezionati', 'Sede Nord')
        ->and($findingSheet?->getCell('E3')->getValue())->toContain('Server gestionale', 'srv-gestionale', '10.20.30.40')
        ->and($findingSheet?->getCell('F3')->getValue())->toContain("\n")
        ->and($findingSheet?->getCell('H3')->getValue())->toContain($recommended->title, $recommended->description)
        ->and($findingSheet?->getCell('I3')->getValue())->toContain('Soluzione alternativa', 'Minore impatto')
        ->and($findingSheet?->getCell('M3')->getValue())->toBe('Circa 250 € una tantum')
        ->and($findingSheet?->getCell('O3')->getValue())->toContain('Nota tecnica riga due')
        ->and($findingSheet?->getCell('P3')->getValue())->toContain('Verbale tecnico', 'Riferimento online')
        ->and($findingSheet?->getStyle('F3')->getAlignment()->getWrapText())->toBeTrue()
        ->and($solutionSheet?->getHighestDataRow())->toBe(3)
        ->and($solutionSheet?->getFreezePane())->toBe('A2')
        ->and($solutionSheet?->getAutoFilter()->getRange())->toBe('A1:N3')
        ->and($solutionSheet?->getCell('J2')->getDataType())->toBe(DataType::TYPE_NUMERIC)
        ->and($solutionSheet?->getCell('J2')->getValue())->toBe(250.0)
        ->and($solutionSheet?->getCell('J3')->getValue())->toBe(500.0)
        ->and($solutionSheet?->getCell('K3')->getValue())->toBe(900.0)
        ->and($evidenceSheet?->getHighestDataRow())->toBe(3)
        ->and($evidenceSheet?->getCell('G2')->getValue())->toBe('evidence/verbale.txt')
        ->and($evidenceSheet?->getCell('G2')->getHyperlink()->getUrl())->toBe('')
        ->and($evidenceSheet?->getCell('G3')->getHyperlink()->getUrl())->toBe('https://example.test/verifica')
        ->and(fn () => $first->update(['file_name' => 'mutato.xlsx']))->toThrow(LogicException::class);
    $first->refresh();

    $administrator = User::factory()->create();
    $this->get(route('generated-reports.download', $first))->assertRedirect('/admin/login');
    $this->actingAs($administrator)
        ->get(route('generated-reports.download', $first))
        ->assertOk()
        ->assertDownload($first->file_name)
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('x-content-type-options', 'nosniff');

    $workbook->disconnectWorksheets();
});

it('honors explicit and configured inclusion of incomplete excluded findings', function (): void {
    [$assessment] = createWorkbookReadyAssessment();
    $excluded = Finding::factory()->for($assessment)->create([
        'title' => 'Finding escluso incompleto',
        'category_id' => null,
        'priority_level_id' => null,
        'include_in_report' => false,
        'sort_order' => 2,
    ]);

    $includedOnly = app(GenerateAssessmentWorkbook::class)($assessment->fresh(), false);
    $withExcluded = app(GenerateAssessmentWorkbook::class)($assessment->fresh(), true);
    $configuredSettings = app(GeneralSettings::class);
    $configuredSettings->report_excluded_findings_in_xlsx = true;
    $configuredSettings->save();
    $configured = app(GenerateAssessmentWorkbook::class)($assessment->fresh());

    $includedWorkbook = IOFactory::load(Storage::disk('local')->path($includedOnly->file_path));
    $excludedWorkbook = IOFactory::load(Storage::disk('local')->path($withExcluded->file_path));

    expect($includedOnly->payload_snapshot['findings'])->toHaveCount(1)
        ->and($withExcluded->payload_snapshot['findings'])->toHaveCount(2)
        ->and($withExcluded->payload_snapshot['findings'][1]['id'])->toBe($excluded->id)
        ->and($withExcluded->payload_snapshot['findings'][1]['include_in_report'])->toBeFalse()
        ->and($withExcluded->payload_snapshot['export_options']['include_excluded_findings'])->toBeTrue()
        ->and($configured->payload_snapshot['findings'])->toHaveCount(2)
        ->and($includedWorkbook->getSheetByName('Finding')?->getHighestDataRow())->toBe(3)
        ->and($excludedWorkbook->getSheetByName('Finding')?->getHighestDataRow())->toBe(4)
        ->and($excludedWorkbook->getSheetByName('Finding')?->getHighestDataColumn())->toBe('O')
        ->and($excludedWorkbook->getSheetByName('Finding')?->getCell('O2')->getValue())->toBe('Evidenze')
        ->and($excludedWorkbook->getSheetByName('Finding')?->getCell('H4')->getValue())->toBeNull();

    $includedWorkbook->disconnectWorksheets();
    $excludedWorkbook->disconnectWorksheets();
});

it('exports every estimate type and billing frequency as native workbook values', function (): void {
    $assessment = Assessment::factory()->create(['title' => 'Assessment stime XLSX']);
    $template = FindingTemplate::query()->with('solutions')->firstOrFail();
    $cases = [
        [EstimateType::Approximate, BillingFrequency::Monthly, '20.00', null, 'EUR', null, 'Circa 20 €/mese'],
        [EstimateType::Range, BillingFrequency::Custom, '500.00', '900.00', 'EUR', 'trimestre', 'Indicativamente 500–900 €/trimestre'],
        [EstimateType::Bundled, BillingFrequency::OneOff, null, null, null, null, 'Da sommare ad altre attività'],
        [EstimateType::RequiresQuote, BillingFrequency::Yearly, null, null, null, null, 'Richiede preventivo'],
        [EstimateType::RequiresAnalysis, BillingFrequency::Monthly, null, null, null, null, 'Richiede approfondimento'],
        [EstimateType::Variable, BillingFrequency::OneOff, null, null, null, null, 'Variabile in base alla Soluzione'],
        [EstimateType::NotApplicable, BillingFrequency::Yearly, null, null, null, null, 'Nessun costo diretto previsto'],
    ];

    foreach ($cases as $index => [$type, $frequency, $minimum, $maximum, $currency, $custom, $expected]) {
        $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
        $finding->update([
            'title' => 'Stima '.($index + 1),
            'scope_type' => ScopeType::Organization,
            'scope_description' => null,
        ]);
        $finding->solutions()->firstOrFail()->update([
            'estimate_type' => $type,
            'billing_frequency' => $frequency,
            'amount_min' => $minimum,
            'amount_max' => $maximum,
            'currency_code' => $currency,
            'custom_billing_frequency' => $custom,
        ]);
    }

    $report = app(GenerateAssessmentWorkbook::class)($assessment->fresh(), false);
    $workbook = IOFactory::load(Storage::disk('local')->path($report->file_path));
    $findingSheet = $workbook->getSheetByName('Finding');
    $solutionSheet = $workbook->getSheetByName('Soluzioni');

    foreach ($cases as $index => $case) {
        expect($findingSheet?->getCell('M'.($index + 3))->getValue())->toBe($case[6]);
    }

    expect($solutionSheet?->getCell('J2')->getDataType())->toBe(DataType::TYPE_NUMERIC)
        ->and($solutionSheet?->getCell('J2')->getValue())->toBe(20.0)
        ->and($solutionSheet?->getCell('J3')->getValue())->toBe(500.0)
        ->and($solutionSheet?->getCell('K3')->getValue())->toBe(900.0)
        ->and($solutionSheet?->getCell('M2')->getValue())->toBe('Mensile')
        ->and($solutionSheet?->getCell('M3')->getValue())->toBe('Personalizzata — trimestre')
        ->and($solutionSheet?->getCell('M4')->getValue())->toBe('Una tantum')
        ->and($solutionSheet?->getCell('M5')->getValue())->toBe('Annuale');

    $workbook->disconnectWorksheets();
});

it('fails visibly and records no workbook when included evidence is missing', function (): void {
    [$assessment, $finding] = createWorkbookReadyAssessment();
    $finding->evidences()->create([
        'type' => EvidenceType::File,
        'title' => 'File non disponibile',
        'file_path' => 'evidence/missing.txt',
        'original_filename' => 'missing.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 10,
        'sha256' => str_repeat('0', 64),
        'include_in_report' => true,
        'sort_order' => 1,
    ]);

    expect(fn () => app(GenerateAssessmentWorkbook::class)($assessment, false))
        ->toThrow(ValidationException::class)
        ->and(GeneratedReport::query()->where('format', GeneratedReportFormat::Xlsx)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('reports'))->toBe([]);

    $this->actingAs(User::factory()->create());
    Livewire::test(WorkspaceAssessment::class, ['record' => $assessment->getRouteKey()])
        ->callAction(TestAction::make('download_xlsx'), ['include_excluded_findings' => false])
        ->assertNotified(__('assestme.reports.errors.generation_xlsx'));

    expect(GeneratedReport::query()->where('format', GeneratedReportFormat::Xlsx)->count())->toBe(0);
});

it('generates and reopens fifty definitive findings inside the XLSX budget', function (): void {
    $assessment = Assessment::factory()->create(['title' => 'Assessment cinquanta finding XLSX']);
    $template = FindingTemplate::query()->with('solutions')->firstOrFail();
    foreach (range(1, 50) as $number) {
        $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
        $finding->update([
            'title' => sprintf('Finding XLSX %02d', $number),
            'scope_type' => ScopeType::Organization,
            'scope_description' => null,
        ]);
    }

    $startedAt = hrtime(true);
    $report = app(GenerateAssessmentWorkbook::class)($assessment->fresh(), false);
    $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;
    $workbook = IOFactory::load(Storage::disk('local')->path($report->file_path));

    expect($elapsedSeconds)->toBeLessThanOrEqual(10.0)
        ->and($workbook->getSheetByName('Finding')?->getHighestDataRow())->toBe(52)
        ->and($workbook->getSheetByName('Soluzioni')?->getHighestDataRow())->toBe(51)
        ->and($workbook->getSheetByName('Finding')?->getCell('B3')->getValue())->toBe('Finding XLSX 01')
        ->and($workbook->getSheetByName('Finding')?->getCell('B52')->getValue())->toBe('Finding XLSX 50');

    $workbook->disconnectWorksheets();
});

/** @return array{Assessment, Finding} */
function createWorkbookReadyAssessment(): array
{
    $assessment = Assessment::factory()->create([
        'title' => 'Assessment workbook definitivo',
        'introduction' => 'Introduzione completa per il workbook.',
        'executive_summary' => 'Riepilogo completo per il workbook.',
    ]);
    $template = FindingTemplate::query()->with('solutions')->firstOrFail();
    $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
    $finding->update([
        'problem' => "Problema riga uno\nProblema riga due",
        'scope_type' => ScopeType::Organization,
        'scope_description' => null,
    ]);

    return [$assessment->fresh(), $finding->fresh()];
}

function countWorkbookValue(Spreadsheet $workbook, string $expected): int
{
    $count = 0;
    foreach ($workbook->getAllSheets() as $sheet) {
        foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
            if ($sheet->getCell($coordinate)->getValue() === $expected) {
                $count++;
            }
        }
    }

    return $count;
}
