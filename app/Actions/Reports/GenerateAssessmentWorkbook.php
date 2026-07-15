<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Data\Reports\AssessmentReportData;
use App\Enums\GeneratedReportFormat;
use App\Models\Assessment;
use App\Models\GeneratedReport;
use App\Services\Export\AssessmentWorkbookBuilder;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

final class GenerateAssessmentWorkbook
{
    private const MAX_SECONDS = 10.0;

    public function __construct(
        private readonly BuildAssessmentSnapshot $buildSnapshot,
        private readonly AssessmentWorkbookBuilder $workbookBuilder,
        private readonly GeneralSettings $generalSettings,
    ) {}

    public function handle(Assessment $assessment, ?bool $includeExcludedFindings = null): GeneratedReport
    {
        return Cache::lock("assessment:{$assessment->getKey()}:save", 10)->block(5, function () use (
            $assessment,
            $includeExcludedFindings,
        ): GeneratedReport {
            $startedAt = hrtime(true);
            $includeExcludedFindings ??= $this->generalSettings->report_excluded_findings_in_xlsx;
            $snapshot = $this->buildSnapshot->handle(
                assessment: $assessment->fresh(),
                includeExcludedFindings: $includeExcludedFindings,
            );
            $version = $this->nextVersion($assessment);
            $fileName = $this->fileName($snapshot, $version);
            $contents = $this->render($snapshot);
            $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;

            if ($elapsedSeconds > self::MAX_SECONDS) {
                throw new RuntimeException(__('assestme.reports.errors.xlsx_time_limit'));
            }
            if (! str_starts_with($contents, "PK\x03\x04")) {
                throw new RuntimeException(__('assestme.reports.errors.invalid_xlsx'));
            }

            $storagePath = 'reports/'.$assessment->getKey().'/'.Str::uuid().'.xlsx';
            $disk = Storage::disk('local');
            if (! $disk->put($storagePath, $contents)) {
                throw new RuntimeException(__('assestme.reports.errors.xlsx_storage'));
            }

            try {
                $settingsSnapshot = [
                    ...$snapshot->settingsSnapshot,
                    'xlsx_include_excluded_findings' => $includeExcludedFindings,
                ];
                $payload = [
                    ...$snapshot->toArray(),
                    'export_options' => [
                        'include_excluded_findings' => $includeExcludedFindings,
                    ],
                ];
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
                    $settingsSnapshot,
                ): GeneratedReport {
                    $lockedAssessment = Assessment::query()->lockForUpdate()->findOrFail($assessment->getKey());

                    return GeneratedReport::query()->create([
                        'assessment_id' => $lockedAssessment->getKey(),
                        'format' => GeneratedReportFormat::Xlsx,
                        'version' => $version,
                        'file_path' => $storagePath,
                        'file_name' => $fileName,
                        'file_size_bytes' => strlen($contents),
                        'file_sha256' => hash('sha256', $contents),
                        'payload_sha256' => hash('sha256', $payloadJson),
                        'payload_snapshot' => $payload,
                        'settings_snapshot' => $settingsSnapshot,
                        'generated_at' => $snapshot->generatedAt,
                    ])->refresh();
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
            ->where('format', GeneratedReportFormat::Xlsx)
            ->max('version');

        return is_numeric($current) ? (int) $current + 1 : 1;
    }

    private function fileName(AssessmentReportData $snapshot, int $version): string
    {
        $client = Str::slug($snapshot->clientName);
        $client = $client === '' ? 'cliente' : $client;

        return sprintf('AssestMe_%s_%s_v%02d.xlsx', $client, $snapshot->assessmentDate, $version);
    }

    private function render(AssessmentReportData $snapshot): string
    {
        $temporaryPath = tempnam(storage_path('framework'), 'assestme-xlsx-');
        if ($temporaryPath === false) {
            throw new RuntimeException(__('assestme.reports.errors.xlsx_temporary_file'));
        }

        $spreadsheet = null;
        try {
            $spreadsheet = $this->workbookBuilder->build($snapshot);
            (new Xlsx($spreadsheet))->save($temporaryPath);
            $spreadsheet->disconnectWorksheets();
            $spreadsheet = null;

            if (IOFactory::identify($temporaryPath) !== 'Xlsx') {
                throw new RuntimeException(__('assestme.reports.errors.invalid_xlsx'));
            }

            $contents = file_get_contents($temporaryPath);
            if ($contents === false) {
                throw new RuntimeException(__('assestme.reports.errors.xlsx_temporary_file'));
            }

            return $contents;
        } finally {
            $spreadsheet?->disconnectWorksheets();
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
