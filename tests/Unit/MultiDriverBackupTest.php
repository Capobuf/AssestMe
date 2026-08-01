<?php

declare(strict_types=1);

use App\Actions\Backups\CreateBackup;
use App\Actions\Backups\PruneBackups;
use App\Actions\Backups\RestoreBackup;
use App\Actions\Backups\VerifyBackup;
use App\Data\Backups\BackupManifest;
use App\Services\Database\DatabaseClientBinaryResolver;
use App\Services\Database\DatabaseDumpBinaryValidator;
use App\Services\Database\DatabaseRestoreBinaryValidator;
use App\Services\Database\Snapshot\DatabaseSnapshotterResolver;
use App\Services\Database\Snapshot\MariaDbSnapshotter;
use App\Services\Database\Snapshot\MySqlSnapshotter;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class);

function assestMeCreateFakeDumpBinary(string $directory, string $name, string $version): string
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
    *mysqldump)
        if [ -z "$MYSQL_TEST_LOGIN_FILE" ] || [ -e "$MYSQL_TEST_LOGIN_FILE" ]; then
            exit 5
        fi
        ;;
    *mariadb-dump)
        if [ -n "${MYSQL_TEST_LOGIN_FILE+x}" ]; then
            exit 6
        fi
        ;;
esac

printf '%s\n' "$@" > "$ASSESTME_TEST_DUMP_ARGUMENTS"
stat -c '%a' "$credentials" > "$ASSESTME_TEST_CREDENTIAL_MODE"
cp "$credentials" "$ASSESTME_TEST_CREDENTIAL_COPY"

if [ "$ASSESTME_TEST_DUMP_FAIL" = "1" ]; then
    printf '%s\n' "$ASSESTME_TEST_DUMP_SECRET" >&2
    exit 7
fi

printf '%s\n' '-- AssestMe SQL dump' 'CREATE TABLE migrations (id BIGINT);'
SHELL);
    File::put($path, $script);
    chmod($path, 0700);

    return $path;
}

/**
 * @return Connection&MockInterface
 */
function assestMeDumpConnection(
    string $driver,
    string $version,
    string $versionComment,
    string $password,
    string $socket = '',
    int $driverCalls = 1,
    string $database = 'assestme',
): Connection {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->times($driverCalls)->andReturn($driver);
    $connection->shouldReceive('selectOne')
        ->once()
        ->with('SELECT VERSION() AS version, @@version_comment AS version_comment')
        ->andReturn((object) [
            'version' => $version,
            'version_comment' => $versionComment,
        ]);
    $connection->shouldReceive('getConfig')->once()->with('host')->andReturn('127.0.0.1');
    $connection->shouldReceive('getConfig')->once()->with('port')->andReturn('3306');
    $connection->shouldReceive('getConfig')->once()->with('database')->andReturn($database);
    $connection->shouldReceive('getConfig')->once()->with('username')->andReturn('assestme_user');
    $connection->shouldReceive('getConfig')->once()->with('password')->andReturn($password);
    $connection->shouldReceive('getConfig')->once()->with('unix_socket')->andReturn($socket);

    return $connection;
}

/**
 * @param array{
 *     driver?: string,
 *     product?: string,
 *     server_version?: string,
 *     format?: string,
 *     path?: string
 * } $database
 */
