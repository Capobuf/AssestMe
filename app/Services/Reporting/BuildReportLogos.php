<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Data\Reports\ReportLogoData;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;

final class BuildReportLogos
{
    /**
     * @return list<ReportLogoData>
     */
    public function __invoke(
        string $branding,
        ?string $consultantLogoPath,
        ?string $clientLogoPath,
    ): array {
        $paths = match ($branding) {
            'consultant' => ['consultant' => $consultantLogoPath],
            'client' => ['client' => $clientLogoPath],
            'both' => [
                'consultant' => $consultantLogoPath,
                'client' => $clientLogoPath,
            ],
            default => throw new LogicException('The configured report branding mode is invalid.'),
        };

        $logos = [];
        foreach ($paths as $owner => $path) {
            if ($path === null) {
                continue;
            }

            $disk = Storage::disk('local');
            if (! $disk->exists($path)) {
                throw ValidationException::withMessages(['report' => __('assestme.reports.errors.logo_missing')]);
            }

            $contents = $disk->get($path);
            if (strlen($contents) > 5 * 1024 * 1024) {
                throw ValidationException::withMessages(['report' => __('assestme.reports.errors.logo_too_large')]);
            }

            $mimeType = $disk->mimeType($path);
            if (! is_string($mimeType) || ! in_array($mimeType, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                throw ValidationException::withMessages(['report' => __('assestme.reports.errors.logo_invalid')]);
            }

            $imageInfo = getimagesizefromstring($contents);
            if ($imageInfo === false || $imageInfo['mime'] !== $mimeType) {
                throw ValidationException::withMessages(['report' => __('assestme.reports.errors.logo_invalid')]);
            }

            $logos[] = new ReportLogoData(
                owner: $owner,
                path: $path,
                mimeType: $mimeType,
                sha256: hash('sha256', $contents),
                dataUri: 'data:'.$mimeType.';base64,'.base64_encode($contents),
            );
        }

        return $logos;
    }
}
