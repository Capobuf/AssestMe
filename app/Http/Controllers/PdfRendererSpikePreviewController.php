<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Reporting\PdfRendererSpikeReportFactory;
use Illuminate\Contracts\View\View;

final class PdfRendererSpikePreviewController extends Controller
{
    public function __invoke(PdfRendererSpikeReportFactory $factory): View
    {
        return view('reports.pdf-renderer-spike.assessment', [
            'report' => $factory->make(),
        ]);
    }
}
