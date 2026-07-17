<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Data\Reports\AssessmentReportData;
use App\Enums\AssessmentStatus;
use App\Enums\GeneratedReportFormat;
use App\Models\Assessment;
use App\Models\GeneratedReport;
use App\Services\Reporting\DomPdfCanvasDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

final class GenerateAssessmentPdf
{
    private const MAX_FILE_SIZE = 50 * 1024 * 1024;

    private const MAX_SECONDS = 30.0;

    public function __construct(private readonly BuildAssessmentSnapshot $buildSnapshot) {}

    public function __invoke(Assessment $assessment): GeneratedReport
    {
        return Cache::lock("assessment:{$assessment->getKey()}:save", 10)->block(5, function () use ($assessment): GeneratedReport {
            $startedAt = hrtime(true);
            $snapshot = ($this->buildSnapshot)($assessment->fresh());
            $version = $this->nextVersion($assessment);
            $fileName = $this->fileName($snapshot, $version);
            $contents = $this->render($snapshot);
            $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;

            if ($elapsedSeconds > self::MAX_SECONDS) {
                throw new RuntimeException(__('assestme.reports.errors.time_limit'));
            }
            if (strlen($contents) > self::MAX_FILE_SIZE) {
                throw new RuntimeException(__('assestme.reports.errors.file_limit'));
            }
            if (! str_starts_with($contents, '%PDF-')) {
                throw new RuntimeException(__('assestme.reports.errors.invalid_pdf'));
            }

            $storagePath = 'reports/'.$assessment->getKey().'/'.Str::uuid().'.pdf';
            $disk = Storage::disk('local');
            if (! $disk->put($storagePath, $contents)) {
                throw new RuntimeException(__('assestme.reports.errors.storage'));
            }

            try {
                $payload = $snapshot->toArray();
                $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                return DB::transaction(function () use (
                    $assessment,
                    $snapshot,
                    $version,
                    $storagePath,
                    $fileName,
                    $contents,
                    $payload,
                    $payloadJson,
                ): GeneratedReport {
                    $lockedAssessment = Assessment::query()->lockForUpdate()->findOrFail($assessment->getKey());

                    $report = GeneratedReport::query()->create([
                        'assessment_id' => $lockedAssessment->getKey(),
                        'format' => GeneratedReportFormat::Pdf,
                        'version' => $version,
                        'file_path' => $storagePath,
                        'file_name' => $fileName,
                        'file_size_bytes' => strlen($contents),
                        'file_sha256' => hash('sha256', $contents),
                        'payload_sha256' => hash('sha256', $payloadJson),
                        'payload_snapshot' => $payload,
                        'settings_snapshot' => $snapshot->settingsSnapshot,
                        'generated_at' => $snapshot->generatedAt,
                    ]);

                    if ($snapshot->setting('freeze_after_generation') === true
                        && $lockedAssessment->status === AssessmentStatus::Draft) {
                        // The report row and lifecycle freeze share one transaction; file storage is compensated on failure.
                        $lockedAssessment->forceFill([
                            'status' => AssessmentStatus::Completed,
                            'completed_at' => now(),
                            'lock_version' => $lockedAssessment->lock_version + 1,
                        ])->save();
                    }

                    return $report->refresh();
                });
            } catch (Throwable $exception) {
                $disk->delete($storagePath);

                throw $exception;
            }
        });
    }

    private function nextVersion(Assessment $assessment): int
    {
        $current = GeneratedReport::query()
            ->where('assessment_id', $assessment->getKey())
            ->where('format', GeneratedReportFormat::Pdf)
            ->max('version');

        return is_numeric($current) ? (int) $current + 1 : 1;
    }

    private function fileName(AssessmentReportData $snapshot, int $version): string
    {
        $client = Str::slug($snapshot->clientName);
        $client = $client === '' ? 'cliente' : $client;

        return sprintf('AssestMe_%s_%s_v%02d.pdf', $client, $snapshot->assessmentDate, $version);
    }

    private function render(AssessmentReportData $snapshot): string
    {
        $temporaryPath = tempnam(storage_path('framework'), 'assestme-pdf-');
        if ($temporaryPath === false) {
            throw new RuntimeException(__('assestme.reports.errors.temporary_file'));
        }

        try {
            $driver = new DomPdfCanvasDriver([
                'is_remote_enabled' => false,
                'chroot' => storage_path('app/private'),
                'page_chrome' => [
                    'header' => (string) ($snapshot->setting('header_text') ?: $snapshot->title),
                    'footer' => (string) ($snapshot->setting('footer_text') ?: $snapshot->setting('business_name') ?: ''),
                    'confidentiality' => (string) $snapshot->setting('confidentiality_label'),
                    'show_cover' => $snapshot->setting('cover') === true,
                    'show_header_footer' => $snapshot->setting('repeated_header_footer') === true,
                    'show_page_numbers' => $snapshot->setting('page_numbers') === true,
                ],
            ]);

            Pdf::view('reports.assessment', ['report' => $snapshot])
                ->setDriver($driver)
                ->format(Format::A4)
                ->margins(top: 24, right: 16, bottom: 22, left: 16)
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