function assestMeCreateManifestArchive(string $root, int $schemaVersion, array $database = []): string
{
    $stage = $root.DIRECTORY_SEPARATOR.'stage-'.bin2hex(random_bytes(6));
    $databasePath = $schemaVersion === 1
        ? 'database/database.sqlite'
        : (string) ($database['path'] ?? '');
    $payload = $stage.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $databasePath);
    File::ensureDirectoryExists(dirname($payload), 0700, true);
    File::put($payload, $schemaVersion === 1 ? 'SQLite format 3' : '-- SQL dump');
    File::put($stage.DIRECTORY_SEPARATOR.'metadata.json', '{"format":"assestme-backup"}');
    $files = [];

    foreach ([$databasePath, 'metadata.json'] as $relativePath) {
        $path = $stage.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $files[] = [
            'path' => $relativePath,
            'size' => filesize($path),
            'sha256' => hash_file('sha256', $path),
        ];
    }

    $manifest = [
        'schema_version' => $schemaVersion,
        'created_at' => '2026-07-31T18:00:00Z',
        'application_version' => 'test',
    ];

    if ($schemaVersion === 2) {
        $manifest['database'] = $database;
    }

    $manifest['files'] = $files;
    $manifestContents = json_encode($manifest, JSON_THROW_ON_ERROR);
    File::put($stage.DIRECTORY_SEPARATOR.'manifest.json', $manifestContents);
    $identifier = bin2hex(random_bytes(6));
    $tarPath = $root.DIRECTORY_SEPARATOR.'working-'.$identifier.'.tar';
    $output = $root.DIRECTORY_SEPARATOR.'archive-'.$identifier.'.tar.gz';
    $archive = new PharData($tarPath);

    foreach (File::allFiles($stage) as $file) {
        $relativePath = str_replace('\\', '/', ltrim(substr($file->getPathname(), strlen($stage)), '/\\'));
        $archive->addFile($file->getPathname(), $relativePath);
    }

    $compressed = $archive->compress(Phar::GZ);

    unset($compressed, $archive);
    File::move($tarPath.'.gz', $output);
    File::deleteDirectory($stage);
    File::delete($tarPath);

    return $output;
}

beforeEach(function (): void {
    $this->backupWorkspace = storage_path('framework/testing/multi-driver-backup-'.bin2hex(random_bytes(6)));
    $this->stage = $this->backupWorkspace.DIRECTORY_SEPARATOR.'stage';
    $this->captureArguments = $this->backupWorkspace.DIRECTORY_SEPARATOR.'arguments.txt';
    $this->captureCredentialMode = $this->backupWorkspace.DIRECTORY_SEPARATOR.'credential-mode.txt';
    $this->captureCredentialCopy = $this->backupWorkspace.DIRECTORY_SEPARATOR.'credentials.txt';
    $this->originalDumpBinary = config('assestme.backup.dump_binary');
    $this->originalRestoreBinary = config('assestme.backup.restore_binary');
    $this->originalBackupRoot = config('assestme.backup.root');
    $this->originalPrivateStorage = config('assestme.backup.private_storage_path');
    $this->originalDatabasePasswordEnvironment = getenv('DB_PASSWORD');
    File::ensureDirectoryExists($this->stage, 0700, true);
    putenv('DB_PASSWORD=parent-environment-secret');
    $_ENV['DB_PASSWORD'] = 'parent-environment-secret';
    $_SERVER['DB_PASSWORD'] = 'parent-environment-secret';

    foreach ([
        'ASSESTME_TEST_DUMP_ARGUMENTS' => $this->captureArguments,
        'ASSESTME_TEST_CREDENTIAL_MODE' => $this->captureCredentialMode,
        'ASSESTME_TEST_CREDENTIAL_COPY' => $this->captureCredentialCopy,
        'ASSESTME_TEST_DUMP_FAIL' => '0',
        'ASSESTME_TEST_DUMP_SECRET' => 'unused',
    ] as $key => $value) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
});

