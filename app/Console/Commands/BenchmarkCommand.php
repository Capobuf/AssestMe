<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Assessments\SaveAssessmentWorkspace;
use App\Actions\Reports\GenerateAssessmentPdf;
use App\Actions\Reports\GenerateAssessmentWorkbook;
use App\Data\Assessments\WorkspaceSaveData;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingTemplate;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $createdAdministrator = false;
        $administrator = User::query()->first();

        if (! $administrator) {
            $administrator = User::query()->create([
                'name' => 'Benchmark Administrator',
                'email' => 'benchmark@assestme.local',
                'password' => Str::password(24, symbols: true),
            ]);
            $createdAdministrator = true;
        }

        $assessment = Assessment::factory()->create(['title' => 'Benchmark definitivo']);
        $template = FindingTemplate::query()
            ->where('is_enabled', true)
            ->where('default_scope_type', 'organization')
            ->firstOrFail();
        foreach (range(1, $count) as $number) {
            $finding = app(CopyTemplateToAssessment::class)->handle($assessment, $template);
            $finding->update(['title' => sprintf('Benchmark finding %03d', $number)]);
        }

        try {
            [$renderSeconds, $response] = $this->measure(function () use ($administrator, $assessment) {
                Auth::login($administrator);
                $request = Request::create(route('filament.admin.resources.assessments.workspace', $assessment), 'GET');
                $response = app(Kernel::class)->handle($request);
                app(Kernel::class)->terminate($request, $response);

                return $response;
            });

            $payload = $this->workspacePayload($assessment->fresh('findings'));
            [$saveSeconds] = $this->measure(function () use ($assessment, $payload): void {
                app(SaveAssessmentWorkspace::class)(
                    $assessment,
                    $this->saveData($assessment, $payload),
                );
            });

            $reorderedPayload = $this->workspacePayload($assessment->fresh('findings'));
            $reorderedPayload['findings'] = array_reverse($reorderedPayload['findings']);
            [$reorderSeconds] = $this->measure(function () use ($assessment, $reorderedPayload): void {
                app(SaveAssessmentWorkspace::class)(
                    $assessment,
                    $this->saveData($assessment->fresh(), $reorderedPayload),
                );
            });

            [$pdfSeconds] = $this->measure(function () use ($assessment): void {
                app(GenerateAssessmentPdf::class)->handle($assessment->fresh());
            });
            [$xlsxSeconds] = $this->measure(function () use ($assessment): void {
                app(GenerateAssessmentWorkbook::class)->handle($assessment->fresh(), false);
            });

            $metrics = [
                'findings' => $count,
                'workspace_status' => $response->getStatusCode(),
                'workspace_first_render_seconds' => round($renderSeconds, 4),
                'workspace_response_bytes' => strlen((string) $response->getContent()),
                'explicit_save_seconds' => round($saveSeconds, 4),
                'reorder_seconds' => round($reorderSeconds, 4),
                'pdf_seconds' => round($pdfSeconds, 4),
                'xlsx_seconds' => round($xlsxSeconds, 4),
            ];
            $checks = [
                'workspace_status' => $metrics['workspace_status'] === 200,
                'workspace_first_render' => $renderSeconds <= 2.5,
                'workspace_response_size' => $metrics['workspace_response_bytes'] <= 5 * 1024 * 1024,
                'explicit_save' => $saveSeconds <= 2.0,
                'reorder' => $reorderSeconds <= 1.5,
                'pdf' => $pdfSeconds <= 30.0,
                'xlsx' => $xlsxSeconds <= 10.0,
            ];
            $result = ['ok' => ! in_array(false, $checks, true), 'metrics' => $metrics, 'checks' => $checks];
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $result['ok'] ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception::class.': '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            Auth::logout();
            Storage::disk('local')->deleteDirectory("reports/{$assessment->getKey()}");
            $assessment->forceDelete();

            if ($createdAdministrator) {
                $administrator->delete();
            }
        }
    }

    /** @return array{0: float, 1: mixed} */
    private function measure(callable $operation): array
    {
        $start = hrtime(true);
        $result = $operation();

        return [(hrtime(true) - $start) / 1_000_000_000, $result];
    }

    /** @return array{assessment: array{title: string, assessment_date: string}, findings: list<array<string, mixed>>} */
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
            'findings' => $assessment->findings->map(fn (Finding $finding): array => [
                'id' => $finding->getKey(),
                '_temporary_uuid' => (string) Str::uuid(),
                'title' => $finding->title,
                'problem' => $finding->problem,
                'entrepreneur_notes' => $finding->entrepreneur_notes,
                'status' => $finding->status->value,
                'include_in_report' => $finding->include_in_report,
            ])->values()->all(),
        ];
    }

    /** @param array{assessment: array{title: string, assessment_date: string}, findings: list<array<string, mixed>>} $payload */
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
}
