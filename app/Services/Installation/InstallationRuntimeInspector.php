<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Data\Installation\InstallationRequirementResult;
use App\Data\Installation\InstallationRuntimeInspectionResult;
use Symfony\Component\Process\Process;
use Throwable;

final class InstallationRuntimeInspector
{
    private const MINIMUM_PHP_VERSION = '8.3.0';

    /** @var list<string> */
    private const COMMON_EXTENSIONS = [
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

    /** @var array<string, string> */
    private const REQUIRED_DIRECTORIES = [
        'storage_app_private' => 'storage/app/private',
        'storage_app_generated' => 'storage/app/generated',
        'storage_app_database' => 'storage/app/database',
        'storage_backups' => 'storage/backups',
        'storage_framework_cache_data' => 'storage/framework/cache/data',
        'storage_framework_sessions' => 'storage/framework/sessions',
        'storage_framework_views' => 'storage/framework/views',
        'storage_framework_installer' => 'storage/framework/installer',
        'storage_logs' => 'storage/logs',
        'bootstrap_cache' => 'bootstrap/cache',
    ];

    /**
     * @param  list<string>|null  $phpCandidatePaths
     * @param  list<string>|null  $weasyPrintCandidatePaths
     */
    public function __construct(
        private readonly ?string $runtimeVersion = null,
        private readonly ?array $phpCandidatePaths = null,
        private readonly ?array $weasyPrintCandidatePaths = null,
    ) {}

    public function inspect(
        string $basePath,
        ?string $configuredPhpBinary = null,
        ?string $configuredWeasyPrintBinary = null,
    ): InstallationRuntimeInspectionResult {
        $requirements = $this->inspectPhpRuntime();
        $projectRoot = $this->resolveProjectRoot($basePath);

        $requirements[] = new InstallationRequirementResult(
            key: 'filesystem.project_root',
            group: 'filesystem',
            passed: $projectRoot !== null,
            expected: 'absolute_existing_directory',
            actual: $projectRoot ?? 'invalid_project_root',
        );

        if ($projectRoot === null) {
            $requirements = [
                ...$requirements,
                ...$this->unavailableFilesystemRequirements(),
            ];
        } else {
            $requirements = [
                ...$requirements,
                ...$this->inspectFilesystem($projectRoot),
            ];
        }

        $phpConfiguration = $configuredPhpBinary ?? $this->configuredString('assestme.installation.php_binary');
        $phpInspection = $this->inspectPhpCli($phpConfiguration, $projectRoot);
        $requirements[] = $phpInspection['requirement'];

        $weasyPrintConfiguration = $configuredWeasyPrintBinary
            ?? $this->configuredString('laravel-pdf.weasyprint.binary');
        $weasyPrintInspection = $this->inspectWeasyPrint(
            $weasyPrintConfiguration,
            $projectRoot,
        );
        $requirements[] = $weasyPrintInspection['requirement'];

        return new InstallationRuntimeInspectionResult(
            requirements: $requirements,
            phpBinary: $phpInspection['binary'],
            weasyPrintBinary: $weasyPrintInspection['binary'],
        );
    }

    /** @return list<InstallationRequirementResult> */
    private function inspectPhpRuntime(): array
    {
        $runtimeVersion = $this->runtimeVersion ?? PHP_VERSION;
        $requirements = [
            new InstallationRequirementResult(
                key: 'runtime.php.version',
                group: 'runtime',
                passed: version_compare($runtimeVersion, self::MINIMUM_PHP_VERSION, '>='),
                expected: '>= 8.3.0',
                actual: $runtimeVersion,
            ),
        ];

        foreach (self::COMMON_EXTENSIONS as $extension) {
            $loaded = extension_loaded($extension);
            $requirements[] = new InstallationRequirementResult(
                key: 'runtime.php.extension.'.$extension,
                group: 'runtime',
                passed: $loaded,
                expected: 'loaded',
                actual: $loaded ? 'loaded' : 'missing',
            );
        }

        return $requirements;
    }

    /** @return list<InstallationRequirementResult> */
    private function inspectFilesystem(string $projectRoot): array
    {
        $requirements = [];
        $publicPath = $projectRoot.DIRECTORY_SEPARATOR.'public';
        $publicRoot = $this->resolveRegularDirectory($publicPath);

        $requirements[] = new InstallationRequirementResult(
            key: 'filesystem.public_root',
            group: 'filesystem',
            passed: $publicRoot !== null,
            expected: 'regular_non_symlink_directory',
            actual: $publicRoot ?? 'invalid_public_root',
        );

        foreach (self::REQUIRED_DIRECTORIES as $name => $relativePath) {
            $path = $projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $outsidePublic = $publicRoot !== null && $this->pathIsOutside($path, $publicRoot);

            $requirements[] = new InstallationRequirementResult(
                key: 'filesystem.outside_public.'.$name,
                group: 'filesystem',
                passed: $outsidePublic,
                expected: 'outside_public_root',
                actual: $this->canonicalizePotentialPath($path) ?? 'unresolved_path',
            );

            $directoryInspection = $outsidePublic
                ? $this->inspectRequiredDirectory($path)
                : ['passed' => false, 'actual' => 'sensitive_path_under_public'];

            $requirements[] = new InstallationRequirementResult(
                key: 'filesystem.directory.'.$name,
                group: 'filesystem',
                passed: $directoryInspection['passed'],
                expected: 'regular_readable_writable_atomic_rename_delete',
                actual: $directoryInspection['actual'],
            );
        }

        $environmentPath = $projectRoot.DIRECTORY_SEPARATOR.'.env';
        $environmentOutsidePublic = $publicRoot !== null
            && $this->pathIsOutside($environmentPath, $publicRoot);

        $requirements[] = new InstallationRequirementResult(
            key: 'filesystem.outside_public.environment_file',
            group: 'filesystem',
            passed: $environmentOutsidePublic,
            expected: 'outside_public_root',
            actual: $this->canonicalizePotentialPath($environmentPath) ?? 'unresolved_path',
        );

        $environmentInspection = $environmentOutsidePublic
            ? $this->inspectEnvironmentFile($environmentPath)
            : ['passed' => false, 'actual' => 'sensitive_path_under_public'];

        $requirements[] = new InstallationRequirementResult(
            key: 'filesystem.environment_file',
            group: 'filesystem',
            passed: $environmentInspection['passed'],
            expected: 'creatable_or_writable_atomic_replace_mode_0600',
            actual: $environmentInspection['actual'],
        );

        return $requirements;
    }

    /** @return list<InstallationRequirementResult> */
    private function unavailableFilesystemRequirements(): array
    {
        $requirements = [
            new InstallationRequirementResult(
                key: 'filesystem.public_root',
                group: 'filesystem',
                passed: false,
                expected: 'regular_non_symlink_directory',
                actual: 'project_root_unavailable',
            ),
        ];

        foreach (self::REQUIRED_DIRECTORIES as $name => $relativePath) {
            $requirements[] = new InstallationRequirementResult(
                key: 'filesystem.outside_public.'.$name,
                group: 'filesystem',
                passed: false,
                expected: 'outside_public_root',
                actual: 'project_root_unavailable',
            );
            $requirements[] = new InstallationRequirementResult(
                key: 'filesystem.directory.'.$name,
                group: 'filesystem',
                passed: false,
                expected: 'regular_readable_writable_atomic_rename_delete',
                actual: 'project_root_unavailable',
            );
        }

        $requirements[] = new InstallationRequirementResult(
            key: 'filesystem.outside_public.environment_file',
            group: 'filesystem',
            passed: false,
            expected: 'outside_public_root',
            actual: 'project_root_unavailable',
        );
        $requirements[] = new InstallationRequirementResult(
            key: 'filesystem.environment_file',
            group: 'filesystem',
            passed: false,
            expected: 'creatable_or_writable_atomic_replace_mode_0600',
            actual: 'project_root_unavailable',
        );

        return $requirements;
    }

    /** @return array{passed: bool, actual: string} */
    private function inspectRequiredDirectory(string $path): array
    {
        if (! file_exists($path) && ! @mkdir($path, 0750, true) && ! is_dir($path)) {
            return ['passed' => false, 'actual' => 'directory_not_creatable'];
        }

        if (is_link($path)) {
            return ['passed' => false, 'actual' => 'symlink_not_allowed'];
        }

        if (! is_dir($path)) {
            return ['passed' => false, 'actual' => 'not_a_directory'];
        }

        if (! is_readable($path) || ! is_writable($path)) {
            return ['passed' => false, 'actual' => 'directory_not_readable_and_writable'];
        }

        return $this->probeAtomicFileOperations($path)
            ? ['passed' => true, 'actual' => $path]
            : ['passed' => false, 'actual' => 'atomic_rename_delete_probe_failed'];
    }

    /** @return array{passed: bool, actual: string} */
    private function inspectEnvironmentFile(string $environmentPath): array
    {
        if (is_link($environmentPath)) {
            return ['passed' => false, 'actual' => 'symlink_not_allowed'];
        }

        if (file_exists($environmentPath)
            && (! is_file($environmentPath) || ! is_readable($environmentPath) || ! is_writable($environmentPath))) {
            return ['passed' => false, 'actual' => 'environment_file_not_readable_and_writable'];
        }

        $directory = dirname($environmentPath);

        if (! is_dir($directory) || ! is_readable($directory) || ! is_writable($directory)) {
            return ['passed' => false, 'actual' => 'project_root_not_readable_and_writable'];
        }

        return $this->probeAtomicFileOperations($directory, '.env-install-probe-')
            ? [
                'passed' => true,
                'actual' => file_exists($environmentPath)
                    ? 'existing_file_atomically_replaceable'
                    : 'new_file_atomically_creatable',
            ]
            : ['passed' => false, 'actual' => 'atomic_environment_probe_failed'];
    }

    private function probeAtomicFileOperations(string $directory, string $prefix = '.assestme-install-probe-'): bool
    {
        try {
            $suffix = bin2hex(random_bytes(12));
        } catch (Throwable) {
            return false;
        }

        $source = $directory.DIRECTORY_SEPARATOR.$prefix.$suffix.'.tmp';
        $target = $directory.DIRECTORY_SEPARATOR.$prefix.$suffix.'.ready';
        $marker = 'assestme-installation-filesystem-probe';
        $passed = false;

        try {
            $handle = @fopen($source, 'x+b');

            if ($handle === false) {
                return false;
            }

            try {
                if (fwrite($handle, $marker) !== strlen($marker) || ! fflush($handle)) {
                    return false;
                }
            } finally {
                fclose($handle);
            }

            if (! @chmod($source, 0600)) {
                return false;
            }

            $permissions = @fileperms($source);

            if ($permissions === false || ($permissions & 0777) !== 0600) {
                return false;
            }

            if (! @rename($source, $target)) {
                return false;
            }

            if (@file_get_contents($target) !== $marker || ! @unlink($target)) {
                return false;
            }

            $passed = true;
        } finally {
            if ((file_exists($source) || is_link($source)) && ! @unlink($source)) {
                $passed = false;
            }

            if ((file_exists($target) || is_link($target)) && ! @unlink($target)) {
                $passed = false;
            }
        }

        return $passed;
    }

    /**
     * @return array{requirement: InstallationRequirementResult, binary: string|null}
     */
    private function inspectPhpCli(?string $configuredBinary, ?string $workingDirectory): array
    {
        $configuredBinary = $configuredBinary !== null ? trim($configuredBinary) : null;

        $candidates = $configuredBinary !== null && $configuredBinary !== ''
            ? [$configuredBinary, ...$this->phpCandidatesFromRuntime()]
            : $this->phpCandidatesFromRuntime();

        foreach ($candidates as $candidate) {
            $inspection = $this->inspectPhpCandidate($candidate, $workingDirectory);

            if ($inspection !== null) {
                return [
                    'requirement' => new InstallationRequirementResult(
                        key: 'runtime.php_cli',
                        group: 'executable',
                        passed: true,
                        expected: 'absolute_executable_php_cli_>=8.3.0',
                        actual: $inspection['binary'].' '.$inspection['version'],
                    ),
                    'binary' => $inspection['binary'],
                ];
            }
        }

        return [
            'requirement' => new InstallationRequirementResult(
                key: 'runtime.php_cli',
                group: 'executable',
                passed: false,
                expected: 'absolute_executable_php_cli_>=8.3.0',
                actual: 'php_cli_>=8.3.0_not_detected',
            ),
            'binary' => null,
        ];
    }

    /** @return list<string> */
    private function phpCandidatesFromRuntime(): array
    {
        if ($this->phpCandidatePaths !== null) {
            return $this->phpCandidatePaths;
        }

        $webVersion = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
        $candidates = [
            "/usr/bin/php{$webVersion}",
            "/usr/local/bin/php{$webVersion}",
            '/usr/bin/php8.3',
            '/usr/local/bin/php8.3',
            '/usr/bin/php',
            '/usr/local/bin/php',
            PHP_BINARY,
        ];

        return array_values(array_unique($candidates));
    }

    /** @return array{binary: string, version: string}|null */
    private function inspectPhpCandidate(string $candidate, ?string $workingDirectory): ?array
    {
        if (! $this->isAbsolutePath($candidate)) {
            return null;
        }

        $binary = realpath($candidate);

        if ($binary === false || ! is_file($binary) || ! is_executable($binary)) {
            return null;
        }

        try {
            $process = new Process([$binary, '--version'], $workingDirectory);
            $process->setTimeout(10);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $output = $process->getOutput().PHP_EOL.$process->getErrorOutput();

            if (preg_match('/^PHP\s+([0-9]+(?:\.[0-9]+){1,2}[^\s]*)\s+\(cli\)/mi', $output, $matches) !== 1
                || ! version_compare($matches[1], self::MINIMUM_PHP_VERSION, '>=')) {
                return null;
            }

            return [
                'binary' => $binary,
                'version' => $matches[1],
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{requirement: InstallationRequirementResult, binary: string|null}
     */
    private function inspectWeasyPrint(?string $configuredBinary, ?string $projectRoot): array
    {
        if ($projectRoot === null) {
            return $this->failedWeasyPrint('project_root_unavailable');
        }

        $probeDirectory = $projectRoot
            .DIRECTORY_SEPARATOR.'storage'
            .DIRECTORY_SEPARATOR.'framework'
            .DIRECTORY_SEPARATOR.'installer';

        if (! is_dir($probeDirectory) || ! is_writable($probeDirectory)) {
            return $this->failedWeasyPrint('installer_runtime_directory_unavailable');
        }

        $configuredBinary = $configuredBinary !== null ? trim($configuredBinary) : '';
        $candidates = $configuredBinary !== ''
            ? [$configuredBinary, ...$this->weasyPrintCandidates()]
            : $this->weasyPrintCandidates();

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if (! $this->isAbsolutePath($candidate)) {
                continue;
            }

            $binary = realpath($candidate);

            if ($binary === false || ! is_file($binary) || ! is_executable($binary)) {
                continue;
            }

            $version = $this->inspectWeasyPrintVersion($binary, $projectRoot);

            if ($version === null || ! $this->generateMinimalPdf($binary, $probeDirectory, $projectRoot)) {
                continue;
            }

            return [
                'requirement' => new InstallationRequirementResult(
                    key: 'runtime.weasyprint',
                    group: 'pdf',
                    passed: true,
                    expected: 'absolute_executable_weasyprint_with_pdf_output',
                    actual: $binary.' WeasyPrint '.$version.' %PDF-',
                ),
                'binary' => $binary,
            ];
        }

        return $this->failedWeasyPrint('weasyprint_not_detected_or_pdf_probe_failed');
    }

    /** @return list<string> */
    private function weasyPrintCandidates(): array
    {
        return $this->weasyPrintCandidatePaths ?? [
            '/usr/bin/weasyprint',
            '/usr/local/bin/weasyprint',
        ];
    }

    private function inspectWeasyPrintVersion(string $binary, string $workingDirectory): ?string
    {
        try {
            $process = new Process([$binary, '--version'], $workingDirectory);
            $process->setTimeout(10);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $output = $process->getOutput().PHP_EOL.$process->getErrorOutput();

            if (preg_match('/\bWeasyPrint(?:\s+version)?\s+([0-9][^\s]*)/i', $output, $matches) !== 1) {
                return null;
            }

            return $matches[1];
        } catch (Throwable) {
            return null;
        }
    }

    private function generateMinimalPdf(string $binary, string $directory, string $workingDirectory): bool
    {
        try {
            $suffix = bin2hex(random_bytes(12));
        } catch (Throwable) {
            return false;
        }

        $probeDirectory = $directory.DIRECTORY_SEPARATOR.'weasyprint-probe-'.$suffix;

        if (! @mkdir($probeDirectory, 0700) || ! @chmod($probeDirectory, 0700)) {
            return false;
        }

        $input = $probeDirectory.DIRECTORY_SEPARATOR.'input.html';
        $output = $probeDirectory.DIRECTORY_SEPARATOR.'output.pdf';
        $html = '<!doctype html><html lang="it"><head><meta charset="utf-8"><title>AssestMe</title>'
            .'</head><body><p>AssestMe installation PDF capability probe</p></body></html>';
        $passed = false;

        try {
            if (@file_put_contents($input, $html, LOCK_EX) !== strlen($html) || ! @chmod($input, 0600)) {
                return false;
            }

            $process = new Process([$binary, $input, $output], $workingDirectory);
            $process->setTimeout(30);
            $process->run();

            if (! $process->isSuccessful()
                || ! is_file($output)
                || is_link($output)
                || @file_get_contents($output, false, null, 0, 5) !== '%PDF-') {
                return false;
            }

            $passed = true;
        } catch (Throwable) {
            $passed = false;
        } finally {
            if ((file_exists($input) || is_link($input)) && ! @unlink($input)) {
                $passed = false;
            }

            if ((file_exists($output) || is_link($output)) && ! @unlink($output)) {
                $passed = false;
            }

            if (is_dir($probeDirectory) && ! @rmdir($probeDirectory)) {
                $passed = false;
            }
        }

        return $passed;
    }

    /** @return array{requirement: InstallationRequirementResult, binary: null} */
    private function failedWeasyPrint(string $actual): array
    {
        return [
            'requirement' => new InstallationRequirementResult(
                key: 'runtime.weasyprint',
                group: 'pdf',
                passed: false,
                expected: 'absolute_executable_weasyprint_with_pdf_output',
                actual: $actual,
            ),
            'binary' => null,
        ];
    }

    private function resolveProjectRoot(string $basePath): ?string
    {
        if (! $this->isAbsolutePath($basePath)) {
            return null;
        }

        $root = realpath($basePath);

        if ($root === false || ! is_dir($root)) {
            return null;
        }

        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    private function resolveRegularDirectory(string $path): ?string
    {
        if (is_link($path)) {
            return null;
        }

        $directory = realpath($path);

        if ($directory === false || ! is_dir($directory)) {
            return null;
        }

        return rtrim($directory, DIRECTORY_SEPARATOR);
    }

    private function pathIsOutside(string $path, string $publicRoot): bool
    {
        $canonicalPath = $this->canonicalizePotentialPath($path);

        if ($canonicalPath === null) {
            return false;
        }

        $publicRoot = rtrim($publicRoot, DIRECTORY_SEPARATOR);

        return $canonicalPath !== $publicRoot
            && ! str_starts_with($canonicalPath, $publicRoot.DIRECTORY_SEPARATOR);
    }

    private function canonicalizePotentialPath(string $path): ?string
    {
        $suffix = [];
        $cursor = $path;

        while (! file_exists($cursor) && ! is_link($cursor)) {
            $parent = dirname($cursor);

            if ($parent === $cursor) {
                return null;
            }

            $suffix[] = basename($cursor);
            $cursor = $parent;
        }

        $resolved = realpath($cursor);

        if ($resolved === false) {
            return null;
        }

        foreach (array_reverse($suffix) as $component) {
            $resolved .= DIRECTORY_SEPARATOR.$component;
        }

        return rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function configuredString(string $key): ?string
    {
        $value = config($key);

        return is_string($value) ? $value : null;
    }
}
