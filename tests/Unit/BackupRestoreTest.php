<?php

declare(strict_types=1);

use App\Actions\Backups\CreateBackup;
use App\Actions\Backups\RestoreBackup;
use App\Actions\Backups\VerifyBackup;
use App\Enums\OperationalCheckStatus;
use App\Enums\OperationalCheckType;
use App\Models\User;
use App\Services\Database\DatabaseServerIdentityResolver;
use App\Services\Database\Restore\DatabaseRestorer;
use App\Services\Database\Restore\DatabaseRestorerResolver;
use App\Services\Database\Restore\DatabaseRestoreVerifier;
use App\Services\Database\Restore\SqliteRestorer;
use App\Services\Operations\OperationalCheckStore;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

function assestMeRemoveAdministratorFromBackup(string $archive, string $workspace): void
{
    $database = $workspace.DIRECTORY_SEPARATOR.'adminless.sqlite';
    $phar = new PharData($archive);
    File::put($database, $phar['database/database.sqlite']->getContent());
    $pdo = new PDO('sqlite:'.$database);
    $pdo->exec('DELETE FROM users');
    $pdo = null;
    $contents = File::get($database);
    $phar['database/database.sqlite'] = $contents;
    $manifest = json_decode($phar['manifest.json']->getContent(), true, flags: JSON_THROW_ON_ERROR);

    foreach ($manifest['files'] as &$file) {
        if (($file['path'] ?? null) === 'database/database.sqlite') {
            $file['size'] = strlen($contents);
            $file['sha256'] = hash('sha256', $contents);
        }
    }

    unset($file);
    $phar['manifest.json'] = json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
    ).PHP_EOL;
    unset($phar);
}

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
        ->and(app(VerifyBackup::class)->handle($archive)->schemaVersion)->toBe(2)
        ->and(app(VerifyBackup::class)->handle($archive)->database->driver)->toBe('sqlite')
        ->and(app(VerifyBackup::class)->handle($archive)->database->path)->toBe('database/database.sqlite')
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

it('rejects archives with a missing manifest extra file or missing manifested file', function (string $mutation): void {
    User::factory()->create();
    $archive = $this->backupRoot.DIRECTORY_SEPARATOR."{$mutation}.tar.gz";
    expect(Artisan::call('assestme:backup', ['--output' => $archive]))->toBe(0);

    $phar = new PharData($archive);

    match ($mutation) {
        'missing-manifest' => $phar->delete('manifest.json'),
        'extra-file' => $phar['unexpected.txt'] = 'unexpected',
        'missing-file' => $phar->delete('metadata.json'),
    };

    unset($phar);

    expect(Artisan::call('assestme:backup:verify', ['archive' => $archive]))->toBe(1);
})->with(['missing-manifest', 'extra-file', 'missing-file']);

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

it('imports the safety backup when a restored database fails the final checks', function (): void {
    $administrator = User::factory()->create(['name' => 'Source administrator']);
    $archive = $this->backupRoot.DIRECTORY_SEPARATOR.'adminless-source.tar.gz';
    File::put($this->privateStoragePath.DIRECTORY_SEPARATOR.'state.txt', 'source storage');
    expect(Artisan::call('assestme:backup', ['--output' => $archive]))->toBe(0);
    assestMeRemoveAdministratorFromBackup($archive, $this->backupWorkspace);
    $administrator->update(['name' => 'Safety administrator']);
    File::put($this->privateStoragePath.DIRECTORY_SEPARATOR.'state.txt', 'safety storage');
    Artisan::call('down');

    try {
        $exit = Artisan::call('assestme:restore-backup', ['archive' => $archive]);
        $stillDown = app()->isDownForMaintenance();
        $output = Artisan::output();
    } finally {
        Artisan::call('up');
    }

    expect($exit)->toBe(1)
        ->and($stillDown)->toBeTrue()
        ->and($output)->toContain('safety backup was reinstated')
        ->and(User::query()->sole()->name)->toBe('Safety administrator')
        ->and(File::get($this->privateStoragePath.DIRECTORY_SEPARATOR.'state.txt'))->toBe('safety storage')
        ->and(File::glob($this->backupRoot.DIRECTORY_SEPARATOR.'assestme-safety-*.tar.gz'))->toHaveCount(1);
});

it('preserves recovery artifacts and reports both archives when safety compensation fails', function (): void {
    User::factory()->create();
    $archive = $this->backupRoot.DIRECTORY_SEPARATOR.'failed-compensation-source.tar.gz';
    expect(Artisan::call('assestme:backup', ['--output' => $archive]))->toBe(0);
    assestMeRemoveAdministratorFromBackup($archive, $this->backupWorkspace);
    $actualRestorer = app(SqliteRestorer::class);
    $failingRestorer = new class($actualRestorer) implements DatabaseRestorer
    {
        private int $calls = 0;

        public function __construct(private readonly SqliteRestorer $actual) {}

        public function restore(Connection $connection, string $source): void
        {
            $this->calls++;

            if ($this->calls === 2) {
                throw new RuntimeException('Deliberate compensation failure.');
            }

            $this->actual->restore($connection, $source);
        }
    };
    $resolver = Mockery::mock(DatabaseRestorerResolver::class);
    $resolver->shouldReceive('resolve')->once()->andReturn($failingRestorer);
    $restore = new RestoreBackup(
        new Filesystem,
        app(CreateBackup::class),
        app(VerifyBackup::class),
        $resolver,
        app(DatabaseRestoreVerifier::class),
        app(DatabaseServerIdentityResolver::class),
    );
    $restoreRoot = storage_path('framework/assestme-restore');
    $before = File::isDirectory($restoreRoot) ? File::directories($restoreRoot) : [];
    Artisan::call('down');
    $exception = null;

    try {
        $restore($archive);
    } catch (RuntimeException $caught) {
        $exception = $caught;
    }

    $remaining = File::isDirectory($restoreRoot)
        ? array_values(array_diff(File::directories($restoreRoot), $before))
        : [];

    expect(app()->isDownForMaintenance())->toBeTrue()
        ->and($exception?->getMessage())->toContain('Source: '.$archive)
        ->and($exception?->getMessage())->toContain('safety: '.$this->backupRoot)
        ->and($exception?->getMessage())->toContain('recovery files: ')
        ->and($remaining)->not->toBeEmpty()
        ->and(File::glob($this->backupRoot.DIRECTORY_SEPARATOR.'assestme-safety-*.tar.gz'))->toHaveCount(1);

    Artisan::call('up');
    DB::purge('backup_restore');

    foreach ($remaining as $directory) {
        File::deleteDirectory($directory);
    }
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
