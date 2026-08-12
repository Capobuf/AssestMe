<?php

declare(strict_types=1);

use App\Support\Installation\BootstrapInstallationKey;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

/** @return array{status: int|null, headers: list<string>, body: string} */
function installationBootstrapHttpGet(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'follow_location' => 0,
            'ignore_errors' => true,
            'timeout' => 1,
        ],
    ]);
    $http_response_header = [];
    $body = @file_get_contents($url, false, $context);
    $status = null;

    if (isset($http_response_header[0])
        && preg_match('/\s(?<status>[1-5][0-9]{2})\s/', $http_response_header[0], $matches) === 1) {
        $status = (int) $matches['status'];
    }

    return [
        'status' => $status,
        'headers' => array_values($http_response_header),
        'body' => is_string($body) ? $body : '',
    ];
}

/** @return array{process: Process, url: string} */
function installationBootstrapStartServer(string $root): array
{
    $socket = @stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

    if ($socket === false) {
        throw new RuntimeException('A temporary HTTP port could not be reserved.');
    }

    $address = stream_socket_get_name($socket, false);
    fclose($socket);

    if (! is_string($address) || preg_match('/:(?<port>[0-9]+)$/', $address, $matches) !== 1) {
        throw new RuntimeException('The temporary HTTP port could not be resolved.');
    }

    $phpBinary = realpath(PHP_BINARY);

    if ($phpBinary === false || ! is_file($phpBinary) || ! is_executable($phpBinary)) {
        throw new RuntimeException('The test PHP CLI executable is unavailable.');
    }

    $port = (int) $matches['port'];
    $process = new Process(
        [
            $phpBinary,
            '-d',
            'display_errors=0',
            '-d',
            'display_startup_errors=0',
            '-S',
            '127.0.0.1:'.$port,
            '-t',
            $root.'/public',
            $root.'/public/index.php',
        ],
        $root,
        [
            'APP_KEY' => false,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'SESSION_DRIVER' => false,
            'SESSION_CONNECTION' => false,
            'SESSION_STORE' => false,
            'SESSION_LIFETIME' => false,
            'SESSION_ENCRYPT' => false,
            'SESSION_PATH' => false,
            'SESSION_DOMAIN' => false,
            'SESSION_SECURE_COOKIE' => false,
            'CACHE_STORE' => false,
            'CACHE_PREFIX' => false,
            'DB_CONNECTION' => false,
            'DB_URL' => false,
            'DATABASE_URL' => false,
            'DB_DATABASE' => false,
            'DB_HOST' => false,
            'DB_PORT' => false,
            'DB_USERNAME' => false,
            'DB_PASSWORD' => false,
            'DB_SOCKET' => false,
            'DB_CHARSET' => false,
            'DB_COLLATION' => false,
            'DB_FOREIGN_KEYS' => false,
            'DB_BUSY_TIMEOUT' => false,
            'DB_JOURNAL_MODE' => false,
            'DB_SYNCHRONOUS' => false,
            'DB_TRANSACTION_MODE' => false,
            'QUEUE_CONNECTION' => false,
            'APP_CONFIG_CACHE' => false,
            'APP_EVENTS_CACHE' => false,
            'APP_PACKAGES_CACHE' => false,
            'APP_ROUTES_CACHE' => false,
            'APP_SERVICES_CACHE' => false,
            'LARAVEL_STORAGE_PATH' => $root.'/storage',
        ],
    );
    $process->setTimeout(null);
    $process->start();

    return [
        'process' => $process,
        'url' => 'http://127.0.0.1:'.$port,
    ];
}

