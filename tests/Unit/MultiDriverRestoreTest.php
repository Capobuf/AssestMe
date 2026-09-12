<?php

declare(strict_types=1);

use App\Services\Database\DatabaseRestoreBinaryValidator;
use App\Services\Database\Restore\MariaDbRestorer;
use App\Services\Database\Restore\MySqlRestorer;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class);

function assestMeCreateFakeRestoreBinary(string $directory, string $name, string $version): string
{
    File::ensureDirectoryExists($directory, 0700, true);
    $path = $directory.DIRECTORY_SEPARATOR.$name;
    $script = str_replace('__VERSION__', $version, <<<'SHELL'
#!/bin/sh
if [ "$1" = "--version" ]; then
    printf '%s\n' '__VERSION__'
    exit 0
fi

credentials=''
for argument in "$@"; do
    case "$argument" in
        --defaults-file=*) credentials=${argument#*=} ;;
    esac
done

if [ -z "$credentials" ] || [ ! -f "$credentials" ]; then
    exit 3
fi

if [ -n "${DB_PASSWORD+x}" ] || [ -n "${MYSQL_PWD+x}" ]; then
    exit 4
fi

case "$0" in
    *mysql)
        if [ -z "$MYSQL_TEST_LOGIN_FILE" ] || [ -e "$MYSQL_TEST_LOGIN_FILE" ]; then
            exit 5
        fi
        ;;
    *mariadb)
        if [ -n "${MYSQL_TEST_LOGIN_FILE+x}" ]; then
            exit 6
        fi
        ;;
esac

printf '%s\n' "$@" > "$ASSESTME_TEST_RESTORE_ARGUMENTS"
stat -c '%a' "$credentials" > "$ASSESTME_TEST_RESTORE_CREDENTIAL_MODE"
cp "$credentials" "$ASSESTME_TEST_RESTORE_CREDENTIAL_COPY"
cat > "$ASSESTME_TEST_RESTORE_INPUT"

if [ "$ASSESTME_TEST_RESTORE_FAIL" = "1" ]; then
    printf '%s\n' "$ASSESTME_TEST_RESTORE_SECRET" >&2
    exit 7
fi
SHELL);
    File::put($path, $script);
    chmod($path, 0700);

    return $path;
}

/** @return Connection&MockInterface */
function assestMeRestoreConnection(
    string $driver,
    string $version,
    string $comment,
    string $password,
): Connection {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->once()->andReturn($driver);
    $connection->shouldReceive('selectOne')
        ->once()
        ->with('SELECT VERSION() AS version, @@version_comment AS version_comment')
        ->andReturn((object) [
            'version' => $version,
            'version_comment' => $comment,
        ]);
    $connection->shouldReceive('getConfig')->once()->with('host')->andReturn('127.0.0.1');
    $connection->shouldReceive('getConfig')->once()->with('port')->andReturn('3306');
    $connection->shouldReceive('getConfig')->once()->with('database')->andReturn('assestme');
    $connection->shouldReceive('getConfig')->once()->with('username')->andReturn('assestme_user');
    $connection->shouldReceive('getConfig')->once()->with('password')->andReturn($password);
    $connection->shouldReceive('getConfig')->once()->with('unix_socket')->andReturn('');
    $connection->shouldReceive('getConfig')->once()->with('options')->andReturn([]);

    return $connection;
}

beforeEach(function (): void {
    $this->restoreWorkspace = storage_path('framework/testing/multi-driver-restore-'.bin2hex(random_bytes(6)));
    $this->sql = $this->restoreWorkspace.DIRECTORY_SEPARATOR.'database.sql';
    $this->arguments = $this->restoreWorkspace.DIRECTORY_SEPARATOR.'arguments.txt';
    $this->credentialMode = $this->restoreWorkspace.DIRECTORY_SEPARATOR.'credential-mode.txt';
    $this->credentialCopy = $this->restoreWorkspace.DIRECTORY_SEPARATOR.'credentials.txt';
    $this->inputCopy = $this->restoreWorkspace.DIRECTORY_SEPARATOR.'input.sql';
    $this->originalRestoreBinary = config('assestme.backup.restore_binary');
    File::ensureDirectoryExists($this->restoreWorkspace, 0700, true);
    File::put($this->sql, "CREATE TABLE users (id BIGINT);\nINSERT INTO users VALUES (1);\n");

    foreach ([
        'ASSESTME_TEST_RESTORE_ARGUMENTS' => $this->arguments,
        'ASSESTME_TEST_RESTORE_CREDENTIAL_MODE' => $this->credentialMode,
        'ASSESTME_TEST_RESTORE_CREDENTIAL_COPY' => $this->credentialCopy,
        'ASSESTME_TEST_RESTORE_INPUT' => $this->inputCopy,
        'ASSESTME_TEST_RESTORE_FAIL' => '0',
        'ASSESTME_TEST_RESTORE_SECRET' => 'unused',
    ] as $key => $value) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
});

