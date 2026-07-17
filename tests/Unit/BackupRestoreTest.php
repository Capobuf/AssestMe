<?php

declare(strict_types=1);

use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use App\Models\User;
use App\Services\Operations\OperationalCheckStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    Artisan::call('up');

    $this->backupWorkspace = storage_path('framework/testing-backups/'.Str::uuid());
    $this->databasePath = $this->backupWorkspace.DIRECTORY_SEPARATOR.'database.sqlite';
    $this->privateStoragePath = $this->backupWorkspace.DIRECTORY_SEPARATOR.'private';
    $this->backupRoot = $this->backupWorkspace.DIRECTORY_SEPARATOR.'archives';
    File::ensureDirectoryExists($this->privateStoragePath);
    File::ensureDirectoryExists($this->backupRoot);
    File::put($this->databasePath, '');

    $sqlite = config('database.connections.sqlite');
    config()->set('database.connections.backup_restore', [
        ...$sqlite,
        'database' => $this->databasePath,
    ]);
    config()->set('database.default', 'backup_restore');
    config()->set('assestme.backup.root', $this->backupRoot);
    config()->set('assestme.backup.private_storage_path', $this->privateStoragePath);
    config()->set('assestme.version', 'test-version');
    DB::purge();

    expect(Artisan::call('migrate', [
        '--database' => 'backup_restore',
        '--force' => true,
    ]))->toBe(0);
});

afterEach(function (): void {
    Artisan::call('up');
    DB::purge('backup_restore');
    File::deleteDirectory($this->backupWorkspace);
});

it('backs up, verifies, and restores the SQLite database and private storage together', function (): void {
    $administrator = User::factory()->create(['name' => 'Before backup']);
    $reportPath = $this->privateStoragePath.DIRECTORY_SEPARATOR.'reports'.DIRECTORY_SEPARATOR.'proof.txt';
    File::ensureDirectoryExists(dirname($reportPath));
    File::put($reportPath, "Report originale\nSeconda riga");
    $hiddenTrashPath = $this->privateStoragePath.DIRECTORY_SEPARATOR.'.trash'.DIRECTORY_SEPARATOR.'operation-1'.DIRECTORY_SEPARATOR.'staged.txt';
    File::ensureDirectoryExists(dirname($hiddenTrashPath));
    File::put($hiddenTrashPath, 'File staged originale');
    $hiddenPlaceholderPath = $this->privateStoragePath.DIRECTORY_SEPARATOR.'.gitignore';
    File::put($hiddenPlaceholderPath, "*\n!.gitignore\n");
    $archive = $this->backupRoot.DIRECTORY_SEPARATOR.'restore-source.tar.gz';

    expect(Artisan::call('assestme:backup', ['--output' => $archive]))->toBe(0)
        ->and(is_file($archive))->toBeTrue()
        ->and(Artisan::call('assestme:backup:verify', ['archive' => $archive]))->toBe(0)
        ->and(app(OperationalCheckStore::class)->find(OperationalCheckType::Backup)?->status)
        ->toBe(OperationalCheckStatus::Succeeded);

    $administrator->update(['name' => 'After backup']);
    File::put($reportPath, 'Report modificato');
    File::put($hiddenTrashPath, 'File staged modificato');
    File::delete($hiddenPlaceholderPath);
    File::put($this->privateStoragePath.DIRECTORY_SEPARATOR.'remove-me.txt', 'newer file');

    expect(Artisan::call('assestme:restore-backup', ['archive' => $archive]))->toBe(1);

    Artisan::call('down');

    try {
        $restoreExit = Artisan::call('assestme:restore-backup', ['archive' => $archive]);
    } finally {
        Artisan::call('up');
    }

    expect($restoreExit)->toBe(0)
        ->and(User::query()->firstOrFail()->name)->toBe('Before backup')
        ->and(File::get($reportPath))->toBe("Report originale\nSeconda riga")
        ->and(File::get($hiddenTrashPath))->toBe('File staged originale')
        ->and(File::get($hiddenPlaceholderPath))->toBe("*\n!.gitignore\n")
        ->and(File::exists($this->privateStoragePath.DIRECTORY_SEPARATOR.'remove-me.txt'))->toBeFalse()
        ->and(File::glob($this->backupRoot.DIRECTORY_SEPARATOR.'assestme-safety-*.tar.gz'))->toHaveCount(1);
});

