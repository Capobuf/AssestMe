<?php

declare(strict_types=1);

namespace App\Actions\Evidence;

use App\Enums\AssessmentStatus;
use App\Enums\EvidenceType;
use App\Models\Evidence;
use App\Models\Finding;
use App\Settings\GeneralSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use ZipArchive;

final class StoreEvidenceFile
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

    /** @param array<string, mixed> $data */
    public function handle(Finding $finding, UploadedFile $file, array $data): Evidence
    {
        $finding->loadMissing('assessment');
        if ($finding->assessment->status !== AssessmentStatus::Draft) {
            throw ValidationException::withMessages(['evidence' => __('assestme.assessments.errors.read_only')]);
        }

        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:20000'],
            'internal_notes' => ['nullable', 'string', 'max:20000'],
            'include_in_report' => ['required', 'boolean'],
        ])->validate();

        $extension = strtolower($file->getClientOriginalExtension());
        $settings = app(GeneralSettings::class);
        $size = $file->getSize();
        if (! is_int($size) || $size <= 0 || $size > $settings->max_evidence_file_mb * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => __('assestme.evidence.errors.file_size')]);
        }

        $mime = $this->normalizedMime($file, $extension);
        if (! is_string($mime) || ! isset(self::MIME_BY_EXTENSION[$extension]) || ! in_array($mime, self::MIME_BY_EXTENSION[$extension], true)) {
            throw ValidationException::withMessages(['file' => __('assestme.evidence.errors.unsupported_type')]);
        }

        $assessmentBytes = Evidence::query()
            ->whereHas('finding', fn ($query) => $query->where('assessment_id', $finding->assessment_id))
            ->sum('size_bytes');
        if ((int) $assessmentBytes + $size > $settings->max_assessment_evidence_mb * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => __('assestme.evidence.errors.assessment_size')]);
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
        if ($finding->evidences()->where('sha256', $sha256)->exists()) {
            throw ValidationException::withMessages(['file' => __('assestme.evidence.errors.duplicate')]);
        }

        $path = sprintf(
            'clients/%d/assessments/%d/findings/%d/evidence/%s.%s',
            $finding->assessment->client_id,
            $finding->assessment_id,
            $finding->getKey(),
            Str::uuid(),
            $extension,
        );
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false || ! Storage::disk('local')->put($path, $stream)) {
            if (is_resource($stream)) {
                fclose($stream);
            }
            throw new RuntimeException('The evidence file could not be written to private storage.');
        }
        fclose($stream);

        try {
            return $finding->evidences()->create([
                ...$validated,
                'type' => EvidenceType::File,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $mime,
                'size_bytes' => $size,
                'sha256' => $sha256,
                'sort_order' => ((int) $finding->evidences()->max('sort_order')) + 1,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
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
                    && $this->archiveEntryContains(
                        $archive,
                        '[Content_Types].xml',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
                    )
                        ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                        : $mime,
                'xlsx' => $this->archiveContains($archive, 'xl/workbook.xml')
                    && $this->archiveEntryContains(
                        $archive,
                        '[Content_Types].xml',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml',
                    )
                        ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                        : $mime,
                'ods' => $this->archiveContains($archive, 'content.xml')
                    && trim($this->boundedArchiveEntry($archive, 'mimetype') ?? '') === 'application/vnd.oasis.opendocument.spreadsheet'
                        ? 'application/vnd.oasis.opendocument.spreadsheet'
                        : $mime,
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
