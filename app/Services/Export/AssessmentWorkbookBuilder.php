<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Data\Reports\AssessmentReportData;
use App\Data\Reports\ReportAssetData;
use App\Data\Reports\ReportEvidenceData;
use App\Data\Reports\ReportFindingData;
use App\Data\Reports\ReportSolutionData;
use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class AssessmentWorkbookBuilder
{
    private const HEADER_TEXT_COLOR = 'FFFFFFFF';

    private const BORDER_COLOR = 'FFD1D5DB';

    private const NOTE_FILL_COLOR = 'FFF3F4F6';

    public function build(AssessmentReportData $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator((string) $report->setting('application_name'))
            ->setTitle($report->title)
            ->setSubject($report->assessmentTitle)
            ->setDescription(__('assestme.reports.workbook.description'));

        $this->buildFindingSheet($spreadsheet->getActiveSheet(), $report);
        $this->buildSolutionsSheet($spreadsheet->createSheet(), $report);
        $this->buildEvidenceSheet($spreadsheet->createSheet(), $report);
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function buildFindingSheet(Worksheet $sheet, AssessmentReportData $report): void
    {
        $includeTechnicalNotes = $report->setting('technical_notes') === true;
        $headers = [
            __('assestme.reports.workbook.findings.number'),
            __('assestme.reports.workbook.findings.title'),
            __('assestme.reports.workbook.findings.category'),
            __('assestme.reports.workbook.findings.tags'),
            __('assestme.reports.workbook.findings.scope'),
            __('assestme.reports.workbook.findings.assets'),
            __('assestme.reports.workbook.findings.problem'),
            __('assestme.reports.workbook.findings.entrepreneur_notes'),
            __('assestme.reports.workbook.findings.recommended_solution'),
            __('assestme.reports.workbook.findings.alternative_solutions'),
            __('assestme.reports.workbook.findings.priority'),
            __('assestme.reports.workbook.findings.priority_rationale'),
            __('assestme.reports.workbook.findings.effort'),
            __('assestme.reports.workbook.findings.estimate'),
            __('assestme.reports.workbook.findings.status'),
        ];
        $widths = [10, 28, 20, 22, 30, 38, 48, 40, 44, 44, 16, 36, 16, 28, 16];
        if ($includeTechnicalNotes) {
            $headers[] = __('assestme.reports.workbook.findings.technical_notes');
            $widths[] = 40;
        }
        $headers[] = __('assestme.reports.workbook.findings.evidence');
        $widths[] = 42;

        $sheet->setTitle('Finding');
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValueExplicit('A1', __('assestme.reports.document.vat_note'), DataType::TYPE_STRING);
        $this->writeRow($sheet, 2, $headers);

        $row = 3;
        foreach ($report->findings as $finding) {
            $recommended = $finding->recommendedSolutionOrNull();
            $values = [
                $finding->number,
                $finding->title,
                $finding->category,
                implode(', ', $finding->tags),
                $this->scope($finding),
                implode("\n", array_map(static fn (ReportAssetData $asset): string => $asset->displayLabel, $finding->assets)),
                $finding->problem,
                $finding->entrepreneurNotes,
                $recommended === null ? null : $this->solutionSummary($recommended),
                $this->alternativeSolutions($finding),
                $finding->priorityLabel,
                $finding->priorityRationale,
                $recommended?->effortLabel,
                $recommended?->estimateLabel,
                $finding->statusLabel,
            ];
            if ($includeTechnicalNotes) {
                $values[] = $finding->technicalNotes;
            }
            $values[] = $this->evidenceSummary($finding);

            $this->writeRow($sheet, $row, $values);
            $row++;
        }

        $lastRow = max(2, $row - 1);
        $this->formatFindingSheet($sheet, $lastColumn, $lastRow, $widths, $this->primaryColor($report));
    }

    private function buildSolutionsSheet(Worksheet $sheet, AssessmentReportData $report): void
    {
        $headers = [
            __('assestme.reports.workbook.solutions.finding_number'),
            __('assestme.reports.workbook.solutions.finding_title'),
            __('assestme.reports.workbook.solutions.title'),
            __('assestme.reports.workbook.solutions.description'),
            __('assestme.reports.workbook.solutions.recommended'),
            __('assestme.reports.workbook.solutions.implemented'),
            __('assestme.reports.workbook.solutions.comparison_notes'),
            __('assestme.reports.workbook.solutions.effort'),
            __('assestme.reports.workbook.solutions.estimate_type'),
            __('assestme.reports.workbook.solutions.minimum'),
            __('assestme.reports.workbook.solutions.maximum'),
            __('assestme.reports.workbook.solutions.currency'),
            __('assestme.reports.workbook.solutions.recurrence'),
            __('assestme.reports.workbook.solutions.estimate_notes'),
        ];
        $widths = [14, 30, 30, 50, 15, 15, 36, 16, 20, 14, 14, 12, 22, 36];

        $sheet->setTitle('Soluzioni');
        $this->writeRow($sheet, 1, $headers);
        $row = 2;
        foreach ($report->findings as $finding) {
            foreach ($finding->solutions as $solution) {
                $this->writeRow($sheet, $row, [
                    $finding->number,
                    $finding->title,
                    $solution->title,
                    $solution->description,
                    $this->booleanLabel($solution->recommended),
                    $this->booleanLabel($solution->implemented),
                    $solution->comparisonNotes,
                    $solution->effortLabel,
                    $this->estimateTypeLabel($solution->estimateType),
                    $this->numericAmount($solution->amountMin),
                    $this->numericAmount($solution->amountMax),
                    $solution->currencyCode,
                    $this->billingFrequencyLabel($solution),
                    $solution->estimateNotes,
                ]);
                $row++;
            }
        }

        $lastRow = max(1, $row - 1);
        $this->formatDataSheet($sheet, 'N', $lastRow, $widths, $this->primaryColor($report));
        if ($lastRow >= 2) {
            $sheet->getStyle("J2:K{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }

    private function buildEvidenceSheet(Worksheet $sheet, AssessmentReportData $report): void
    {
        $headers = [
            __('assestme.reports.workbook.evidence.finding_number'),
            __('assestme.reports.workbook.evidence.finding_title'),
            __('assestme.reports.workbook.evidence.type'),
            __('assestme.reports.workbook.evidence.title'),
            __('assestme.reports.workbook.evidence.original_filename'),
            __('assestme.reports.workbook.evidence.caption'),
            __('assestme.reports.workbook.evidence.reference'),
            __('assestme.reports.workbook.evidence.included'),
            __('assestme.reports.workbook.evidence.size'),
            __('assestme.reports.workbook.evidence.sha256'),
        ];
        $widths = [14, 30, 16, 30, 30, 36, 48, 18, 18, 68];

        $sheet->setTitle('Evidenze');
        $this->writeRow($sheet, 1, $headers);
        $row = 2;
        foreach ($report->findings as $finding) {
            foreach ($finding->evidences as $evidence) {
                $reference = $evidence->type === 'url' ? $evidence->url : $evidence->filePath;
                $this->writeRow($sheet, $row, [
                    $finding->number,
                    $finding->title,
                    $this->evidenceTypeLabel($evidence),
                    $evidence->title,
                    $evidence->originalFilename,
                    $evidence->caption,
                    $reference,
                    $this->booleanLabel($evidence->included),
                    $evidence->sizeBytes,
                    $evidence->sha256,
                ]);

                if ($evidence->type === 'url' && $evidence->url !== null) {
                    $sheet->getCell("G{$row}")->getHyperlink()
                        ->setUrl($evidence->url)
                        ->setTooltip($evidence->title);
                    $sheet->getStyle("G{$row}")->getFont()
                        ->setUnderline(true)
                        ->getColor()->setARGB('FF2563EB');
                }
                $row++;
            }
        }

        $lastRow = max(1, $row - 1);
        $this->formatDataSheet($sheet, 'J', $lastRow, $widths, $this->primaryColor($report));
    }

    /** @param list<int|float|string|null> $values */
    private function writeRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $offset => $value) {
            $coordinate = Coordinate::stringFromColumnIndex($offset + 1).$row;
            if (is_int($value) || is_float($value)) {
                $sheet->setCellValueExplicit($coordinate, $value, DataType::TYPE_NUMERIC);

                continue;
            }

            $sheet->setCellValueExplicit($coordinate, $value ?? '', DataType::TYPE_STRING);
        }
    }

    /** @param list<int> $widths */
    private function formatFindingSheet(
        Worksheet $sheet,
        string $lastColumn,
        int $lastRow,
        array $widths,
        string $headerColor,
    ): void {
        $sheet->freezePane('A3');
        $sheet->setAutoFilter("A2:{$lastColumn}{$lastRow}");
        $sheet->getStyle("A2:{$lastColumn}2")->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_TEXT_COLOR);
        $sheet->getStyle("A2:{$lastColumn}2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($headerColor);
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setItalic(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::NOTE_FILL_COLOR);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $this->formatBody($sheet, $lastColumn, $lastRow, $widths);
    }

    /** @param list<int> $widths */
    private function formatDataSheet(
        Worksheet $sheet,
        string $lastColumn,
        int $lastRow,
        array $widths,
        string $headerColor,
    ): void {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true)->getColor()->setARGB(self::HEADER_TEXT_COLOR);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($headerColor);
        $this->formatBody($sheet, $lastColumn, $lastRow, $widths);
    }

    /** @param list<int> $widths */
    private function formatBody(Worksheet $sheet, string $lastColumn, int $lastRow, array $widths): void
    {
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()
            ->setVertical(Alignment::VERTICAL_TOP)
            ->setWrapText(true);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setARGB(self::BORDER_COLOR);

        foreach ($widths as $offset => $width) {
            $column = Coordinate::stringFromColumnIndex($offset + 1);
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function scope(ReportFindingData $finding): string
    {
        $parts = [$finding->scopeLabel];
        if ($finding->sites !== []) {
            $parts[] = __('assestme.reports.workbook.site_list', ['sites' => implode(', ', $finding->sites)]);
        }

        return implode("\n", $parts);
    }

    private function solutionSummary(ReportSolutionData $solution): string
    {
        return $solution->title."\n".$solution->description;
    }

    private function alternativeSolutions(ReportFindingData $finding): string
    {
        return implode("\n\n", array_map(function (ReportSolutionData $solution): string {
            $summary = $this->solutionSummary($solution);

            return $solution->comparisonNotes === null
                ? $summary
                : $summary."\n".$solution->comparisonNotes;
        }, $finding->alternativeSolutions()));
    }

    private function evidenceSummary(ReportFindingData $finding): string
    {
        return implode("\n", array_map(static function (ReportEvidenceData $evidence): string {
            $reference = $evidence->type === 'url'
                ? $evidence->url
                : ($evidence->originalFilename ?? $evidence->filePath);
            $parts = array_values(array_filter([
                $evidence->title,
                $reference,
                $evidence->caption,
            ], static fn (?string $value): bool => $value !== null && $value !== ''));

            return implode(' — ', $parts);
        }, $finding->evidences));
    }

    private function booleanLabel(bool $value): string
    {
        return $value
            ? __('assestme.reports.workbook.values.yes')
            : __('assestme.reports.workbook.values.no');
    }

    private function estimateTypeLabel(string $type): string
    {
        $estimateType = EstimateType::tryFrom($type);
        if (! $estimateType instanceof EstimateType) {
            throw new \LogicException('The workbook snapshot contains an invalid estimate type.');
        }

        return EstimateType::options()[$estimateType->value];
    }

    private function billingFrequencyLabel(ReportSolutionData $solution): string
    {
        $frequency = BillingFrequency::tryFrom($solution->billingFrequency);
        if (! $frequency instanceof BillingFrequency) {
            throw new \LogicException('The workbook snapshot contains an invalid billing frequency.');
        }

        $label = __('assestme.billing.'.$frequency->value);

        return $frequency === BillingFrequency::Custom && $solution->customBillingFrequency !== null
            ? $label.' — '.$solution->customBillingFrequency
            : $label;
    }

    private function evidenceTypeLabel(ReportEvidenceData $evidence): string
    {
        return match ($evidence->type) {
            'file' => __('assestme.reports.workbook.values.file'),
            'url' => __('assestme.reports.workbook.values.url'),
            default => throw new \LogicException('The workbook snapshot contains an invalid evidence type.'),
        };
    }

    private function numericAmount(?string $amount): ?float
    {
        return $amount === null ? null : (float) $amount;
    }

    private function primaryColor(AssessmentReportData $report): string
    {
        $color = $report->setting('primary_color');
        if (! is_string($color) || preg_match('/^#[0-9A-F]{6}$/', $color) !== 1) {
            throw new \LogicException('The workbook snapshot contains an invalid primary color.');
        }

        return 'FF'.substr($color, 1);
    }
}
