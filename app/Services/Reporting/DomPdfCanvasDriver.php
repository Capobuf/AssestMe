<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Spatie\LaravelPdf\Drivers\DomPdfDriver;
use Spatie\LaravelPdf\PdfOptions;

final class DomPdfCanvasDriver extends DomPdfDriver
{
    /**
     * DOMPDF exposes page chrome only through its Canvas after rendering.
     * Spatie's stock driver returns the bytes immediately, so this
     * application-owned driver adds the required callback without changing
     * the selected renderer.
     */
    public function generatePdf(string $html, ?string $headerHtml, ?string $footerHtml, PdfOptions $options): string
    {
        $dompdf = $this->buildDompdf($html, $headerHtml, $footerHtml, $options);
        $dompdf->render();

        $header = (string) ($this->config['page_chrome']['header'] ?? 'AssestMe');
        $footer = (string) ($this->config['page_chrome']['footer'] ?? 'Riservato');
        $showCover = (bool) ($this->config['page_chrome']['show_cover'] ?? false);

        $dompdf->getCanvas()->page_script(
            static function (
                int $pageNumber,
                int $pageCount,
                Canvas $canvas,
                FontMetrics $fontMetrics,
            ) use ($header, $footer, $showCover): void {
                if ($showCover && $pageNumber === 1) {
                    return;
                }

                $contentPage = $showCover ? $pageNumber - 1 : $pageNumber;
                $contentPages = $showCover ? $pageCount - 1 : $pageCount;
                $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
                $fontSize = 9.0;
                $left = 42.0;
                $pageText = "Pagina {$contentPage} di {$contentPages}";
                $pageTextWidth = $fontMetrics->getTextWidth($pageText, $font, $fontSize);

                $canvas->text($left, 22.0, $header, $font, $fontSize, [0.12, 0.25, 0.45]);
                $canvas->text($left, $canvas->get_height() - 28.0, $footer, $font, $fontSize, [0.35, 0.35, 0.35]);
                $canvas->text(
                    $canvas->get_width() - $left - $pageTextWidth,
                    $canvas->get_height() - 28.0,
                    $pageText,
                    $font,
                    $fontSize,
                    [0.35, 0.35, 0.35],
                );
            },
        );

        return $dompdf->output();
    }
}