it('returns a failure when an archived file no longer matches the manifest', function (): void {
    User::factory()->create();
    $archive = $this->backupRoot.DIRECTORY_SEPARATOR.'tampered.tar.gz';
    expect(Artisan::call('assestme:backup', ['--output' => $archive]))->toBe(0);

    $phar = new PharData($archive);
    $phar['metadata.json'] = '{"tampered":true}';
    unset($phar);

    expect(Artisan::call('assestme:backup:verify', ['archive' => $archive]))->toBe(1);
});

it('persists a failed backup attempt without reporting success', function (): void {
    User::factory()->create();
    $archive = $this->backupRoot.DIRECTORY_SEPARATOR.'already-exists.tar.gz';
    File::put($archive, 'existing archive');

    expect(Artisan::call('assestme:backup', ['--output' => $archive]))->toBe(1)
        ->and(Artisan::output())->toContain('already exists');

    $check = app(OperationalCheckStore::class)->find(OperationalCheckType::Backup);

    expect($check)->not->toBeNull()
        ->and($check?->status)->toBe(OperationalCheckStatus::Failed)
        ->and($check?->lastFailedAt)->not->toBeNull()
        ->and($check?->lastSucceededAt)->toBeNull()
        ->and($check?->errorText)->toContain('already exists');
});

it('restores an empty private storage directory', function (): void {
    User::factory()->create();
    $archive = $this->backupRoot.DIRECTORY_SEPARATOR.'empty-storage.tar.gz';
    expect(Artisan::call('assestme:backup', ['--output' => $archive]))->toBe(0);
    File::put($this->privateStoragePath.DIRECTORY_SEPARATOR.'newer.txt', 'not in backup');

    Artisan::call('down');

    try {
        $restoreExit = Artisan::call('assestme:restore-backup', ['archive' => $archive]);
    } finally {
        Artisan::call('up');
    }

    expect($restoreExit)->toBe(0)
        ->and(File::isDirectory($this->privateStoragePath))->toBeTrue()
        ->and(File::files($this->privateStoragePath))->toBeEmpty();
});

it('keeps safety archives while pruning managed daily weekly and monthly generations', function (): void {
    User::factory()->create();

    for ($daysAgo = 1; $daysAgo <= 250; $daysAgo++) {
        $date = now('UTC')->subDays($daysAgo);
        File::put(
            $this->backupRoot.DIRECTORY_SEPARATOR.'assestme-'.$date->format('Ymd-His').'.tar.gz',
            'archive',
        );
    }

    $safety = $this->backupRoot.DIRECTORY_SEPARATOR.'assestme-safety-20260101-020000.tar.gz';
    File::put($safety, 'safety');
    $current = $this->backupRoot.DIRECTORY_SEPARATOR.'assestme-'.now('UTC')->format('Ymd-His').'.tar.gz';
    expect(Artisan::call('assestme:backup', ['--output' => $current]))->toBe(0);
    $managed = File::glob($this->backupRoot.DIRECTORY_SEPARATOR.'assestme-[0-9]*.tar.gz');

    expect(count($managed))->toBeLessThanOrEqual(17)
        ->and(File::exists($current))->toBeTrue()
        ->and(File::exists($safety))->toBeTrue();
});

it('schedules the managed backup at 02:30 Europe Rome time', function (): void {
    $event = collect(Schedule::events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'assestme:backup'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 2 * * *')
        ->and($event->timezone)->toBe('Europe/Rome');
});

it('schedules the SQLite integrity check after the managed backup', function (): void {
    $event = collect(Schedule::events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'assestme:integrity-check'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 3 * * *')
        ->and($event->timezone)->toBe('Europe/Rome');
});
