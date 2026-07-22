<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Spatie\LaravelPdf\Drivers\DomPdfDriver;
use Spatie\LaravelPdf\PdfOptions;

final class DomPdfCanvasDriver extends DomPdfDriver
{
    private const POINTS_PER_MILLIMETRE = 72 / 25.4;

    private const CONTENT_LEFT_MARGIN_MM = 20.0;

    private const CONTENT_RIGHT_MARGIN_MM = 17.0;

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
        $footer = (string) ($this->config['page_chrome']['footer'] ?? '');
        $showCover = (bool) ($this->config['page_chrome']['show_cover'] ?? false);
        $showHeaderFooter = (bool) ($this->config['page_chrome']['show_header_footer'] ?? true);
        $showPageNumbers = (bool) ($this->config['page_chrome']['show_page_numbers'] ?? true);

        $dompdf->getCanvas()->page_script(
            static function (
                int $pageNumber,
                int $pageCount,
                Canvas $canvas,
                FontMetrics $fontMetrics,
            ) use ($header, $footer, $showCover, $showHeaderFooter, $showPageNumbers): void {
                if ($showCover && $pageNumber === 1) {
                    return;
                }

                $contentPage = $showCover ? $pageNumber - 1 : $pageNumber;
                $contentPages = $showCover ? $pageCount - 1 : $pageCount;
                $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
                $fontSize = 9.0;
                $left = self::CONTENT_LEFT_MARGIN_MM * self::POINTS_PER_MILLIMETRE;
                $right = $canvas->get_width() - (self::CONTENT_RIGHT_MARGIN_MM * self::POINTS_PER_MILLIMETRE);
                $headerTextY = 3.0 * self::POINTS_PER_MILLIMETRE;
                $headerLineY = 10.0 * self::POINTS_PER_MILLIMETRE;
                $footerLineY = $canvas->get_height() - (20.0 * self::POINTS_PER_MILLIMETRE);
                $footerTextY = $canvas->get_height() - (14.0 * self::POINTS_PER_MILLIMETRE);
                $primaryTextColor = [0.067, 0.067, 0.067];
                $secondaryTextColor = [0.4, 0.4, 0.4];
                $lineColor = [0.843, 0.843, 0.824];
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
                    $canvas->text($left, $headerTextY, $fitText($header, $right - $left), $font, $fontSize, $primaryTextColor);
                    $canvas->line($left, $headerLineY, $right, $headerLineY, $lineColor, 0.5);
                }

                $rightText = $showPageNumbers ? $pageText : '';
                $rightText = $fitText($rightText, ($right - $left) * 0.55);
                $rightTextWidth = $fontMetrics->getTextWidth($rightText, $font, $fontSize);
                $footerWidth = max(0.0, ($right - $left) - $rightTextWidth - 12.0);

                if (($showHeaderFooter && $footer !== '') || $rightText !== '') {
                    $canvas->line($left, $footerLineY, $right, $footerLineY, $lineColor, 0.5);
                }
                if ($showHeaderFooter && $footer !== '') {
                    $canvas->text($left, $footerTextY, $fitText($footer, $footerWidth), $font, $fontSize, $secondaryTextColor);
                }
                if ($rightText !== '') {
                    $canvas->text(
                        $right - $rightTextWidth,
                        $footerTextY,
                        $rightText,
                        $font,
                        $fontSize,
                        $secondaryTextColor,
                    );
                }
            },
        );

        return $dompdf->output();
    }
}
