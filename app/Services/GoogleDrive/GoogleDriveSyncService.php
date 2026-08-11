<?php

declare(strict_types=1);

namespace App\Services\GoogleDrive;

use App\Data\GoogleDrive\GoogleDriveFileSnapshotData;
use App\Data\GoogleDrive\GoogleDriveSyncResult;
use App\Models\Assessment;
use App\Models\AssessmentGoogleDriveSync;
use App\Settings\GoogleDriveSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final readonly class GoogleDriveSyncService
{
    public const LOCK_NAME = 'assestme:google-drive-sync';

    public function __construct(
        private GoogleDriveSettings $settings,
        private BuildGoogleDriveAssessmentSnapshot $snapshots,
        private GoogleWorkspaceClient $google,
        private GoogleDriveTokenService $tokens,
    ) {}

    public function syncAll(bool $force = false, bool $allowWhenDisabled = false): GoogleDriveSyncResult
    {
        if (! $this->isIntegrationReady($allowWhenDisabled)) {
            return new GoogleDriveSyncResult;
        }

        $lock = Cache::lock(self::LOCK_NAME, 3600);
        if (! $lock->get()) {
            return new GoogleDriveSyncResult(lockUnavailable: true);
        }

        $evaluated = 0;
        $skipped = 0;
        $synchronized = 0;
        $failed = 0;

        try {
            Assessment::query()->orderBy('id')->chunkById(50, function ($assessments) use (
                $force,
                &$evaluated,
                &$skipped,
                &$synchronized,
                &$failed,
            ): void {
                foreach ($assessments as $assessment) {
                    $evaluated++;
                    try {
                        $outcome = $this->syncAssessment($assessment, $force);
                        if ($outcome === 'skipped') {
                            $skipped++;
                        } else {
                            $synchronized++;
                        }
                    } catch (Throwable) {
                        $failed++;
                    }
                }
            });
        } finally {
            $lock->release();
        }

        return new GoogleDriveSyncResult($evaluated, $skipped, $synchronized, $failed);
    }

    public function syncAssessment(Assessment $assessment, bool $force = false): string
    {
        $rootFolderId = $this->configuredRootId();
        $snapshot = $this->snapshots->build($assessment, $rootFolderId);
        $state = AssessmentGoogleDriveSync::query()->firstOrNew([
            'assessment_id' => $assessment->getKey(),
        ]);

        if (! $force && hash_equals((string) $state->last_content_hash, $snapshot->contentHash)) {
            return 'skipped';
        }

        try {
            $evidenceBytes = $this->verifiedFiles($snapshot->evidenceFiles);
            $reportBytes = $this->verifiedFiles($snapshot->reports);

            $clientFolder = $this->google->ensureFolder(
                $rootFolderId,
                $this->managedPrefix($snapshot->clientFolderName),
                $snapshot->clientFolderName,
            );
            $assessmentFolder = $this->google->ensureFolder(
                $clientFolder->id,
                $this->managedPrefix($snapshot->assessmentFolderName),
                $snapshot->assessmentFolderName,
            );
            $documentsFolder = $this->google->ensureFolder($assessmentFolder->id, 'Documenti', 'Documenti');
            $evidencesFolder = $this->google->ensureFolder($assessmentFolder->id, 'Evidenze', 'Evidenze');

            foreach ($snapshot->reports as $report) {
                $this->google->uploadFile(
                    $documentsFolder->id,
                    $this->managedPrefix($report->remoteName),
                    $report->remoteName,
                    $reportBytes[$report->localId],
                    $report->mimeType,
                );
            }

            $remoteEvidenceLinks = [];
            foreach ($snapshot->evidenceFiles as $evidence) {
                $remote = $this->google->uploadFile(
                    $evidencesFolder->id,
                    $this->evidencePrefix($evidence->remoteName),
                    $evidence->remoteName,
                    $evidenceBytes[$evidence->localId],
                    $evidence->mimeType,
                );
                if (! is_string($remote->webViewLink) || $remote->webViewLink === '') {
                    throw new GoogleDriveSyncException(__('assestme.google_drive.errors.remote_link_missing'));
                }
                $remoteEvidenceLinks[$evidence->localId] = $remote->webViewLink;
            }

            $spreadsheet = $this->google->ensureSpreadsheet(
                $assessmentFolder->id,
                $snapshot->sheetName,
                $snapshot->sheetName,
            );
            $syncedAt = now();
            $this->google->replaceSpreadsheet(
                $spreadsheet->id,
                $snapshot->assessmentRows($syncedAt),
                $snapshot->findingRows,
                $snapshot->solutionRows,
                $snapshot->evidenceRows($remoteEvidenceLinks),
            );

            $state->fill([
                'last_content_hash' => $snapshot->contentHash,
                'last_synced_at' => $syncedAt,
                'last_error' => null,
                'last_error_at' => null,
            ])->save();
        } catch (Throwable $exception) {
            $message = $this->sanitizedMessage($exception);
            $state->fill([
                'last_error' => $message,
                'last_error_at' => now(),
            ])->save();

            throw new GoogleDriveSyncException($message, previous: $exception);
        }

        return 'synchronized';
    }

    private function configuredRootId(): string
    {
        $root = $this->settings->root_folder_id;
        if (! is_string($root) || $root === '') {
            throw new GoogleDriveSyncException(__('assestme.google_drive.errors.configuration_incomplete'));
        }

        return $root;
    }

    private function isIntegrationReady(bool $allowWhenDisabled): bool
    {
        return ($allowWhenDisabled || $this->settings->sync_enabled)
            && $this->tokens->isInstallationConfigured()
            && is_string($this->settings->google_account_email)
            && $this->settings->google_account_email !== ''
            && is_string($this->settings->encrypted_refresh_token)
            && $this->settings->encrypted_refresh_token !== ''
            && is_string($this->settings->root_folder_id)
            && $this->settings->root_folder_id !== '';
    }

    /**
     * @param  list<GoogleDriveFileSnapshotData>  $files
     * @return array<int, string>
     */
    private function verifiedFiles(array $files): array
    {
        $verified = [];
        $disk = Storage::disk('local');

        foreach ($files as $file) {
            if ($file->localPath === '' || ! $disk->exists($file->localPath)) {
                throw new GoogleDriveSyncException(__('assestme.google_drive.errors.local_file_missing'));
            }

            try {
                $bytes = $disk->get($file->localPath);
            } catch (Throwable) {
                throw new GoogleDriveSyncException(__('assestme.google_drive.errors.local_file_unreadable'));
            }

            if (strlen($bytes) !== $file->sizeBytes || ! hash_equals($file->sha256, hash('sha256', $bytes))) {
                throw new GoogleDriveSyncException(__('assestme.google_drive.errors.local_file_integrity'));
            }
            $verified[$file->localId] = $bytes;
        }

        return $verified;
    }

    private function managedPrefix(string $name): string
    {
        return explode(' - ', $name, 2)[0];
    }

    private function evidencePrefix(string $name): string
    {
        if (preg_match('/^(F-\d{6} - E-\d{6})/', $name, $matches) !== 1) {
            throw new RuntimeException(__('assestme.google_drive.errors.invalid_managed_name'));
        }

        return $matches[1];
    }

    private function sanitizedMessage(Throwable $exception): string
    {
        if ($exception instanceof GoogleDriveSyncException && $exception->getMessage() !== '') {
            return mb_substr($exception->getMessage(), 0, 1000);
        }

        return __('assestme.google_drive.errors.provider_failure');
    }
}
