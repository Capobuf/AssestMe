<?php

declare(strict_types=1);

use App\Data\Installation\InstallationRequirementResult;
use App\Services\Installation\InstallationRuntimeInspector;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->runtimeInspectorRoot = sys_get_temp_dir()
        .DIRECTORY_SEPARATOR.'assestme-runtime-inspector-'.bin2hex(random_bytes(8));
    $this->runtimeInspectorLinks = [];
    $this->runtimeInspectorExternalFiles = [];

    File::ensureDirectoryExists($this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'public', 0750);
});

afterEach(function (): void {
    foreach (array_reverse($this->runtimeInspectorLinks) as $link) {
        if (is_link($link)) {
            unlink($link);
        }
    }

    File::deleteDirectory($this->runtimeInspectorRoot);

    foreach ($this->runtimeInspectorExternalFiles as $file) {
        File::delete($file);
    }
});

it('returns structured passing results after real runtime filesystem CLI and PDF probes', function (): void {
    $weasyPrintBinary = config('laravel-pdf.weasyprint.binary');

    expect($weasyPrintBinary)->toBeString()->not->toBeEmpty();

    $result = app(InstallationRuntimeInspector::class)->inspect(
        basePath: $this->runtimeInspectorRoot,
        configuredPhpBinary: '',
        configuredWeasyPrintBinary: $weasyPrintBinary,
    );

    $expectedExtensions = [
        'bcmath',
        'ctype',
        'curl',
        'dom',
        'fileinfo',
        'filter',
        'gd',
        'iconv',
        'intl',
        'libxml',
        'mbstring',
        'openssl',
        'pdo',
        'phar',
        'session',
        'simplexml',
        'tokenizer',
        'xml',
        'xmlreader',
        'xmlwriter',
        'zip',
        'zlib',
    ];
    $extensionKeys = array_values(array_map(
        static fn (InstallationRequirementResult $requirement): string => $requirement->key,
        array_filter(
            $result->requirements(),
            static fn (InstallationRequirementResult $requirement): bool => str_starts_with(
                $requirement->key,
                'runtime.php.extension.',
            ),
        ),
    ));

    expect($result->passed())->toBeTrue()
        ->and($result->failures())->toBe([])
        ->and($result->phpBinary)->toBeString()->toStartWith(DIRECTORY_SEPARATOR)
        ->and($result->weasyPrintBinary)->toBeString()->toStartWith(DIRECTORY_SEPARATOR)
        ->and($result->requirement('runtime.php.version')?->actual)->toStartWith('8.3.')
        ->and($result->requirement('runtime.php_cli')?->passed)->toBeTrue()
        ->and($result->requirement('runtime.weasyprint')?->actual)->toContain('%PDF-')
        ->and($extensionKeys)->toBe(array_map(
            static fn (string $extension): string => 'runtime.php.extension.'.$extension,
            $expectedExtensions,
        ))
        ->and($result->toArray()['passed'])->toBeTrue()
        ->and($result->toArray()['requirements'])->not->toBeEmpty()
        ->and($this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'.env')->not->toBeFile();

    foreach ([
        'storage/app/private',
        'storage/app/generated',
        'storage/app/database',
        'storage/backups',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/framework/installer',
        'storage/logs',
        'bootstrap/cache',
    ] as $relativeDirectory) {
        expect($this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.$relativeDirectory)->toBeDirectory();
    }

    expect(File::glob(
        $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'storage/framework/installer/weasyprint-probe-*',
    ))->toBe([]);
});

it('reports unsafe directories and environment symlinks without writing through them', function (): void {
    File::ensureDirectoryExists($this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'storage', 0750);
    File::put($this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'storage/logs', 'not-a-directory');

    $externalEnvironment = sys_get_temp_dir()
        .DIRECTORY_SEPARATOR.'assestme-env-probe-'.bin2hex(random_bytes(8));
    File::put($externalEnvironment, 'UNCHANGED');
    $this->runtimeInspectorExternalFiles[] = $externalEnvironment;

    $environmentLink = $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'.env';
    symlink($externalEnvironment, $environmentLink);
    $this->runtimeInspectorLinks[] = $environmentLink;

    $result = app(InstallationRuntimeInspector::class)->inspect(
        basePath: $this->runtimeInspectorRoot,
        configuredPhpBinary: $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'missing-php',
        configuredWeasyPrintBinary: $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'missing-weasyprint',
    );

    expect($result->passed())->toBeFalse()
        ->and($result->requirement('filesystem.directory.storage_logs')?->passed)->toBeFalse()
        ->and($result->requirement('filesystem.directory.storage_logs')?->actual)->toBe('not_a_directory')
        ->and($result->requirement('filesystem.environment_file')?->passed)->toBeFalse()
        ->and($result->requirement('filesystem.environment_file')?->actual)->toBe('symlink_not_allowed')
        ->and(File::get($externalEnvironment))->toBe('UNCHANGED');
});

