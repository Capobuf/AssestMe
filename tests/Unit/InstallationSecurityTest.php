<?php

declare(strict_types=1);

use App\Data\Installation\ApplicationConfigurationData;
use App\Data\Installation\DatabaseConfigurationData;
use App\Data\Installation\InstallationProgressData;
use App\Enums\SupportedDatabaseDriver;
use App\Services\Installation\InstallationEnvironmentWriter;
use App\Services\Installation\InstallationState;
use App\Support\Installation\BootstrapInstallationKey;
use Dotenv\Dotenv;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

uses(TestCase::class);

function installationSecurityTemporaryDirectory(string $suffix): string
{
    $path = sys_get_temp_dir().'/assestme-install-security-'.$suffix.'-'.bin2hex(random_bytes(8));

    if (! mkdir($path, 0700, true) && ! is_dir($path)) {
        throw new RuntimeException('Test directory could not be created.');
    }

    return $path;
}

it('keeps one stable private bootstrap key and fails closed for an installed instance without a key', function (): void {
    $files = new Filesystem;
    $basePath = installationSecurityTemporaryDirectory('key');
    $originalKey = getenv('APP_KEY');
    $originalStoragePath = getenv('LARAVEL_STORAGE_PATH');

    try {
        putenv('APP_KEY');
        putenv('LARAVEL_STORAGE_PATH');
        unset($_ENV['APP_KEY'], $_SERVER['APP_KEY']);
        unset($_ENV['LARAVEL_STORAGE_PATH'], $_SERVER['LARAVEL_STORAGE_PATH']);

        BootstrapInstallationKey::apply($basePath);
        $firstKey = (string) file_get_contents($basePath.'/storage/framework/installer/bootstrap-key');
        BootstrapInstallationKey::apply($basePath);
        $secondKey = (string) file_get_contents($basePath.'/storage/framework/installer/bootstrap-key');

        expect($firstKey)->toBe($secondKey)
            ->and(BootstrapInstallationKey::isValidKey($firstKey))->toBeTrue()
            ->and(fileperms($basePath.'/storage/framework/installer/bootstrap-key') & 0777)->toBe(0600);

        putenv('APP_KEY');
        unset($_ENV['APP_KEY'], $_SERVER['APP_KEY']);
        $files->ensureDirectoryExists($basePath.'/storage/app/private', 0700);
        $files->put($basePath.'/storage/app/private/installed.lock', '{}');

        expect(fn () => BootstrapInstallationKey::apply($basePath))
            ->toThrow(RuntimeException::class, 'APP_KEY is missing');
    } finally {
        if (is_string($originalKey)) {
            putenv('APP_KEY='.$originalKey);
            $_ENV['APP_KEY'] = $originalKey;
            $_SERVER['APP_KEY'] = $originalKey;
        }

        if (is_string($originalStoragePath)) {
            putenv('LARAVEL_STORAGE_PATH='.$originalStoragePath);
            $_ENV['LARAVEL_STORAGE_PATH'] = $originalStoragePath;
            $_SERVER['LARAVEL_STORAGE_PATH'] = $originalStoragePath;
        }

        $files->deleteDirectory($basePath);
    }
});

it('encrypts resumable installation state and writes a non-sensitive irreversible lock', function (): void {
    $files = new Filesystem;
    $root = installationSecurityTemporaryDirectory('state');
    Config::set('assestme.installation.state_path', $root.'/state.enc');
    Config::set('assestme.installation.lock_path', $root.'/installed.lock');
    Config::set('assestme.version', 'test-version');
    $state = app(InstallationState::class);
    $password = 'State secret #$\\" value';
    $progress = new InstallationProgressData(
        installationId: '4e7f98ae-25f0-4e09-bc26-77525903228c',
        step: 'administrator',
        application: new ApplicationConfigurationData('AssestMe', 'https://assestme.test', 'Europe/Rome', 'it', $root.'/backups', '/usr/bin/weasyprint', '/usr/bin/php'),
        database: new DatabaseConfigurationData(SupportedDatabaseDriver::MySql, 'assestme', password: $password),
    );

    try {
        $state->save($progress);
        $stored = (string) file_get_contents($root.'/state.enc');

        expect($stored)->not->toContain($password)
            ->and(fileperms($root.'/state.enc') & 0777)->toBe(0600)
            ->and($state->progress()->database?->password)->toBe($password);

        $state->createInstalledLock(SupportedDatabaseDriver::MySql);
        $lock = (string) file_get_contents($root.'/installed.lock');

        expect($lock)->toContain('"database_driver": "mysql"')
            ->not->toContain($password)
            ->and($state->isInstalled())->toBeTrue();

        $state->removeProgress();
        expect(file_exists($root.'/state.enc'))->toBeFalse();
    } finally {
        $files->deleteDirectory($root);
    }
});

it('round trips special environment values through an atomic pending file', function (): void {
    $files = new Filesystem;
    $root = installationSecurityTemporaryDirectory('environment');
    $password = "  pa#\$\\\"' line\nnext\tend  ";
    $application = new ApplicationConfigurationData(
        name: 'AssestMe Studio #1',
        url: 'https://assestme.test',
        timezone: 'Europe/Rome',
        locale: 'it',
        backupRoot: $root.'/backup con spazi',
        weasyPrintBinary: '/usr/bin/weasyprint',
        phpBinary: '/usr/bin/php8.3',
    );
    $database = new DatabaseConfigurationData(
        driver: SupportedDatabaseDriver::MariaDb,
        database: 'assestme',
        username: 'user name',
        password: $password,
        dumpBinary: '/usr/bin/mariadb-dump',
        restoreBinary: '/usr/bin/mariadb',
    );
    $writer = app(InstallationEnvironmentWriter::class);
    $key = 'base64:'.base64_encode(str_repeat('K', 32));

    try {
        $pending = $writer->writePending($application, $database, $key, $root);
        $loaded = Dotenv::createArrayBacked($root, '.env.pending')->load();

        expect($pending)->toBe($root.'/.env.pending')
            ->and($loaded['DB_PASSWORD'])->toBe($password)
            ->and($loaded['APP_NAME'])->toBe('AssestMe Studio #1')
            ->and(fileperms($pending) & 0777)->toBe(0600);

        $writer->activatePending($application, $database, $key, $root);
        $final = Dotenv::createArrayBacked($root)->load();

        expect($final['DB_PASSWORD'])->toBe($password)
            ->and($final['APP_KEY'])->toBe($key)
            ->and(file_exists($pending))->toBeFalse();
    } finally {
        $files->deleteDirectory($root);
    }
});
