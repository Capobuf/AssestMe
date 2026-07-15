<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\Assessment;
use App\Services\Reporting\DomPdfCanvasDriver;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

final class GenerateAssessmentProofPdf
{
    public function __invoke(Assessment $assessment): PdfBuilder
    {
        $assessment->load(['findings' => fn ($query) => $query->with(['priorityLevel', 'recommendedSolution'])->orderBy('sort_order')]);

        return Pdf::view('reports.milestone-zero-proof', [
            'assessment' => $assessment,
        ])
            ->setDriver(app(DomPdfCanvasDriver::class))
            ->format(Format::A4)
            ->margins(top: 18, right: 14, bottom: 18, left: 14)
            ->download("AssestMe_proof_assessment_{$assessment->getKey()}.pdf");
    }
}
