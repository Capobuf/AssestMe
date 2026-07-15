<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GeneratedReport;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DownloadGeneratedReportController extends Controller
{
    public function __invoke(GeneratedReport $generatedReport): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($generatedReport->file_path)) {
            abort(409, __('assestme.reports.errors.generated_file_missing'));
        }

        $path = $disk->path($generatedReport->file_path);
        $hash = hash_file('sha256', $path);
        if ($hash === false || ! hash_equals($generatedReport->file_sha256, $hash)) {
            abort(409, __('assestme.reports.errors.generated_file_corrupt'));
        }

        return response()->download($path, $generatedReport->file_name, [
            'Content-Type' => $generatedReport->format->value === 'pdf'
                ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }
}
