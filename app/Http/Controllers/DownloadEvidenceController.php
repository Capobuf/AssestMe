<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EvidenceType;
use App\Models\Evidence;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DownloadEvidenceController
{
    public function __invoke(Evidence $evidence): BinaryFileResponse
    {
        Gate::authorize('view', $evidence);
        abort_if($evidence->type !== EvidenceType::File || $evidence->file_path === null, Response::HTTP_NOT_FOUND);
        abort_unless(Storage::disk('local')->exists($evidence->file_path), Response::HTTP_NOT_FOUND);

        $preview = in_array($evidence->mime_type, ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'], true);
        $disposition = $preview ? 'inline' : 'attachment';
        $filename = $evidence->original_filename ?? 'evidence';

        return response()->file(Storage::disk('local')->path($evidence->file_path), [
            'Content-Type' => $evidence->mime_type ?? 'application/octet-stream',
            'Content-Disposition' => $disposition.'; filename="'.addslashes($filename).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
