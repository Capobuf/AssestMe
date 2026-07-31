<?php

declare(strict_types=1);

use App\Data\Installation\DatabaseConfigurationData;
use App\Data\Installation\InstallationDatabaseStatus;
use App\Enums\SupportedDatabaseDriver;
use App\Services\Installation\DatabaseCapabilityProbe;
use App\Services\Installation\DatabaseCapabilityProbeException;
use App\Services\Installation\DatabaseServerProductRecognizer;
use App\Services\Installation\InstallationDatabaseClassificationException;
use App\Services\Installation\InstallationDatabaseClassifier;
use PDO as NativePdo;
use RuntimeException as TestRuntimeException;

it('creates a missing private SQLite database with site-user-only permissions', function (): void {
    $directory = storage_path('app/database');
    if (! is_dir($directory)) {
        mkdir($directory, 0750, true);
    }
    $path = $directory.'/new-install.sqlite';

    if (is_file($path)) {
        unlink($path);
    }

    $result = app(DatabaseCapabilityProbe::class)->probe(new DatabaseConfigurationData(
        driver: SupportedDatabaseDriver::Sqlite,
        database: $path,
    ));

    expect($result->driver)->toBe(SupportedDatabaseDriver::Sqlite)
        ->and(is_file($path))->toBeTrue()
        ->and(fileperms($path) & 0777)->toBe(0600);
});

