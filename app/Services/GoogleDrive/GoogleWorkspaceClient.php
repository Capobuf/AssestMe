<?php

declare(strict_types=1);

namespace App\Services\GoogleDrive;

use App\Data\GoogleDrive\GoogleDriveObjectData;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Sheets as GoogleSheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Revolution\Google\Sheets\SheetsClient;
use RuntimeException;
use Throwable;

class GoogleWorkspaceClient
{
    public const FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';

    public const SPREADSHEET_MIME_TYPE = 'application/vnd.google-apps.spreadsheet';

    public function __construct(
        private readonly Client $googleClient,
        private readonly GoogleDriveTokenService $tokens,
    ) {}

    public function createApplicationRoot(): GoogleDriveObjectData
    {
        try {
            $folder = $this->drive()->files->create(new DriveFile([
                'name' => 'AssestMe',
                'mimeType' => self::FOLDER_MIME_TYPE,
                'parents' => ['root'],
            ]), [
                'fields' => 'id,name,mimeType,webViewLink',
                'supportsAllDrives' => false,
            ]);
        } catch (Throwable) {
            throw new GoogleDriveSyncException(__('assestme.google_drive.errors.root_creation_failed'));
        }

        return $this->objectData($folder);
    }

    public function inspectFolder(string $folderId): GoogleDriveObjectData
    {
        if ($folderId === '') {
            throw new RuntimeException(__('assestme.google_drive.errors.folder_unavailable'));
        }

        try {
            $file = $this->drive()
                ->files
                ->get($folderId, [
                    'fields' => 'id,name,mimeType,trashed,webViewLink',
                    'supportsAllDrives' => false,
                ]);
        } catch (Throwable) {
            throw new RuntimeException(__('assestme.google_drive.errors.folder_unavailable'));
        }

        if ($file->getTrashed() === true || $file->getMimeType() !== self::FOLDER_MIME_TYPE) {
            throw new RuntimeException(__('assestme.google_drive.errors.not_folder'));
        }

        $id = $file->getId();
        $name = $file->getName();
        if (! is_string($id) || $id === '' || ! is_string($name) || $name === '') {
            throw new RuntimeException(__('assestme.google_drive.errors.folder_unavailable'));
        }

        return new GoogleDriveObjectData(
            id: $id,
            name: $name,
            mimeType: (string) $file->getMimeType(),
            webViewLink: is_string($file->getWebViewLink()) ? $file->getWebViewLink() : null,
        );
    }

    public function ensureFolder(
        string $parentId,
        string $managedPrefix,
        string $desiredName,
    ): GoogleDriveObjectData {
        $matches = $this->managedChildren($parentId, $managedPrefix, self::FOLDER_MIME_TYPE);
        if (count($matches) > 1) {
            throw new GoogleDriveSyncException(__('assestme.google_drive.errors.duplicate_remote'));
        }

        if ($matches !== []) {
            return $this->renameIfNeeded($matches[0], $desiredName);
        }

        try {
            $created = $this->drive()->files->create(new DriveFile([
                'name' => $desiredName,
                'mimeType' => self::FOLDER_MIME_TYPE,
                'parents' => [$parentId],
            ]), [
                'fields' => 'id,name,mimeType,webViewLink',
                'supportsAllDrives' => false,
            ]);
        } catch (Throwable) {
            throw new GoogleDriveSyncException(__('assestme.google_drive.errors.folder_write_failed'));
        }

        return $this->objectData($created);
    }

    public function ensureSpreadsheet(
        string $parentId,
        string $managedPrefix,
        string $desiredName,
    ): GoogleDriveObjectData {
        $matches = $this->managedChildren($parentId, $managedPrefix, self::SPREADSHEET_MIME_TYPE);
        if (count($matches) > 1) {
            throw new GoogleDriveSyncException(__('assestme.google_drive.errors.duplicate_remote'));
        }
        if ($matches !== []) {
            return $this->renameIfNeeded($matches[0], $desiredName);
        }

        try {
            $created = $this->drive()->files->create(new DriveFile([
                'name' => $desiredName,
                'mimeType' => self::SPREADSHEET_MIME_TYPE,
                'parents' => [$parentId],
            ]), [
                'fields' => 'id,name,mimeType,webViewLink',
                'supportsAllDrives' => false,
            ]);
        } catch (Throwable $exception) {
            throw new GoogleDriveSyncException(
                __('assestme.google_drive.errors.spreadsheet_write_failed'),
                previous: $exception,
            );
        }

        return $this->objectData($created);
    }

