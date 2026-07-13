<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reports\GenerateAssessmentProofPdf;
use App\Models\Assessment;
use Spatie\LaravelPdf\PdfBuilder;

final class DownloadAssessmentProofPdfController extends Controller
{
    public function __invoke(
        Assessment $assessment,
        GenerateAssessmentProofPdf $generateAssessmentProofPdf,
    ): PdfBuilder {
        return $generateAssessmentProofPdf($assessment);
    }
}
