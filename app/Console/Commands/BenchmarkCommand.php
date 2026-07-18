<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Assessments\ReorderFindings;
use App\Actions\Assessments\SaveAssessmentWorkspace;
use App\Actions\Assessments\SaveFindingDetails;
use App\Actions\Reports\GenerateAssessmentPdf;
use App\Actions\Reports\GenerateAssessmentWorkbook;
use App\Data\Assessments\FindingSaveData;
use App\Data\Assessments\WorkspaceSaveData;
use App\Enums\FindingStatus;
use App\Filament\Resources\Assessments\Schemas\FindingEditorSchema;
use App\Models\Assessment;
use App\Models\FindingTemplate;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class BenchmarkCommand extends Command
{
    protected $signature = 'assestme:benchmark {--findings=50 : Number of findings from 1 to 100}';

    protected $description = 'Run the isolated definitive workspace, PDF, and XLSX performance benchmark';

    public function handle(): int
    {
        $count = filter_var($this->option('findings'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);

        if ($count === false) {
            $this->error('The --findings option must be an integer between 1 and 100.');

            return self::INVALID;
        }

        $environment = $this->activateIsolatedEnvironment();

        try {
            $this->initializeIsolatedDatabase();

            $administrator = User::query()->create([
                'name' => 'Benchmark Administrator',
                'email' => 'benchmark@assestme.local',
                'password' => Str::password(24, symbols: true),
            ]);
            $assessment = Assessment::factory()->create(['title' => 'Benchmark definitivo']);
            $template = FindingTemplate::query()
                ->where('is_enabled', true)
                ->where('default_scope_type', 'organization')
                ->firstOrFail();
            foreach (range(1, $count) as $number) {
                $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
                $finding->update(['title' => sprintf('Benchmark finding %03d', $number)]);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            [$renderSeconds, $response] = $this->measure(function () use ($administrator, $assessment) {
                Auth::login($administrator);
                $request = Request::create(route('filament.admin.resources.assessments.workspace', $assessment), 'GET');
                $response = app(Kernel::class)->handle($request);
                app(Kernel::class)->terminate($request, $response);

                return $response;
            });
            $listQueryCount = count(DB::getQueryLog());
            DB::disableQueryLog();

            $payload = $this->workspacePayload($assessment->fresh('findings'));
            [$assessmentSaveSeconds] = $this->measure(function () use ($assessment, $payload): void {
                app(SaveAssessmentWorkspace::class)(
                    $assessment,
                    $this->saveData($assessment, $payload),
                );
            });

            $selectedFinding = $assessment->findings()->firstOrFail();
            [$inspectorOpenSeconds, $findingPayload] = $this->measure(
                fn (): array => FindingEditorSchema::data($selectedFinding->fresh()),
            );
            $findingPayload['title'] = $selectedFinding->title.' aggiornato';
            [$findingSaveSeconds, $saveResult] = $this->measure(function () use ($assessment, $selectedFinding, $findingPayload) {
                return app(SaveFindingDetails::class)(
                    $selectedFinding,
                    $this->findingSaveData($assessment->fresh(), $findingPayload),
                );
            });

            $inlinePayload = FindingEditorSchema::data($saveResult->finding);
            $inlinePayload['status'] = FindingStatus::Planned->value;
            [$inlineSaveSeconds] = $this->measure(function () use ($assessment, $saveResult, $inlinePayload): void {
                app(SaveFindingDetails::class)(
                    $saveResult->finding,
                    $this->findingSaveData($assessment->fresh(), $inlinePayload),
                );
            });

            $reorderedIds = $assessment->findings()->pluck('id')->reverse()->values()->all();
            [$reorderSeconds] = $this->measure(function () use ($assessment, $reorderedIds): void {
                app(ReorderFindings::class)($assessment->fresh(), $reorderedIds);
            });

            [$pdfSeconds] = $this->measure(function () use ($assessment): void {
                app(GenerateAssessmentPdf::class)($assessment->fresh());
            });
            [$xlsxSeconds] = $this->measure(function () use ($assessment): void {
                app(GenerateAssessmentWorkbook::class)($assessment->fresh(), false);
            });

            $metrics = [
                'findings' => $count,
                'workspace_status' => $response->getStatusCode(),
                'workspace_first_render_seconds' => round($renderSeconds, 4),
                'workspace_response_bytes' => strlen((string) $response->getContent()),
                'workspace_list_queries' => $listQueryCount,
                'inspector_open_seconds' => round($inspectorOpenSeconds, 4),
                'single_finding_save_seconds' => round($findingSaveSeconds, 4),
                'inline_status_save_seconds' => round($inlineSaveSeconds, 4),
                'assessment_save_seconds' => round($assessmentSaveSeconds, 4),
                'reorder_seconds' => round($reorderSeconds, 4),
                'pdf_seconds' => round($pdfSeconds, 4),
                'xlsx_seconds' => round($xlsxSeconds, 4),
            ];
            $checks = [
                'workspace_status' => $metrics['workspace_status'] === 200,
                'workspace_first_render' => $renderSeconds <= 2.5,
                'workspace_response_size' => $metrics['workspace_response_bytes'] <= 5 * 1024 * 1024,
                'workspace_list_queries' => $listQueryCount <= 30,
                'inspector_open' => $inspectorOpenSeconds <= 1.0,
                'single_finding_save' => $findingSaveSeconds <= 2.0,
                'inline_status_save' => $inlineSaveSeconds <= 2.0,
                'assessment_save' => $assessmentSaveSeconds <= 2.0,
                'reorder' => $reorderSeconds <= 1.5,
                'pdf' => $pdfSeconds <= 30.0,
                'xlsx' => $xlsxSeconds <= 10.0,
            ];
            $result = [
                'ok' => ! in_array(false, $checks, true),
                'isolated' => true,
                'metrics' => $metrics,
                'checks' => $checks,
            ];
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception::class.': '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            Auth::logout();
            $this->restoreEnvironment($environment);
        }
    }

    /**
     * @return array{
     *     root: string,
     *     storage_path: string,
     *     database_default: string,
     *     database: string,
     *     local_disk_root: string,
     *     backup_root: string,
     *     backup_private_root: string,
     *     trash_root: string,
     *     cache_default: string,
     *     session_driver: string
     * }
     */
    private function activateIsolatedEnvironment(): array
    {
        $root = sys_get_temp_dir().'/assestme-benchmark-'.bin2hex(random_bytes(12));
        $storage = $root.'/storage';
        $database = $root.'/database.sqlite';
        $filesystem = new Filesystem;
        $directories = [
            $storage.'/app/private',
            $storage.'/framework/cache/data',
            $storage.'/framework/sessions',
            $storage.'/framework/views',
            $storage.'/logs',
            $storage.'/backups',
        ];

        foreach ($directories as $directory) {
            $filesystem->ensureDirectoryExists($directory);
        }

        if (! touch($database)) {
            $filesystem->deleteDirectory($root);

            throw new RuntimeException('The isolated benchmark database could not be created.');
        }

        $environment = [
            'root' => $root,
            'storage_path' => $this->laravel->storagePath(),
            'database_default' => (string) config('database.default'),
            'database' => (string) config('database.connections.sqlite.database'),
            'local_disk_root' => (string) config('filesystems.disks.local.root'),
            'backup_root' => (string) config('assestme.backup.root'),
            'backup_private_root' => (string) config('assestme.backup.private_storage_path'),
            'trash_root' => (string) config('assestme.deletion.trash_root'),
            'cache_default' => (string) config('cache.default'),
            'session_driver' => (string) config('session.driver'),
        ];

        $this->laravel->useStoragePath($storage);
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $database);
        config()->set('filesystems.disks.local.root', $storage.'/app/private');
        config()->set('assestme.backup.root', $storage.'/backups');
        config()->set('assestme.backup.private_storage_path', $storage.'/app/private');
        config()->set('assestme.deletion.trash_root', $storage.'/app/private/.trash');
        config()->set('cache.default', 'array');
        config()->set('session.driver', 'array');
        DB::purge('sqlite');
        Storage::forgetDisk('local');

        return $environment;
    }

    private function initializeIsolatedDatabase(): void
    {
        $exitCode = Artisan::call('migrate:fresh', [
            '--database' => 'sqlite',
            '--seed' => true,
            '--force' => true,
        ]);

        if ($exitCode !== self::SUCCESS) {
            throw new RuntimeException('The isolated benchmark database could not be initialized.');
        }
    }

    /**
     * @param array{
     *     root: string,
     *     storage_path: string,
     *     database_default: string,
     *     database: string,
     *     local_disk_root: string,
     *     backup_root: string,
     *     backup_private_root: string,
     *     trash_root: string,
     *     cache_default: string,
     *     session_driver: string
     * } $environment
     */
    private function restoreEnvironment(array $environment): void
    {
        DB::purge('sqlite');
        Storage::forgetDisk('local');
        $this->laravel->useStoragePath($environment['storage_path']);
        config()->set('database.default', $environment['database_default']);
        config()->set('database.connections.sqlite.database', $environment['database']);
        config()->set('filesystems.disks.local.root', $environment['local_disk_root']);
        config()->set('assestme.backup.root', $environment['backup_root']);
        config()->set('assestme.backup.private_storage_path', $environment['backup_private_root']);
        config()->set('assestme.deletion.trash_root', $environment['trash_root']);
        config()->set('cache.default', $environment['cache_default']);
        config()->set('session.driver', $environment['session_driver']);
        (new Filesystem)->deleteDirectory($environment['root']);
    }

    /** @return array{0: float, 1: mixed} */
    private function measure(callable $operation): array
    {
        $start = hrtime(true);
        $result = $operation();

        return [(hrtime(true) - $start) / 1_000_000_000, $result];
    }

    /** @return array{assessment: array<string, mixed>} */
    private function workspacePayload(Assessment $assessment): array
    {
        return [
            'assessment' => [
                'title' => $assessment->title,
                'assessment_date' => $assessment->assessment_date->format('Y-m-d'),
                'report_title_override' => $assessment->report_title_override,
                'scope_type' => $assessment->scope_type->value,
                'scope_description' => $assessment->scope_description,
                'introduction' => $assessment->introduction,
                'executive_summary' => $assessment->executive_summary,
                'methodology_notes' => $assessment->methodology_notes,
                'site_ids' => $assessment->sites()->pluck('sites.id')->all(),
            ],
        ];
    }

    /** @param array{assessment: array<string, mixed>} $payload */
    private function saveData(Assessment $assessment, array $payload): WorkspaceSaveData
    {
        return new WorkspaceSaveData(
            requestId: (string) Str::uuid(),
            expectedVersion: (int) $assessment->lock_version,
            tabId: (string) Str::uuid(),
            payload: $payload,
            payloadSha256: WorkspaceSaveData::hashPayload($payload),
        );
    }

    /** @param array<string, mixed> $payload */
    private function findingSaveData(Assessment $assessment, array $payload): FindingSaveData
    {
        return new FindingSaveData(
            requestId: (string) Str::uuid(),
            expectedVersion: (int) $assessment->lock_version,
            tabId: (string) Str::uuid(),
            payload: $payload,
            payloadSha256: FindingSaveData::hashPayload($payload),
        );
    }
}