it('executes the complete SQLite capability probe and removes every probe table', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'assestme-probe-');

    if (! is_string($path)) {
        throw new TestRuntimeException('Unable to create the temporary SQLite database.');
    }

    $configuration = new DatabaseConfigurationData(
        driver: SupportedDatabaseDriver::Sqlite,
        database: $path,
    );

    try {
        $result = app(DatabaseCapabilityProbe::class)->probe($configuration);
        $classification = app(InstallationDatabaseClassifier::class)->classify(
            $configuration,
            (string) str()->uuid(),
        );

        expect($result->driver)->toBe(SupportedDatabaseDriver::Sqlite)
            ->and($result->product)->toBe('SQLite')
            ->and($result->serverVersion)->not->toBeEmpty()
            ->and($result->charset)->toBe('UTF-8')
            ->and($result->checks)->toContain(
                'connection',
                'create_table',
                'primary_key',
                'auto_increment',
                'unique_index',
                'foreign_key',
                'restrict_delete',
                'insert',
                'select',
                'update',
                'delete',
                'transaction_rollback',
                'decimal',
                'text',
                'timestamps',
                'foreign_keys',
                'wal',
                'busy_timeout',
                'transaction_mode',
                'cleanup',
            )
            ->and($classification->status)->toBe(InstallationDatabaseStatus::Empty)
            ->and($classification->tables)->toBe([]);
    } finally {
        foreach ([$path, $path.'-wal', $path.'-shm'] as $temporaryPath) {
            if (is_file($temporaryPath) || is_link($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
});

it('rejects unsafe SQLite paths before configuring the probe connection', function (): void {
    $probe = app(DatabaseCapabilityProbe::class);
    $relative = new DatabaseConfigurationData(
        driver: SupportedDatabaseDriver::Sqlite,
        database: 'database.sqlite',
    );
    $public = new DatabaseConfigurationData(
        driver: SupportedDatabaseDriver::Sqlite,
        database: public_path('database.sqlite'),
    );
    $traversal = new DatabaseConfigurationData(
        driver: SupportedDatabaseDriver::Sqlite,
        database: sys_get_temp_dir().'/../database.sqlite',
    );
    $target = tempnam(sys_get_temp_dir(), 'assestme-probe-target-');

    if (! is_string($target)) {
        throw new TestRuntimeException('Unable to create the SQLite symlink target.');
    }

    $link = $target.'.link';
    if (! symlink($target, $link)) {
        throw new TestRuntimeException('Unable to create the SQLite symlink fixture.');
    }

    try {
        expect(fn () => $probe->probe($relative))
            ->toThrow(DatabaseCapabilityProbeException::class, 'must be absolute')
            ->and(fn () => $probe->probe($public))
            ->toThrow(DatabaseCapabilityProbeException::class, 'must not be stored under the public directory')
            ->and(fn () => $probe->probe($traversal))
            ->toThrow(DatabaseCapabilityProbeException::class, 'must not contain parent traversal segments')
            ->and(fn () => $probe->probe(new DatabaseConfigurationData(
                driver: SupportedDatabaseDriver::Sqlite,
                database: $link,
            )))
            ->toThrow(DatabaseCapabilityProbeException::class, 'must not contain symbolic links');
    } finally {
        if (is_link($link)) {
            unlink($link);
        }

        if (is_file($target)) {
            unlink($target);
        }
    }
});

it('recognizes MySQL and MariaDB independently and rejects product mismatches', function (): void {
    $recognizer = app(DatabaseServerProductRecognizer::class);
    $mysql = $recognizer->recognize(
        SupportedDatabaseDriver::MySql,
        'server-version',
        'MySQL Community Server',
    );
    $mariaDb = $recognizer->recognize(
        SupportedDatabaseDriver::MariaDb,
        'server-version-MariaDB',
        'MariaDB Server',
    );

    expect($mysql->driver)->toBe(SupportedDatabaseDriver::MySql)
        ->and($mysql->product)->toBe('MySQL')
        ->and($mariaDb->driver)->toBe(SupportedDatabaseDriver::MariaDb)
        ->and($mariaDb->product)->toBe('MariaDB')
        ->and(fn () => $recognizer->recognize(
            SupportedDatabaseDriver::MySql,
            'server-version-MariaDB',
            'MariaDB Server',
        ))->toThrow(DatabaseCapabilityProbeException::class, 'Selected MySQL, but the server reports MariaDB.')
        ->and(fn () => $recognizer->recognize(
            SupportedDatabaseDriver::MariaDb,
            'server-version',
            'MySQL Community Server',
        ))->toThrow(DatabaseCapabilityProbeException::class, 'Selected MariaDB, but the server reports MySQL.')
        ->and(fn () => $recognizer->recognize(
            SupportedDatabaseDriver::MySql,
            'unknown-server',
            'unknown-product',
        ))->toThrow(DatabaseCapabilityProbeException::class, 'could not be recognized');
});

it('does not expose credentials when a server connection fails', function (): void {
    $configuration = new DatabaseConfigurationData(
        driver: SupportedDatabaseDriver::MySql,
        database: 'secret_database_name',
        host: '127.0.0.1',
        port: 1,
        username: 'secret_database_user',
        password: 'Secret database password #1',
    );

    try {
        app(DatabaseCapabilityProbe::class)->probe($configuration);
        throw new TestRuntimeException('The intentionally unavailable database server accepted a connection.');
    } catch (DatabaseCapabilityProbeException $exception) {
        $message = $exception->getMessage();
        expect($message)->toBe('Database capability probe failed during connection and authentication.');
        foreach ([$configuration->database, $configuration->username, $configuration->password] as $secret) {
            expect($message)->not->toContain($secret);
        }
    }
});

it('classifies only a matching marked installation as resumable or complete', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'assestme-classifier-');

    if (! is_string($path)) {
        throw new TestRuntimeException('Unable to create the temporary classification database.');
    }

    $configuration = new DatabaseConfigurationData(
        driver: SupportedDatabaseDriver::Sqlite,
        database: $path,
    );
    $installationId = (string) str()->uuid();
    $otherInstallationId = (string) str()->uuid();
    $classifier = app(InstallationDatabaseClassifier::class);

    try {
        expect($classifier->classify($configuration, $installationId)->status)
            ->toBe(InstallationDatabaseStatus::Empty);

        $classifier->markPartial($configuration, $installationId);
        $classifier->markPartial($configuration, $installationId);

        expect($classifier->classify($configuration, $installationId)->status)
            ->toBe(InstallationDatabaseStatus::RecognizedPartial)
            ->and($classifier->classify($configuration, $otherInstallationId)->status)
            ->toBe(InstallationDatabaseStatus::Foreign);

        $pdo = new NativePdo('sqlite:'.$path);
        $pdo->setAttribute(NativePdo::ATTR_ERRMODE, NativePdo::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, migration TEXT NOT NULL)');
        $pdo->exec("INSERT INTO migrations (migration) VALUES ('initial')");
        $pdo->exec('CREATE TABLE settings (id INTEGER PRIMARY KEY AUTOINCREMENT, payload TEXT NOT NULL)');
        $pdo->exec("INSERT INTO settings (payload) VALUES ('{}')");
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL)');
        $pdo->exec("INSERT INTO users (email) VALUES ('admin@example.test')");
        unset($pdo);

        $classifier->markComplete($configuration, $installationId);
        $classifier->markComplete($configuration, $installationId);

        expect($classifier->classify($configuration, $installationId)->status)
            ->toBe(InstallationDatabaseStatus::Complete)
            ->and($classifier->classify($configuration, $otherInstallationId)->status)
            ->toBe(InstallationDatabaseStatus::Foreign);
    } finally {
        foreach ([$path, $path.'-wal', $path.'-shm'] as $temporaryPath) {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
});

it('classifies an unknown non-empty database as foreign and never erases it', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'assestme-foreign-');

    if (! is_string($path)) {
        throw new TestRuntimeException('Unable to create the foreign SQLite database.');
    }

    $configuration = new DatabaseConfigurationData(
        driver: SupportedDatabaseDriver::Sqlite,
        database: $path,
    );
    $installationId = (string) str()->uuid();
    $classifier = app(InstallationDatabaseClassifier::class);

    try {
        $pdo = new NativePdo('sqlite:'.$path);
        $pdo->setAttribute(NativePdo::ATTR_ERRMODE, NativePdo::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE foreign_application_data (id INTEGER PRIMARY KEY, payload TEXT NOT NULL)');
        $pdo->exec("INSERT INTO foreign_application_data (id, payload) VALUES (1, 'preserve me')");
        unset($pdo);

        $classification = $classifier->classify($configuration, $installationId);

        expect($classification->status)->toBe(InstallationDatabaseStatus::Foreign)
            ->and($classification->tables)->toContain('foreign_application_data')
            ->and(fn () => $classifier->markPartial($configuration, $installationId))
            ->toThrow(
                InstallationDatabaseClassificationException::class,
                'must not be modified',
            );

        $pdo = new NativePdo('sqlite:'.$path);
        $statement = $pdo->query('SELECT payload FROM foreign_application_data WHERE id = 1');
        expect($statement)->not->toBeFalse();
        if ($statement === false) {
            throw new TestRuntimeException('The preserved foreign table could not be read.');
        }
        expect($statement->fetchColumn())->toBe('preserve me');
        unset($pdo);
    } finally {
        foreach ([$path, $path.'-wal', $path.'-shm'] as $temporaryPath) {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
});
