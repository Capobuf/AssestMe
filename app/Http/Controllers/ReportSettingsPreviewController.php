<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Reporting\ReportPreviewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class ReportSettingsPreviewController extends Controller
{
    public function __invoke(Request $request, string $token, ReportPreviewFactory $factory): View
    {
        $sessionBinding = $request->session()->get('report_preview_binding');
        abort_unless(is_string($sessionBinding) && $sessionBinding !== '', 404);

        $key = sprintf(
            'report-preview:%d:%s:%s',
            (int) $request->user()->getAuthIdentifier(),
            hash('sha256', $sessionBinding),
            $token,
        );
        $settings = Cache::get($key);
        abort_unless(is_array($settings), 404);

        return view('reports.assessment', ['report' => $factory->make($settings)]);
    }
}