it('refuses a sensitive required directory that resolves below public', function (): void {
    $exposedDirectory = $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'public/exposed-private';
    $privateDirectory = $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'storage/app/private';
    File::ensureDirectoryExists($exposedDirectory, 0750);
    File::ensureDirectoryExists(dirname($privateDirectory), 0750);
    symlink($exposedDirectory, $privateDirectory);
    $this->runtimeInspectorLinks[] = $privateDirectory;

    $result = app(InstallationRuntimeInspector::class)->inspect(
        basePath: $this->runtimeInspectorRoot,
        configuredPhpBinary: $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'missing-php',
        configuredWeasyPrintBinary: $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'missing-weasyprint',
    );

    expect($result->requirement('filesystem.outside_public.storage_app_private')?->passed)->toBeFalse()
        ->and($result->requirement('filesystem.directory.storage_app_private')?->passed)->toBeFalse()
        ->and($result->requirement('filesystem.directory.storage_app_private')?->actual)
        ->toBe('sensitive_path_under_public')
        ->and(File::allFiles($exposedDirectory))->toBe([]);
});

it('does not silently replace an invalid configured PHP CLI with another candidate', function (): void {
    $result = app(InstallationRuntimeInspector::class)->inspect(
        basePath: $this->runtimeInspectorRoot,
        configuredPhpBinary: $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'missing-php',
        configuredWeasyPrintBinary: $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'missing-weasyprint',
    );

    expect($result->phpBinary)->toBeNull()
        ->and($result->requirement('runtime.php_cli')?->passed)->toBeFalse()
        ->and($result->requirement('runtime.php_cli')?->actual)->toBe('configured_php_cli_invalid');
});

it('requires an absolute regular non-symlink WeasyPrint executable', function (): void {
    $configuredBinary = config('laravel-pdf.weasyprint.binary');

    expect($configuredBinary)->toBeString()->not->toBeEmpty();

    $weasyPrintLink = $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'weasyprint-link';
    symlink($configuredBinary, $weasyPrintLink);
    $this->runtimeInspectorLinks[] = $weasyPrintLink;

    $relativeResult = app(InstallationRuntimeInspector::class)->inspect(
        basePath: $this->runtimeInspectorRoot,
        configuredPhpBinary: $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'missing-php',
        configuredWeasyPrintBinary: 'weasyprint',
    );
    $symlinkResult = app(InstallationRuntimeInspector::class)->inspect(
        basePath: $this->runtimeInspectorRoot,
        configuredPhpBinary: $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'missing-php',
        configuredWeasyPrintBinary: $weasyPrintLink,
    );

    expect($relativeResult->requirement('runtime.weasyprint')?->actual)
        ->toBe('configured_path_not_absolute')
        ->and($symlinkResult->requirement('runtime.weasyprint')?->actual)
        ->toBe('symlink_not_allowed')
        ->and($relativeResult->weasyPrintBinary)->toBeNull()
        ->and($symlinkResult->weasyPrintBinary)->toBeNull();
});

it('rejects a successful WeasyPrint process that does not create a PDF signature', function (): void {
    $fakeBinary = $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'fake-weasyprint';
    $script = <<<'SH'
#!/bin/sh
if [ "${1:-}" = "--version" ]; then
    printf 'WeasyPrint version 57.2\n'
    exit 0
fi
printf 'NOT_A_PDF' > "${2}"
SH;
    File::put($fakeBinary, $script);
    chmod($fakeBinary, 0700);

    $result = app(InstallationRuntimeInspector::class)->inspect(
        basePath: $this->runtimeInspectorRoot,
        configuredPhpBinary: $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'missing-php',
        configuredWeasyPrintBinary: $fakeBinary,
    );

    expect($result->requirement('runtime.weasyprint')?->passed)->toBeFalse()
        ->and($result->requirement('runtime.weasyprint')?->actual)->toBe('minimal_pdf_probe_failed')
        ->and(File::glob(
            $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'storage/framework/installer/weasyprint-probe-*',
        ))->toBe([]);
});
