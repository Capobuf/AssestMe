<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Data\Reports\AssessmentReportData;
use App\Data\Reports\PdfRendererSpikeResult;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Spatie\LaravelPdf\Facades\Pdf;

final class GeneratePdfRendererSpike
{
    public function __construct(private readonly Filesystem $files) {}

    public function __invoke(AssessmentReportData $report): PdfRendererSpikeResult
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('The PDF renderer spike is available only in local and testing environments.');
        }

        $directory = storage_path('app/qa-artifacts/d059-c940473');
        $path = $directory.'/weasyprint-renderer-proof.pdf';
        $this->files->ensureDirectoryExists($directory);

        $startedAt = hrtime(true);
        Pdf::view('reports.pdf-renderer-spike.assessment', ['report' => $report])
            ->driver('weasyprint')
            ->save($path);
        $generationSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;

        $size = filesize($path);
        $sha256 = hash_file('sha256', $path);
        if (! is_int($size) || ! is_string($sha256)) {
            throw new RuntimeException('The PDF renderer spike artifact could not be measured.');
        }

        $result = new PdfRendererSpikeResult(
            path: $path,
            generationSeconds: $generationSeconds,
            sizeBytes: $size,
            sha256: $sha256,
        );

        $this->files->put(
            $directory.'/metrics.json',
            json_encode([
                'renderer' => 'weasyprint',
                'path' => $result->path,
                'generation_seconds' => round($result->generationSeconds, 4),
                'size_bytes' => $result->sizeBytes,
                'sha256' => $result->sha256,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );

        return $result;
    }
}
