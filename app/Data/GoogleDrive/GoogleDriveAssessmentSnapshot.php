<?php

declare(strict_types=1);

namespace App\Data\GoogleDrive;

use DateTimeInterface;
use JsonException;

final readonly class GoogleDriveAssessmentSnapshot
{
    public string $contentHash;

    /**
     * @param  list<list<string|int|bool|null>>  $assessmentValues
     * @param  list<list<string|int|bool|null>>  $findingRows
     * @param  list<list<string|int|bool|null>>  $solutionRows
     * @param  list<GoogleDriveEvidenceSnapshotData>  $evidences
     * @param  list<GoogleDriveFileSnapshotData>  $evidenceFiles
     * @param  list<GoogleDriveFileSnapshotData>  $reports
     *
     * @throws JsonException
     */
    public function __construct(
        public int $assessmentId,
        public string $rootFolderId,
        public string $clientFolderName,
        public string $assessmentFolderName,
        public string $sheetName,
        public array $assessmentValues,
        public array $findingRows,
        public array $solutionRows,
        public array $evidences,
        public array $evidenceFiles,
        public array $reports,
    ) {
        $canonical = [
            'assessment_id' => $assessmentId,
            'root_folder_id' => $rootFolderId,
            'client_folder_name' => $clientFolderName,
            'assessment_folder_name' => $assessmentFolderName,
            'sheet_name' => $sheetName,
            'assessment' => $assessmentValues,
            'findings' => $findingRows,
            'solutions' => $solutionRows,
            'evidences' => array_map(
                static fn (GoogleDriveEvidenceSnapshotData $evidence): array => $evidence->toArray(),
                $evidences,
            ),
            'evidence_files' => array_map(
                static fn (GoogleDriveFileSnapshotData $file): array => $file->toArray(),
                $evidenceFiles,
            ),
            'reports' => array_map(
                static fn (GoogleDriveFileSnapshotData $file): array => $file->toArray(),
                $reports,
            ),
        ];
        $json = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->contentHash = hash('sha256', $json);
    }

    /** @return list<list<string|int|bool|null>> */
    public function assessmentRows(DateTimeInterface $syncedAt): array
    {
        $rome = (new \DateTimeImmutable($syncedAt->format(DateTimeInterface::ATOM)))
            ->setTimezone(new \DateTimeZone('Europe/Rome'));

        return [
            ...$this->assessmentValues,
            ['Ultima sincronizzazione', $rome->format('d/m/Y H:i')],
        ];
    }

    /** @param array<int, string> $remoteLinks
     * @return list<list<string|int|bool|null>>
     */
    public function evidenceRows(array $remoteLinks): array
    {
        $rows = [[
            'ID Evidenza', 'ID Finding', 'Ordine', 'Tipo', 'Titolo', 'Nome file',
            'Link Google Drive / URL', 'Nome originale', 'Didascalia', 'Note interne',
            'MIME type', 'Dimensione', 'SHA-256', 'Inclusa nel report',
        ]];
        foreach ($this->evidences as $evidence) {
            $rows[] = $evidence->row($remoteLinks[$evidence->id] ?? null);
        }

        return $rows;
    }
}
