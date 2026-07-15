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
        $confidentiality = (string) ($this->config['page_chrome']['confidentiality'] ?? '');
        $showCover = (bool) ($this->config['page_chrome']['show_cover'] ?? false);
        $showHeaderFooter = (bool) ($this->config['page_chrome']['show_header_footer'] ?? true);
        $showPageNumbers = (bool) ($this->config['page_chrome']['show_page_numbers'] ?? true);

        $dompdf->getCanvas()->page_script(
            static function (
                int $pageNumber,
                int $pageCount,
                Canvas $canvas,
                FontMetrics $fontMetrics,
            ) use ($header, $footer, $confidentiality, $showCover, $showHeaderFooter, $showPageNumbers): void {
                if ($showCover && $pageNumber === 1) {
                    return;
                }

                $contentPage = $showCover ? $pageNumber - 1 : $pageNumber;
                $contentPages = $showCover ? $pageCount - 1 : $pageCount;
                $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
                $fontSize = 9.0;
                $left = 42.0;
                $right = $canvas->get_width() - $left;
                $pageText = __('assestme.reports.document.page_number', [
                    'current' => $contentPage,
                    'total' => $contentPages,
                ]);
                $fitText = static function (string $text, float $maxWidth) use ($fontMetrics, $font, $fontSize): string {
                    if ($fontMetrics->getTextWidth($text, $font, $fontSize) <= $maxWidth) {
                        return $text;
                    }

                    $suffix = '…';
                    while ($text !== '' && $fontMetrics->getTextWidth($text.$suffix, $font, $fontSize) > $maxWidth) {
                        $text = mb_substr($text, 0, -1);
                    }

                    return $text === '' ? '' : $text.$suffix;
                };

                if ($showHeaderFooter && $header !== '') {
                    $canvas->text($left, 22.0, $fitText($header, $right - $left), $font, $fontSize, [0.12, 0.25, 0.45]);
                }

                $rightParts = [];
                if ($confidentiality !== '') {
                    $rightParts[] = $confidentiality;
                }
                if ($showPageNumbers) {
                    $rightParts[] = $pageText;
                }
                $rightText = implode(' — ', $rightParts);
                $rightText = $fitText($rightText, ($right - $left) * 0.55);
                $rightTextWidth = $fontMetrics->getTextWidth($rightText, $font, $fontSize);
                $footerWidth = max(0.0, ($right - $left) - $rightTextWidth - 12.0);

                if ($showHeaderFooter && $footer !== '') {
                    $canvas->text($left, $canvas->get_height() - 28.0, $fitText($footer, $footerWidth), $font, $fontSize, [0.35, 0.35, 0.35]);
                }
                if ($rightText !== '') {
                    $canvas->text(
                        $right - $rightTextWidth,
                        $canvas->get_height() - 28.0,
                        $rightText,
                        $font,
                        $fontSize,
                        [0.35, 0.35, 0.35],
                    );
                }
            },
        );

        return $dompdf->output();
    }
}