afterEach(function (): void {
    config()->set('assestme.backup.restore_binary', $this->originalRestoreBinary);

    foreach ([
        'ASSESTME_TEST_RESTORE_ARGUMENTS',
        'ASSESTME_TEST_RESTORE_CREDENTIAL_MODE',
        'ASSESTME_TEST_RESTORE_CREDENTIAL_COPY',
        'ASSESTME_TEST_RESTORE_INPUT',
        'ASSESTME_TEST_RESTORE_FAIL',
        'ASSESTME_TEST_RESTORE_SECRET',
    ] as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    File::deleteDirectory($this->restoreWorkspace);
});

it('streams a MySQL restore with isolated credentials and no shell or password argument', function (): void {
    $binary = assestMeCreateFakeRestoreBinary(
        $this->restoreWorkspace.DIRECTORY_SEPARATOR.'bin',
        'mysql',
        'mysql Ver 8.0.46 for Linux on x86_64 (MySQL Community Server - GPL)',
    );
    config()->set('assestme.backup.restore_binary', $binary);
    $password = ' leading # $ " \\ password'."\n".'next ';
    $connection = assestMeRestoreConnection('mysql', '8.0.46', 'MySQL Community Server', $password);

    app(MySqlRestorer::class)->restore($connection, $this->sql);
    $arguments = file($this->arguments, FILE_IGNORE_NEW_LINES);
    $credentialsArgument = collect($arguments)
        ->first(static fn (string $argument): bool => str_starts_with($argument, '--defaults-file='));

    expect($arguments)->toContain('--binary-mode')
        ->and($arguments)->toContain('--default-character-set=utf8mb4')
        ->and($arguments)->toContain('--local-infile=0')
        ->and($arguments)->toContain('--database=assestme')
        ->and($arguments)->not->toContain('--force')
        ->and(implode("\n", $arguments))->not->toContain($password)
        ->and(trim(File::get($this->credentialMode)))->toBe('600')
        ->and(File::get($this->credentialCopy))->toContain('password=" leading # $ \" \\\\ password\\nnext "')
        ->and(File::get($this->inputCopy))->toBe(File::get($this->sql))
        ->and($credentialsArgument)->toBeString()
        ->and(File::exists(substr((string) $credentialsArgument, strlen('--defaults-file='))))->toBeFalse();
});

it('uses the MariaDB client identity and returns a sanitized nonzero import failure', function (): void {
    $binary = assestMeCreateFakeRestoreBinary(
        $this->restoreWorkspace.DIRECTORY_SEPARATOR.'bin',
        'mariadb',
        'mariadb Ver 15.1 Distrib 10.11.18-MariaDB',
    );
    config()->set('assestme.backup.restore_binary', $binary);
    $password = 'do not expose';
    $secret = 'stderr secret';
    putenv('ASSESTME_TEST_RESTORE_FAIL=1');
    putenv("ASSESTME_TEST_RESTORE_SECRET={$secret}");
    $_ENV['ASSESTME_TEST_RESTORE_FAIL'] = '1';
    $_SERVER['ASSESTME_TEST_RESTORE_FAIL'] = '1';
    $_ENV['ASSESTME_TEST_RESTORE_SECRET'] = $secret;
    $_SERVER['ASSESTME_TEST_RESTORE_SECRET'] = $secret;
    $connection = assestMeRestoreConnection(
        'mariadb',
        '10.11.18-MariaDB',
        'mariadb.org binary distribution',
        $password,
    );

    expect(fn () => app(MariaDbRestorer::class)->restore($connection, $this->sql))
        ->toThrow(RuntimeException::class, 'The MariaDB database restore process failed.')
        ->and(File::get($this->credentialCopy))->toContain('ssl-verify-server-cert=0')
        ->and(File::glob($this->restoreWorkspace.DIRECTORY_SEPARATOR.'.assestme-db-credentials-*'))->toBeEmpty();
});

it('accepts a verified restore symlink and rejects basenames or product mismatches', function (): void {
    $validator = new DatabaseRestoreBinaryValidator;
    $real = assestMeCreateFakeRestoreBinary(
        $this->restoreWorkspace.DIRECTORY_SEPARATOR.'real',
        'mysql',
        'mysql Ver 8.0.46 MySQL Community Server',
    );
    $linkDirectory = $this->restoreWorkspace.DIRECTORY_SEPARATOR.'link';
    File::ensureDirectoryExists($linkDirectory, 0700, true);
    $link = $linkDirectory.DIRECTORY_SEPARATOR.'mysql';
    symlink($real, $link);
    $wrongProduct = assestMeCreateFakeRestoreBinary(
        $this->restoreWorkspace.DIRECTORY_SEPARATOR.'wrong-product',
        'mysql',
        'mysql Ver 15.1 Distrib 10.11.18-MariaDB',
    );
    $wrongName = assestMeCreateFakeRestoreBinary(
        $this->restoreWorkspace.DIRECTORY_SEPARATOR.'wrong-name',
        'client',
        'mysql Ver 8.0.46 MySQL Community Server',
    );

    expect($validator->validate('mysql', $link))->toBe((string) realpath($real))
        ->and(fn (): string => $validator->validate('mysql', $wrongName))
        ->toThrow(RuntimeException::class, 'restore binary is invalid')
        ->and(fn (): string => $validator->validate('mysql', $wrongProduct))
        ->toThrow(RuntimeException::class, 'does not match driver mysql');
});
