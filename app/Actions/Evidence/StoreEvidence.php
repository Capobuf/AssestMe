<?php

declare(strict_types=1);

namespace App\Actions\Evidence;

use App\Actions\Assessments\IncrementAssessmentVersion;
use App\Data\Evidence\PreparedEvidenceFileData;
use App\Enums\AssessmentStatus;
use App\Enums\EvidenceType;
use App\Models\Evidence;
use App\Models\Finding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class StoreEvidence
{
    public function __construct(
        private readonly IncrementAssessmentVersion $incrementAssessmentVersion,
        private readonly InspectEvidenceUpload $inspectEvidenceUpload,
        private readonly PrepareEvidenceFiles $prepareEvidenceFiles,
    ) {}

    /**
     * Compatibility entry point for evidence operations outside the Finding workspace.
     * The workspace uses SaveFindingDetails so all pending evidence shares one version.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function __invoke(
        Finding $finding,
        EvidenceType $type,
        array $data,
        ?UploadedFile $file = null,
        ?int $expectedVersion = null,
    ): Evidence {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'max:255'],
            'url' => $type === EvidenceType::Url
                ? ['required', 'url:http,https', 'max:2048']
                : ['prohibited'],
            'caption' => ['nullable', 'string', 'max:20000'],
            'internal_notes' => ['nullable', 'string', 'max:20000'],
            'include_in_report' => ['required', 'boolean'],
        ])->validate();

        if ($type === EvidenceType::File && ! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => __('validation.required', ['attribute' => 'file'])]);
        }
        if ($type === EvidenceType::Url && $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['file' => __('validation.prohibited', ['attribute' => 'file'])]);
        }

        $pendingFile = $file instanceof UploadedFile
            ? $this->inspectEvidenceUpload->handle(
                $file,
                (string) $validated['title'],
                (bool) $validated['include_in_report'],
                isset($validated['caption']) ? (string) $validated['caption'] : null,
                isset($validated['internal_notes']) ? (string) $validated['internal_notes'] : null,
            )
            : null;
        $expectedVersion ??= (int) $finding->assessment()->firstOrFail()->lock_version;

        return Cache::lock("assessment:{$finding->assessment_id}:save", 10)->block(
            5,
            function () use ($finding, $type, $validated, $pendingFile, $expectedVersion): Evidence {
                $persisted = Finding::query()->with('assessment')->findOrFail($finding->getKey());
                if ($persisted->assessment->status !== AssessmentStatus::Draft) {
                    throw ValidationException::withMessages(['evidence' => __('assestme.assessments.errors.read_only')]);
                }

                $prepared = $pendingFile === null ? [] : $this->prepareEvidenceFiles->handle($persisted, [$pendingFile]);
                try {
                    return DB::transaction(
                        fn (): Evidence => $this->persist($persisted, $type, $validated, $prepared, $expectedVersion),
                        attempts: 1,
                    );
                } catch (Throwable $exception) {
                    $this->prepareEvidenceFiles->compensate($prepared, $persisted, null);
                    throw $exception;
                }
            },
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  list<PreparedEvidenceFileData>  $prepared
     */
    private function persist(
        Finding $finding,
        EvidenceType $type,
        array $validated,
        array $prepared,
        int $expectedVersion,
    ): Evidence {
        $evidence = $type === EvidenceType::Url
            ? $this->storeUrl($finding, $validated)
            : $this->storeFile($finding, $prepared);
        ($this->incrementAssessmentVersion)($finding->assessment, $expectedVersion);

        return $evidence;
    }

    /** @param array<string, mixed> $validated */
    private function storeUrl(Finding $finding, array $validated): Evidence
    {
        $parts = parse_url((string) $validated['url']);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages(['url' => __('assestme.evidence.errors.url_credentials')]);
        }

        return $finding->evidences()->create([
            ...$validated,
            'type' => EvidenceType::Url,
            'sort_order' => ((int) $finding->evidences()->max('sort_order')) + 1,
        ]);
    }

    /** @param list<PreparedEvidenceFileData> $prepared */
    private function storeFile(Finding $finding, array $prepared): Evidence
    {
        $file = $prepared[0] ?? throw new LogicException('File evidence requires one prepared upload.');

        return $finding->evidences()->create([
            'title' => $file->pending->title,
            'caption' => $file->pending->caption,
            'internal_notes' => $file->pending->internalNotes,
            'include_in_report' => $file->pending->includeInReport,
            'type' => EvidenceType::File,
            'file_path' => $file->finalPath,
            'original_filename' => $file->pending->file->getClientOriginalName(),
            'mime_type' => $file->pending->mimeType,
            'size_bytes' => $file->pending->sizeBytes,
            'sha256' => $file->pending->sha256,
            'sort_order' => ((int) $finding->evidences()->max('sort_order')) + 1,
        ]);
    }
}
