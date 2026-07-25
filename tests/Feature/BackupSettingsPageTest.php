<?php

declare(strict_types=1);

use App\Actions\Backups\CreateBackup;
use App\Actions\Backups\VerifyBackup;
use App\Filament\Clusters\SettingsCluster;
use App\Filament\Pages\BackupSettingsPage;
use App\Models\User;
use App\Services\Backups\BackupArchiveCatalog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->originalDatabaseConnection = config('database.default');
    $this->backupWorkspace = storage_path('framework/testing/backup-page-'.Str::uuid());
    $this->backupRoot = $this->backupWorkspace.DIRECTORY_SEPARATOR.'archives';
    $this->privateStorage = $this->backupWorkspace.DIRECTORY_SEPARATOR.'private';
    $this->databasePath = $this->backupWorkspace.DIRECTORY_SEPARATOR.'database.sqlite';
    File::ensureDirectoryExists($this->backupRoot);
    File::ensureDirectoryExists($this->privateStorage);
    File::put($this->databasePath, '');
    config()->set('database.connections.backup_page', [
        ...config('database.connections.sqlite'),
        'database' => $this->databasePath,
    ]);
    config()->set('database.default', 'backup_page');
    config()->set('assestme.backup.root', $this->backupRoot);
    config()->set('assestme.backup.private_storage_path', $this->privateStorage);
    DB::purge();
    expect(Artisan::call('migrate', [
        '--database' => 'backup_page',
        '--force' => true,
    ]))->toBe(0);
});

afterEach(function (): void {
    DB::purge('backup_page');
    config()->set('database.default', $this->originalDatabaseConnection);
    DB::reconnect();
    File::deleteDirectory($this->backupWorkspace);
});

it('is an authenticated native page in the Settings cluster with the approved position', function (): void {
    expect(BackupSettingsPage::getCluster())->toBe(SettingsCluster::class)
        ->and(BackupSettingsPage::getNavigationSort())->toBe(3);

    $this->get(BackupSettingsPage::getUrl())->assertRedirect('/admin/login');

    $this->actingAs(User::factory()->create())
        ->get(BackupSettingsPage::getUrl())
        ->assertOk()
        ->assertSeeText(__('assestme.backups.title'))
        ->assertSeeText(__('assestme.backups.overview.schedule_heading'))
        ->assertSeeText(__('assestme.backups.overview.schedule_value'))
        ->assertSeeText(__('assestme.backups.overview.scheduler_required'))
        ->assertSeeText(__('assestme.backups.restore.description'));
});

it('creates verifies and immediately lists a real managed backup through the page action', function (): void {
    $this->actingAs(User::factory()->create());
    $component = Livewire::test(BackupSettingsPage::class)
        ->assertSee(__('assestme.backups.table.empty'))
        ->callAction(TestAction::make('createBackup')->table())
        ->assertNotified(__('assestme.backups.notifications.created'));

    $archive = app(BackupArchiveCatalog::class)->all()[0] ?? null;

    expect($archive)->not->toBeNull()
        ->and($archive?->kind)->toBe('backup')
        ->and(app(VerifyBackup::class)->handle($archive->absolutePath)->files())->not->toBeEmpty();

    $component->assertSee($archive->name);
});

it('reports creation failure without a success notification', function (): void {
    $this->actingAs(User::factory()->create());
    config()->set('assestme.backup.private_storage_path', $this->backupRoot);

    Livewire::test(BackupSettingsPage::class)
        ->callAction(TestAction::make('createBackup')->table())
        ->assertNotified(__('assestme.backups.errors.creation_failed'))
        ->assertNotNotified(__('assestme.backups.notifications.created'));

    expect(app(BackupArchiveCatalog::class)->all())->toBe([]);
});

it('shows success only for a valid archive and leaves a corrupt archive available', function (): void {
    $this->actingAs(User::factory()->create());
    $name = 'assestme-20260725-023000.tar.gz';
    $path = $this->backupRoot.DIRECTORY_SEPARATOR.$name;
    app(CreateBackup::class)($path, false);

    Livewire::test(BackupSettingsPage::class)
        ->callAction(TestAction::make('verify')->table($name))
        ->assertNotified(__('assestme.backups.notifications.verified'));

    $archive = new PharData($path);
    $archive['metadata.json'] = '{"tampered":true}';
    unset($archive);

    Livewire::test(BackupSettingsPage::class)
        ->callAction(TestAction::make('verify')->table($name))
        ->assertNotified(__('assestme.backups.errors.verification_failed'))
        ->assertNotNotified(__('assestme.backups.notifications.verified'));

    expect(File::exists($path))->toBeTrue();
});

it('shows exact server-resolved CLI restore instructions without a web restore control', function (): void {
    $this->actingAs(User::factory()->create());
    $name = 'assestme-20260725-023000.tar.gz';
    $path = $this->backupRoot.DIRECTORY_SEPARATOR.$name;
    app(CreateBackup::class)($path, false);

    Livewire::test(BackupSettingsPage::class)
        ->mountAction(TestAction::make('restoreInstructions')->table($name))
        ->assertActionMounted(TestAction::make('restoreInstructions')->table($name))
        ->assertMountedActionModalSee([
            $name,
            "php artisan assestme:backup:verify '".str_replace("'", "'\\''", $path)."'",
            'php artisan down',
            "php artisan assestme:restore-backup '".str_replace("'", "'\\''", $path)."'",
            'php artisan up',
            __('assestme.backups.restore.rollback'),
        ])
        ->assertDontSee('Ripristina ora')
        ->assertDontSee('type="file"', escape: false);
});

it('deletes ordinary and safety backups only after the confirmed table action runs', function (string $name): void {
    $this->actingAs(User::factory()->create());
    $path = $this->backupRoot.DIRECTORY_SEPARATOR.$name;
    app(CreateBackup::class)($path, false);
    $component = Livewire::test(BackupSettingsPage::class)
        ->mountAction(TestAction::make('delete')->table($name))
        ->assertActionMounted(TestAction::make('delete')->table($name))
        ->assertMountedActionModalSee(__('assestme.backups.delete.description', ['name' => $name]));

    expect(File::exists($path))->toBeTrue();

    $component->callMountedAction()
        ->assertNotified(__('assestme.backups.notifications.deleted'))
        ->assertDontSee($name);

    expect(File::exists($path))->toBeFalse();
})->with([
    'ordinary' => 'assestme-20260725-023000.tar.gz',
    'safety' => 'assestme-safety-20260725-023000.tar.gz',
]);

it('does not delete an archive when the filesystem deletion fails', function (): void {
    $this->actingAs(User::factory()->create());
    $name = 'assestme-20260725-023000.tar.gz';
    $path = $this->backupRoot.DIRECTORY_SEPARATOR.$name;
    app(CreateBackup::class)($path, false);
    $files = Mockery::mock(Filesystem::class)->makePartial();
    $files->shouldReceive('delete')->once()->with($path)->andReturnFalse();
    app()->instance(Filesystem::class, $files);

    Livewire::test(BackupSettingsPage::class)
        ->callAction(TestAction::make('delete')->table($name))
        ->assertNotified(__('assestme.backups.errors.deletion_failed'))
        ->assertNotNotified(__('assestme.backups.notifications.deleted'));

    expect(File::exists($path))->toBeTrue();
});
