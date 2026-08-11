<?php

declare(strict_types=1);

namespace App\Services\GoogleDrive;

use App\Enums\GeneratedReportFormat;
use Illuminate\Support\Str;

final class GoogleDriveName
{
    public function client(int $id, string $displayName): string
    {
        return sprintf('C-%06d - %s', $id, $this->readable($displayName, 'Azienda'));
    }

    public function assessment(int $id, string $date, string $title): string
    {
        return sprintf('A-%06d - %s - %s', $id, $date, $this->readable($title, 'Assessment'));
    }

    public function spreadsheet(int $assessmentId): string
    {
        return sprintf('Findings - A-%06d', $assessmentId);
    }

    public function evidence(
        int $findingId,
        int $evidenceId,
        string $title,
        ?string $originalFilename,
    ): string {
        $extension = is_string($originalFilename) ? pathinfo($originalFilename, PATHINFO_EXTENSION) : '';
        $suffix = $extension === '' ? '' : '.'.mb_strtolower($this->readable($extension, ''));

        return sprintf(
            'F-%06d - E-%06d - %s%s',
            $findingId,
            $evidenceId,
            $this->readable($title, 'Evidenza'),
            $suffix,
        );
    }

    public function generatedReport(int $id, GeneratedReportFormat $format, int $version): string
    {
        $label = $format === GeneratedReportFormat::Pdf ? 'Report' : 'Esportazione';

        return sprintf('R-%06d - %s v%d.%s', $id, $label, $version, $format->value);
    }

    private function readable(string $value, string $fallback): string
    {
        $value = preg_replace('/[\\x00-\\x1F\\x7F\\/\\\\?%*:|"<>]+/u', ' ', $value) ?? '';
        $value = preg_replace('/[\p{Z}\s]+/u', ' ', $value) ?? '';
        $value = trim($value, " .\t\n\r\0\x0B");
        $value = Str::limit($value, 120, '');

        return $value === '' ? $fallback : $value;
    }
}
