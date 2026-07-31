<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Diagnostics\ApplicationDiagnostics;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use PDO as NativePdo;

/** @return Connection&MockInterface */
function assestMeDiagnosticServerConnection(
    string $driver,
    string $version,
    string $versionComment,
): Connection {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);
    $users = Mockery::mock(Builder::class);

    $connection->shouldReceive('getPdo')->once()->andReturn(new NativePdo('sqlite::memory:'));
    $connection->shouldReceive('getDriverName')->times(3)->andReturn($driver);
    $connection->shouldReceive('getName')->once()->andReturn("{$driver}-diagnostic");
    $connection->shouldReceive('getDatabaseName')->twice()->andReturn('assestme');
    $connection->shouldReceive('table')->once()->with('users')->andReturn($users);
    $users->shouldReceive('count')->once()->andReturn(1);
    $connection->shouldReceive('selectOne')
        ->once()
        ->with('SELECT VERSION() AS version, @@version_comment AS version_comment')
        ->andReturn((object) ['version' => $version, 'version_comment' => $versionComment]);
    $connection->shouldReceive('selectOne')
        ->once()
        ->with(
            'SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation '
            .'FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            ['assestme'],
        )
        ->andReturn((object) ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);
    $connection->shouldReceive('select')
        ->once()
        ->with(
            'SELECT TABLE_NAME AS table_name, ENGINE AS engine, TABLE_COLLATION AS table_collation '
            .'FROM information_schema.TABLES '
            ."WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
            ['assestme'],
        )
        ->andReturn([(object) [
            'table_name' => 'migrations',
            'engine' => 'InnoDB',
            'table_collation' => 'utf8mb4_unicode_ci',
        ]]);
    $grammar->shouldReceive('wrap')->once()->with('migrations')->andReturn('`migrations`');
    $connection->shouldReceive('getQueryGrammar')->once()->andReturn($grammar);
    $connection->shouldReceive('select')
        ->once()
        ->with('CHECK TABLE `migrations`')
        ->andReturn([(object) ['Msg_type' => 'status', 'Msg_text' => 'OK']]);

    return $connection;
}

