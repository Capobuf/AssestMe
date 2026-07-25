<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\CoverTitleMode;

final class ResolveReportTitle
{
    public function __invoke(
        ?string $assessmentOverride,
        string $defaultPattern,
        string $clientName,
        CoverTitleMode $mode,
    ): string {
        $hasOverride = filled($assessmentOverride);
        $configured = trim($hasOverride ? (string) $assessmentOverride : $defaultPattern);
        $baseTitle = $hasOverride ? $configured : str_replace('{client}', '', $configured);
        $baseTitle = trim((string) preg_replace('/(?:\s*[—-]\s*)+$/u', '', $baseTitle));
        $baseTitle = $baseTitle === '' ? __('assestme.reports.document.default_title') : $baseTitle;

        if ($mode === CoverTitleMode::Separate) {
            return $baseTitle;
        }

        if (str_contains(mb_strtolower($baseTitle), mb_strtolower($clientName))) {
            return $baseTitle;
        }

        return $baseTitle.' — '.$clientName;
    }
}
