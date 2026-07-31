<?php

declare(strict_types=1);

use App\Data\Database\DatabaseIntegrityResult;
use App\Services\Database\DatabaseDriverResolver;
use App\Services\Database\Integrity\MariaDbIntegrityChecker;
use App\Services\Database\Integrity\MySqlIntegrityChecker;
use App\Services\Database\Integrity\SqliteIntegrityChecker;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\Grammar;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return Connection&MockInterface
 */
function assestMeServerIntegrityConnection(
    string $driver,
    string $version,
    string $versionComment,
    string $engine = 'InnoDB',
    string $checkMessageType = 'status',
    string $checkMessage = 'OK',
): Connection {
    $connection = Mockery::mock(Connection::class);
    $grammar = Mockery::mock(Grammar::class);

    $connection->shouldReceive('getDriverName')->once()->andReturn($driver);
    $connection->shouldReceive('getDatabaseName')->once()->andReturn('assestme');
    $connection->shouldReceive('selectOne')
        ->once()
        ->with('SELECT VERSION() AS version, @@version_comment AS version_comment')
        ->andReturn((object) [
            'version' => $version,
            'version_comment' => $versionComment,
        ]);
    $connection->shouldReceive('selectOne')
        ->once()
        ->with(
            'SELECT DEFAULT_CHARACTER_SET_NAME AS charset, DEFAULT_COLLATION_NAME AS collation '
            .'FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
            ['assestme'],
        )
        ->andReturn((object) [
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);
    $connection->shouldReceive('select')
        ->once()
        ->with(
            'SELECT TABLE_NAME AS table_name, ENGINE AS engine, TABLE_COLLATION AS table_collation '
            .'FROM information_schema.TABLES '
            ."WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
            ['assestme'],
        )
        ->andReturn([
            (object) [
                'table_name' => 'migrations',
                'engine' => $engine,
                'table_collation' => 'utf8mb4_unicode_ci',
            ],
        ]);
    $grammar->shouldReceive('wrap')->zeroOrMoreTimes()->with('migrations')->andReturn('`migrations`');
    $connection->shouldReceive('getQueryGrammar')->zeroOrMoreTimes()->andReturn($grammar);
    $connection->shouldReceive('select')
        ->zeroOrMoreTimes()
        ->with('CHECK TABLE `migrations`')
        ->andReturn([
            (object) [
                'Table' => 'assestme.migrations',
                'Op' => 'check',
                'Msg_type' => $checkMessageType,
                'Msg_text' => $checkMessage,
            ],
        ]);

    return $connection;
}

it('returns a typed result after checking all required SQLite runtime settings', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->once()->andReturn('sqlite');
    $connection->shouldReceive('select')
        ->once()
        ->with('PRAGMA integrity_check')
        ->andReturn([(object) ['integrity_check' => 'ok']]);
    $connection->shouldReceive('scalar')->once()->with('PRAGMA foreign_keys')->andReturn(1);
    $connection->shouldReceive('scalar')->once()->with('PRAGMA journal_mode')->andReturn('wal');
    $connection->shouldReceive('scalar')->once()->with('PRAGMA busy_timeout')->andReturn(5000);
    $connection->shouldReceive('scalar')->once()->with('PRAGMA synchronous')->andReturn(1);
    $connection->shouldReceive('getConfig')->once()->with('transaction_mode')->andReturn('IMMEDIATE');
    $connection->shouldReceive('selectOne')
        ->once()
        ->with('SELECT sqlite_version() AS version')
        ->andReturn((object) ['version' => '3.46.1']);
    $connection->shouldReceive('getDatabaseName')->once()->andReturn('/private/database.sqlite');

    $result = (new SqliteIntegrityChecker)->check($connection);

    expect($result)->toBeInstanceOf(DatabaseIntegrityResult::class)
        ->and($result->healthy)->toBeTrue()
        ->and($result->driver)->toBe('sqlite')
        ->and($result->product)->toBe('SQLite')
        ->and($result->serverVersion)->toBe('3.46.1')
        ->and($result->details)->toMatchArray([
            'foreign_keys' => true,
            'journal_mode' => 'WAL',
            'busy_timeout_ms' => 5000,
            'synchronous' => 'NORMAL',
            'transaction_mode' => 'IMMEDIATE',
        ]);
});

it('keeps SQLite integrity failures explicit', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->once()->andReturn('sqlite');
    $connection->shouldReceive('select')
        ->once()
        ->with('PRAGMA integrity_check')
        ->andReturn([(object) ['integrity_check' => 'database disk image is malformed']]);

    expect(fn (): DatabaseIntegrityResult => (new SqliteIntegrityChecker)->check($connection))
        ->toThrow(RuntimeException::class, 'database disk image is malformed');
});

