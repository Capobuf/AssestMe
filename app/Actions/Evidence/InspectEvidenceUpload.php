<?php

declare(strict_types=1);

namespace App\Actions\Evidence;

use App\Data\Evidence\PendingEvidenceFileData;
use App\Settings\GeneralSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use ZipArchive;

final class InspectEvidenceUpload
{
    /** @var array<string, list<string>> */
    private const MIME_BY_EXTENSION = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'log' => ['text/plain', 'text/x-log'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet'],
    ];

    public function __construct(private readonly GeneralSettings $settings) {}

    public function handle(
        UploadedFile $file,
        ?string $title = null,
        bool $includeInReport = true,
        ?string $caption = null,
        ?string $internalNotes = null,
    ): PendingEvidenceFileData {
        $extension = strtolower($file->getClientOriginalExtension());
        $size = $file->getSize();
        if (! is_int($size) || $size <= 0 || $size > $this->settings->max_evidence_file_mb * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => __('assestme.evidence.errors.file_size')]);
        }

        $mime = $this->normalizedMime($file, $extension);
        if (! is_string($mime) || ! isset(self::MIME_BY_EXTENSION[$extension]) || ! in_array($mime, self::MIME_BY_EXTENSION[$extension], true)) {
            throw ValidationException::withMessages(['file' => __('assestme.evidence.errors.unsupported_type')]);
        }

        if (str_starts_with($mime, 'image/')) {
            $dimensions = getimagesize($file->getRealPath());
            if ($dimensions === false || $dimensions[0] > 12000 || $dimensions[1] > 12000) {
                throw ValidationException::withMessages(['file' => __('assestme.evidence.errors.image_dimensions')]);
            }
        }

        $sha256 = hash_file('sha256', $file->getRealPath());
        if ($sha256 === false) {
            throw new RuntimeException('The uploaded evidence could not be hashed.');
        }

        return new PendingEvidenceFileData(
            file: $file,
            title: filled($title) ? (string) $title : $file->getClientOriginalName(),
            includeInReport: $includeInReport,
            extension: $extension,
            mimeType: $mime,
            sizeBytes: $size,
            sha256: $sha256,
            caption: $caption,
            internalNotes: $internalNotes,
        );
    }

    private function normalizedMime(UploadedFile $file, string $extension): ?string
    {
        $mime = $file->getMimeType();
        if ($mime !== 'application/zip') {
            return $mime;
        }

        $archive = new ZipArchive;
        if ($archive->open($file->getRealPath(), ZipArchive::RDONLY) !== true) {
            return $mime;
        }

        try {
            return match ($extension) {
                'docx' => $this->archiveContains($archive, 'word/document.xml')
                    && $this->archiveEntryContains($archive, '[Content_Types].xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml')
                        ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : $mime,
                'xlsx' => $this->archiveContains($archive, 'xl/workbook.xml')
                    && $this->archiveEntryContains($archive, '[Content_Types].xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml')
                        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : $mime,
                'ods' => $this->archiveContains($archive, 'content.xml')
                    && trim($this->boundedArchiveEntry($archive, 'mimetype') ?? '') === 'application/vnd.oasis.opendocument.spreadsheet'
                        ? 'application/vnd.oasis.opendocument.spreadsheet' : $mime,
                default => $mime,
            };
        } finally {
            $archive->close();
        }
    }

    private function archiveContains(ZipArchive $archive, string $name): bool
    {
        return $archive->locateName($name, ZipArchive::FL_NOCASE) !== false;
    }

    private function archiveEntryContains(ZipArchive $archive, string $name, string $expected): bool
    {
        $contents = $this->boundedArchiveEntry($archive, $name);

        return is_string($contents) && str_contains($contents, $expected);
    }

    private function boundedArchiveEntry(ZipArchive $archive, string $name): ?string
    {
        $index = $archive->locateName($name, ZipArchive::FL_NOCASE);
        if ($index === false) {
            return null;
        }

        $stat = $archive->statIndex($index);
        if ($stat === false || $stat['size'] > 262_144) {
            return null;
        }

        $contents = $archive->getFromIndex($index, 262_145);

        return is_string($contents) ? $contents : null;
    }
}
