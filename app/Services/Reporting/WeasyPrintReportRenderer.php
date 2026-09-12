<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Data\Reports\AssessmentReportData;
use RuntimeException;
use Spatie\LaravelPdf\Drivers\WeasyPrintDriver;
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
            $dpi = $report->setting('pdf_image_dpi');
            $jpegQuality = $report->setting('pdf_jpeg_quality');
            $optimizeImages = $report->setting('pdf_optimize_images');
            if (! is_int($dpi) || $dpi < 72 || $dpi > 600
                || ! is_int($jpegQuality) || $jpegQuality < 0 || $jpegQuality > 95
                || ! is_bool($optimizeImages)) {
                throw new RuntimeException(__('assestme.reports.errors.pdf_generation_settings'));
            }

            /** @var array<string, bool|int|string|null> $config */
            $config = config('laravel-pdf.weasyprint', []);
            $config['dpi'] = $dpi;
            $config['jpeg-quality'] = $jpegQuality;
            $config['optimize-images'] = $optimizeImages;

            Pdf::view('reports.assessment', ['report' => $report])
                ->setDriver(new WeasyPrintDriver($config))
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
