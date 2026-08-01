<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('defines a production-only self-contained CloudPanel archive build', function (): void {
    $projectPath = dirname(__DIR__, 2);
    $script = file_get_contents($projectPath.'/scripts/build-cloudpanel-release.sh');
    $legacyArchiveSuffix = '-cloudpanel'.'.zip';
    $externalChecksumSuffix = '.zip'.'.sha256';

    expect($script)
        ->toContain('composer install')
        ->toContain('--no-dev')
        ->toContain('--classmap-authoritative')
        ->toContain('php artisan filament:assets')
        ->toContain("--exclude='.env'")
        ->toContain("--exclude='vendor/'")
        ->toContain('RELEASE-MANIFEST.sha256')
        ->toContain('vendor/autoload.php')
        ->toContain('assestme-${release_version}.zip')
        ->not->toContain($legacyArchiveSuffix)
        ->not->toContain($externalChecksumSuffix)
        ->not->toContain('checksum'.'_path');
});

it('rejects an unsafe release version before creating files', function (): void {
    $projectPath = dirname(__DIR__, 2);
    $process = new Process([
        $projectPath.'/scripts/build-cloudpanel-release.sh',
        '../unsafe',
        sys_get_temp_dir().'/assestme-invalid-release',
    ]);
    $process->run();

    expect($process->getExitCode())->toBe(64)
        ->and($process->getErrorOutput())->toContain('Usage:');
});

it('installs the extracted release with production-safe configuration checks', function (): void {
    $projectPath = dirname(__DIR__, 2);
    $script = file_get_contents($projectPath.'/scripts/ci-install-release-sqlite.sh');

    expect($script)
        ->toContain('configured_url="https://127.0.0.1:${port}"')
        ->toContain('--data-urlencode "application_url=$configured_url"')
        ->toContain('artisan assestme:diagnose --json')
        ->toContain('echo App\\Models\\User::query()->count();');
});