/** @return array{status: int|null, headers: list<string>, body: string} */
function installationBootstrapAwaitResponse(Process $process, string $url): array
{
    $deadline = microtime(true) + 10;

    do {
        $response = installationBootstrapHttpGet($url);

        if ($response['status'] !== null) {
            return $response;
        }

        if (! $process->isRunning()) {
            throw new RuntimeException('The temporary Laravel HTTP process exited before responding.');
        }

        usleep(50_000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException('The temporary Laravel HTTP process did not become ready.');
}

function installationBootstrapCreateReleaseRoot(): string
{
    $root = sys_get_temp_dir().'/assestme-no-env-http-'.bin2hex(random_bytes(8));

    foreach ([
        $root.'/bootstrap/cache',
        $root.'/public/css',
        $root.'/public/images/brand',
        $root.'/public/js',
        $root.'/storage/app/private',
        $root.'/storage/framework/cache/data',
        $root.'/storage/framework/sessions',
        $root.'/storage/framework/views',
        $root.'/storage/framework/installer',
        $root.'/storage/logs',
    ] as $directory) {
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('A temporary release directory could not be created.');
        }
    }

    foreach (['app', 'config', 'resources', 'routes', 'vendor'] as $directory) {
        if (! symlink(base_path($directory), $root.'/'.$directory)) {
            throw new RuntimeException('A temporary release resource could not be linked.');
        }
    }

    foreach ([
        base_path('bootstrap/app.php') => $root.'/bootstrap/app.php',
        base_path('bootstrap/providers.php') => $root.'/bootstrap/providers.php',
        base_path('composer.json') => $root.'/composer.json',
        base_path('composer.lock') => $root.'/composer.lock',
        base_path('public/index.php') => $root.'/public/index.php',
        base_path('public/css/assestme-installer.css') => $root.'/public/css/assestme-installer.css',
        base_path('public/images/brand/assestme-logo-black.svg') => $root.'/public/images/brand/assestme-logo-black.svg',
        base_path('public/images/brand/assestme-logo-white.svg') => $root.'/public/images/brand/assestme-logo-white.svg',
        base_path('public/js/assestme-installer.js') => $root.'/public/js/assestme-installer.js',
    ] as $source => $destination) {
        if (! copy($source, $destination)) {
            throw new RuntimeException('A temporary release resource could not be copied.');
        }
    }

    return $root;
}

function installationBootstrapDeleteReleaseRoot(string $root): void
{
    if (! str_starts_with($root, sys_get_temp_dir().'/assestme-no-env-http-')) {
        throw new RuntimeException('Refusing to remove an unexpected test path.');
    }

    foreach (['app', 'config', 'resources', 'routes', 'vendor'] as $directory) {
        $link = $root.'/'.$directory;

        if (is_link($link)) {
            unlink($link);
        }
    }

    File::deleteDirectory($root);
}

beforeEach(function (): void {
    $this->installationBootstrapRoots = [];
});

afterEach(function (): void {
    foreach ($this->installationBootstrapRoots as $root) {
        installationBootstrapDeleteReleaseRoot($root);
    }
});

it('boots the real HTTP entry point without env or database and reuses one private bootstrap key', function (): void {
    $root = installationBootstrapCreateReleaseRoot();
    $this->installationBootstrapRoots[] = $root;
    $keyPath = $root.'/storage/framework/installer/bootstrap-key';
    $installerRouteRegistered = collect(app('router')->getRoutes()->getRoutes())->contains(
        static fn (LaravelRoute $route): bool => $route->uri() === 'install',
    );

    expect($root.'/.env')->not->toBeFile()
        ->and($keyPath)->not->toBeFile()
        ->and($root.'/public/images/brand/assestme-logo-black.svg')->toBeFile()
        ->and($root.'/public/images/brand/assestme-logo-white.svg')->toBeFile()
        ->and($root.'/database/database.sqlite')->not->toBeFile();

    $firstServer = installationBootstrapStartServer($root);

    try {
        $health = installationBootstrapAwaitResponse(
            $firstServer['process'],
            $firstServer['url'].'/up',
        );
        $rootResponse = installationBootstrapHttpGet($firstServer['url'].'/');
        $installResponse = installationBootstrapHttpGet($firstServer['url'].'/install');
        $firstProcessOutput = $firstServer['process']->getOutput()
            .$firstServer['process']->getErrorOutput();

        expect($health['status'])->toBe(200, $health['body']."\n".$firstProcessOutput)
            ->and(json_decode($health['body'], true))->toBe([
                'status' => 'not_installed',
                'bootable' => true,
                'scheduler' => 'pending',
            ])
            ->and($rootResponse['status'])->toBe(302)
            ->and(implode("\n", $rootResponse['headers']))->toContain('/install')
            ->and($health['body'].$installResponse['body'])
            ->not->toContain('MissingAppKey')
            ->not->toContain('SQLSTATE')
            ->not->toContain('no such table');

        if ($installerRouteRegistered) {
            expect($installResponse['status'])->toBeIn([200, 302]);
        } else {
            expect($installResponse['status'])->toBe(404);
        }

        expect($firstProcessOutput)
            ->not->toContain('MissingAppKey')
            ->not->toContain('SQLSTATE')
            ->not->toContain('no such table');
    } finally {
        $firstServer['process']->stop(1);
    }

    $firstKey = (string) file_get_contents($keyPath);

    expect(BootstrapInstallationKey::isValidKey($firstKey))->toBeTrue()
        ->and(fileperms($keyPath) & 0777)->toBe(0600)
        ->and($root.'/.env')->not->toBeFile()
        ->and($root.'/database/database.sqlite')->not->toBeFile();

    $secondServer = installationBootstrapStartServer($root);

    try {
        $secondHealth = installationBootstrapAwaitResponse(
            $secondServer['process'],
            $secondServer['url'].'/up',
        );

        expect($secondHealth['status'])->toBe(200)
            ->and((string) file_get_contents($keyPath))->toBe($firstKey);
    } finally {
        $secondServer['process']->stop(1);
    }
});

it('fails closed over HTTP when an installed lock exists without env or application key', function (): void {
    $root = installationBootstrapCreateReleaseRoot();
    $this->installationBootstrapRoots[] = $root;
    $lockPath = $root.'/storage/app/private/installed.lock';
    $keyPath = $root.'/storage/framework/installer/bootstrap-key';
    File::put($lockPath, '{}');
    chmod($lockPath, 0600);

    expect($root.'/.env')->not->toBeFile()
        ->and($keyPath)->not->toBeFile();

    $server = installationBootstrapStartServer($root);

    try {
        $response = installationBootstrapAwaitResponse($server['process'], $server['url'].'/up');
        $processOutput = $server['process']->getOutput().$server['process']->getErrorOutput();

        expect($response['status'])->toBe(500, $response['body']."\n".$processOutput)
            ->and($keyPath)->not->toBeFile()
            ->and($root.'/.env')->not->toBeFile()
            ->and($response['body'])->not->toContain('/install');

        expect($processOutput)
            ->toContain('APP_KEY is missing from an installed AssestMe instance.')
            ->not->toContain('SQLSTATE')
            ->not->toContain('no such table');
    } finally {
        $server['process']->stop(1);
    }
});
