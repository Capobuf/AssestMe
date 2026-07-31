<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\Diagnostics\DiagnosticCheckData;
use App\Data\Diagnostics\DiagnosticCheckStatus;
use App\Services\Diagnostics\ApplicationDiagnostics;
use Illuminate\Console\Command;

final class DiagnoseCommand extends Command
{
    protected $signature = 'assestme:diagnose
                            {--json : Emit a machine-readable JSON result}';

    protected $description = 'Verify the AssestMe runtime, database, scheduler, backup, and production configuration';

    public function handle(ApplicationDiagnostics $diagnostics): int
    {
        $report = $diagnostics->run();

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode(
                $report->toArray(),
                JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));

            return $report->ok() ? self::SUCCESS : self::FAILURE;
        }

        $this->components->info((string) __('assestme.diagnostics.title'));
        $this->line((string) __('assestme.diagnostics.database', [
            'driver' => $report->driver,
            'product' => $report->product ?? '—',
            'version' => $report->serverVersion ?? '—',
        ]));

        $rows = [];
        foreach ($report->checks as $check) {
            $rows[] = [
                (string) __("assestme.diagnostics.checks.{$check->key}"),
                (string) __("assestme.diagnostics.status.{$check->status->value}"),
                $check->detail,
            ];
        }

        $this->table([
            (string) __('assestme.diagnostics.columns.check'),
            (string) __('assestme.diagnostics.columns.status'),
            (string) __('assestme.diagnostics.columns.detail'),
        ], $rows);

        if ($report->ok()) {
            $this->components->info((string) __('assestme.diagnostics.passed'));
        } else {
            $this->components->error((string) __('assestme.diagnostics.failed'));
        }

        $warnings = array_filter(
            $report->checks,
            static fn (DiagnosticCheckData $check): bool => $check->status === DiagnosticCheckStatus::Warning,
        );
        if ($warnings !== []) {
            $this->components->warn((string) __('assestme.diagnostics.warnings', [
                'count' => count($warnings),
            ]));
        }

        return $report->ok() ? self::SUCCESS : self::FAILURE;
    }
}
