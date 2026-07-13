<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Reports\GenerateAssessmentProofXlsx;
use App\Models\Assessment;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DownloadAssessmentProofXlsxController
{
    public function __invoke(Assessment $assessment, GenerateAssessmentProofXlsx $generator): BinaryFileResponse
    {
        $directory = storage_path('app/private/generated');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.Str::uuid().'.xlsx';
        $generator->save($assessment, $path);

        return response()
            ->download($path, "AssestMe_proof_assessment_{$assessment->getKey()}.xlsx")
            ->deleteFileAfterSend(true);
    }
}
