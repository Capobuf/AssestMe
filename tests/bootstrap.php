<?php

declare(strict_types=1);
use Illuminate\Filesystem\Filesystem;

require dirname(__DIR__).'/vendor/autoload.php';

ini_set('memory_limit', '512M');

$projectRoot = dirname(__DIR__);
$providedRoot = getenv('ASSESTME_TEST_ROOT');
$ownsRoot = ! is_string($providedRoot) || trim($providedRoot) === '';
$testRoot = $ownsRoot
    ? sys_get_temp_dir().'/assestme-tests-'.getmypid().'-'.bin2hex(random_bytes(8))
    : rtrim($providedRoot, DIRECTORY_SEPARATOR);

if ($testRoot === '' || str_starts_with($projectRoot, $testRoot.DIRECTORY_SEPARATOR) || str_starts_with($testRoot, $projectRoot.DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('The isolated test root must be outside the project directory.');
}

if (! $ownsRoot && (getenv('ASSESTME_TEST_ISOLATED') !== '1' || ! is_file($testRoot.'/.assestme-test-root'))) {
    throw new RuntimeException('A provided test root must already be marked as an isolated AssestMe test root.');
}

$filesystem = new Filesystem;
$storage = $testRoot.'/storage';
$database = $testRoot.'/database.sqlite';
$directories = [
    $storage.'/app/private',
    $storage.'/framework/cache/data',
    $storage.'/framework/sessions',
    $storage.'/framework/testing',
    $storage.'/framework/views',
    $storage.'/logs',
    $storage.'/backups',
];

foreach ($directories as $directory) {
    $filesystem->ensureDirectoryExists($directory);
}

if (! file_exists($database) && ! touch($database)) {
    throw new RuntimeException('The isolated test database could not be created.');
}

$environment = [
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    'ASSESTME_BACKUP_ROOT' => $storage.'/backups',
    'ASSESTME_TEST_ISOLATED' => '1',
    'ASSESTME_TEST_ROOT' => $testRoot,
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $database,
    'FILESYSTEM_DISK' => 'local',
    'LARAVEL_STORAGE_PATH' => $storage,
    'LOG_CHANNEL' => 'null',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
];

foreach ($environment as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

if ($ownsRoot) {
    register_shutdown_function(static function () use ($filesystem, $testRoot): void {
        $filesystem->deleteDirectory($testRoot);
    });
}
