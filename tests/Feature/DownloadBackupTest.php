<?php

declare(strict_types=1);

use App\Actions\Backups\CreateBackup;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->originalDatabaseConnection = config('database.default');
    $this->backupWorkspace = storage_path('framework/testing/download-backups-'.Str::uuid());
    $this->backupRoot = $this->backupWorkspace.DIRECTORY_SEPARATOR.'archives';
    $this->privateStorage = $this->backupWorkspace.DIRECTORY_SEPARATOR.'private';
    $this->databasePath = $this->backupWorkspace.DIRECTORY_SEPARATOR.'database.sqlite';
    File::ensureDirectoryExists($this->backupRoot);
    File::ensureDirectoryExists($this->privateStorage);
    File::put($this->databasePath, '');
    config()->set('database.connections.backup_download', [
        ...config('database.connections.sqlite'),
        'database' => $this->databasePath,
    ]);
    config()->set('database.default', 'backup_download');
    config()->set('assestme.backup.root', $this->backupRoot);
    config()->set('assestme.backup.private_storage_path', $this->privateStorage);
    DB::purge();
    expect(Artisan::call('migrate', [
        '--database' => 'backup_download',
        '--force' => true,
    ]))->toBe(0);
});

afterEach(function (): void {
    DB::purge('backup_download');
    config()->set('database.default', $this->originalDatabaseConnection);
    DB::reconnect();
    File::deleteDirectory($this->backupWorkspace);
});

it('requires authentication for managed backup downloads', function (): void {
    $name = 'assestme-20260725-023000.tar.gz';

    $this->get(route('backups.download', ['archive' => $name]))
        ->assertRedirect('/admin/login');
});

it('downloads only a server-verified archive with defensive headers and filename', function (): void {
    $administrator = User::factory()->create();
    $name = 'assestme-20260725-023000.tar.gz';
    $path = $this->backupRoot.DIRECTORY_SEPARATOR.$name;
    app(CreateBackup::class)($path, false);

    $response = $this->actingAs($administrator)
        ->get(route('backups.download', ['archive' => $name]));

    $response->assertOk()
        ->assertDownload($name)
        ->assertHeader('content-type', 'application/gzip')
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertHeader('content-security-policy', "default-src 'none'; sandbox")
        ->assertHeader('cache-control', 'no-store, private');
});

it('returns not found for invalid missing traversal and symlink targets', function (): void {
    $administrator = User::factory()->create();
    $this->actingAs($administrator);
    $validMissing = 'assestme-20260725-023000.tar.gz';

    $this->get(route('backups.download', ['archive' => $validMissing]))->assertNotFound();
    $this->get('/admin/settings/backups/not-a-backup/download')->assertNotFound();
    $this->get('/admin/settings/backups/..%2Fassestme-20260725-023000.tar.gz/download')->assertNotFound();

    $external = storage_path('framework/testing/external-backup-'.Str::uuid().'.tar.gz');
    File::put($external, 'external');
    symlink($external, $this->backupRoot.DIRECTORY_SEPARATOR.$validMissing);

    try {
        $this->get(route('backups.download', ['archive' => $validMissing]))->assertNotFound();
    } finally {
        unlink($this->backupRoot.DIRECTORY_SEPARATOR.$validMissing);
        File::delete($external);
    }
});

it('blocks a corrupt archive without exposing the internal verification error', function (): void {
    $administrator = User::factory()->create();
    $name = 'assestme-20260725-023000.tar.gz';
    $path = $this->backupRoot.DIRECTORY_SEPARATOR.$name;
    app(CreateBackup::class)($path, false);
    $archive = new PharData($path);
    $archive['metadata.json'] = '{"tampered":true}';
    unset($archive);

    $this->actingAs($administrator)
        ->get(route('backups.download', ['archive' => $name]))
        ->assertStatus(409)
        ->assertSeeText(__('assestme.backups.errors.corrupt'))
        ->assertDontSee('Backup file failed verification');
});
