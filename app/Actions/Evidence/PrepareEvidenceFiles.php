<?php

declare(strict_types=1);

namespace App\Actions\Evidence;

use App\Data\Evidence\PendingEvidenceFileData;
use App\Data\Evidence\PreparedEvidenceFileData;
use App\Models\Evidence;
use App\Models\Finding;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class PrepareEvidenceFiles
{
    public function __construct(private readonly GeneralSettings $settings) {}

    /**
     * @param  list<PendingEvidenceFileData>  $files
     * @return list<PreparedEvidenceFileData>
     */
    public function handle(Finding $finding, array $files): array
    {
        if ($files === []) {
            return [];
        }

        $existingBytes = (int) Evidence::query()
            ->whereHas('finding', fn ($query) => $query->where('assessment_id', $finding->assessment_id))
            ->sum('size_bytes');
        $batchBytes = 0;
        $batchHashes = [];

        foreach ($files as $index => $file) {
            $actualSize = $file->file->getSize();
            $actualHash = hash_file('sha256', $file->file->getRealPath());
            if ($actualSize !== $file->sizeBytes || ! is_string($actualHash) || ! hash_equals($file->sha256, $actualHash)) {
                throw ValidationException::withMessages(["evidence.files.{$index}" => __('assestme.workspace.errors.payload_hash')]);
            }
            if (isset($batchHashes[$file->sha256]) || $finding->evidences()->where('sha256', $file->sha256)->exists()) {
                throw ValidationException::withMessages(["evidence.files.{$index}" => __('assestme.evidence.errors.duplicate')]);
            }

            $batchHashes[$file->sha256] = true;
            $batchBytes += $file->sizeBytes;
        }

        if ($existingBytes + $batchBytes > $this->settings->max_assessment_evidence_mb * 1024 * 1024) {
            throw ValidationException::withMessages(['evidence.files' => __('assestme.evidence.errors.assessment_size')]);
        }

        $prepared = [];
        try {
            foreach ($files as $file) {
                $path = sprintf(
                    'clients/%d/assessments/%d/findings/%d/evidence/%s.%s',
                    $finding->assessment->client_id,
                    $finding->assessment_id,
                    $finding->getKey(),
                    Str::uuid(),
                    $file->extension,
                );
                $stream = fopen($file->file->getRealPath(), 'rb');
                if ($stream === false) {
                    throw new RuntimeException('The evidence file could not be opened.');
                }
                try {
                    $written = Storage::disk('local')->put($path, $stream);
                } finally {
                    fclose($stream);
                }
                if (! $written) {
                    throw new RuntimeException('The evidence file could not be written to private storage.');
                }
                $prepared[] = new PreparedEvidenceFileData($file, $path);
            }
        } catch (\Throwable $exception) {
            $this->compensate($prepared, $finding, null);
            throw $exception;
        }

        return $prepared;
    }

    /** @param list<PreparedEvidenceFileData> $files */
    public function compensate(array $files, Finding $finding, ?string $requestId): void
    {
        foreach ($files as $file) {
            if (Storage::disk('local')->delete($file->finalPath)) {
                continue;
            }

            Log::error('Prepared evidence file compensation failed.', [
                'assessment_id' => $finding->assessment_id,
                'finding_id' => $finding->getKey(),
                'request_id' => $requestId,
                'file_path' => $file->finalPath,
            ]);
        }
    }
}
