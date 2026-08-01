<?php

declare(strict_types=1);

use App\Actions\Backups\CreateBackup;
use App\Actions\Backups\RestoreBackup;
use App\Actions\Backups\VerifyBackup;
use App\Models\Client;
use App\Models\User;
use App\Services\Database\DatabaseClientBinaryResolver;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

it('round trips a real server database dump restore and private storage with safety compensation available', function (): void {
    $driver = (string) config('database.default');

    if (! in_array($driver, ['mysql', 'mariadb'], true)) {
        expect($driver)->toBe('sqlite');

        return;
    }

    config()->set('assestme.backup.dump_binary');
    config()->set('assestme.backup.restore_binary');
    $clients = app(DatabaseClientBinaryResolver::class);
    expect($clients->resolveDump($driver))->toBeString()->not->toBeEmpty()
        ->and($clients->resolveRestore($driver))->toBeString()->not->toBeEmpty();

    $backupRoot = (string) config('assestme.backup.root');
    $privateRoot = (string) config('assestme.backup.private_storage_path');
    $archive = $backupRoot.DIRECTORY_SEPARATOR.'server-round-trip.tar.gz';
    $privateMarker = $privateRoot.DIRECTORY_SEPARATOR.'server-round-trip.txt';

    File::ensureDirectoryExists($backupRoot, 0700, true);
    File::ensureDirectoryExists($privateRoot, 0700, true);

    try {
        expect(Artisan::call('migrate:fresh', ['--force' => true]))->toBe(0)
            ->and(Artisan::call('db:seed', ['--force' => true, '--class' => DatabaseSeeder::class]))->toBe(0);
        User::factory()->create(['email' => 'server-backup@assestme.invalid']);
        $client = Client::factory()->create(['legal_name' => 'Before real server backup']);
        File::put($privateMarker, 'before');

        $created = app(CreateBackup::class)($archive, false);
        $manifest = app(VerifyBackup::class)->handle($created);
        expect($manifest->database->driver)->toBe($driver)
            ->and($manifest->database->format)->toBe('sql')
            ->and($manifest->database->path)->toBe('database/database.sql');

        $client->update(['legal_name' => 'After real server backup']);
        File::put($privateMarker, 'after');
        expect(Artisan::call('down'))->toBe(0);

        $safetyBackup = app(RestoreBackup::class)($archive);
        DB::purge();

        expect(Client::query()->findOrFail($client->getKey())->legal_name)->toBe('Before real server backup')
            ->and(File::get($privateMarker))->toBe('before')
            ->and($safetyBackup)->toBeFile()
            ->and(app(VerifyBackup::class)->handle($safetyBackup)->database->driver)->toBe($driver);
    } finally {
        Artisan::call('up');
        File::delete($archive);
        File::delete($privateMarker);
    }
});