it('emits a machine-readable driver-aware SQLite diagnostic report', function (): void {
    User::factory()->create();

    $exitCode = Artisan::call('assestme:diagnose', ['--json' => true]);
    /** @var array{
     *     ok: bool,
     *     database: array{driver: string, product: string|null, server_version: string|null},
     *     checks: array<string, array{group: string, status: string, detail: string}>
     * } $report
     */
    $report = json_decode(Artisan::output(), true, 64, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($report['ok'])->toBeTrue()
        ->and($report['database']['driver'])->toBe('sqlite')
        ->and($report['database']['product'])->toBe('SQLite')
        ->and($report['database']['server_version'])->not->toBeEmpty()
        ->and($report['checks']['database_extension']['detail'])->toBe('pdo_sqlite')
        ->and($report['checks']['database_integrity']['status'])->toBe('passed')
        ->and($report['checks']['sqlite_foreign_keys']['status'])->toBe('passed')
        ->and($report['checks']['sqlite_journal_mode']['status'])->toBe('passed')
        ->and($report['checks']['sqlite_busy_timeout']['status'])->toBe('passed')
        ->and($report['checks']['sqlite_synchronous']['status'])->toBe('passed')
        ->and($report['checks']['sqlite_transaction_mode']['status'])->toBe('passed')
        ->and($report['checks'])->not->toHaveKeys([
            'database_charset',
            'database_collation',
            'database_engine',
            'database_utilities',
        ]);
});

it('renders the default diagnostic output for an administrator in Italian', function (): void {
    User::factory()->create();

    $this->artisan('assestme:diagnose')
        ->expectsOutputToContain('Diagnostica AssestMe')
        ->expectsOutputToContain('Integrità database')
        ->expectsOutputToContain('Superato')
        ->assertSuccessful();
});

it('renders the authenticated driver-aware administrative diagnostics page', function (): void {
    $administrator = User::factory()->create();

    $this->actingAs($administrator)
        ->get('/admin/settings/diagnostics')
        ->assertOk()
        ->assertSee('Diagnostica AssestMe')
        ->assertSee('SQLite')
        ->assertSee('Heartbeat scheduler')
        ->assertSee('Stato backup');
});

it('fails diagnostics when the singleton administrator is missing', function (): void {
    $exitCode = Artisan::call('assestme:diagnose', ['--json' => true]);
    /** @var array{ok: bool, checks: array<string, array{group: string, status: string, detail: string}>} $report */
    $report = json_decode(Artisan::output(), true, 64, JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(1)
        ->and($report['ok'])->toBeFalse()
        ->and($report['checks']['single_administrator']['status'])->toBe('failed')
        ->and($report['checks']['single_administrator']['detail'])->toBe('0');
});

it('delegates driver SQL to integrity checkers without embedding PRAGMA queries', function (): void {
    $source = File::get((new ReflectionClass(ApplicationDiagnostics::class))->getFileName());

    expect($source)->not->toContain('PRAGMA');
});

it('reports server database identity encoding engine utilities and integrity without PRAGMA', function (
    string $driver,
    string $version,
    string $versionComment,
    string $product,
): void {
    $workspace = storage_path('framework/testing/diagnostic-binaries-'.bin2hex(random_bytes(6)));
    $dumpName = $driver === 'mysql' ? 'mysqldump' : 'mariadb-dump';
    $restoreName = $driver === 'mysql' ? 'mysql' : 'mariadb';
    $dumpBinary = $workspace.DIRECTORY_SEPARATOR.$dumpName;
    $restoreBinary = $workspace.DIRECTORY_SEPARATOR.$restoreName;
    $dumpVersionOutput = $driver === 'mysql'
        ? 'mysqldump Ver 8.0.46 for Linux on x86_64 (MySQL Community Server - GPL)'
        : 'mariadb-dump Ver 15.1 Distrib 11.8.6-MariaDB';
    $restoreVersionOutput = $driver === 'mysql'
        ? 'mysql Ver 8.0.46 for Linux on x86_64 (MySQL Community Server - GPL)'
        : 'mariadb Ver 15.1 Distrib 11.8.6-MariaDB';
    $originalDump = config('assestme.backup.dump_binary');
    $originalRestore = config('assestme.backup.restore_binary');

    File::ensureDirectoryExists($workspace);
    File::put($dumpBinary, "#!/bin/sh\nprintf '%s\\n' '{$dumpVersionOutput}'\n");
    File::put($restoreBinary, "#!/bin/sh\nprintf '%s\\n' '{$restoreVersionOutput}'\n");
    chmod($dumpBinary, 0700);
    chmod($restoreBinary, 0700);
    config()->set('assestme.backup.dump_binary', $dumpBinary);
    config()->set('assestme.backup.restore_binary', $restoreBinary);

    try {
        $report = app(ApplicationDiagnostics::class)->run(
            assestMeDiagnosticServerConnection($driver, $version, $versionComment),
        );
        $checks = collect($report->checks)->keyBy('key');

        expect($report->driver)->toBe($driver)
            ->and($report->product)->toBe($product)
            ->and($report->serverVersion)->toBe($version)
            ->and($checks->get('database_extension')?->status->value)->toBe('passed')
            ->and($checks->get('database_identity')?->detail)->toBe($product)
            ->and($checks->get('database_version')?->detail)->toBe($version)
            ->and($checks->get('database_charset')?->detail)->toBe('utf8mb4')
            ->and($checks->get('database_collation')?->detail)->toBe('utf8mb4_unicode_ci')
            ->and($checks->get('database_engine')?->detail)->toBe('InnoDB')
            ->and($checks->get('database_utilities')?->status->value)->toBe('passed')
            ->and($checks->get('database_integrity')?->status->value)->toBe('passed')
            ->and($checks->has('sqlite_foreign_keys'))->toBeFalse();
    } finally {
        config()->set('assestme.backup.dump_binary', $originalDump);
        config()->set('assestme.backup.restore_binary', $originalRestore);
        File::deleteDirectory($workspace);
    }
})->with([
    'MySQL' => ['mysql', 'server-version', 'MySQL Community Server', 'MySQL'],
    'MariaDB' => ['mariadb', 'server-version-MariaDB', 'MariaDB Server', 'MariaDB'],
]);