    /**
     * @param  list<list<string|int|bool|null>>  $assessmentRows
     * @param  list<list<string|int|bool|null>>  $findingRows
     * @param  list<list<string|int|bool|null>>  $solutionRows
     * @param  list<list<string|int|bool|null>>  $evidenceRows
     */
    public function replaceSpreadsheet(
        string $spreadsheetId,
        array $assessmentRows,
        array $findingRows,
        array $solutionRows,
        array $evidenceRows,
    ): void {
        try {
            $sheets = $this->sheets()->spreadsheet($spreadsheetId);
            $existing = $sheets->sheetList();
            if (! in_array('Assessment', $existing, true) && count($existing) === 1) {
                $this->renameOnlySheetToAssessment($sheets, $spreadsheetId, (int) array_key_first($existing));
                $existing = $sheets->sheetList();
            }
            foreach (['Assessment', 'Findings', 'Soluzioni', 'Evidenze'] as $title) {
                if (! in_array($title, $existing, true)) {
                    $sheets->addSheet($title);
                }
            }

            $values = [
                'Assessment' => $assessmentRows,
                'Findings' => $findingRows,
                'Soluzioni' => $solutionRows,
                'Evidenze' => $evidenceRows,
            ];
            foreach ($values as $title => $rows) {
                $sheets->sheet($title)->range('A:Z')->clear();
                $sheets->sheet($title)->range('A1')->update($this->normalizeSheetRows($rows));
            }

            $this->freezeTableHeaders($sheets, $spreadsheetId, array_flip($sheets->sheetList()));
        } catch (Throwable $exception) {
            throw new GoogleDriveSyncException(
                __('assestme.google_drive.errors.spreadsheet_write_failed'),
                previous: $exception,
            );
        }
    }

    public function uploadFile(
        string $parentId,
        string $managedPrefix,
        string $desiredName,
        string $bytes,
        string $mimeType,
    ): GoogleDriveObjectData {
        $matches = $this->managedChildren($parentId, $managedPrefix, '*');
        if (count($matches) > 1) {
            throw new GoogleDriveSyncException(__('assestme.google_drive.errors.duplicate_remote'));
        }

        try {
            if ($matches === []) {
                $created = $this->drive()->files->create(new DriveFile([
                    'name' => $desiredName,
                    'parents' => [$parentId],
                ]), [
                    'data' => $bytes,
                    'mimeType' => $mimeType,
                    'uploadType' => 'multipart',
                    'fields' => 'id,name,mimeType,webViewLink',
                    'supportsAllDrives' => false,
                ]);

                return $this->objectData($created);
            }
            $file = $this->renameIfNeeded($matches[0], $desiredName);
            $updated = $this->drive()->files->update($file->id, new DriveFile, [
                'data' => $bytes,
                'mimeType' => $mimeType,
                'uploadType' => 'media',
                'fields' => 'id,name,mimeType,webViewLink',
                'supportsAllDrives' => false,
            ]);
        } catch (GoogleDriveSyncException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new GoogleDriveSyncException(__('assestme.google_drive.errors.file_write_failed'));
        }

        return $this->objectData($updated);
    }

    /** @return list<GoogleDriveObjectData> */
    protected function managedChildren(string $parentId, string $managedPrefix, string $mimeType): array
    {
        $escapedParent = $this->escapeQuery($parentId);
        $escapedPrefix = $this->escapeQuery($managedPrefix);
        $query = sprintf("'%s' in parents and trashed = false and name contains '%s'", $escapedParent, $escapedPrefix);
        if ($mimeType !== '*') {
            $query .= sprintf(" and mimeType = '%s'", $this->escapeQuery($mimeType));
        }

        try {
            $files = [];
            $pageToken = null;
            do {
                $response = $this->drive()->files->listFiles([
                    'q' => $query,
                    'fields' => 'nextPageToken,files(id,name,mimeType,webViewLink)',
                    'pageSize' => 100,
                    'pageToken' => $pageToken,
                    'spaces' => 'drive',
                ]);
                array_push($files, ...$response->getFiles());
                $pageToken = $response->getNextPageToken();
            } while (is_string($pageToken) && $pageToken !== '');
        } catch (Throwable) {
            throw new GoogleDriveSyncException(__('assestme.google_drive.errors.provider_failure'));
        }

        $matches = [];
        foreach ($files as $file) {
            $name = $file->getName();
            if (is_string($name) && ($name === $managedPrefix || str_starts_with($name, $managedPrefix.' - '))) {
                $matches[] = $this->objectData($file);
            }
        }

        return $matches;
    }