afterEach(function (): void {
    config()->set('assestme.backup.dump_binary', $this->originalDumpBinary);
    config()->set('assestme.backup.restore_binary', $this->originalRestoreBinary);
    config()->set('assestme.backup.root', $this->originalBackupRoot);
    config()->set('assestme.backup.private_storage_path', $this->originalPrivateStorage);

    if (is_string($this->originalDatabasePasswordEnvironment)) {
        putenv('DB_PASSWORD='.$this->originalDatabasePasswordEnvironment);
        $_ENV['DB_PASSWORD'] = $this->originalDatabasePasswordEnvironment;
        $_SERVER['DB_PASSWORD'] = $this->originalDatabasePasswordEnvironment;
    } else {
        putenv('DB_PASSWORD');
        unset($_ENV['DB_PASSWORD'], $_SERVER['DB_PASSWORD']);
    }

    foreach ([
        'ASSESTME_TEST_DUMP_ARGUMENTS',
        'ASSESTME_TEST_CREDENTIAL_MODE',
        'ASSESTME_TEST_CREDENTIAL_COPY',
        'ASSESTME_TEST_DUMP_FAIL',
        'ASSESTME_TEST_DUMP_SECRET',
    ] as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    File::deleteDirectory($this->backupWorkspace);
});

it('automatically resolves verified MySQL clients and rejects a MariaDB product under the MySQL name', function (): void {
    $directory = $this->backupWorkspace.DIRECTORY_SEPARATOR.'automatic-bin';
    $dump = assestMeCreateFakeDumpBinary(
        $directory,
        'mysqldump',
        'mysqldump Ver unit-test for Linux (MySQL Community Server)',
    );
    $restore = $directory.DIRECTORY_SEPARATOR.'mysql';
    File::put($restore, "#!/bin/sh\nprintf '%s\\n' 'mysql Ver 8.4.0 MySQL Community Server'\n");
    chmod($restore, 0700);
    config()->set('assestme.backup.dump_binary');
    config()->set('assestme.backup.restore_binary');

    $resolver = new DatabaseClientBinaryResolver(
        new DatabaseDumpBinaryValidator,
        new DatabaseRestoreBinaryValidator,
        [$directory],
    );

    expect($resolver->resolveDump('mysql'))->toBe((string) realpath($dump))
        ->and($resolver->resolveRestore('mysql'))->toBe((string) realpath($restore));

    $mismatchDirectory = $this->backupWorkspace.DIRECTORY_SEPARATOR.'mismatched-bin';
    assestMeCreateFakeDumpBinary(
        $mismatchDirectory,
        'mysqldump',
        'mysqldump Ver unit-test Distrib unit-test-MariaDB',
    );
    $mismatchedResolver = new DatabaseClientBinaryResolver(
        new DatabaseDumpBinaryValidator,
        new DatabaseRestoreBinaryValidator,
        [$mismatchDirectory],
    );

    expect($mismatchedResolver->resolveDump('mysql'))->toBeNull();
});

it('creates and verifies a complete MySQL manifest v2 archive through the public backup action', function (): void {
    $binary = assestMeCreateFakeDumpBinary(
        $this->backupWorkspace.DIRECTORY_SEPARATOR.'bin',
        'mysqldump',
        'mysqldump Ver unit-test for Linux (MySQL Community Server)',
    );
    $privateStorage = $this->backupWorkspace.DIRECTORY_SEPARATOR.'private';
    $backupRoot = $this->backupWorkspace.DIRECTORY_SEPARATOR.'archives';
    $output = $backupRoot.DIRECTORY_SEPARATOR.'mysql.tar.gz';
    File::ensureDirectoryExists($privateStorage, 0700, true);
    File::put($privateStorage.DIRECTORY_SEPARATOR.'proof.txt', 'private payload');
    config()->set('assestme.backup.dump_binary', $binary);
    config()->set('assestme.backup.root', $backupRoot);
    config()->set('assestme.backup.private_storage_path', $privateStorage);
    $connection = assestMeDumpConnection(
        driver: 'mysql',
        version: 'unit-test-mysql',
        versionComment: 'MySQL Community Server',
        password: 'action-password',
        driverCalls: 3,
    );
    $schema = Mockery::mock(Builder::class);
    $schema->shouldReceive('hasTable')->once()->with('settings')->andReturnFalse();
    $connection->shouldReceive('getSchemaBuilder')->once()->andReturn($schema);

    $created = app(CreateBackup::class)($output, false, $connection);
    $manifest = app(VerifyBackup::class)->handle($created);
    $archive = new PharData($created);
    $metadataContents = $archive['metadata.json']->getContent();
    $manifestContents = $archive['manifest.json']->getContent();
    unset($archive);

    expect($created)->toBe($output)
        ->and($manifest->schemaVersion)->toBe(2)
        ->and($manifest->database->driver)->toBe('mysql')
        ->and($manifest->database->product)->toBe('MySQL')
        ->and($manifest->database->format)->toBe('sql')
        ->and($manifest->database->path)->toBe('database/database.sql')
        ->and($manifest->paths())->toContain('database/database.sql')
        ->and($manifest->paths())->toContain('storage/private/proof.txt')
        ->and($manifest->paths())->not->toContain('database/database.sqlite')
        ->and(implode("\n", $manifest->paths()))->not->toContain('credentials')
        ->and($metadataContents)->not->toContain('action-password')
        ->and($manifestContents)->not->toContain('action-password');
});

it('creates and verifies a complete MariaDB manifest v2 archive through the public backup action', function (): void {
    $binary = assestMeCreateFakeDumpBinary(
        $this->backupWorkspace.DIRECTORY_SEPARATOR.'bin',
        'mariadb-dump',
        'mariadb-dump Ver unit-test Distrib unit-test-MariaDB',
    );
    $privateStorage = $this->backupWorkspace.DIRECTORY_SEPARATOR.'private';
    $backupRoot = $this->backupWorkspace.DIRECTORY_SEPARATOR.'archives';
    $output = $backupRoot.DIRECTORY_SEPARATOR.'mariadb.tar.gz';
    File::ensureDirectoryExists($privateStorage, 0700, true);
    config()->set('assestme.backup.dump_binary', $binary);
    config()->set('assestme.backup.root', $backupRoot);
    config()->set('assestme.backup.private_storage_path', $privateStorage);
    $connection = assestMeDumpConnection(
        driver: 'mariadb',
        version: 'unit-test-MariaDB',
        versionComment: 'mariadb.org binary distribution',
        password: 'action-password',
        driverCalls: 3,
    );
    $schema = Mockery::mock(Builder::class);
    $schema->shouldReceive('hasTable')->once()->with('settings')->andReturnFalse();
    $connection->shouldReceive('getSchemaBuilder')->once()->andReturn($schema);

    $created = app(CreateBackup::class)($output, false, $connection);
    $manifest = app(VerifyBackup::class)->handle($created);

    expect($created)->toBe($output)
        ->and($manifest->schemaVersion)->toBe(2)
        ->and($manifest->database->driver)->toBe('mariadb')
        ->and($manifest->database->product)->toBe('MariaDB')
        ->and($manifest->database->format)->toBe('sql')
        ->and($manifest->database->path)->toBe('database/database.sql')
        ->and($manifest->paths())->toContain('database/database.sql');
});

it('creates a MySQL SQL snapshot with fixed consistency options and protected escaped credentials', function (): void {
    $binary = assestMeCreateFakeDumpBinary(
        $this->backupWorkspace.DIRECTORY_SEPARATOR.'bin',
        'mysqldump',
        'mysqldump Ver unit-test for Linux (MySQL Community Server)',
    );
    config()->set('assestme.backup.dump_binary', $binary);
    $password = ' leading # $ " \' \\ literal'."\n".'next'."\t".'end ';
    $connection = assestMeDumpConnection(
        driver: 'mysql',
        version: 'unit-test-mysql',
        versionComment: 'MySQL Community Server',
        password: $password,
    );
    $snapshotter = app(MySqlSnapshotter::class);

    $snapshot = $snapshotter->createSnapshot($connection, $this->stage);
    $arguments = file($this->captureArguments, FILE_IGNORE_NEW_LINES);
    $credentials = File::get($this->captureCredentialCopy);
    $expectedPasswordLine = <<<'CREDENTIAL'
password=" leading # $ \" ' \\ literal\nnext\tend "
CREDENTIAL;
    $credentialsArgument = collect($arguments)
        ->first(static fn (string $argument): bool => str_starts_with($argument, '--defaults-file='));

    expect($snapshot->driver)->toBe('mysql')
        ->and($snapshot->product)->toBe('MySQL')
        ->and($snapshot->format)->toBe('sql')
        ->and($snapshot->path)->toBe('database/database.sql')
        ->and(File::get($this->stage.DIRECTORY_SEPARATOR.'database/database.sql'))
        ->toContain('CREATE TABLE migrations')
        ->and($arguments)->toContain('--single-transaction')
        ->and($arguments)->toContain('--quick')
        ->and($arguments)->toContain('--skip-lock-tables')
        ->and($arguments)->toContain('--skip-add-locks')
        ->and($arguments)->toContain('--default-character-set=utf8mb4')
        ->and($arguments)->toContain('--set-gtid-purged=OFF')
        ->and($arguments)->not->toContain('--no-login-paths')
        ->and(implode("\n", $arguments))->not->toContain($password)
        ->and(trim(File::get($this->captureCredentialMode)))->toBe('600')
        ->and($credentials)->toContain($expectedPasswordLine)
        ->and(substr_count($credentials, "\n"))->toBe(5)
        ->and($credentialsArgument)->toBeString()
        ->and(File::exists(substr((string) $credentialsArgument, strlen('--defaults-file='))))->toBeFalse();
});

it('uses the distinct MariaDB binary and preserves a configured Unix socket', function (): void {
    $binary = assestMeCreateFakeDumpBinary(
        $this->backupWorkspace.DIRECTORY_SEPARATOR.'bin',
        'mariadb-dump',
        'mariadb-dump Ver unit-test Distrib unit-test-MariaDB',
    );
    config()->set('assestme.backup.dump_binary', $binary);
    $connection = assestMeDumpConnection(
        driver: 'mariadb',
        version: 'unit-test-MariaDB',
        versionComment: 'mariadb.org binary distribution',
        password: 'maria-password',
        socket: '/run/mysqld/mysqld.sock',
    );
    $snapshotter = app(MariaDbSnapshotter::class);

    $snapshot = $snapshotter->createSnapshot($connection, $this->stage);

    expect($snapshot->driver)->toBe('mariadb')
        ->and($snapshot->product)->toBe('MariaDB')
        ->and(File::get($this->captureCredentialCopy))->toContain('socket="/run/mysqld/mysqld.sock"')
        ->and(File::get($this->captureCredentialCopy))->not->toContain("\nhost=");
});

it('does not expose a database password or process stderr when a dump fails', function (): void {
    $binary = assestMeCreateFakeDumpBinary(
        $this->backupWorkspace.DIRECTORY_SEPARATOR.'bin',
        'mysqldump',
        'mysqldump Ver unit-test for Linux (MySQL Community Server)',
    );
    config()->set('assestme.backup.dump_binary', $binary);
    $password = 'never expose # $ password';
    $stderrSecret = 'stderr must remain private';
    putenv('ASSESTME_TEST_DUMP_FAIL=1');
    putenv("ASSESTME_TEST_DUMP_SECRET={$stderrSecret}");
    $_ENV['ASSESTME_TEST_DUMP_FAIL'] = '1';
    $_SERVER['ASSESTME_TEST_DUMP_FAIL'] = '1';
    $_ENV['ASSESTME_TEST_DUMP_SECRET'] = $stderrSecret;
    $_SERVER['ASSESTME_TEST_DUMP_SECRET'] = $stderrSecret;
    $connection = assestMeDumpConnection(
        driver: 'mysql',
        version: 'unit-test-mysql',
        versionComment: 'MySQL Community Server',
        password: $password,
    );
    $snapshotter = app(MySqlSnapshotter::class);

    try {
        $snapshotter->createSnapshot($connection, $this->stage);
        $this->fail('The failed database dump unexpectedly succeeded.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('The MySQL database snapshot could not be created.')
            ->and($exception->getMessage())->not->toContain($password)
            ->and($exception->getMessage())->not->toContain($stderrSecret)
            ->and(File::glob($this->stage.DIRECTORY_SEPARATOR.'database/.assestme-db-credentials-*'))->toBeEmpty()
            ->and(File::exists($this->stage.DIRECTORY_SEPARATOR.'database/database.sql'))->toBeFalse();
    }
});

it('rejects a leading-dash database name before starting the dump process', function (): void {
    $binary = assestMeCreateFakeDumpBinary(
        $this->backupWorkspace.DIRECTORY_SEPARATOR.'bin',
        'mysqldump',
        'mysqldump Ver unit-test for Linux (MySQL Community Server)',
    );
    config()->set('assestme.backup.dump_binary', $binary);
    $connection = assestMeDumpConnection(
        driver: 'mysql',
        version: 'unit-test-mysql',
        versionComment: 'MySQL Community Server',
        password: 'never expose',
        database: '--all-databases',
    );
    $snapshotter = app(MySqlSnapshotter::class);

    expect(fn () => $snapshotter->createSnapshot($connection, $this->stage))
        ->toThrow(RuntimeException::class, 'Database client connection configuration is invalid.')
        ->and(File::exists($this->captureArguments))->toBeFalse()
        ->and(File::glob($this->stage.DIRECTORY_SEPARATOR.'database/.assestme-db-credentials-*'))->toBeEmpty()
        ->and(File::exists($this->stage.DIRECTORY_SEPARATOR.'database/database.sql'))->toBeFalse();
});

it('accepts a verified dump symlink and rejects basename or product mismatches', function (): void {
    $validator = new DatabaseDumpBinaryValidator;
    $realBinary = assestMeCreateFakeDumpBinary(
        $this->backupWorkspace.DIRECTORY_SEPARATOR.'real-bin',
        'mysqldump',
        'mysqldump Ver unit-test for Linux (MySQL Community Server)',
    );
    $symlinkDirectory = $this->backupWorkspace.DIRECTORY_SEPARATOR.'symlink-bin';
    File::ensureDirectoryExists($symlinkDirectory);
    $symlink = $symlinkDirectory.DIRECTORY_SEPARATOR.'mysqldump';
    symlink($realBinary, $symlink);
    $wrongProduct = assestMeCreateFakeDumpBinary(
        $this->backupWorkspace.DIRECTORY_SEPARATOR.'wrong-bin',
        'mysqldump',
        'mysqldump Ver unit-test Distrib unit-test-MariaDB',
    );
    $wrongBasename = assestMeCreateFakeDumpBinary(
        $this->backupWorkspace.DIRECTORY_SEPARATOR.'wrong-name-bin',
        'dump',
        'mysqldump Ver unit-test for Linux (MySQL Community Server)',
    );

    expect($validator->validate('mysql', $symlink))->toBe((string) realpath($realBinary))
        ->and(fn (): string => $validator->validate('mysql', $wrongBasename))
        ->toThrow(RuntimeException::class, 'dump binary is invalid')
        ->and(fn (): string => $validator->validate('mysql', $wrongProduct))
        ->toThrow(RuntimeException::class, 'does not match driver mysql');
});

it('verifies legacy v1 SQLite archives and exposes their typed database identity', function (): void {
    $archive = assestMeCreateManifestArchive($this->backupWorkspace, 1);

    $manifest = app(VerifyBackup::class)->handle($archive);

    expect($manifest->schemaVersion)->toBe(1)
        ->and($manifest->database->driver)->toBe('sqlite')
        ->and($manifest->database->format)->toBe('sqlite')
        ->and($manifest->database->path)->toBe('database/database.sqlite');
});

it('verifies a rigid v2 SQL payload and rejects an inconsistent database path', function (): void {
    $database = [
        'driver' => 'mysql',
        'product' => 'MySQL',
        'server_version' => 'unit-test-mysql',
        'format' => 'sql',
        'path' => 'database/database.sql',
    ];
    $archive = assestMeCreateManifestArchive($this->backupWorkspace, 2, $database);

    $manifest = app(VerifyBackup::class)->handle($archive);
    $archiveData = new PharData($archive);
    $archiveData->addEmptyDir('unexpected');
    unset($archiveData);
    $database['path'] = 'database/database.sqlite';
    $invalidJson = json_encode([
        'schema_version' => 2,
        'created_at' => '2026-07-31T18:00:00Z',
        'application_version' => 'test',
        'database' => $database,
        'files' => [],
    ], JSON_THROW_ON_ERROR);

    expect($manifest->schemaVersion)->toBe(2)
        ->and($manifest->database->driver)->toBe('mysql')
        ->and($manifest->database->path)->toBe('database/database.sql')
        ->and(fn (): BackupManifest => BackupManifest::fromJson($invalidJson))
        ->toThrow(InvalidArgumentException::class, 'database entry is inconsistent')
        ->and(fn (): BackupManifest => app(VerifyBackup::class)->handle($archive))
        ->toThrow(RuntimeException::class, 'Unsafe backup archive directory: unexpected');
});

it('rejects public and symlinked backup output paths before creating an archive', function (): void {
    config()->set(
        'assestme.backup.private_storage_path',
        $this->backupWorkspace.DIRECTORY_SEPARATOR.'private',
    );
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
    $publicOutput = public_path('assestme-public-test-'.bin2hex(random_bytes(6)).'.tar.gz');
    $publicLink = $this->backupWorkspace.DIRECTORY_SEPARATOR.'public-link';
    symlink(public_path(), $publicLink);
    $linkedOutput = $publicLink.DIRECTORY_SEPARATOR.'assestme-linked-test.tar.gz';

    expect(fn () => app(CreateBackup::class)($publicOutput, false, $connection))
        ->toThrow(InvalidArgumentException::class, 'outside the public directory')
        ->and(fn () => app(CreateBackup::class)($linkedOutput, false, $connection))
        ->toThrow(InvalidArgumentException::class, 'must not use symbolic links')
        ->and(File::exists($publicOutput))->toBeFalse();
});

it('fails closed and removes the archive when backup staging cleanup fails', function (): void {
    $binary = assestMeCreateFakeDumpBinary(
        $this->backupWorkspace.DIRECTORY_SEPARATOR.'bin',
        'mysqldump',
        'mysqldump Ver unit-test for Linux (MySQL Community Server)',
    );
    $privateStorage = $this->backupWorkspace.DIRECTORY_SEPARATOR.'private';
    $backupRoot = $this->backupWorkspace.DIRECTORY_SEPARATOR.'archives';
    $output = $backupRoot.DIRECTORY_SEPARATOR.'cleanup-failure.tar.gz';
    File::ensureDirectoryExists($privateStorage, 0700, true);
    config()->set('assestme.backup.dump_binary', $binary);
    config()->set('assestme.backup.root', $backupRoot);
    config()->set('assestme.backup.private_storage_path', $privateStorage);
    $connection = assestMeDumpConnection(
        driver: 'mysql',
        version: 'unit-test-mysql',
        versionComment: 'MySQL Community Server',
        password: 'action-password',
        driverCalls: 3,
    );
    $schema = Mockery::mock(Builder::class);
    $schema->shouldReceive('hasTable')->once()->with('settings')->andReturnFalse();
    $connection->shouldReceive('getSchemaBuilder')->once()->andReturn($schema);
    $files = Mockery::mock(Filesystem::class)->makePartial();
    $files->shouldReceive('deleteDirectory')
        ->once()
        ->with(Mockery::on(
            static fn (string $path): bool => str_contains($path, '/framework/assestme-backups/'),
        ))
        ->andReturnFalse();
    $action = new CreateBackup(
        $files,
        app(PruneBackups::class),
        app(VerifyBackup::class),
        app(DatabaseSnapshotterResolver::class),
    );
    $stagingRoot = storage_path('framework/assestme-backups');
    $before = File::isDirectory($stagingRoot) ? File::directories($stagingRoot) : [];
    $exception = null;

    try {
        $action($output, false, $connection);
    } catch (RuntimeException $caught) {
        $exception = $caught;
    }

    $remaining = File::isDirectory($stagingRoot)
        ? array_values(array_diff(File::directories($stagingRoot), $before))
        : [];

    foreach ($remaining as $directory) {
        File::deleteDirectory($directory);
    }

    expect($exception)->not->toBeNull()
        ->and($exception?->getMessage())->toBe('The backup working directory could not be removed securely.')
        ->and(File::exists($output))->toBeFalse();
});

it('extracts into a canonical empty directory and rejects pre-populated or symlinked destinations', function (): void {
    $archive = assestMeCreateManifestArchive($this->backupWorkspace, 1);
    $destination = $this->backupWorkspace.DIRECTORY_SEPARATOR.'extracted';
    File::ensureDirectoryExists($destination, 0700, true);

    $manifest = app(VerifyBackup::class)->extractVerified($archive, $destination);

    expect($manifest->schemaVersion)->toBe(1)
        ->and(File::exists($destination.DIRECTORY_SEPARATOR.'database/database.sqlite'))->toBeTrue();

    $occupied = $this->backupWorkspace.DIRECTORY_SEPARATOR.'occupied';
    File::ensureDirectoryExists($occupied, 0700, true);
    File::put($occupied.DIRECTORY_SEPARATOR.'existing.txt', 'do not overwrite');
    $realDestination = $this->backupWorkspace.DIRECTORY_SEPARATOR.'real-destination';
    File::ensureDirectoryExists($realDestination, 0700, true);
    $linkedDestination = $this->backupWorkspace.DIRECTORY_SEPARATOR.'linked-destination';
    symlink($realDestination, $linkedDestination);

    expect(fn () => app(VerifyBackup::class)->extractVerified($archive, $occupied))
        ->toThrow(InvalidArgumentException::class, 'must be empty')
        ->and(fn () => app(VerifyBackup::class)->extractVerified($archive, $linkedDestination))
        ->toThrow(InvalidArgumentException::class, 'existing writable directory');
});

it('rejects a cross-driver restore before creating a safety backup', function (): void {
    $archive = assestMeCreateManifestArchive($this->backupWorkspace, 2, [
        'driver' => 'mysql',
        'product' => 'MySQL',
        'server_version' => '8.0.46',
        'format' => 'sql',
        'path' => 'database/database.sql',
    ]);
    $backupRoot = $this->backupWorkspace.DIRECTORY_SEPARATOR.'archives';
    $private = $this->backupWorkspace.DIRECTORY_SEPARATOR.'private';
    File::ensureDirectoryExists($backupRoot, 0700, true);
    File::ensureDirectoryExists($private, 0700, true);
    config()->set('assestme.backup.root', $backupRoot);
    config()->set('assestme.backup.private_storage_path', $private);
    Artisan::call('down');

    try {
        expect(fn () => app(RestoreBackup::class)($archive))
            ->toThrow(RuntimeException::class, 'does not match configured driver sqlite')
            ->and(File::glob($backupRoot.DIRECTORY_SEPARATOR.'assestme-safety-*.tar.gz'))->toBeEmpty();
    } finally {
        Artisan::call('up');
    }
});

it('rejects a concurrent restore through the stable filesystem lock', function (): void {
    $root = storage_path('framework/assestme-restore');
    File::ensureDirectoryExists($root, 0700, true);
    $handle = fopen($root.DIRECTORY_SEPARATOR.'restore.lock', 'c+');
    expect($handle)->toBeResource();
    chmod($root.DIRECTORY_SEPARATOR.'restore.lock', 0600);
    flock($handle, LOCK_EX | LOCK_NB);
    Artisan::call('down');

    try {
        expect(fn () => app(RestoreBackup::class)($this->backupWorkspace.'/missing.tar.gz'))
            ->toThrow(RuntimeException::class, 'Another backup restore is already running.');
    } finally {
        Artisan::call('up');
        flock($handle, LOCK_UN);
        fclose($handle);
    }
});
