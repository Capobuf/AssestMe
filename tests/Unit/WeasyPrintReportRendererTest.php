<?php

declare(strict_types=1);

use App\Services\Reporting\WeasyPrintReportRenderer;
use App\Support\Reporting\ReportPreviewFactory;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->weasyPrintRendererRoot = sys_get_temp_dir()
        .DIRECTORY_SEPARATOR.'assestme-weasyprint-renderer-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->weasyPrintRendererRoot);

    $this->weasyPrintRendererBinary = $this->weasyPrintRendererRoot.DIRECTORY_SEPARATOR.'weasyprint';
    File::put($this->weasyPrintRendererBinary, <<<'SH'
#!/bin/sh
printf '%s\n' "$@" > "${0}.arguments"
for argument do
    output="$argument"
done
printf '%%PDF-1.4\n' > "$output"
SH);
    chmod($this->weasyPrintRendererBinary, 0700);
});

afterEach(function (): void {
    File::deleteDirectory($this->weasyPrintRendererRoot);
});

it('passes PDF snapshot image options to one per-generation WeasyPrint driver', function (
    bool $optimizeImages,
): void {
    config()->set('laravel-pdf.weasyprint', [
        'binary' => $this->weasyPrintRendererBinary,
        'timeout' => null,
    ]);
    $report = app(ReportPreviewFactory::class)->make([
        'pdf_image_dpi' => 180,
        'pdf_jpeg_quality' => 78,
        'pdf_optimize_images' => $optimizeImages,
    ]);

    $contents = app(WeasyPrintReportRenderer::class)->render($report);
    $arguments = file($this->weasyPrintRendererBinary.'.arguments', FILE_IGNORE_NEW_LINES);

    expect($contents)->toStartWith('%PDF-')
        ->and($arguments)->toBeArray()
        ->and($arguments)->toContain('--dpi', '180', '--jpeg-quality', '78');

    if ($optimizeImages) {
        expect($arguments)->toContain('--optimize-images');
    } else {
        expect($arguments)->not->toContain('--optimize-images');
    }

    expect(config('laravel-pdf.weasyprint'))->toBe([
        'binary' => $this->weasyPrintRendererBinary,
        'timeout' => null,
    ]);
})->with([
    'optimization enabled' => [true],
    'optimization disabled' => [false],
]);

it('rejects invalid PDF image options in the report snapshot', function (): void {
    $report = app(ReportPreviewFactory::class)->make([
        'pdf_image_dpi' => 71,
        'pdf_jpeg_quality' => 78,
        'pdf_optimize_images' => true,
    ]);

    expect(fn (): string => app(WeasyPrintReportRenderer::class)->render($report))
        ->toThrow(RuntimeException::class, __('assestme.reports.errors.pdf_generation_settings'));
});