    private function renameIfNeeded(GoogleDriveObjectData $object, string $desiredName): GoogleDriveObjectData
    {
        if ($object->name === $desiredName) {
            return $object;
        }

        try {
            $updated = $this->drive()->files->update($object->id, new DriveFile(['name' => $desiredName]), [
                'fields' => 'id,name,mimeType,webViewLink',
                'supportsAllDrives' => false,
            ]);
        } catch (Throwable) {
            throw new GoogleDriveSyncException(__('assestme.google_drive.errors.provider_failure'));
        }

        return $this->objectData($updated);
    }

    private function objectData(DriveFile $file): GoogleDriveObjectData
    {
        $id = $file->getId();
        $name = $file->getName();
        $mimeType = $file->getMimeType();
        if ($id === '' || $name === '' || $mimeType === '') {
            throw new GoogleDriveSyncException(__('assestme.google_drive.errors.provider_failure'));
        }

        $link = $file->getWebViewLink();

        return new GoogleDriveObjectData($id, $name, $mimeType, $link !== '' ? $link : null);
    }

    /**
     * Google model serialization removes null entries and can turn the remaining numeric keys into
     * a JSON object. Preserve column positions by replacing null or missing cells with empty strings.
     *
     * @param  list<list<string|int|bool|null>>  $rows
     * @return list<list<string|int|bool|null>>
     */
    private function normalizeSheetRows(array $rows): array
    {
        $width = 0;
        foreach ($rows as $row) {
            $keys = array_keys($row);
            $width = max($width, $keys === [] ? 0 : ((int) max($keys)) + 1);
        }

        return array_map(static function (array $row) use ($width): array {
            $normalized = array_fill(0, $width, '');
            foreach ($row as $column => $value) {
                $normalized[(int) $column] = $value ?? '';
            }

            return $normalized;
        }, $rows);
    }

    /** @param array<string, int|string> $sheetIds */
    protected function freezeTableHeaders(SheetsClient $sheets, string $spreadsheetId, array $sheetIds): void
    {
        $requests = [];
        foreach (['Findings', 'Soluzioni', 'Evidenze'] as $title) {
            $requests[] = [
                'updateSheetProperties' => [
                    'properties' => [
                        'sheetId' => $sheetIds[$title],
                        'gridProperties' => ['frozenRowCount' => 1],
                    ],
                    'fields' => 'gridProperties.frozenRowCount',
                ],
            ];
        }
        $sheets->getService()->spreadsheets->batchUpdate(
            $spreadsheetId,
            new BatchUpdateSpreadsheetRequest(['requests' => $requests]),
        );
    }

    private function renameOnlySheetToAssessment(
        SheetsClient $sheets,
        string $spreadsheetId,
        int $sheetId,
    ): void {
        $sheets->getService()->spreadsheets->batchUpdate(
            $spreadsheetId,
            new BatchUpdateSpreadsheetRequest([
                'requests' => [[
                    'updateSheetProperties' => [
                        'properties' => [
                            'sheetId' => $sheetId,
                            'title' => 'Assessment',
                        ],
                        'fields' => 'title',
                    ],
                ]],
            ]),
        );
    }

    protected function sheets(): SheetsClient
    {
        return (new SheetsClient)
            ->setService(new GoogleSheets($this->authorizedClient()))
            ->setDriveService(new Drive($this->authorizedClient()));
    }

    private function escapeQuery(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }

    protected function drive(): Drive
    {
        return new Drive($this->authorizedClient());
    }

    private function authorizedClient(): Client
    {
        $token = $this->tokens->accessToken();
        $this->googleClient->setAccessToken([
            'access_token' => $token->token,
            'expires_in' => $token->expiresIn,
            'created' => time(),
        ]);

        return $this->googleClient;
    }
}
