<?php

declare(strict_types=1);

use App\Data\GoogleDrive\GoogleDriveObjectData;
use App\Services\GoogleDrive\GoogleWorkspaceClient;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Resource\Files;
use Illuminate\Support\Facades\Route;

it('creates a new application root directly in My Drive without searching by name', function (): void {
    $files = Mockery::mock(Files::class);
    $files->shouldNotReceive('listFiles');
    $files->shouldReceive('create')->once()->withArgs(
        static fn (DriveFile $metadata, array $parameters): bool => $metadata->getName() === 'AssestMe'
            && $metadata->getMimeType() === GoogleWorkspaceClient::FOLDER_MIME_TYPE
            && $metadata->getParents() === ['root']
            && $parameters === [
                'fields' => 'id,name,mimeType,webViewLink',
                'supportsAllDrives' => false,
            ],
    )->andReturn(new DriveFile([
        'id' => 'google-root-id',
        'name' => 'AssestMe',
        'mimeType' => GoogleWorkspaceClient::FOLDER_MIME_TYPE,
        'webViewLink' => 'https://drive.google.test/root',
    ]));
    $drive = Mockery::mock(Drive::class);
    $drive->files = $files;

    $client = new class($drive) extends GoogleWorkspaceClient
    {
        public function __construct(private readonly Drive $fakeDrive) {}

        protected function drive(): Drive
        {
            return $this->fakeDrive;
        }
    };

    $root = $client->createApplicationRoot();

    expect($root->id)->toBe('google-root-id')
        ->and($root->name)->toBe('AssestMe')
        ->and($root->mimeType)->toBe(GoogleWorkspaceClient::FOLDER_MIME_TYPE);
});

it('creates managed child folders under the exact Google parent ID', function (): void {
    $files = Mockery::mock(Files::class);
    $files->shouldReceive('create')->once()->withArgs(
        static fn (DriveFile $metadata): bool => $metadata->getName() === 'C-000001 - Cliente'
            && $metadata->getParents() === ['managed-root-id'],
    )->andReturn(new DriveFile([
        'id' => 'client-folder-id',
        'name' => 'C-000001 - Cliente',
        'mimeType' => GoogleWorkspaceClient::FOLDER_MIME_TYPE,
    ]));
    $drive = Mockery::mock(Drive::class);
    $drive->files = $files;
    $client = new class($drive) extends GoogleWorkspaceClient
    {
        public function __construct(private readonly Drive $fakeDrive) {}

        /** @return list<GoogleDriveObjectData> */
        protected function managedChildren(string $parentId, string $managedPrefix, string $mimeType): array
        {
            return [];
        }

        protected function drive(): Drive
        {
            return $this->fakeDrive;
        }
    };

    $folder = $client->ensureFolder('managed-root-id', 'C-000001', 'C-000001 - Cliente');

    expect($folder->id)->toBe('client-folder-id');
});

it('uploads new managed files under the exact Google parent ID', function (): void {
    $files = Mockery::mock(Files::class);
    $files->shouldReceive('create')->once()->withArgs(
        static fn (DriveFile $metadata, array $parameters): bool => $metadata->getName() === 'R-000001 - Report v1.pdf'
            && $metadata->getParents() === ['documents-folder-id']
            && $parameters['data'] === '%PDF-test'
            && $parameters['mimeType'] === 'application/pdf'
            && $parameters['uploadType'] === 'multipart',
    )->andReturn(new DriveFile([
        'id' => 'report-file-id',
        'name' => 'R-000001 - Report v1.pdf',
        'mimeType' => 'application/pdf',
        'webViewLink' => 'https://drive.google.test/report',
    ]));
    $drive = Mockery::mock(Drive::class);
    $drive->files = $files;
    $client = new class($drive) extends GoogleWorkspaceClient
    {
        public function __construct(private readonly Drive $fakeDrive) {}

        /** @return list<GoogleDriveObjectData> */
        protected function managedChildren(string $parentId, string $managedPrefix, string $mimeType): array
        {
            return [];
        }

        protected function drive(): Drive
        {
            return $this->fakeDrive;
        }
    };

    $file = $client->uploadFile(
        'documents-folder-id',
        'R-000001',
        'R-000001 - Report v1.pdf',
        '%PDF-test',
        'application/pdf',
    );

    expect($file->id)->toBe('report-file-id')
        ->and($file->webViewLink)->toBe('https://drive.google.test/report');
});

it('creates native spreadsheets directly under the assessment folder ID', function (): void {
    $files = Mockery::mock(Files::class);
    $files->shouldReceive('create')->once()->withArgs(
        static fn (DriveFile $metadata): bool => $metadata->getName() === 'Findings - A-000001'
            && $metadata->getMimeType() === GoogleWorkspaceClient::SPREADSHEET_MIME_TYPE
            && $metadata->getParents() === ['assessment-folder-id'],
    )->andReturn(new DriveFile([
        'id' => 'spreadsheet-id',
        'name' => 'Findings - A-000001',
        'mimeType' => GoogleWorkspaceClient::SPREADSHEET_MIME_TYPE,
        'webViewLink' => 'https://docs.google.test/spreadsheet',
    ]));
    $drive = Mockery::mock(Drive::class);
    $drive->files = $files;
    $client = new class($drive) extends GoogleWorkspaceClient
    {
        public function __construct(private readonly Drive $fakeDrive) {}

        /** @return list<GoogleDriveObjectData> */
        protected function managedChildren(string $parentId, string $managedPrefix, string $mimeType): array
        {
            return [];
        }

        protected function drive(): Drive
        {
            return $this->fakeDrive;
        }
    };

    $sheet = $client->ensureSpreadsheet(
        'assessment-folder-id',
        'Findings - A-000001',
        'Findings - A-000001',
    );

    expect($sheet->id)->toBe('spreadsheet-id');
});

it('does not expose selector token or arbitrary root-selection surfaces', function (): void {
    expect(Route::has('google-drive.picker-token'))->toBeFalse()
        ->and(Route::has('google-drive.root.store'))->toBeFalse()
        ->and(method_exists(GoogleWorkspaceClient::class, 'temporaryAccessToken'))->toBeFalse();
});
