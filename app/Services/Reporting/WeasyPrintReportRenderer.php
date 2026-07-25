<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Data\Reports\AssessmentReportData;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;

final class WeasyPrintReportRenderer
{
    public function render(AssessmentReportData $report): string
    {
        $temporaryPath = tempnam(storage_path('framework'), 'assestme-pdf-');
        if ($temporaryPath === false) {
            throw new RuntimeException(__('assestme.reports.errors.temporary_file'));
        }

        try {
            Pdf::view('reports.assessment', ['report' => $report])
                ->driver('weasyprint')
                ->save($temporaryPath);

            $contents = file_get_contents($temporaryPath);
            if ($contents === false) {
                throw new RuntimeException(__('assestme.reports.errors.temporary_file'));
            }

            return $contents;
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
