<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\Assessment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class GenerateAssessmentProofXlsx
{
    public function build(Assessment $assessment): Spreadsheet
    {
        $assessment->load(['findings' => fn ($query) => $query->with(['priorityLevel', 'recommendedSolution.effortLevel'])->orderBy('sort_order')]);

        $spreadsheet = new Spreadsheet;
        $findingSheet = $spreadsheet->getActiveSheet();
        $findingSheet->setTitle('Finding');
        $findingSheet->fromArray([
            'Numero', 'Titolo', 'Categoria', 'Tag', 'Ambito', 'Asset', 'Problema',
            "Note per l'imprenditore", 'Soluzione raccomandata', 'Soluzioni alternative',
            'Priorità', 'Motivazione priorità', 'Impegno', 'Stima economica', 'Stato',
            'Note tecniche', 'Evidenze',
        ], null, 'A1');

        foreach ($assessment->findings as $index => $finding) {
            $row = $index + 2;
            $values = [
                $index + 1, $finding->title, null, null, null, null, $finding->problem,
                $finding->entrepreneur_notes, $finding->recommendedSolution?->description, null,
                $finding->priorityLevel?->label, null, $finding->recommendedSolution?->effortLevel?->label,
                $finding->recommendedSolution?->estimate_notes, $finding->status->value, null, null,
            ];

            foreach ($values as $columnIndex => $value) {
                $coordinate = chr(65 + $columnIndex).$row;
                $findingSheet->setCellValueExplicit(
                    $coordinate,
                    is_int($value) ? $value : (string) ($value ?? ''),
                    is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING,
                );
            }
        }

        $this->formatSheet($findingSheet, 'Q', max(2, $assessment->findings->count() + 1));

        $solutions = $spreadsheet->createSheet();
        $solutions->setTitle('Soluzioni');
        $solutions->fromArray([
            'Numero finding', 'Titolo finding', 'Titolo soluzione', 'Descrizione',
            'Raccomandata', 'Implementata', 'Note confronto', 'Impegno', 'Tipo stima',
            'Minimo', 'Massimo', 'Valuta', 'Ricorrenza', 'Note stima',
        ], null, 'A1');
        $this->formatSheet($solutions, 'N', 2);

        $evidence = $spreadsheet->createSheet();
        $evidence->setTitle('Evidenze');
        $evidence->fromArray([
            'Numero finding', 'Titolo finding', 'Tipo', 'Titolo', 'Nome file originale',
            'Didascalia', 'Riferimento URL/percorso', 'Inclusa nel report', 'Dimensione', 'SHA-256',
        ], null, 'A1');
        $this->formatSheet($evidence, 'J', 2);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public function save(Assessment $assessment, string $path): void
    {
        (new Xlsx($this->build($assessment)))->save($path);
    }

    private function formatSheet(Worksheet $sheet, string $lastColumn, int $lastRow): void
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E3A5F');
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setWidth($column === 'A' ? 14 : 28);
        }
    }
}