it('checks MySQL identity schema settings InnoDB tables and every CHECK TABLE result', function (): void {
    $connection = assestMeServerIntegrityConnection(
        driver: 'mysql',
        version: '8.4.5',
        versionComment: 'MySQL Community Server - GPL',
    );

    $result = (new MySqlIntegrityChecker)->check($connection);

    expect($result->healthy)->toBeTrue()
        ->and($result->driver)->toBe('mysql')
        ->and($result->product)->toBe('MySQL')
        ->and($result->database)->toBe('assestme')
        ->and($result->checkedTables)->toBe(['migrations'])
        ->and($result->details['check_table'])->toBe('OK');
});

it('checks MariaDB independently from MySQL', function (): void {
    $connection = assestMeServerIntegrityConnection(
        driver: 'mariadb',
        version: '11.8.2-MariaDB-ubu2404',
        versionComment: 'mariadb.org binary distribution',
    );

    $result = (new MariaDbIntegrityChecker)->check($connection);

    expect($result->healthy)->toBeTrue()
        ->and($result->driver)->toBe('mariadb')
        ->and($result->product)->toBe('MariaDB')
        ->and($result->serverVersion)->toBe('11.8.2-MariaDB-ubu2404');
});

it('rejects a MariaDB server selected as MySQL', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
    $connection->shouldReceive('selectOne')
        ->once()
        ->with('SELECT VERSION() AS version, @@version_comment AS version_comment')
        ->andReturn((object) [
            'version' => '11.8.2-MariaDB-ubu2404',
            'version_comment' => 'mariadb.org binary distribution',
        ]);

    expect(fn (): DatabaseIntegrityResult => (new MySqlIntegrityChecker)->check($connection))
        ->toThrow(RuntimeException::class, 'configured MySQL, detected MariaDB');
});

it('rejects a MySQL server selected as MariaDB', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->once()->andReturn('mariadb');
    $connection->shouldReceive('selectOne')
        ->once()
        ->with('SELECT VERSION() AS version, @@version_comment AS version_comment')
        ->andReturn((object) [
            'version' => '8.4.5',
            'version_comment' => 'MySQL Community Server - GPL',
        ]);

    expect(fn (): DatabaseIntegrityResult => (new MariaDbIntegrityChecker)->check($connection))
        ->toThrow(RuntimeException::class, 'configured MariaDB, detected MySQL');
});

it('does not accept non-InnoDB application tables', function (): void {
    $connection = assestMeServerIntegrityConnection(
        driver: 'mysql',
        version: '8.4.5',
        versionComment: 'MySQL Community Server - GPL',
        engine: 'MyISAM',
    );

    expect(fn (): DatabaseIntegrityResult => (new MySqlIntegrityChecker)->check($connection))
        ->toThrow(RuntimeException::class, 'must use InnoDB');
});

it('does not treat CHECK TABLE warnings as success', function (): void {
    $connection = assestMeServerIntegrityConnection(
        driver: 'mariadb',
        version: '11.8.2-MariaDB-ubu2404',
        versionComment: 'mariadb.org binary distribution',
        checkMessageType: 'warning',
        checkMessage: 'Table is marked as crashed',
    );

    expect(fn (): DatabaseIntegrityResult => (new MariaDbIntegrityChecker)->check($connection))
        ->toThrow(RuntimeException::class, 'CHECK TABLE failed for migrations');
});

it('resolves only the three supported Laravel database drivers', function (string $driver, string $checker): void {
    $resolver = new DatabaseDriverResolver(
        new SqliteIntegrityChecker,
        new MySqlIntegrityChecker,
        new MariaDbIntegrityChecker,
    );
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->once()->andReturn($driver);

    expect($resolver->integrityChecker($connection))->toBeInstanceOf($checker);
})->with([
    'SQLite' => ['sqlite', SqliteIntegrityChecker::class],
    'MySQL' => ['mysql', MySqlIntegrityChecker::class],
    'MariaDB' => ['mariadb', MariaDbIntegrityChecker::class],
]);

it('rejects an unsupported database driver instead of falling back', function (): void {
    $resolver = new DatabaseDriverResolver(
        new SqliteIntegrityChecker,
        new MySqlIntegrityChecker,
        new MariaDbIntegrityChecker,
    );
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');

    expect(fn () => $resolver->integrityChecker($connection))
        ->toThrow(RuntimeException::class, 'Unsupported database driver: pgsql');
});
