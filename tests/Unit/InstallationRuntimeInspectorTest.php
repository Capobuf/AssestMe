<?php

declare(strict_types=1);

use App\Data\Installation\InstallationRequirementResult;
use App\Services\Installation\InstallationRuntimeInspector;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

function installationRuntimeFakePhp(string $directory, string $version): string
{
    $path = $directory.DIRECTORY_SEPARATOR.'php-'.$version;
    File::put($path, "#!/bin/sh\nprintf 'PHP {$version} (cli) (built: test)\\n'\n");
    chmod($path, 0700);

    return $path;
}

function installationRuntimeFakeWeasyPrint(string $directory): string
{
    $path = $directory.DIRECTORY_SEPARATOR.'weasyprint';
    File::put($path, <<<'SH'
#!/bin/sh
if [ "${1:-}" = "--version" ]; then
    printf 'WeasyPrint version 57.2\n'
    exit 0
fi
printf '%%PDF-1.4\n' > "$2"
SH);
    chmod($path, 0700);

    return $path;
}

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
        ->and($result->requirement('runtime.php.version')?->expected)->toBe('>= 8.3.0')
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

    foreach (['storage/app/private', 'storage/framework/installer'] as $relativeDirectory) {
        $permissions = fileperms($this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.$relativeDirectory);

        expect($permissions)->not->toBeFalse()
            ->and($permissions & 0777)->toBe(0700);
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

it('tries standard PHP CLI candidates after an invalid advanced override', function (): void {
    $php = installationRuntimeFakePhp($this->runtimeInspectorRoot, '8.4.2');
    $weasyPrint = installationRuntimeFakeWeasyPrint($this->runtimeInspectorRoot);
    $inspector = new InstallationRuntimeInspector(
        phpCandidatePaths: [$php],
        weasyPrintCandidatePaths: [$weasyPrint],
    );
    $result = $inspector->inspect(
        basePath: $this->runtimeInspectorRoot,
        configuredPhpBinary: $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'missing-php',
    );

    expect($result->phpBinary)->toBe(realpath($php))
        ->and($result->requirement('runtime.php_cli')?->passed)->toBeTrue();
});

it('accepts CLI probes from cPanel and Plesk candidate-equivalent paths', function (string $relativePath): void {
    $php = installationRuntimeFakePhp($this->runtimeInspectorRoot, '8.3.21');
    $candidate = $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.$relativePath;
    File::ensureDirectoryExists(dirname($candidate), 0700, true);
    File::move($php, $candidate);
    chmod($candidate, 0700);
    $weasyPrint = installationRuntimeFakeWeasyPrint($this->runtimeInspectorRoot);
    $inspector = new InstallationRuntimeInspector(
        phpCandidatePaths: [$candidate],
        weasyPrintCandidatePaths: [$weasyPrint],
    );

    $result = $inspector->inspect($this->runtimeInspectorRoot);

    expect($result->phpBinary)->toBe((string) realpath($candidate))
        ->and($result->requirement('runtime.php_cli')?->passed)->toBeTrue();
})->with([
    'cPanel EA PHP' => ['usr/local/bin/ea-php83'],
    'Plesk PHP' => ['opt/plesk/php/8.3/bin/php'],
]);

it('reports that mandatory WeasyPrint was not detected after all candidates fail', function (): void {
    $inspector = new InstallationRuntimeInspector(
        phpCandidatePaths: [],
        weasyPrintCandidatePaths: [],
    );
    $result = $inspector->inspect(
        basePath: $this->runtimeInspectorRoot,
        configuredWeasyPrintBinary: 'weasyprint',
    );

    expect($result->requirement('runtime.weasyprint')?->actual)
        ->toBe('weasyprint_not_detected_or_pdf_probe_failed')
        ->and($result->weasyPrintBinary)->toBeNull();
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

    $inspector = new InstallationRuntimeInspector(
        phpCandidatePaths: [],
        weasyPrintCandidatePaths: [$fakeBinary],
    );
    $result = $inspector->inspect(
        basePath: $this->runtimeInspectorRoot,
        configuredWeasyPrintBinary: $fakeBinary,
    );

    expect($result->requirement('runtime.weasyprint')?->passed)->toBeFalse()
        ->and($result->requirement('runtime.weasyprint')?->actual)->toBe('weasyprint_not_detected_or_pdf_probe_failed')
        ->and(File::glob(
            $this->runtimeInspectorRoot.DIRECTORY_SEPARATOR.'storage/framework/installer/weasyprint-probe-*',
        ))->toBe([]);
});

it('accepts PHP 8.3 and 8.4 web and CLI runtimes and rejects PHP 8.2', function (
    string $version,
    bool $expected,
): void {
    $php = installationRuntimeFakePhp($this->runtimeInspectorRoot, $version);
    $weasyPrint = installationRuntimeFakeWeasyPrint($this->runtimeInspectorRoot);
    $inspector = new InstallationRuntimeInspector(
        runtimeVersion: $version,
        phpCandidatePaths: [$php],
        weasyPrintCandidatePaths: [$weasyPrint],
    );
    $result = $inspector->inspect(
        $this->runtimeInspectorRoot,
        configuredWeasyPrintBinary: $weasyPrint,
    );

    expect($result->requirement('runtime.php.version')?->passed)->toBe($expected)
        ->and($result->requirement('runtime.php_cli')?->passed)->toBe($expected)
        ->and($result->phpBinary === null)->toBe(! $expected);
})->with([
    'minimum PHP 8.3' => ['8.3.0', true],
    'newer PHP 8.4' => ['8.4.3', true],
    'unsupported PHP 8.2' => ['8.2.29', false],
]);

it('detects WeasyPrint automatically and verifies a real PDF signature', function (): void {
    $php = installationRuntimeFakePhp($this->runtimeInspectorRoot, '8.3.0');
    $weasyPrint = installationRuntimeFakeWeasyPrint($this->runtimeInspectorRoot);
    $inspector = new InstallationRuntimeInspector(
        phpCandidatePaths: [$php],
        weasyPrintCandidatePaths: [$weasyPrint],
    );
    config()->set('laravel-pdf.weasyprint.binary');
    $result = $inspector->inspect($this->runtimeInspectorRoot);

    expect($result->weasyPrintBinary)->toBe(realpath($weasyPrint))
        ->and($result->requirement('runtime.weasyprint')?->passed)->toBeTrue()
        ->and($result->requirement('runtime.weasyprint')?->actual)->toContain('%PDF-');
});
