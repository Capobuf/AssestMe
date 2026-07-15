<?php

declare(strict_types=1);

use App\Actions\Reports\GenerateAssessmentProofPdf;
use App\Actions\Reports\GenerateAssessmentProofXlsx;
use App\Models\Assessment;
use App\Models\Finding;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser;

it('generates a real multipage DOMPDF proof with repeated chrome and Italian text', function (): void {
    $assessment = Assessment::factory()->create(['title' => 'Assessment qualità e continuità']);
    Finding::factory()->count(50)->for($assessment)->sequence(
        fn ($sequence): array => [
            'sort_order' => $sequence->index + 1,
            'problem' => "Riga uno con priorità.\nRiga due con attività e qualità.",
        ],
    )->create();
    $path = storage_path('framework/testing-proof.pdf');

    app(GenerateAssessmentProofPdf::class)($assessment)->save($path);
    $pdf = (new Parser)->parseFile($path);
    $text = $pdf->getText();

    expect(file_get_contents($path, false, null, 0, 4))->toBe('%PDF')
        ->and(count($pdf->getPages()))->toBeGreaterThan(1)
        ->and($text)->toContain('priorità', 'continuità', 'Pagina 1 di', 'Riservato');

    unlink($path);
});

it('generates and reopens a styled native XLSX proof', function (): void {
    $assessment = Assessment::factory()->create();
    Finding::factory()->count(10)->for($assessment)->sequence(
        fn ($sequence): array => [
            'sort_order' => $sequence->index + 1,
            'problem' => "Prima riga\nSeconda riga",
        ],
    )->create();
    $path = storage_path('framework/testing-proof.xlsx');

    app(GenerateAssessmentProofXlsx::class)->save($assessment, $path);
    $workbook = IOFactory::load($path);
    $findingSheet = $workbook->getSheetByName('Finding');

    expect($workbook->getSheetNames())->toBe(['Finding', 'Soluzioni', 'Evidenze'])
        ->and($findingSheet)->not->toBeNull()
        ->and($findingSheet?->getHighestDataRow())->toBe(11)
        ->and($findingSheet?->getFreezePane())->toBe('A2')
        ->and($findingSheet?->getAutoFilter()->getRange())->toBe('A1:Q11')
        ->and($findingSheet?->getStyle('G2')->getAlignment()->getWrapText())->toBeTrue()
        ->and($findingSheet?->getCell('G2')->getValue())->toContain("\n");

    unlink($path);
});
